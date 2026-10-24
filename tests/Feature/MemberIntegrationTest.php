<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Partner;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MemberIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(DatabaseSeeder::class);
    }

    private function anotherGroup(): User
    {
        $user = User::factory()->create(['role' => 'partner', 'password' => 'Kelompok!2026']);
        Partner::create([
            'user_id' => $user->id, 'group_name' => 'Kelompok Kedua', 'address' => 'Batu',
            'bank_name' => 'Bank Contoh', 'bank_account' => '0012345678', 'account_holder' => 'Kelompok Kedua',
            'document' => 'legal/kelompok-kedua.pdf', 'status' => 'approved',
        ]);

        return $user;
    }

    public function test_actual_login_switch_between_groups_preserves_member_ownership_through_the_crud_lifecycle(): void
    {
        $other = $this->anotherGroup();
        $this->post('/login', ['email' => 'mitra@treefund.test', 'password' => 'TreeFund!2026'])->assertRedirect('/dashboard');
        $this->from('/partner/members')->post('/partner/members', [
            'name' => 'Anggota Alur Lengkap', 'position' => 'Sekretaris', 'phone' => '08123456789',
        ])->assertRedirect('/partner/members')->assertSessionHasNoErrors();
        $member = Member::firstOrFail();
        $ownerId = $member->partner_id;
        $this->get('/partner/members?q=Alur')->assertOk()->assertSee('Anggota Alur Lengkap');
        $this->post('/logout')->assertRedirect('/');
        $this->post('/login', ['email' => $other->email, 'password' => 'Kelompok!2026'])->assertRedirect('/dashboard');
        $this->get('/partner/members?q=Alur')->assertOk()->assertViewHas('members', fn ($members) => $members->isEmpty());
        $this->put('/partner/members/'.$member->id, ['name' => 'Dibajak', 'position' => 'Ketua'])->assertForbidden();
        $this->delete('/partner/members/'.$member->id)->assertForbidden();
        $this->post('/partner/members', ['name' => 'Anggota Kelompok Kedua', 'position' => 'Ketua', 'partner_id' => $ownerId])->assertSessionHasNoErrors();
        $second = Member::where('name', 'Anggota Kelompok Kedua')->firstOrFail();
        $this->assertEquals($other->partner->id, $second->partner_id);
        $this->post('/logout')->assertRedirect('/');
        $this->post('/login', ['email' => 'mitra@treefund.test', 'password' => 'TreeFund!2026'])->assertRedirect('/dashboard');
        $this->put('/partner/members/'.$member->id, ['name' => 'Nama Sesudah Edit', 'position' => 'Bendahara'])->assertSessionHasNoErrors();
        $this->get('/partner/members?q=Bendahara')->assertOk()->assertSee('Nama Sesudah Edit')->assertDontSee('Anggota Kelompok Kedua');
        $this->delete('/partner/members/'.$member->id)->assertSessionHas('success');
        $this->assertDatabaseMissing('members', ['id' => $member->id]);
        $this->assertDatabaseHas('members', ['id' => $second->id, 'partner_id' => $other->partner->id]);
        Mail::assertNothingQueued();
    }

    public function test_member_update_uses_route_owner_and_ignores_forged_id_owner_and_account_fields(): void
    {
        $owner = User::where('role', 'partner')->firstOrFail();
        $other = $this->anotherGroup();
        $member = $owner->partner->members()->create(['name' => 'Anggota Pemilik', 'position' => 'Sekretaris']);
        $foreign = $other->partner->members()->create(['name' => 'Anggota Lain', 'position' => 'Ketua']);
        $this->actingAs($owner)->put('/partner/members/'.$member->id, [
            'name' => 'Nama Baru', 'position' => 'Anggota', 'id' => $foreign->id, 'member_id' => $foreign->id,
            'partner_id' => $other->partner->id, 'user_id' => $other->id, 'role' => 'admin', 'can_manage_finance' => true,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('members', ['id' => $member->id, 'name' => 'Nama Baru', 'partner_id' => $owner->partner->id]);
        $this->assertSame('Anggota Lain', $foreign->fresh()->name);
        $this->assertSame('partner', $owner->fresh()->role);
        $this->assertFalse($owner->fresh()->can_manage_finance);
        $this->assertDatabaseCount('members', 2);
    }

    public function test_session_logout_and_unverified_or_wrong_role_block_all_member_mutations(): void
    {
        $owner = User::where('role', 'partner')->firstOrFail();
        $member = $owner->partner->members()->create(['name' => 'Anggota Aman', 'position' => 'Anggota']);
        $data = ['name' => 'Nama Diubah', 'position' => 'Ketua'];
        foreach (['admin', 'donor'] as $role) {
            $this->actingAs(User::where('role', $role)->firstOrFail())->put('/partner/members/'.$member->id, $data)->assertForbidden();
            $this->delete('/partner/members/'.$member->id)->assertForbidden();
        }
        $owner->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($owner->fresh())->put('/partner/members/'.$member->id, $data)->assertRedirect('/email/verify');
        $this->delete('/partner/members/'.$member->id)->assertRedirect('/email/verify');
        $this->post('/logout')->assertRedirect('/');
        $this->put('/partner/members/'.$member->id, $data)->assertRedirect('/login');
        $this->delete('/partner/members/'.$member->id)->assertRedirect('/login');
        $this->assertSame('Anggota Aman', $member->fresh()->name);
        $this->assertDatabaseCount('members', 1);
    }

    public function test_script_like_member_text_is_escaped_in_list_edit_fields_and_confirmation_attributes(): void
    {
        $owner = User::where('role', 'partner')->firstOrFail();
        $payload = '<img src=x onerror=alert(1)>';
        $this->actingAs($owner)->post('/partner/members', ['name' => $payload, 'position' => '<script>alert(2)</script>', 'phone' => '" onfocus="alert(3)'])->assertSessionHasNoErrors();
        $response = $this->get('/partner/members')->assertOk()
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)->assertSee('&lt;script&gt;alert(2)&lt;/script&gt;', false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false)->assertDontSee('<script>alert(2)</script>', false);
        $dom = new \DOMDocument();
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(0, $xpath->query('//*[@onfocus or @onerror="alert(1)"]')->length);
        $this->assertSame($payload, $xpath->query('//form[@data-confirm-name]')->item(0)->getAttribute('data-confirm-name'));
    }
}
