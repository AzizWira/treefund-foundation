<?php

namespace Tests\Feature;

use App\Mail\TreeFundMail;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FoundationAccountEdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_login_accepts_the_same_email_with_different_letter_case(): void
    {
        $this->post('/login', [
            'email' => 'DONATUR@TREEFUND.TEST', 'password' => 'TreeFund!2026',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs(User::where('email', 'donatur@treefund.test')->firstOrFail());
    }

    public function test_registration_rejects_an_existing_email_before_normalizing_it_for_storage(): void
    {
        $this->post('/register', [
            'name' => 'Akun Duplikat', 'email' => 'DONATUR@TREEFUND.TEST', 'role' => 'donor',
            'password' => 'Foundation!2026', 'password_confirmation' => 'Foundation!2026',
        ])->assertSessionHasErrors('email');

        $this->assertSame(1, User::where('email', 'donatur@treefund.test')->count());
        $this->assertGuest();
        Mail::assertNothingQueued();
    }

    public function test_registration_stores_a_new_email_consistently_and_keeps_it_unverified(): void
    {
        $this->post('/register', [
            'name' => 'Mitra Baru', 'email' => 'MITRA.BARU@TREEFUND.TEST', 'role' => 'partner',
            'password' => 'Foundation!2026', 'password_confirmation' => 'Foundation!2026',
        ])->assertRedirect('/email/verify');

        $user = User::where('email', 'mitra.baru@treefund.test')->firstOrFail();
        $this->assertSame('partner', $user->role);
        $this->assertFalse($user->hasVerifiedEmail());
        Mail::assertQueued(TreeFundMail::class, fn ($mail) => $mail->hasTo($user->email));
    }

    public function test_expired_code_and_link_cannot_verify_an_account(): void
    {
        $user = User::factory()->unverified()->create();
        $user->sendEmailVerificationNotification();
        $message = Mail::queued(TreeFundMail::class)->first();
        $this->travel(16)->minutes();

        $this->actingAs($user)->post('/email/verify-code', [
            'code' => $message->payload['code'],
        ])->assertSessionHasErrors('code');
        $this->get($message->payload['url'])->assertStatus(410);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_resending_replaces_the_old_link_and_new_code_can_verify(): void
    {
        $user = User::factory()->unverified()->create();
        $user->sendEmailVerificationNotification();
        $oldMessage = Mail::queued(TreeFundMail::class)->first();
        $this->travel(61)->seconds();

        $this->actingAs($user)->post('/email/verification-notification')->assertRedirect();
        Mail::assertQueuedCount(2);
        $newMessage = Mail::queued(TreeFundMail::class)->last();
        $this->get($oldMessage->payload['url'])->assertStatus(410);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->post('/email/verify-code', [
            'code' => $newMessage->payload['code'],
        ])->assertRedirect('/dashboard');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertDatabaseMissing('email_verification_challenges', ['user_id' => $user->id]);
    }

    public function test_attempt_limit_blocks_even_a_correct_code_but_the_signed_link_still_works(): void
    {
        $user = User::factory()->unverified()->create();
        $user->sendEmailVerificationNotification();
        $message = Mail::queued(TreeFundMail::class)->first();
        DB::table('email_verification_challenges')->where('user_id', $user->id)->update(['attempts' => 5]);

        $this->actingAs($user)->post('/email/verify-code', [
            'code' => $message->payload['code'],
        ])->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->get($message->payload['url'])->assertOk();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }
}
