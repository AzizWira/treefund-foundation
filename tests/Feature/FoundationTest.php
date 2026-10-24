<?php

namespace Tests\Feature;

use App\Mail\TreeFundMail;
use App\Models\Plot;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_public_pages_and_their_navigation_work_for_guests_and_every_role(): void
    {
        $paths = ['/', '/catalog', '/catalog?q=Alpukat', '/catalog?species=Kopi', '/peta', '/faq', '/informasi/tentang', '/informasi/cara-kerja', '/informasi/transparansi', '/informasi/privasi', '/informasi/ketentuan'];
        foreach ([null, 'admin', 'partner', 'donor'] as $role) {
            if ($role) {
                $this->actingAs(User::where('role', $role)->firstOrFail());
            }
            foreach ($paths as $path) {
                $this->get($path)->assertOk()->assertSee('site-header', false)->assertDontSee('foundation-placeholder', false);
            }
        }
        $this->get('/informasi/tidak-ada')->assertNotFound();
    }

    public function test_seeded_users_log_in_and_unfinished_portal_menus_remain_placeholders(): void
    {
        $routes = [
            'admin' => ['/admin/plantings', '/admin/monitorings', '/admin/disbursements', '/admin/reports', '/admin/settings'],
            'partner' => ['/partner/work-orders', '/partner/work-orders/1', '/partner/monitoring', '/partner/funds', '/partner/members'],
            'donor' => ['/checkout/1', '/orders', '/orders/1', '/certificates', '/trees'],
        ];
        foreach (['admin' => 'admin', 'partner' => 'mitra', 'donor' => 'donatur'] as $role => $email) {
            $this->post('/login', ['email' => $email.'@treefund.test', 'password' => 'TreeFund!2026'])->assertRedirect('/dashboard');
            foreach (array_merge(['/dashboard', '/settings', '/notifications'], $routes[$role]) as $path) {
                $this->get($path)->assertOk()->assertSee('Halaman ')->assertSee('foundation-placeholder', false)
                    ->assertSee('sidebar', false)->assertSee('topbar', false)->assertSee('app-footer', false)->assertDontSee('data-pay', false);
            }
            $this->post('/logout')->assertRedirect('/');
            $this->assertGuest();
        }
    }

    public function test_guests_and_wrong_roles_cannot_open_other_portals(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->actingAs(User::where('role', 'donor')->firstOrFail());
        $this->get('/admin/catalog')->assertRedirect('/dashboard');
        $this->getJson('/admin/catalog')->assertForbidden();
        $this->get('/partner/members')->assertRedirect('/dashboard');
    }

    public function test_registration_supports_real_code_verification_before_the_placeholder_dashboard(): void
    {
        $this->post('/register', [
            'name' => 'Pengguna Baru', 'email' => 'baru@treefund.test', 'role' => 'donor',
            'password' => 'Foundation!2026', 'password_confirmation' => 'Foundation!2026',
        ])->assertRedirect('/email/verify');
        $user = User::where('email', 'baru@treefund.test')->firstOrFail();
        $this->assertFalse($user->hasVerifiedEmail());
        $this->get('/dashboard')->assertRedirect('/email/verify');
        Mail::assertQueued(TreeFundMail::class);
        $message = Mail::queued(TreeFundMail::class)->first();
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $message->payload['code']);
        $this->post('/email/verify-code', ['code' => $message->payload['code']])->assertRedirect('/dashboard');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        app('auth')->forgetGuards();
        $this->get('/dashboard')->assertOk()->assertSee('Halaman Dashboard Donatur');
        $this->assertDatabaseMissing('email_verification_challenges', ['user_id' => $user->id]);
    }

    public function test_signed_email_link_verifies_without_signing_a_guest_in(): void
    {
        $user = User::factory()->unverified()->create();
        $user->sendEmailVerificationNotification();
        $url = Mail::queued(TreeFundMail::class)->first()->payload['url'];
        $this->get($url)->assertOk();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertGuest();
    }

    public function test_invalid_verification_code_and_rapid_resend_are_rejected(): void
    {
        $user = User::factory()->unverified()->create();
        $user->sendEmailVerificationNotification();
        $this->actingAs($user)->post('/email/verify-code', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post('/email/verification-notification')->assertRedirect();
        Mail::assertQueuedCount(1);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->assertSame(1, DB::table('email_verification_challenges')->where('user_id', $user->id)->value('attempts'));
    }

    public function test_password_recovery_uses_email_and_a_single_use_token(): void
    {
        $this->post('/forgot-password', ['email' => 'donatur@treefund.test'])->assertRedirect();
        $message = Mail::queued(TreeFundMail::class)->first();
        $this->assertSame('password_reset', $message->kind);
        parse_str(parse_url($message->payload['url'], PHP_URL_QUERY), $query);
        $token = basename(parse_url($message->payload['url'], PHP_URL_PATH));
        $data = ['token' => $token, 'email' => $query['email'], 'password' => 'NewPassword!2026', 'password_confirmation' => 'NewPassword!2026'];
        $this->get($message->payload['url'])->assertOk();
        $this->post('/reset-password', $data)->assertRedirect('/login');
        $this->post('/login', ['email' => $query['email'], 'password' => 'NewPassword!2026'])->assertRedirect('/dashboard');
        $this->post('/logout');
        $this->post('/reset-password', $data)->assertSessionHasErrors('email');
    }

    public function test_registration_cannot_create_an_admin_and_login_rejects_bad_passwords(): void
    {
        $this->post('/register', [
            'name' => 'Bukan Admin', 'email' => 'admin-baru@treefund.test', 'role' => 'admin',
            'password' => 'Foundation!2026', 'password_confirmation' => 'Foundation!2026',
        ])->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'admin-baru@treefund.test']);
        $this->post('/login', ['email' => 'admin@treefund.test', 'password' => 'salah'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_public_catalog_and_map_only_expose_published_fixture_data(): void
    {
        $plot = Plot::firstOrFail();
        $plot->update(['status' => 'pending']);
        $this->get('/catalog')->assertOk()->assertDontSee($plot->name);
        $data = $this->getJson('/peta/data')->assertOk()->assertJsonCount(2)->json();
        $this->assertArrayNotHasKey('bank_account', $data[0]);
        $this->assertArrayNotHasKey('document', $data[0]);
        $this->assertArrayNotHasKey('user_id', $data[0]);
        $this->assertFalse(collect($data)->contains('name', $plot->name));
    }

    public function test_business_transactions_are_not_installed_in_this_stage(): void
    {
        foreach (['adoptions', 'adoption_items', 'trees', 'certificates', 'work_orders', 'plantings', 'monitorings', 'disbursements'] as $table) {
            $this->assertFalse(Schema::hasTable($table), $table.' must be added in a later module.');
        }
        $this->actingAs(User::where('role', 'donor')->firstOrFail());
        $this->post('/orders')->assertStatus(405);
        $this->post('/payments/midtrans/notification')->assertNotFound();
        $this->post('/partner/partnership')->assertForbidden();
    }
}
