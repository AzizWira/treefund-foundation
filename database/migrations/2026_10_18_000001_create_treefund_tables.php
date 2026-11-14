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
        Schema::create('plantings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tree_id')->unique()->constrained();
            $t->date('planted_at');
            $t->decimal('latitude', 10, 7);
            $t->decimal('longitude', 10, 7);
            $t->string('photo');
            $t->string('status')->default('pending')->index();
            $t->text('review_note')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users');
            $t->timestamps();
        });
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
            $t->timestamps();
        });
        Schema::create('termin_rules', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->unsignedTinyInteger('percentage');
            $t->string('milestone');
            $t->timestamps();
        });
        Schema::create('disbursements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('work_order_id')->constrained();
            $t->foreignId('termin_rule_id')->constrained();
            $t->unsignedBigInteger('amount');
            $t->string('status')->default('pending')->index();
            $t->text('note')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users');
            $t->foreignId('recorded_by')->nullable()->constrained('users');
            $t->date('transferred_at')->nullable();
            $t->string('proof')->nullable();
            $t->timestamps();
            $t->unique(['work_order_id', 'termin_rule_id']);
        });
        Schema::create('activity_notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->string('title');
            $t->text('body');
            $t->string('url')->default('/dashboard');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_id')->nullable()->constrained('users');
            $t->string('action');
            $t->string('subject_type');
            $t->unsignedBigInteger('subject_id');
            $t->json('details')->nullable();
            $t->timestamps();
        });
        Schema::create('members', function (Blueprint $t) {
            $t->id();
            $t->foreignId('partner_id')->constrained();
            $t->string('name');
            $t->string('position');
            $t->string('phone')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['members', 'audit_logs', 'activity_notifications', 'disbursements', 'termin_rules', 'monitorings', 'plantings', 'trees', 'work_orders', 'certificates', 'adoption_items', 'adoptions', 'offers', 'plots', 'partners'] as $name) {
            Schema::dropIfExists($name);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role', 'can_manage_finance', 'phone']));
    }
};
