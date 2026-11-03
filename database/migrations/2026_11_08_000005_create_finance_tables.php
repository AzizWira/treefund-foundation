<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
            $t->unsignedInteger('submission_version')->default(1);
            $t->unique(['work_order_id', 'termin_rule_id']);
        });
        \Illuminate\Support\Facades\DB::table('termin_rules')->insert([
            ['name'=>'Termin 1','percentage'=>30,'milestone'=>'paid','created_at'=>now(),'updated_at'=>now()],
            ['name'=>'Termin 2','percentage'=>40,'milestone'=>'planted','created_at'=>now(),'updated_at'=>now()],
            ['name'=>'Termin 3','percentage'=>30,'milestone'=>'monitored','created_at'=>now(),'updated_at'=>now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('disbursements');
        Schema::dropIfExists('termin_rules');
    }
};
