<?php

namespace App\Models;

use App\Services\EmailService;
use App\Services\EmailVerificationService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'phone'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'can_manage_finance' => 'boolean', 'email_preferences' => 'array'];
    }

    public function wantsEmail(string $category): bool
    {
        return (bool) ($this->email_preferences[$category] ?? true);
    }

    public function sendEmailVerificationNotification(): void
    {
        app(EmailVerificationService::class)->issue($this);
    }

    public function sendPasswordResetNotification(mixed $token): void
    {
        app(EmailService::class)->send($this, 'password_reset', [
            'title' => 'Atur ulang kata sandi Anda', 'body' => 'Kami menerima permintaan untuk mengganti kata sandi akun TreeFund Anda.',
            'url' => route('password.reset', ['token' => $token, 'email' => $this->email]),
            'action' => 'Atur Ulang Kata Sandi', 'minutes' => config('auth.passwords.users.expire'),
        ], false);
    }

    public function partner(): mixed
    {
        return $this->hasOne(Partner::class);
    }
}
