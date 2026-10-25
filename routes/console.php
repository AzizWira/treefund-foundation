<?php

use App\Models\Adoption;
use App\Services\AdoptionService;
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
