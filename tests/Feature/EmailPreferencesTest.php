<?php

namespace Tests\Feature;

use App\Mail\TreeFundMail;
use App\Models\User;
use App\Services\EmailService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailPreferencesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_every_role_can_save_and_reload_its_preferences_without_altering_another_account(): void
    {
        foreach (['admin', 'partner', 'donor'] as $role) {
            $user = User::where('role', $role)->firstOrFail();
            $other = User::where('id', '!=', $user->id)->firstOrFail();
            $otherBefore = $other->getAttributes();
            $this->actingAs($user)->get('/settings')->assertOk()->assertSee('Notifikasi Email')
                ->assertSee('name="updates"', false)->assertSee('name="reminders"', false);
            $this->from('/settings')->put('/email/preferences', [
                'updates' => '1', 'digest' => '1', 'user_id' => $other->id, 'role' => 'admin', 'can_manage_finance' => true,
            ])->assertRedirect('/settings')->assertSessionHasNoErrors()->assertSessionHas('success');
            $preferences = $user->fresh()->email_preferences;
            $this->assertSame(['updates' => true, 'reminders' => false, 'digest' => true], $preferences);
            $this->assertSame($role, $user->fresh()->role);
            $this->assertSame($otherBefore, $other->fresh()->getAttributes());
            $this->actingAs($user->fresh())->get('/settings')->assertOk()->assertViewHas('user', fn ($user) => $user->wantsEmail('updates') && ! $user->wantsEmail('reminders'));
        }
        Mail::assertNothingQueued();
    }

    public function test_unchecked_boxes_disable_routine_mail_but_security_verification_and_decisions_still_queue(): void
    {
        $user = User::where('role', 'donor')->firstOrFail();
        $this->actingAs($user)->put('/email/preferences', [])->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertSame(['updates' => false, 'reminders' => false, 'digest' => false], $user->email_preferences);
        $service = app(EmailService::class);
        foreach (['field_report', 'reminder', 'payment_reminder', 'admin_digest'] as $kind) {
            $service->send($user, $kind, ['title' => 'Kabar rutin']);
        }
        Mail::assertNothingQueued();
        foreach (['security', 'partnership', 'admin_request', 'password_reset'] as $kind) {
            $service->send($user, $kind, ['title' => 'Pesan wajib']);
        }
        Mail::assertQueuedCount(4);
        Mail::fake();
        $user->forceFill(['email_verified_at' => null])->save();
        $user->sendEmailVerificationNotification();
        Mail::assertQueued(TreeFundMail::class, fn ($mail) => $mail->kind === 'verification' && $mail->hasTo($user->email));
    }

    public function test_enabled_categories_queue_routine_mail_and_unverified_users_do_not_receive_routine_mail(): void
    {
        $user = User::where('role', 'partner')->firstOrFail();
        $this->actingAs($user)->put('/email/preferences', ['updates' => '1', 'reminders' => '1', 'digest' => '1'])->assertSessionHasNoErrors();
        $service = app(EmailService::class);
        foreach (['field_report', 'reminder', 'payment_reminder', 'admin_digest'] as $kind) {
            $service->send($user->fresh(), $kind, ['title' => 'Kabar rutin']);
        }
        Mail::assertQueuedCount(4);
        Mail::fake();
        $user->forceFill(['email_verified_at' => null])->save();
        $service->send($user, 'field_report', ['title' => 'Kabar rutin']);
        Mail::assertNothingQueued();
    }

    public function test_guests_and_invalid_values_cannot_change_preferences(): void
    {
        $this->put('/email/preferences', ['updates' => '1'])->assertRedirect('/login');
        $user = User::where('role', 'donor')->firstOrFail();
        $before = $user->email_preferences;
        $this->actingAs($user)->put('/email/preferences', ['updates' => 'yes', 'reminders' => ['1'], 'digest' => 2])->assertSessionHasErrors(['updates', 'reminders', 'digest']);
        $this->assertSame($before, $user->fresh()->email_preferences);
        $this->actingAs(User::factory()->unverified()->create(['role' => 'partner']))->put('/email/preferences', [])->assertSessionHasNoErrors();
    }

    public function test_profile_and_password_changes_keep_existing_preferences_and_send_mandatory_security_mail(): void
    {
        $user = User::where('role', 'donor')->firstOrFail();
        $this->actingAs($user)->put('/email/preferences', [])->assertSessionHasNoErrors();
        $preferences = $user->fresh()->email_preferences;
        $this->put('/settings', [
            'name' => 'Profil Terakhir', 'email' => $user->email, 'phone' => '08123456789',
            'current_password' => 'TreeFund!2026', 'password' => 'BaruTerakhir!2026', 'password_confirmation' => 'BaruTerakhir!2026',
            'email_preferences' => ['updates' => true],
        ])->assertSessionHasNoErrors();
        $this->assertSame($preferences, $user->fresh()->email_preferences);
        Mail::assertQueued(TreeFundMail::class, fn ($mail) => $mail->kind === 'security');
        $this->post('/logout')->assertRedirect('/');
        $this->post('/login', ['email' => $user->email, 'password' => 'BaruTerakhir!2026'])->assertRedirect('/dashboard');
        $this->get('/settings')->assertOk()->assertSee('Profil Terakhir')->assertViewHas('user', fn ($user) => ! $user->wantsEmail('updates'));
    }
}
