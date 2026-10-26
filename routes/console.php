<?php

use App\Models\Adoption;
use App\Services\AdoptionService;
use App\Services\MidtransService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

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
