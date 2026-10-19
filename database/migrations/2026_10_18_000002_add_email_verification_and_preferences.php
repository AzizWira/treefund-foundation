<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'email_preferences')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->json('email_preferences')->nullable();
            });
        }
        if (! Schema::hasTable('email_verification_challenges')) {
            Schema::create('email_verification_challenges', function (Blueprint $table): void {
                $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
                $table->string('email');
                $table->string('code_hash');
                $table->string('token_hash', 64);
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->dateTime('expires_at');
                $table->dateTime('sent_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verification_challenges');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('email_preferences'));
    }
};
