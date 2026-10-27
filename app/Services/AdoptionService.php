<?php

namespace App\Services;

use App\Models\Adoption;
use App\Models\Offer;
use App\Models\Plot;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdoptionService
{
    public function checkout(User $user, Offer $offer, int $quantity, string $name): Adoption
    {
        return DB::transaction(function () use ($user, $offer, $quantity, $name): Adoption {
            $plot = Plot::lockForUpdate()->findOrFail($offer->plot_id);
            $offer = Offer::published()->where('plot_id', $plot->id)->lockForUpdate()->find($offer->id);
            if (! $offer) {
                throw ValidationException::withMessages(['offer_id' => 'Penawaran belum tersedia.']);
            }
            if ($quantity < 1 || $quantity > min(1000, $plot->available)) {
                throw ValidationException::withMessages(['quantity' => 'Kuota lahan tidak mencukupi.']);
            }

            $order = Adoption::create([
                'user_id' => $user->id,
                'reference' => 'TF-'.now()->format('ymd').'-'.Str::upper(Str::random(10)),
                'certificate_name' => $name,
                'total' => $offer->price * $quantity,
                'status' => 'pending',
                'expires_at' => now()->addMinutes(config('treefund.reservation_minutes')),
            ]);
            $order->items()->create(['offer_id' => $offer->id, 'quantity' => $quantity, 'unit_price' => $offer->price]);
            $plot->increment('reserved', $quantity);
            app(ActivityService::class)->audit('adoption.checkout', $order);

            return $order;
        }, 3);
    }

    public function expireUnissued(Adoption $order): void
    {
        Cache::lock('snap-order-'.$order->id, 35)->get(function () use ($order): void {
            DB::transaction(function () use ($order): void {
                $order = Adoption::lockForUpdate()->findOrFail($order->id);
                if ($order->status !== 'pending' || $order->snap_token || $order->expires_at->isFuture()) {
                    return;
                }
                foreach ($order->items()->with('offer')->orderBy('id')->get() as $item) {
                    Plot::lockForUpdate()->findOrFail($item->offer->plot_id)->decrement('reserved', $item->quantity);
                }
                $order->update(['status' => 'expired']);
            }, 3);
        });
    }

    public function applyProviderStatus(Adoption $order, array $payment): void
    {
        DB::transaction(function () use ($order, $payment): void {
            $order = Adoption::lockForUpdate()->findOrFail($order->id);
            if ($order->status === 'paid') {
                return;
            }

            $state = $payment['transaction_status'] ?? '';
            $paid = $state === 'settlement' || ($state === 'capture' && ($payment['fraud_status'] ?? '') === 'accept');
            $closed = in_array($state, ['deny', 'cancel', 'expire', 'failure'], true);
            $reserved = $order->status === 'pending';
            if (! $paid && (! $closed || ! $reserved)) {
                if ($reserved) {
                    $order->update(['provider_status' => $state]);
                }

                return;
            }

            $items = $order->items()->with('offer')->orderBy('id')->get();
            $required = $items->groupBy(fn ($item) => $item->offer->plot_id)->map->sum('quantity');
            $plots = Plot::whereIn('id', $required->keys())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($paid && ! $reserved && $required->contains(fn ($quantity, $id) => $plots[$id]->available < $quantity)) {
                if ($order->status !== 'review') {
                    $order->update(['status' => 'review', 'provider_status' => $state]);
                    app(ActivityService::class)->audit('payment.review', $order);
                }

                return;
            }

            foreach ($required as $id => $quantity) {
                if ($reserved) {
                    $plots[$id]->decrement('reserved', $quantity);
                }
                if ($paid) {
                    $plots[$id]->increment('confirmed', $quantity);
                }
            }

            if ($paid) {
                foreach ($items as $item) {
                    for ($index = 1; $index <= $item->quantity; $index++) {
                        $item->trees()->firstOrCreate(['code' => sprintf('%s-%d-%03d', $order->reference, $item->id, $index)]);
                    }
                    $item->workOrder()->firstOrCreate([], ['partner_id' => $plots[$item->offer->plot_id]->partner_id, 'allocated_amount' => 0, 'status' => 'waiting']);
                }
                $order->certificate()->firstOrCreate([], ['number' => 'CERT-'.$order->reference]);
                $order->update(['status' => 'paid', 'paid_at' => now(), 'provider_status' => $state, 'transaction_id' => $payment['transaction_id'] ?? null]);
            } else {
                $order->update(['status' => $state === 'expire' ? 'expired' : 'failed', 'provider_status' => $state]);
            }
            app(ActivityService::class)->audit('payment.'.$order->status, $order, ['provider_status' => $state]);
        }, 3);
    }
}
