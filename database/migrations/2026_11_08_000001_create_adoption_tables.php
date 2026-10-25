<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adoptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->string('reference')->unique();
            $t->string('certificate_name');
            $t->unsignedBigInteger('total');
            $t->string('status')->default('pending')->index();
            $t->timestamp('expires_at');
            $t->timestamp('paid_at')->nullable();
            $t->text('snap_token')->nullable();
            $t->text('payment_url')->nullable();
            $t->string('transaction_id')->nullable();
            $t->string('provider_status')->nullable();
            $t->boolean('is_demo')->default(false);
            $t->timestamps();
        });
        Schema::create('adoption_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('adoption_id')->constrained();
            $t->foreignId('offer_id')->constrained();
            $t->unsignedInteger('quantity');
            $t->unsignedBigInteger('unit_price');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adoption_items');
        Schema::dropIfExists('adoptions');
    }
};
