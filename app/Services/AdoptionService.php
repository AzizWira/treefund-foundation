<?php

namespace App\Services;

use App\Models\Adoption;
use App\Models\Certificate;
use App\Models\Offer;
use App\Models\Plot;
use App\Models\Tree;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdoptionService
{
    public function checkout(User $user, Offer $offer, int $quantity, string $name): Adoption
    {
        return DB::transaction(function () use ($user, $offer, $quantity, $name) {
            $plot = Plot::lockForUpdate()->findOrFail($offer->plot_id);
            $offer = Offer::findOrFail($offer->id);
            if (! $offer->active || $plot->status !== 'approved' || $plot->partner->status !== 'approved') {
                throw ValidationException::withMessages(['offer_id' => 'Penawaran belum tersedia.']);
            }
            if ($quantity < 1 || $quantity > $plot->available) {
                throw ValidationException::withMessages(['quantity' => 'Kuota lahan tidak mencukupi. Pilih jumlah atau lahan lainnya.']);
            }
            $plot->increment('reserved', $quantity);
            $order = Adoption::create(['user_id' => $user->id, 'reference' => 'TF-'.now()->format('ymd').'-'.Str::upper(Str::random(10)), 'certificate_name' => $name, 'total' => $offer->price * $quantity, 'status' => 'pending', 'expires_at' => now()->addMinutes(config('treefund.reservation_minutes'))]);
            $order->items()->create(['offer_id' => $offer->id, 'quantity' => $quantity, 'unit_price' => $offer->price]);
            app(ActivityService::class)->audit('adoption.checkout', $order);
            app(ActivityService::class)->notify($user->id, 'Pesanan adopsi Anda siap dibayar', 'Kuota pohon pilihan Anda telah dicadangkan. Selesaikan pembayaran sebelum batas waktu.', '/orders/'.$order->id, 'payment_pending', ['reference' => $order->reference, 'amount' => $order->total, 'deadline' => $order->expires_at->format('d M Y, H:i')]);

            return $order;
        });
    }

    public function applyProviderStatus(Adoption $order, array $payment): void
    {
        DB::transaction(function () use ($order, $payment) {
            $order = Adoption::lockForUpdate()->findOrFail($order->id);
            if ($order->status === 'paid') {
                return;
            }
            $state = $payment['transaction_status'] ?? '';
            $paid = $state === 'settlement' || ($state === 'capture' && ($payment['fraud_status'] ?? '') === 'accept');
            $closed = in_array($state, ['deny', 'cancel', 'expire', 'failure'], true);
            if ($closed && in_array($order->status, ['failed', 'expired'], true)) {
                return;
            }
            if (! $paid && ! $closed) {
                $order->update(['provider_status' => $state]);

                return;
            }
            foreach ($order->items()->with('offer')->orderBy('id')->get() as $item) {
                $plot = Plot::lockForUpdate()->findOrFail($item->offer->plot_id);
                $reserved = $order->status === 'pending';
                if ($paid && ! $reserved && $plot->available < $item->quantity) {
                    $order->update(['status' => 'review', 'provider_status' => $state]);
                    app(ActivityService::class)->notifyAdmins('Pembayaran perlu rekonsiliasi', $order->reference.' dibayar setelah kuota dilepas.', '/admin/orders', 'payment_review', ['reference' => $order->reference]);

                    return;
                }
                if ($reserved) {
                    $plot->decrement('reserved', $item->quantity);
                }
                if ($paid) {
                    $plot->increment('confirmed', $item->quantity);
                    for ($i = 1; $i <= $item->quantity; $i++) {
                        Tree::firstOrCreate(['code' => $order->reference.'-'.$item->id.'-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT)], ['adoption_item_id' => $item->id]);
                    }
                    $work = WorkOrder::firstOrCreate(['adoption_item_id' => $item->id], ['partner_id' => $plot->partner_id, 'allocated_amount' => 0, 'status' => 'waiting']);
                    app(ActivityService::class)->notify($plot->partner->user_id, 'Work order penanaman baru', 'Penugasan '.$order->reference.' siap ditanam.', '/partner/work-orders/'.$work->id, 'work_order', ['reference' => $order->reference, 'quantity' => $item->quantity, 'species' => $item->offer->species, 'plot' => $plot->name]);
                }
            }
            if ($paid) {
                Certificate::firstOrCreate(['adoption_id' => $order->id], ['number' => 'CERT-'.$order->reference]);
                $order->update(['status' => 'paid', 'paid_at' => now(), 'provider_status' => $state, 'transaction_id' => $payment['transaction_id'] ?? null]);
                app(ActivityService::class)->notify($order->user_id, 'Adopsi berhasil dibayar', 'Sertifikat dan identitas pohon untuk '.$order->reference.' telah terbit.', '/orders/'.$order->id, 'payment_success', ['reference' => $order->reference, 'amount' => $order->total]);
            } else {
                $order->update(['status' => $state === 'expire' ? 'expired' : 'failed', 'provider_status' => $state]);
                app(ActivityService::class)->notify($order->user_id, 'Pembaruan status pembayaran', 'Pembayaran '.$order->reference.' '.($state === 'expire' ? 'telah kedaluwarsa.' : 'belum berhasil atau dibatalkan.'), '/orders/'.$order->id, 'payment_failed', ['reference' => $order->reference, 'amount' => $order->total]);
            }
            app(ActivityService::class)->audit('payment.'.$order->status, $order, ['provider_status' => $state]);
        });
    }

    public function expireUnissued(Adoption $order): void
    {
        DB::transaction(function () use ($order) {
            $order = Adoption::lockForUpdate()->findOrFail($order->id);
            if ($order->status !== 'pending' || $order->snap_token || $order->expires_at->isFuture()) {
                return;
            }
            foreach ($order->items()->with('offer')->get() as $item) {
                $plot = Plot::lockForUpdate()->findOrFail($item->offer->plot_id);
                $plot->decrement('reserved', $item->quantity);
            }
            $order->update(['status' => 'expired']);
            app(ActivityService::class)->notify($order->user_id, 'Batas pembayaran berakhir', 'Reservasi kuota untuk '.$order->reference.' telah dilepas. Anda dapat membuat adopsi baru melalui katalog.', '/orders/'.$order->id, 'payment_failed', ['reference' => $order->reference, 'amount' => $order->total]);
        });
    }
}
