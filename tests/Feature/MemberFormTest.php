<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Partner;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MemberFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(DatabaseSeeder::class);
    }

    private function owner(): User
    {
        return User::where('role', 'partner')->firstOrFail();
    }

    public function test_partner_can_add_member_and_edit_the_same_record_without_changing_its_owner(): void
    {
        $user = $this->owner();
        $this->actingAs($user)->get('/partner/members')->assertOk()->assertSee('Data Anggota Poktan')
            ->assertSee('Tambah Anggota')->assertSee('sidebar', false)->assertDontSee('foundation-placeholder', false);
        $this->from('/partner/members')->post('/partner/members', [
            'name' => 'Anggota Baru', 'position' => 'Sekretaris', 'phone' => '08123456789', 'partner_id' => 999999,
        ])->assertRedirect('/partner/members')->assertSessionHasNoErrors()->assertSessionHas('success');
        $member = Member::firstOrFail();
        $this->assertEquals($user->partner->id, $member->partner_id);
        $this->put('/partner/members/'.$member->id, [
            'name' => 'Anggota Diperbarui', 'position' => 'Bendahara', 'phone' => '', 'partner_id' => 999999,
        ])->assertRedirect('/partner/members')->assertSessionHasNoErrors();
        $member->refresh();
        $this->assertSame('Anggota Diperbarui', $member->name);
        $this->assertSame('Bendahara', $member->position);
        $this->assertNull($member->phone);
        $this->assertEquals($user->partner->id, $member->partner_id);
        $this->assertDatabaseCount('members', 1);
        $this->get('/partner/members')->assertOk()->assertSee('Anggota Diperbarui')->assertSee('Simpan Perubahan');
        Mail::assertNothingQueued();
    }

    public function test_member_fields_are_validated_and_invalid_edit_preserves_the_record(): void
    {
        $user = $this->owner();
        $member = $user->partner->members()->create(['name' => 'Nama Asli', 'position' => 'Anggota']);
        $this->actingAs($user)->post('/partner/members', [])->assertSessionHasErrors(['name', 'position']);
        foreach ([['name' => str_repeat('x', 121)], ['position' => str_repeat('x', 101)], ['phone' => str_repeat('1', 26)]] as $invalid) {
            $this->post('/partner/members', array_replace(['name' => 'Baru', 'position' => 'Anggota'], $invalid))->assertSessionHasErrors(array_keys($invalid));
        }
        $this->put('/partner/members/'.$member->id, ['name' => '   ', 'position' => ''])->assertSessionHasErrors(['name', 'position']);
        $this->assertSame('Nama Asli', $member->fresh()->name);
        $this->assertDatabaseCount('members', 1);
    }

    public function test_other_group_cannot_see_or_edit_members_and_missing_member_returns_not_found(): void
    {
        $user = $this->owner();
        $member = $user->partner->members()->create(['name' => 'Anggota Privat', 'position' => 'Ketua']);
        $otherUser = User::factory()->create(['role' => 'partner']);
        $attributes = $user->partner->getAttributes();
        unset($attributes['id'], $attributes['created_at'], $attributes['updated_at']);
        Partner::create(array_replace($attributes, ['user_id' => $otherUser->id]));
        $this->actingAs($otherUser)->get('/partner/members')->assertOk()->assertDontSee('Anggota Privat');
        $this->put('/partner/members/'.$member->id, ['name' => 'Diubah', 'position' => 'Ketua'])->assertForbidden();
        $this->assertSame('Anggota Privat', $member->fresh()->name);
        $this->actingAs($user)->put('/partner/members/999999', ['name' => 'Diubah', 'position' => 'Ketua'])->assertNotFound();
    }

    public function test_missing_partnership_guests_other_roles_and_unverified_users_cannot_save(): void
    {
        $data = ['name' => 'Nama', 'position' => 'Anggota'];
        $this->post('/partner/members', $data)->assertRedirect('/login');
        foreach (['admin', 'donor'] as $role) {
            $this->actingAs(User::where('role', $role)->firstOrFail())->post('/partner/members', $data)->assertForbidden();
        }
        $this->actingAs(User::factory()->unverified()->create(['role' => 'partner']))->post('/partner/members', $data)->assertRedirect('/email/verify');
        $this->actingAs(User::factory()->create(['role' => 'partner']))->get('/partner/members')->assertOk()->assertSee('Lengkapi profil kemitraan');
        $this->post('/partner/members', $data)->assertForbidden();
        $this->assertDatabaseCount('members', 0);
    }

    public function test_failed_edit_restores_only_the_edited_form_and_keeps_its_details_open(): void
    {
        $user = $this->owner();
        $first = $user->partner->members()->create(['name' => 'Anggota Satu', 'position' => 'Ketua']);
        $second = $user->partner->members()->create(['name' => 'Anggota Dua', 'position' => 'Sekretaris']);
        $this->actingAs($user)->from('/partner/members')->put('/partner/members/'.$first->id, [
            'member_id' => $first->id, 'name' => 'Isian Koreksi', 'position' => '',
        ])->assertRedirect('/partner/members')->assertSessionHasErrors('position');
        $response = $this->get('/partner/members')->assertOk();
        $dom = new \DOMDocument();
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        foreach (['/partner/members' => '', '/partner/members/'.$first->id => 'Isian Koreksi', '/partner/members/'.$second->id => 'Anggota Dua'] as $path => $name) {
            $input = $xpath->query('//form[@action="'.url($path).'"]//input[@name="name"]')->item(0);
            $this->assertNotNull($input);
            $this->assertSame($name, $input->getAttribute('value'));
        }
        $this->assertSame(1, $xpath->query('//details[@open]')->length);
    }
}
