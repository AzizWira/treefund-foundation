<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmailVerificationService
{
    public function issue(User $user): bool
    {
        return DB::transaction(function () use ($user): bool {
            $user = User::lockForUpdate()->findOrFail($user->id);
            if ($user->hasVerifiedEmail()) {
                return false;
            }
            $previous = DB::table('email_verification_challenges')->where('user_id', $user->id)->first();
            if ($previous && Carbon::parse($previous->sent_at)->greaterThan(now()->subSeconds(60))) {
                return false;
            }
            $code = (string) random_int(100000, 999999);
            $token = Str::random(64);
            $expiresAt = now()->addMinutes(15);
            DB::table('email_verification_challenges')->updateOrInsert(['user_id' => $user->id], [
                'email' => $user->email, 'code_hash' => Hash::make($code), 'token_hash' => hash('sha256', $token),
                'attempts' => 0, 'sent_at' => now(), 'expires_at' => $expiresAt,
            ]);
            $link = URL::temporarySignedRoute('verification.verify', $expiresAt, [
                'id' => $user->id, 'hash' => sha1($user->email), 'token' => $token,
            ]);
            app(EmailService::class)->send($user, 'verification', [
                'title' => 'Verifikasi email Anda', 'body' => 'Pilih salah satu: masukkan kode atau klik tautan verifikasi.',
                'code' => $code, 'url' => $link, 'action' => 'Verifikasi Email Saya', 'minutes' => 15,
            ], false);

            return true;
        });
    }

    public function verifyCode(User $user, string $code): void
    {
        $error = $this->consume($user, $code, false);
        if ($error !== null) {
            throw ValidationException::withMessages(['code' => $error]);
        }
    }

    public function verifyLink(User $user, string $token): void
    {
        $error = $this->consume($user, $token, true);
        if ($error !== null) {
            throw ValidationException::withMessages(['verification' => $error]);
        }
    }

    private function consume(User $user, string $value, bool $viaLink): ?string
    {
        return DB::transaction(function () use ($user, $value, $viaLink): ?string {
            $user = User::lockForUpdate()->findOrFail($user->id);
            if ($user->hasVerifiedEmail()) {
                return null;
            }
            $challenge = DB::table('email_verification_challenges')->where('user_id', $user->id)->lockForUpdate()->first();
            if (! $challenge || $challenge->email !== $user->email || Carbon::parse($challenge->expires_at)->isPast()) {
                return 'Verifikasi telah kedaluwarsa atau diganti. Silakan minta email baru.';
            }
            if (! $viaLink && $challenge->attempts >= 5) {
                return 'Batas percobaan kode tercapai. Gunakan tautan dalam email atau minta kode baru.';
            }
            $valid = $viaLink
                ? hash_equals($challenge->token_hash, hash('sha256', $value))
                : Hash::check($value, $challenge->code_hash);
            if (! $valid) {
                if (! $viaLink) {
                    DB::table('email_verification_challenges')->where('user_id', $user->id)->increment('attempts');
                }

                return $viaLink ? 'Tautan verifikasi sudah tidak berlaku.' : 'Kode verifikasi tidak sesuai.';
            }
            $user->markEmailAsVerified();
            DB::table('email_verification_challenges')->where('user_id', $user->id)->delete();
            event(new Verified($user));
            app(EmailService::class)->send($user, 'security', [
                'title' => 'Email berhasil diverifikasi', 'body' => 'Akun Anda sudah aktif. Mulai adopsi atau kelola kegiatan kelompok melalui dashboard.',
                'action' => 'Buka Dashboard',
            ]);

            return null;
        });
    }
}
