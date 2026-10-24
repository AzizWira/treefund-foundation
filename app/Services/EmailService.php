<?php

namespace App\Services;

use App\Mail\TreeFundMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class EmailService
{
    public function send(User $user, string $kind, array $payload, bool $requiresVerifiedEmail = true): void
    {
        if ($requiresVerifiedEmail && ! $user->hasVerifiedEmail()) {
            return;
        }
        $category = match ($kind) {
            'field_report' => 'updates',
            'reminder', 'payment_reminder' => 'reminders',
            'admin_digest' => 'digest',
            default => null,
        };
        if ($category !== null && ! $user->wantsEmail($category)) {
            return;
        }
        $payload += ['body' => '', 'url' => route('dashboard'), 'action' => 'Buka TreeFund', 'occurred_at' => now()->format('d M Y, H:i')];
        $payload['url'] = url($payload['url']);
        Mail::to($user->email)->queue(new TreeFundMail($kind, $user->name, $payload));
    }
}
