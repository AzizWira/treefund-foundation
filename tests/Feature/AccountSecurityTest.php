<?php

namespace Tests\Feature;

use App\Mail\TreeFundMail;
use App\Models\User;
use App\Services\EmailService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(DatabaseSeeder::class);
    }

    private function data(User $user, array $changes = []): array
    {
        return array_replace(['name' => $user->name, 'email' => $user->email, 'phone' => $user->phone], $changes);
    }

    public function test_each_role_changes_email_only_with_password_then_verifies_new_email_by_code(): void
    {
        foreach (['admin', 'partner', 'donor'] as $role) {
            Mail::fake();
            $user = User::where('role', $role)->firstOrFail();
            $oldEmail = $user->email;
            $this->actingAs($user)->put('/settings', $this->data($user, [
                'email' => 'BARU-'.$role.'@TREEFUND.TEST', 'current_password' => 'TreeFund!2026',
            ]))->assertRedirect('/email/verify')->assertSessionHasNoErrors();
            $user->refresh();
            $this->assertSame('baru-'.$role.'@treefund.test', $user->email);
            $this->assertFalse($user->hasVerifiedEmail());
            Mail::assertQueued(TreeFundMail::class, fn ($mail) => $mail->kind === 'security' && $mail->hasTo($oldEmail));
            Mail::assertQueued(TreeFundMail::class, fn ($mail) => $mail->kind === 'verification' && $mail->hasTo($user->email));
            Mail::assertQueuedCount(2);
            app('auth')->forgetGuards();
            $this->actingAs($user->fresh())->get('/dashboard')->assertRedirect('/email/verify');
            $message = Mail::queued(TreeFundMail::class, fn ($mail) => $mail->kind === 'verification')->first();
            $this->post('/email/verify-code', ['code' => $message->payload['code']])->assertRedirect('/dashboard');
            $this->assertTrue($user->fresh()->hasVerifiedEmail());
            $this->assertDatabaseMissing('email_verification_challenges', ['user_id' => $user->id]);
        }
    }

    public function test_password_change_for_every_role_hashes_new_password_rotates_token_and_invalidates_old_login(): void
    {
        foreach (['admin', 'partner', 'donor'] as $role) {
            Mail::fake();
            $user = User::where('role', $role)->firstOrFail();
            $oldToken = $user->remember_token;
            $this->actingAs($user)->from('/settings')->put('/settings', $this->data($user, [
                'current_password' => 'TreeFund!2026', 'password' => 'PasswordBaru!2026', 'password_confirmation' => 'PasswordBaru!2026',
            ]))->assertRedirect('/settings')->assertSessionHasNoErrors();
            $user->refresh();
            $this->assertTrue(Hash::check('PasswordBaru!2026', $user->password));
            $this->assertNotSame('PasswordBaru!2026', $user->password);
            $this->assertNotSame($oldToken, $user->remember_token);
            $this->assertTrue($user->hasVerifiedEmail());
            Mail::assertQueued(TreeFundMail::class, fn ($mail) => $mail->kind === 'security' && $mail->hasTo($user->email));
            $this->post('/logout')->assertRedirect('/');
            $this->post('/login', ['email' => $user->email, 'password' => 'TreeFund!2026'])->assertSessionHasErrors('email');
            $this->post('/login', ['email' => $user->email, 'password' => 'PasswordBaru!2026'])->assertRedirect('/dashboard');
            $this->post('/logout');
        }
    }

    public function test_invalid_current_password_confirmation_duplicate_email_and_short_password_do_not_change_account(): void
    {
        $user = User::where('role', 'donor')->firstOrFail();
        $before = $user->getAttributes();
        $this->actingAs($user);
        foreach ([null, 'Salah!2026'] as $password) {
            $this->put('/settings', $this->data($user, ['email' => 'baru@treefund.test', 'current_password' => $password]))->assertSessionHasErrors('current_password');
            $this->put('/settings', $this->data($user, ['current_password' => $password, 'password' => 'Baru!2026', 'password_confirmation' => 'Baru!2026']))->assertSessionHasErrors('current_password');
        }
        $this->put('/settings', $this->data($user, ['email' => 'ADMIN@TREEFUND.TEST', 'current_password' => 'TreeFund!2026']))->assertSessionHasErrors('email');
        $this->put('/settings', $this->data($user, ['current_password' => 'TreeFund!2026', 'password' => 'short', 'password_confirmation' => 'short']))->assertSessionHasErrors('password');
        $this->put('/settings', $this->data($user, ['current_password' => 'TreeFund!2026', 'password' => 'Baru!2026', 'password_confirmation' => 'Berbeda!2026']))->assertSessionHasErrors('password');
        $this->assertSame($before, $user->fresh()->getAttributes());
        $old = session()->getOldInput();
        $this->assertArrayNotHasKey('password', $old);
        $this->assertArrayNotHasKey('password_confirmation', $old);
        $this->assertArrayNotHasKey('current_password', $old);
        Mail::assertNothingQueued();
    }

    public function test_email_change_replaces_old_challenge_and_new_link_verifies_only_new_address(): void
    {
        $user = User::factory()->unverified()->create(['role' => 'donor', 'password' => 'Akun!2026']);
        $user->sendEmailVerificationNotification();
        $oldMessage = Mail::queued(TreeFundMail::class)->first();
        $this->actingAs($user)->put('/settings', $this->data($user, ['email' => 'alamat-baru@treefund.test', 'current_password' => 'Akun!2026']))->assertRedirect('/email/verify');
        $newMessage = Mail::queued(TreeFundMail::class, fn ($mail) => $mail->kind === 'verification' && $mail->hasTo('alamat-baru@treefund.test'))->first();
        $this->assertNotSame($oldMessage->payload['url'], $newMessage->payload['url']);
        $this->get($oldMessage->payload['url'])->assertStatus(410);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->get($newMessage->payload['url'])->assertOk();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_case_only_email_change_does_not_revoke_verification_or_send_mail(): void
    {
        $user = User::where('role', 'partner')->firstOrFail();
        $verifiedAt = $user->email_verified_at->toISOString();
        $this->actingAs($user)->put('/settings', $this->data($user, ['email' => strtoupper($user->email)]))->assertSessionHasNoErrors();
        $this->assertSame($verifiedAt, $user->fresh()->email_verified_at->toISOString());
        Mail::assertNothingQueued();
    }

    public function test_sensitive_changes_remove_only_other_sessions_for_the_same_user(): void
    {
        $user = User::where('role', 'donor')->firstOrFail();
        $admin = User::where('role', 'admin')->firstOrFail();
        foreach (['password', 'email'] as $kind) {
            $this->actingAs($user->fresh())->get('/settings')->assertOk();
            $currentSession = session()->getId();
            foreach ([[$currentSession, $user->id], ['other-user-session', $user->id], ['admin-session', $admin->id]] as [$id, $userId]) {
                DB::table('sessions')->updateOrInsert(['id' => $id], ['user_id' => $userId, 'payload' => '', 'last_activity' => time()]);
            }
            $changes = $kind === 'password'
                ? ['current_password' => 'TreeFund!2026', 'password' => 'Lanjut!2026', 'password_confirmation' => 'Lanjut!2026']
                : ['email' => 'sesi-baru@treefund.test', 'current_password' => 'Lanjut!2026'];
            $this->withCookie(config('session.cookie'), $currentSession)
                ->put('/settings', $this->data($user->fresh(), $changes))->assertSessionHasNoErrors();
            $this->assertDatabaseHas('sessions', ['id' => $currentSession]);
            $this->assertDatabaseMissing('sessions', ['id' => 'other-user-session']);
            $this->assertDatabaseHas('sessions', ['id' => 'admin-session']);
        }
    }

    public function test_verification_failure_rolls_back_email_and_keeps_previous_session(): void
    {
        $user = User::where('role', 'donor')->firstOrFail();
        $before = $user->getAttributes();
        $this->mock(EmailService::class, function ($mock): void {
            $mock->shouldReceive('send')->withArgs(fn ($recipient, $kind) => $kind === 'security')->once();
            $mock->shouldReceive('send')->withArgs(fn ($recipient, $kind) => $kind === 'verification')->once()->andThrow(new \RuntimeException('Verification mail failed'));
        });
        $this->actingAs($user)->put('/settings', $this->data($user, ['email' => 'gagal@treefund.test', 'current_password' => 'TreeFund!2026']))->assertStatus(500);
        $this->assertSame($before, $user->fresh()->getAttributes());
        $this->assertDatabaseMissing('email_verification_challenges', ['user_id' => $user->id]);
    }
}
