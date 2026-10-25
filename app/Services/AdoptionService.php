<?php

namespace App\Services;

use App\Models\Adoption;
use App\Models\Offer;
use App\Models\Plot;
use App\Models\User;
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
    }
}
