<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class TreeFundMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 45;

    public function __construct(public string $kind, public string $recipientName, public array $payload)
    {
        $this->onQueue('default')->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->payload['title'].' · TreeFund');
    }

    public function content(): Content
    {
        $template = match ($this->kind) {
            'verification' => 'verification',
            'password_reset' => 'password-reset',
            'security' => 'security',
            'payment_pending', 'payment_success', 'payment_failed' => 'payment',
            'certificate' => 'certificate',
            'field_report' => 'field-report',
            'partnership' => 'partnership',
            'work_order' => 'work-order',
            'funds' => 'funds',
            'reminder', 'payment_reminder' => 'reminder',
            'admin_digest', 'admin_request', 'payment_review' => 'admin',
            default => 'activity',
        };

        return new Content(view: 'emails.'.$template, text: 'emails.text');
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }
}
