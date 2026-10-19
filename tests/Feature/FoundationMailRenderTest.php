<?php

namespace Tests\Feature;

use App\Mail\TreeFundMail;
use Tests\TestCase;

class FoundationMailRenderTest extends TestCase
{
    public function test_authentication_email_builds_a_complete_message_with_a_sender_without_smtp(): void
    {
        $this->assertNotFalse(filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL));
        $mailer = (new \Illuminate\Mail\MailManager($this->app))->mailer('array');
        $message = new TreeFundMail('verification', 'Pengguna Pengujian', [
            'title' => 'Pengujian Header Email', 'body' => 'Pesan lokal tanpa pengiriman SMTP.',
            'code' => '123456', 'minutes' => 15, 'url' => 'https://treefund.test/verification-example',
        ]);
        $message->to('recipient@treefund.test')->send($mailer);
        $messages = $mailer->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $email = $messages->first()->getOriginalMessage();
        $this->assertCount(1, $email->getFrom());
        $this->assertStringContainsString('123456', $email->getHtmlBody());
        $this->assertStringContainsString('Kode verifikasi: 123456', $email->getTextBody());
    }

    public function test_all_authentication_emails_render_html_and_plain_text_with_the_shared_action(): void
    {
        foreach (['verification', 'password_reset', 'security'] as $kind) {
            $payload = [
                'title' => 'Pengujian Email TreeFund',
                'body' => 'Pesan pengujian tanpa mengirim email.',
                'url' => 'https://treefund.test/verification-example',
                'action' => 'Buka Tautan Akun',
                'minutes' => 15,
            ];
            if ($kind === 'verification') {
                $payload['code'] = '123456';
            }
            $mail = new TreeFundMail($kind, 'Pengguna Pengujian', $payload);
            $html = $mail->render();
            $this->assertStringContainsString('Pengujian Email TreeFund', $html);
            $this->assertStringContainsString('Buka Tautan Akun', $html);
            $this->assertStringContainsString($payload['url'], $html);
            $plain = view($mail->content()->text, [
                'kind' => $kind, 'recipientName' => 'Pengguna Pengujian', 'payload' => $payload,
            ])->render();
            $this->assertStringContainsString($payload['url'], $plain);
            $this->assertStringContainsString('Pengguna Pengujian', $plain);
            if ($kind === 'verification') {
                $this->assertStringContainsString('123456', $html);
                $this->assertStringContainsString('Kode verifikasi: 123456', $plain);
            }
        }
    }
}
