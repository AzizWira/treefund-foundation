<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('adoption_id')->unique()->constrained();
            $t->string('number')->unique();
            $t->timestamps();
        });
        Schema::create('work_orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('adoption_item_id')->unique()->constrained();
            $t->foreignId('partner_id')->constrained();
            $t->unsignedBigInteger('allocated_amount');
            $t->string('status')->default('waiting');
            $t->timestamps();
        });
        Schema::create('trees', function (Blueprint $t) {
            $t->id();
            $t->foreignId('adoption_item_id')->constrained();
            $t->string('code')->unique();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trees');
        Schema::dropIfExists('work_orders');
        Schema::dropIfExists('certificates');
    }
};
