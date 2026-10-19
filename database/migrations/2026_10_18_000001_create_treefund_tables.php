<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('role')->default('donor')->index();
            $t->boolean('can_manage_finance')->default(false);
            $t->string('phone')->nullable();
        });
        Schema::create('partners', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('group_name');
            $t->text('address');
            $t->string('bank_name');
            $t->string('bank_account');
            $t->string('account_holder');
            $t->string('document');
            $t->string('status')->default('pending')->index();
            $t->text('review_note')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users');
            $t->timestamps();
        });
        Schema::create('plots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('partner_id')->constrained();
            $t->string('name');
            $t->string('location');
            $t->decimal('latitude', 10, 7);
            $t->decimal('longitude', 10, 7);
            $t->unsignedInteger('capacity');
            $t->unsignedInteger('reserved')->default(0);
            $t->unsignedInteger('confirmed')->default(0);
            $t->string('proposed_species');
            $t->string('status')->default('pending')->index();
            $t->text('review_note')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users');
            $t->timestamps();
        });
        Schema::create('offers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('plot_id')->constrained();
            $t->string('species');
            $t->text('description');
            $t->string('photo')->nullable();
            $t->unsignedBigInteger('price');
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
        Schema::dropIfExists('plots');
        Schema::dropIfExists('partners');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['role']);
            $table->dropColumn(['role', 'can_manage_finance', 'phone']);
        });
    }
};
