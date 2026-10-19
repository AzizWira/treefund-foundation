<?php

namespace App\Services;

use App\Models\ActivityNotification;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ActivityService
{
    public function audit(string $action, Model $subject, array $details = []): void
    {
        AuditLog::create(['actor_id' => auth()->id(), 'action' => $action, 'subject_type' => $subject::class, 'subject_id' => $subject->id, 'details' => $details]);
    }

    public function notify(int $userId, string $title, string $body, string $url = '/dashboard', string $kind = 'activity', array $context = []): void
    {
        ActivityNotification::create(['user_id' => $userId, 'title' => $title, 'body' => $body, 'url' => $url]);
        $user = User::find($userId);
        if ($user) {
            app(EmailService::class)->send($user, $kind, array_merge($context, ['title' => $title, 'body' => $body, 'url' => $url]));
        }
    }

    public function notifyAdmins(string $title, string $body, string $url, string $kind = 'admin_request', array $context = []): void
    {
        User::where('role', 'admin')->when(str_starts_with($url, '/admin/disbursements'), fn ($query) => $query->where('can_manage_finance', true))
            ->each(fn ($u) => $this->notify($u->id, $title, $body, $url, $kind, $context));
    }
}
