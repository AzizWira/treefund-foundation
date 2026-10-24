<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AccountProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_every_role_can_view_and_update_its_own_profile_using_the_original_layout(): void
    {
        foreach (['admin' => 'Admin Verifikator', 'partner' => 'Mitra Petani', 'donor' => 'Donatur'] as $role => $label) {
            $user = User::where('role', $role)->firstOrFail();
            $this->actingAs($user)->get('/settings')->assertOk()->assertSee('Pengaturan Profil & Keamanan', false)
                ->assertSee($user->name)->assertSee($user->email)->assertSee($label)
                ->assertSee('sidebar', false)->assertSee('app-footer', false)->assertDontSee('foundation-placeholder', false);
            $this->from('/settings')->put('/settings', ['name' => 'Nama Baru '.$role, 'phone' => '08123456789'])->assertRedirect('/settings')->assertSessionHasNoErrors()->assertSessionHas('success');
            $this->assertSame('Nama Baru '.$role, $user->fresh()->name);
            $this->assertSame('08123456789', $user->fresh()->phone);
            $this->get('/settings')->assertOk()->assertSee('Nama Baru '.$role);
        }
        Mail::assertNothingQueued();
    }

    public function test_invalid_profile_data_preserves_saved_values_and_restores_input(): void
    {
        $user = User::where('role', 'donor')->firstOrFail();
        $name = $user->name;
        $this->actingAs($user)->from('/settings')->put('/settings', ['name' => '', 'phone' => '08123456789'])->assertRedirect('/settings')->assertSessionHasErrors('name');
        $this->assertSame($name, $user->fresh()->name);
        $this->get('/settings')->assertOk()->assertSee('value="08123456789"', false);
        $this->put('/settings', ['name' => str_repeat('x', 121), 'phone' => str_repeat('1', 26)])->assertSessionHasErrors(['name', 'phone']);
        $this->put('/settings', ['name' => $name, 'phone' => ''])->assertSessionHasNoErrors();
        $this->assertNull($user->fresh()->phone);
    }

    public function test_profile_cannot_change_other_accounts_ownership_or_access_rights(): void
    {
        $user = User::where('role', 'donor')->firstOrFail();
        $admin = User::where('role', 'admin')->firstOrFail();
        $adminBefore = $admin->getAttributes();
        $this->actingAs($user)->put('/settings', [
            'name' => 'Profil Sendiri', 'phone' => '08123456789', 'id' => $admin->id,
            'user_id' => $admin->id, 'role' => 'admin', 'can_manage_finance' => true,
            'email_verified_at' => null, 'remember_token' => 'forged',
        ])->assertSessionHasNoErrors();
        $this->assertSame($adminBefore, $admin->fresh()->getAttributes());
        $this->assertSame('donor', $user->fresh()->role);
        $this->assertFalse($user->fresh()->can_manage_finance);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertSame('Profil Sendiri', $user->fresh()->name);
    }

    public function test_guests_are_redirected_and_unverified_users_can_access_profile_and_verification_link(): void
    {
        $this->get('/settings')->assertRedirect('/login');
        $this->put('/settings', ['name' => 'Nama'])->assertRedirect('/login');
        $user = User::factory()->unverified()->create(['role' => 'partner']);
        $this->actingAs($user)->get('/settings')->assertOk()->assertSee('Email belum terverifikasi.')->assertSee(route('verification.notice'), false);
        $this->put('/settings', ['name' => 'Profil Belum Verifikasi'])->assertSessionHasNoErrors();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->get('/dashboard')->assertRedirect('/email/verify');
    }
}
