<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitorings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tree_id')->constrained();
            $t->date('observed_at');
            $t->decimal('height', 8, 2);
            $t->string('health');
            $t->text('care_note');
            $t->string('photo');
            $t->string('status')->default('pending')->index();
            $t->text('review_note')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users');
            $t->unsignedInteger('submission_version')->default(1);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitorings');
    }
};
