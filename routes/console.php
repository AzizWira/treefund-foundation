<?php

use App\Mail\TreeFundMail;
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
use Dompdf\Dompdf;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;

Artisan::command('treefund:doctor', function (): int {
    $this->info('Pemeriksaan instalasi TreeFund');
    $errors = 0;
    foreach (['curl', 'fileinfo', 'mbstring', 'openssl', 'dom', 'gd'] as $extension) {
        if (extension_loaded($extension)) {
            $this->line('[OK] PHP '.$extension);
        } else {
            $errors++;
            $this->error('[GAGAL] PHP '.$extension.' belum aktif.');
        }
    }
    try {
        DB::connection()->getPdo();
        $this->line('[OK] Database '.config('database.default').' terhubung.');
    } catch (Throwable $exception) {
        $errors++;
        $this->error('[GAGAL] Database belum terhubung. Periksa DB_* pada .env.');
    }
    if (file_exists(public_path('build/manifest.json'))) {
        $this->line('[OK] Aset frontend hasil build tersedia.');
    } else {
        $errors++;
        $this->error('[GAGAL] Jalankan npm ci dan npm run build.');
    }
    if (class_exists(Dompdf::class)) {
        $this->line('[OK] Generator e-sertifikat PDF tersedia.');
    } else {
        $errors++;
        $this->error('[GAGAL] Jalankan composer install untuk dependency PDF.');
    }
    foreach (['server_key' => 'SB-Mid-server-', 'client_key' => 'SB-Mid-client-'] as $key => $prefix) {
        if (str_starts_with((string) config('treefund.midtrans.'.$key), $prefix)) {
            $this->line('[OK] Format Midtrans '.$key.' Sandbox terisi.');
        } else {
            $value = (string) config('treefund.midtrans.'.$key);
            $this->warn(($value === '' ? '[BELUM DIISI] ' : '[FORMAT TIDAK SESUAI SANDBOX] ').'MIDTRANS_'.strtoupper($key).' harus diambil dari mode Sandbox (awalan '.$prefix.').');
        }
    }
    $this->line('Kunci tidak ditampilkan. Validitas merchant baru dapat diperiksa melalui transaksi Sandbox.');

    return $errors ? 1 : 0;
})->purpose('Periksa runtime, database, build, dan konfigurasi Midtrans tanpa mencetak kunci.');

Artisan::command('treefund:admin {email} {--name=} {--finance}', function (): int {
    $email = strtolower((string) $this->argument('email'));
    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('Format email tidak valid.');

        return 1;
    }
    if (User::where('email', $email)->exists()) {
        $this->error('Email sudah terdaftar. Gunakan email baru agar akun lama tidak diubah.');

        return 1;
    }
    $password = $this->secret('Kata sandi Admin baru (minimal 12 karakter)');
    if (! is_string($password) || mb_strlen($password) < 12) {
        $this->error('Kata sandi minimal 12 karakter.');

        return 1;
    }
    if ($password !== $this->secret('Ulangi kata sandi')) {
        $this->error('Konfirmasi kata sandi tidak sama.');

        return 1;
    }
    $user = new User(['name' => $this->option('name') ?: 'Admin TreeFund', 'email' => $email, 'password' => $password]);
    $user->role = 'admin';
    $user->can_manage_finance = (bool) $this->option('finance');
    $user->save();
    $this->info('Akun Admin berhasil dibuat.');

    return 0;
})->purpose('Buat Admin tanpa membuka registrasi Admin untuk publik.');
Artisan::command('treefund:reconcile', function () {
    Adoption::where('status', 'pending')->where('is_demo', false)->orderBy('id')->chunkById(100, function ($orders) {
        foreach ($orders as $order) {
            try {
                if ($order->snap_token) {
                    app(AdoptionService::class)->applyProviderStatus($order, app(MidtransService::class)->status($order));
                } else {
                    app(AdoptionService::class)->expireUnissued($order);
                }
            } catch (Throwable $e) {
                report($e);
                $this->warn($order->reference.': status belum pasti, kuota tetap ditahan untuk mencegah overselling.');
            }
        }
    });
    $this->info('Rekonsiliasi pembayaran selesai.');
})->purpose('Periksa pembayaran Sandbox dan lepaskan reservasi yang aman untuk kedaluwarsa.');
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
Schedule::command('treefund:reconcile')->everyFiveMinutes()->withoutOverlapping();
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
            if ($user->can_manage_finance) {
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
Schedule::command('auth:clear-resets')->daily();

Artisan::command('treefund:mail-test {email} ', function (): int {
    $email = (string) $this->argument('email');
    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('Alamat penerima tidak valid.');

        return 1;
    }
    if (config('mail.default') !== 'smtp') {
        $this->error('MAIL_MAILER belum memakai smtp. Pengiriman nyata belum dicoba.');

        return 1;
    }
    try {
        $mail = (new TreeFundMail('security', 'Penguji TreeFund', [
            'title' => 'Uji pengiriman SMTP TreeFund',
            'body' => 'Ini adalah email pengujian yang Anda minta. Koneksi SMTP TreeFund berhasil digunakan untuk mengirim pesan ini. Email ini tidak mengubah akun atau transaksi Anda.',
            'url' => url('/'), 'action' => 'Buka TreeFund',
        ]))->to($email);
        $mail->send(Mail::mailer());
        $this->info('Email pengujian diterima server SMTP untuk '.$email.'. Periksa inbox atau spam untuk memastikan penerimaan.');

        return 0;
    } catch (Throwable $exception) {
        $message = strtolower($exception->getMessage());
        $reason = match (true) {
            str_contains($message, 'auth'), str_contains($message, '535') => 'Autentikasi SMTP gagal. Periksa username dan password/App Password.',
            str_contains($message, 'certificate'), str_contains($message, 'crypto') => 'Koneksi TLS atau sertifikat SMTP gagal diverifikasi. Periksa host, port, dan MAIL_SCHEME.',
            str_contains($message, 'connect'), str_contains($message, 'timed out') => 'Server SMTP tidak dapat dihubungi. Periksa host, port, dan akses jaringan.',
            default => 'Server SMTP belum menerima pesan. Periksa pengaturan akun dan alamat pengirim.',
        };
        $this->error($reason.' Kredensial tidak ditampilkan.');

        return 1;
    }
})->purpose('Kirim satu email pengujian SMTP secara langsung tanpa menunggu worker.');

Artisan::command('treefund:email-preview {--path=}', function (): int {
    if (! app()->environment(['local', 'testing'])) {
        $this->error('Pratinjau hanya tersedia di lingkungan local/testing.');

        return 1;
    }
    $directory = $this->option('path') ?: storage_path('app/private/email-previews');
    File::ensureDirectoryExists($directory);
    $types = [
        'verification' => 'Verifikasi kode dan tautan', 'password_reset' => 'Reset kata sandi', 'security' => 'Keamanan akun',
        'payment_pending' => 'Pesanan menunggu pembayaran', 'payment_success' => 'Pembayaran berhasil', 'payment_failed' => 'Pembayaran berakhir',
        'certificate' => 'E-sertifikat siap', 'field_report' => 'Kabar perkembangan pohon', 'partnership' => 'Keputusan pengajuan',
        'work_order' => 'Work order baru', 'funds' => 'Pencairan dana', 'reminder' => 'Pengingat monitoring',
        'payment_reminder' => 'Pengingat pembayaran', 'admin_digest' => 'Ringkasan Admin', 'admin_request' => 'Antrean pengajuan',
        'payment_review' => 'Rekonsiliasi pembayaran', 'activity' => 'Aktivitas akun',
    ];
    $links = '';
    foreach ($types as $kind => $title) {
        $payload = [
            'title' => $title, 'body' => 'Kabar terbaru dari TreeFund untuk perjalanan penghijauan Anda. Ini adalah contoh tampilan email, bukan transaksi atau kode verifikasi nyata.',
            'url' => url('/'), 'action' => 'Lihat Detail', 'code' => '123456', 'minutes' => 15,
            'amount' => 125000, 'reference' => 'TF-CONTOH-2026', 'deadline' => '18 Oktober 2026, 19:00',
            'species' => 'Alpukat', 'quantity' => 1, 'plot' => 'Lereng Hijau Malang', 'tree_code' => 'TF-CONTOH-001',
            'decision' => 'revision', 'note' => 'Mohon lengkapi foto agar lokasi dan kondisi bibit terlihat lebih jelas.',
            'term' => 'Termin 2 — Bukti tanam terverifikasi', 'stats' => ['Bukti tanam menunggu' => 3, 'Monitoring menunggu' => 2],
        ];
        File::put($directory.'/'.$kind.'.html', (new TreeFundMail($kind, 'Sahabat TreeFund', $payload))->render());
        $links .= '<a href="'.$kind.'.html" target="email-preview">'.e($title).'</a>';
    }
    File::put($directory.'/index.html', '<!doctype html><html lang="id"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Pratinjau Email TreeFund</title><style>body{margin:0;background:#edf3e8;font:14px Arial;color:#244733}header{padding:22px 28px;background:#143f2a;color:white}h1{font:700 26px Georgia;margin:0 0 8px}.grid{display:grid;grid-template-columns:270px 1fr;height:calc(100vh - 100px)}nav{padding:18px;overflow:auto}nav a{display:block;text-decoration:none;color:#295d3c;padding:12px;border-radius:7px;margin:4px 0;background:#fff}nav a:hover{background:#d8e8cf}iframe{width:100%;height:100%;border:0}@media(max-width:700px){.grid{display:block}nav{max-height:150px}iframe{height:900px}}</style><header><h1>TreeFund · Studio Email</h1>17 varian pesan, satu tema. Pratinjau lokal ini tidak mengirim email.</header><div class="grid"><nav>'.$links.'</nav><iframe title="Pratinjau email" name="email-preview" src="verification.html"></iframe></div></html>');
    $this->info('17 pratinjau email dibuat di '.$directory);

    return 0;
})->purpose('Render galeri semua desain email dengan data contoh yang aman.');
