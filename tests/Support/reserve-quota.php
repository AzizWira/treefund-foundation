<?php

use App\Models\Offer;
use App\Models\User;
use App\Services\AdoptionService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$donor = User::findOrFail((int) $argv[1]);
$offer = Offer::findOrFail((int) $argv[2]);
echo "ready\n";
flush();

try {
    app(AdoptionService::class)->checkout($donor, $offer, 1, 'Donatur Uji');
    echo "reserved\n";
} catch (ValidationException $exception) {
    if (! isset($exception->errors()['quantity'])) {
        throw $exception;
    }
    echo "rejected\n";
}
