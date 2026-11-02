<?php

use App\Models\ActivityNotification;
use App\Models\Adoption;
use App\Models\Disbursement;
use App\Models\Monitoring;
use App\Models\Partner;
use App\Models\Planting;
use App\Models\Plot;
use App\Models\Tree;
use App\Models\User;
use App\Services\ActivityService;
use App\Services\AdoptionService;
use App\Services\MidtransService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Schedule::command('auth:clear-resets')->daily();

Artisan::command('treefund:expire-reservations', function (): void {
    Adoption::where('status', 'pending')->whereNull('snap_token')->where('expires_at', '<=', now())
        ->chunkById(100, function ($orders): void {
            foreach ($orders as $order) {
                app(AdoptionService::class)->expireUnissued($order);
            }
        });
});

Schedule::command('treefund:expire-reservations')->everyFiveMinutes()->withoutOverlapping();

Artisan::command('treefund:reconcile', function (): void {
    if (! config('treefund.midtrans.enabled')) {
        return;
    }
    Adoption::where('status', 'pending')->where('is_demo', false)->whereNotNull('snap_token')->chunkById(100, function ($orders): void {
        foreach ($orders as $order) {
            try {
                app(AdoptionService::class)->applyProviderStatus($order, app(MidtransService::class)->status($order));
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    });
});

Schedule::command('treefund:reconcile')->everyFiveMinutes()->withoutOverlapping();

Artisan::command('treefund:monitoring-reminders', function (): void {
    $due = Tree::with('item.offer.plot.partner', 'planting', 'monitorings')->whereHas('planting', fn ($query) => $query->where('status', 'approved'))->get()
        ->filter(function ($tree): bool {
            $last = $tree->monitorings->where('status', 'approved')->first()?->observed_at ?? $tree->planting->planted_at;

            return $last->copy()->addMonths(3)->isPast();
        })->groupBy(fn ($tree) => $tree->item->offer->plot->partner->user_id);
    foreach ($due as $userId => $trees) {
        DB::transaction(function () use ($userId, $trees): void {
            $user = User::lockForUpdate()->findOrFail($userId);
            $title = 'Pengingat monitoring kelompok '.now()->startOfWeek()->toDateString();
            if (ActivityNotification::where('user_id', $user->id)->where('title', $title)->exists()) {
                return;
            }
            app(ActivityService::class)->notify($user->id, $title, $trees->count().' pohon telah memasuki jadwal monitoring tiga bulanan. Lengkapi laporan melalui akun Mitra Anda.', '/partner/monitoring', 'reminder', ['reference' => $trees->count().' pohon memerlukan laporan']);
        });
    }
    $this->info('Pengingat monitoring diperiksa; maksimal satu ringkasan per kelompok per minggu.');
});

Schedule::command('treefund:monitoring-reminders')->dailyAt('08:00')->withoutOverlapping();

Artisan::command('treefund:payment-reminders', function (): void {
    Adoption::where('status', 'pending')->where('is_demo', false)->whereBetween('expires_at', [now(), now()->addMinutes(15)])
        ->orderBy('id')->chunkById(100, function ($orders): void {
            foreach ($orders as $candidate) {
                DB::transaction(function () use ($candidate): void {
                    $order = Adoption::lockForUpdate()->findOrFail($candidate->id);
                    $title = 'Pengingat pembayaran '.$order->reference;
                    if ($order->status !== 'pending' || $order->expires_at->isPast() || ActivityNotification::where('user_id', $order->user_id)->where('title', $title)->exists()) {
                        return;
                    }
                    app(ActivityService::class)->notify($order->user_id, $title, 'Batas reservasi akan berakhir. Selesaikan simulasi pembayaran untuk mengamankan kuota pohon Anda.', '/orders/'.$order->id, 'payment_reminder', [
                        'reference' => $order->reference, 'amount' => $order->total, 'deadline' => $order->expires_at->format('d M Y, H:i'),
                    ]);
                });
            }
        });
    $this->info('Pengingat pembayaran diperiksa.');
})->purpose('Kirim satu pengingat per pesanan menjelang akhir reservasi.');

Artisan::command('treefund:admin-digest', function (): void {
    $stats = [
        'Kemitraan menunggu' => Partner::where('status', 'pending')->count(),
        'Lahan menunggu' => Plot::where('status', 'pending')->count(),
        'Bukti tanam menunggu' => Planting::where('status', 'pending')->count(),
        'Monitoring menunggu' => Monitoring::where('status', 'pending')->count(),
        'Pembayaran perlu rekonsiliasi' => Adoption::where('status', 'review')->count(),
    ];
    User::where('role', 'admin')->whereNotNull('email_verified_at')->each(function (User $candidate) use ($stats): void {
        DB::transaction(function () use ($candidate, $stats): void {
            $user = User::lockForUpdate()->findOrFail($candidate->id);
            $title = 'Ringkasan antrean TreeFund '.today()->toDateString();
            if (ActivityNotification::where('user_id', $user->id)->where('title', $title)->exists()) {
                return;
            }
            if ($user->can_manage_finance && Schema::hasTable('disbursements')) {
                $stats['Pencairan menunggu'] = Disbursement::where('status', 'pending')->count();
            }
            if (array_sum($stats) === 0) {
                return;
            }
            app(ActivityService::class)->notify($user->id, $title, 'Berikut ringkasan pengajuan yang memerlukan perhatian Anda hari ini.', '/dashboard', 'admin_digest', ['stats' => $stats]);
        });
    });
    $this->info('Ringkasan Admin diperiksa.');
})->purpose('Kirim ringkasan antrean maksimal sekali per hari per Admin.');

Schedule::command('treefund:payment-reminders')->everyTenMinutes()->withoutOverlapping();
Schedule::command('treefund:admin-digest')->dailyAt('08:30')->withoutOverlapping();
