<?php

namespace Tests\Feature;

use App\Mail\TreeFundMail;
use App\Models\ActivityNotification;
use App\Models\Monitoring;
use App\Models\Offer;
use App\Models\User;
use App\Services\ActivityService;
use App\Services\AdoptionService;
use App\Services\EmailService;
use App\Services\EmailVerificationService;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class EmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Mail::fake();
        $this->seed(DemoSeeder::class);
        Mail::fake();
    }

    private function verificationMail(User $user): TreeFundMail
    {
        app(EmailVerificationService::class)->issue($user);

        return Mail::queued(TreeFundMail::class, fn ($mail) => $mail->kind === 'verification' && $mail->hasTo($user->email))->last();
    }

    public function test_registration_queues_both_code_and_signed_link_and_protects_portal(): void
    {
        $this->post('/register', ['name' => 'Pengguna Baru', 'email' => 'new-user@example.com', 'role' => 'donor', 'password' => 'StrongPassword123!', 'password_confirmation' => 'StrongPassword123!'])->assertRedirect('/email/verify');
        $user = User::where('email', 'new-user@example.com')->firstOrFail();
        $mail = Mail::queued(TreeFundMail::class)->first();
        $this->assertSame('verification', $mail->kind);
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $mail->payload['code']);
        $this->assertStringContainsString('signature=', $mail->payload['url']);
        $row = DB::table('email_verification_challenges')->where('user_id', $user->id)->first();
        $this->assertNotSame($mail->payload['code'], $row->code_hash);
        $this->assertTrue(Hash::check($mail->payload['code'], $row->code_hash));
        $this->get('/dashboard')->assertRedirect('/email/verify');
        $this->get('/donor/catalog')->assertRedirect('/email/verify');
        $this->get('/settings')->assertOk();
        $this->post('/orders', ['offer_id' => Offer::first()->id, 'quantity' => 1])->assertRedirect('/email/verify');
    }

    public function test_code_verifies_email_once_and_consumes_challenge(): void
    {
        $user = User::factory()->unverified()->create();
        $mail = $this->verificationMail($user);
        $this->actingAs($user)->post('/email/verify-code', ['code' => $mail->payload['code']])->assertRedirect('/dashboard');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertDatabaseMissing('email_verification_challenges', ['user_id' => $user->id]);
        $this->post('/email/verify-code', ['code' => $mail->payload['code']])->assertRedirect('/dashboard');
        Mail::assertQueued(TreeFundMail::class, fn ($mail) => $mail->kind === 'security' && $mail->hasTo($user->email));
        $this->assertCount(1, Mail::queued(TreeFundMail::class, fn ($mail) => $mail->kind === 'security'));
    }

    public function test_signed_link_verifies_without_logging_the_recipient_in(): void
    {
        $user = User::factory()->unverified()->create();
        $mail = $this->verificationMail($user);
        $this->get($mail->payload['url'])->assertOk()->assertSee('Email berhasil diverifikasi');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertGuest();
        $this->get($mail->payload['url'])->assertOk();
        $this->assertCount(1, Mail::queued(TreeFundMail::class, fn ($mail) => $mail->kind === 'security'));
    }

    public function test_bad_signature_and_expired_link_cannot_verify_an_email(): void
    {
        $user = User::factory()->unverified()->create();
        $mail = $this->verificationMail($user);
        $this->get($mail->payload['url'].'tampered')->assertStatus(410);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->travel(16)->minutes();
        $this->get($mail->payload['url'])->assertStatus(410);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_code_expires_and_attempt_limit_is_persisted(): void
    {
        $user = User::factory()->unverified()->create();
        $mail = $this->verificationMail($user);
        $wrong = $mail->payload['code'] === '111111' ? '222222' : '111111';
        $this->actingAs($user);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/email/verify-code', ['code' => $wrong])->assertSessionHasErrors('code');
        }
        $this->assertDatabaseHas('email_verification_challenges', ['user_id' => $user->id, 'attempts' => 5]);
        $this->post('/email/verify-code', ['code' => $mail->payload['code']])->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->get($mail->payload['url'])->assertOk();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_resend_has_cooldown_and_invalidates_earlier_code_and_link(): void
    {
        $user = User::factory()->unverified()->create();
        $first = $this->verificationMail($user);
        $this->assertFalse(app(EmailVerificationService::class)->issue($user));
        $this->assertCount(1, Mail::queued(TreeFundMail::class));
        $this->travel(61)->seconds();
        $second = $this->verificationMail($user);
        $this->get($first->payload['url'])->assertStatus(410);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->actingAs($user)->post('/email/verify-code', ['code' => $second->payload['code']])->assertRedirect('/dashboard');
    }

    public function test_code_for_another_user_does_not_verify_current_user(): void
    {
        $owner = User::factory()->unverified()->create();
        $stranger = User::factory()->unverified()->create();
        $mail = $this->verificationMail($owner);
        $this->actingAs($stranger)->post('/email/verify-code', ['code' => $mail->payload['code']])->assertSessionHasErrors('code');
        $this->assertFalse($owner->fresh()->hasVerifiedEmail());
        $this->assertFalse($stranger->fresh()->hasVerifiedEmail());
    }

    public function test_expired_code_cannot_activate_an_account(): void
    {
        $user = User::factory()->unverified()->create();
        $mail = $this->verificationMail($user);
        $this->travel(16)->minutes();
        $this->actingAs($user)->post('/email/verify-code', ['code' => $mail->payload['code']])->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_changed_email_invalidates_an_existing_verification_link(): void
    {
        $user = User::factory()->unverified()->create();
        $mail = $this->verificationMail($user);
        $user->email = 'different@example.com';
        $user->save();
        $this->get($mail->payload['url'])->assertStatus(410);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_password_reset_hides_account_existence_and_enforces_one_time_token(): void
    {
        $user = User::factory()->create();
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');
        Mail::assertQueued(TreeFundMail::class, fn ($mail) => $mail->kind === 'password_reset' && $mail->hasTo($user->email));
        $this->post('/forgot-password', ['email' => 'missing@example.com'])->assertSessionHas('status');
        $token = Password::createToken($user);
        DB::table('sessions')->insert(['id' => 'old-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        $data = ['token' => $token, 'email' => $user->email, 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!'];
        $this->post('/reset-password', $data)->assertRedirect('/login');
        $this->assertTrue(Hash::check($data['password'], $user->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'old-session']);
        $this->post('/reset-password', $data)->assertSessionHasErrors('email');
        Mail::assertQueued(TreeFundMail::class, fn ($mail) => $mail->kind === 'security');
    }

    public function test_email_change_requires_password_and_sends_new_verification(): void
    {
        $user = User::factory()->create(['password' => 'CurrentPass123!']);
        $oldEmail = $user->email;
        $this->actingAs($user)->put('/settings', ['name' => $user->name, 'email' => 'changed@example.com'])->assertSessionHasErrors('current_password');
        $this->put('/settings', ['name' => $user->name, 'email' => 'changed@example.com', 'current_password' => 'CurrentPass123!'])->assertRedirect('/email/verify');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        Mail::assertQueued(TreeFundMail::class, fn ($mail) => $mail->kind === 'verification' && $mail->hasTo('changed@example.com'));
        Mail::assertQueued(TreeFundMail::class, fn ($mail) => $mail->kind === 'security' && $mail->hasTo($oldEmail));
    }

    public function test_optional_email_preferences_do_not_disable_security_or_payment_messages(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->put('/email/preferences', [])->assertRedirect();
        $service = app(EmailService::class);
        foreach (['field_report', 'reminder', 'admin_digest'] as $kind) {
            $service->send($user->fresh(), $kind, ['title' => 'Optional']);
        }
        Mail::assertNothingQueued();
        $service->send($user->fresh(), 'security', ['title' => 'Security']);
        $service->send($user->fresh(), 'payment_success', ['title' => 'Payment']);
        $this->assertCount(3, Mail::queued(TreeFundMail::class));
    }

    public function test_payment_webhook_replays_do_not_duplicate_success_or_certificate_emails(): void
    {
        $donor = User::where('role', 'donor')->firstOrFail();
        $order = app(AdoptionService::class)->checkout($donor, Offer::first(), 1, 'Sertifikat');
        $status = ['transaction_status' => 'settlement', 'transaction_id' => 'test-provider-transaction'];
        app(AdoptionService::class)->applyProviderStatus($order, $status);
        app(AdoptionService::class)->applyProviderStatus($order, $status);
        $this->assertCount(1, Mail::queued(TreeFundMail::class, fn ($mail) => $mail->kind === 'payment_success'));
        $this->assertCount(1, Mail::queued(TreeFundMail::class, fn ($mail) => $mail->kind === 'certificate'));
        $this->assertCount(1, Mail::queued(TreeFundMail::class, fn ($mail) => $mail->kind === 'work_order'));
    }

    public function test_unverified_users_do_not_receive_activity_email_but_keep_in_app_notifications(): void
    {
        $user = User::factory()->unverified()->create();
        app(ActivityService::class)->notify($user->id, 'Kabar', 'Kabar pengujian');
        Mail::assertNothingQueued();
        $this->assertDatabaseHas('activity_notifications', ['user_id' => $user->id, 'title' => 'Kabar']);
    }

    public function test_payment_reminder_and_admin_digest_are_deduplicated(): void
    {
        $donor = User::where('role', 'donor')->firstOrFail();
        $order = app(AdoptionService::class)->checkout($donor, Offer::first(), 1, 'Pengingat');
        $order->update(['expires_at' => now()->addMinutes(10)]);
        Mail::fake();
        $this->artisan('treefund:payment-reminders')->assertSuccessful();
        $this->artisan('treefund:payment-reminders')->assertSuccessful();
        $this->assertCount(1, Mail::queued(TreeFundMail::class, fn ($mail) => $mail->kind === 'payment_reminder'));
        $this->artisan('treefund:admin-digest')->assertSuccessful();
        $this->artisan('treefund:admin-digest')->assertSuccessful();
        $this->assertCount(1, Mail::queued(TreeFundMail::class, fn ($mail) => $mail->kind === 'admin_digest'));
        $this->assertSame(1, ActivityNotification::where('user_id', $donor->id)->where('title', 'Pengingat pembayaran '.$order->reference)->count());
    }

    public function test_sensitive_mails_are_encrypted_queued_and_sent_after_commit(): void
    {
        $mail = new TreeFundMail('verification', 'Tester', ['title' => 'Verification', 'code' => '123456']);
        $job = new SendQueuedMailable($mail);
        $this->assertTrue($job->shouldBeEncrypted);
        $this->assertTrue($job->afterCommit);
        $this->assertSame('default', $job->queue);
        $this->assertSame(3, $job->tries);
    }

    public function test_monitoring_reminders_are_grouped_and_limited_to_once_per_week(): void
    {
        Monitoring::where('status', 'approved')->update(['observed_at' => now()->subMonths(4)]);
        $this->artisan('treefund:monitoring-reminders')->assertSuccessful();
        $this->artisan('treefund:monitoring-reminders')->assertSuccessful();
        $this->assertCount(1, Mail::queued(TreeFundMail::class, fn ($mail) => $mail->kind === 'reminder'));
        $this->travel(8)->days();
        $this->artisan('treefund:monitoring-reminders')->assertSuccessful();
        $this->assertCount(2, Mail::queued(TreeFundMail::class, fn ($mail) => $mail->kind === 'reminder'));
    }

    public function test_every_email_template_renders_distinct_html_and_plain_text(): void
    {
        $templates = ['verification', 'password_reset', 'security', 'payment_pending', 'payment_success', 'payment_failed', 'certificate', 'field_report', 'partnership', 'work_order', 'funds', 'reminder', 'payment_reminder', 'admin_digest', 'admin_request', 'payment_review', 'activity'];
        $rendered = [];
        foreach ($templates as $kind) {
            $mail = new TreeFundMail($kind, '<script>unsafe</script>', [
                'title' => 'Kabar TreeFund', 'body' => '<script>Unsafe body</script>', 'url' => route('dashboard'), 'action' => 'Lihat Detail',
                'code' => '123456', 'minutes' => 15, 'amount' => 125000, 'reference' => 'TF-TEST', 'deadline' => '18 Okt 2026 18:00',
                'stats' => ['Menunggu verifikasi' => 3], 'species' => 'Alpukat', 'quantity' => 1, 'decision' => 'revision',
            ]);
            $html = $mail->render();
            $this->assertStringContainsString('TreeFund', $html);
            $this->assertStringContainsString('Lihat Detail', $html);
            $this->assertStringNotContainsString('<script>', $html);
            $rendered[$kind] = $html;
            $this->assertStringContainsString('TF-TEST', view('emails.text', ['payload' => $mail->payload, 'recipientName' => 'Tester'])->render());
        }
        $this->assertNotSame($rendered['verification'], $rendered['password_reset']);
        $this->assertNotSame($rendered['certificate'], $rendered['payment_success']);
        $this->assertNotSame($rendered['funds'], $rendered['work_order']);
    }
}
