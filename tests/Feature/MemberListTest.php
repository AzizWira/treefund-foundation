<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MemberListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_search_matches_name_position_and_phone_and_pagination_preserves_query(): void
    {
        $owner = User::where('role', 'partner')->firstOrFail();
        for ($i = 1; $i <= 10; $i++) {
            $owner->partner->members()->create(['name' => 'Anggota Pencarian '.$i, 'position' => 'Relawan', 'phone' => '08123456789']);
        }
        $this->actingAs($owner);
        foreach (['Pencarian', 'Relawan', '08123456789'] as $query) {
            $this->get('/partner/members?q='.$query)->assertOk()->assertViewHas('members', fn ($members) => $members->total() === 10 && $members->count() === 8
                && str_contains($members->nextPageUrl(), 'q='.$query));
        }
        $this->get('/partner/members?q=Pencarian&page=2')->assertOk()->assertViewHas('members', fn ($members) => $members->count() === 2);
        $this->get('/partner/members?q=TidakAda')->assertOk()->assertViewHas('members', fn ($members) => $members->isEmpty());
        $this->getJson('/partner/members?q[]=test')->assertUnprocessable()->assertJsonValidationErrors('q');
    }

    public function test_delete_confirmation_has_member_name_and_only_deletes_on_delete_request(): void
    {
        $owner = User::where('role', 'partner')->firstOrFail();
        $member = $owner->partner->members()->create(['name' => 'Anggota Uji', 'position' => 'Ketua']);
        $this->actingAs($owner)->get('/partner/members')->assertOk()
            ->assertSee('data-confirm-name="Anggota Uji"', false)->assertSee('id="member-delete-confirm"', false)
            ->assertSee('Ya, Hapus Anggota')->assertSee('Batal')->assertSee('name="_method" value="DELETE"', false);
        $this->assertDatabaseHas('members', ['id' => $member->id]);
        $this->from('/partner/members')->delete('/partner/members/'.$member->id)->assertRedirect('/partner/members')->assertSessionHas('success');
        $this->assertDatabaseMissing('members', ['id' => $member->id]);
        $this->delete('/partner/members/'.$member->id)->assertNotFound();
        Mail::assertNothingQueued();
    }

    public function test_only_member_owner_can_delete_and_role_or_guest_requests_are_blocked(): void
    {
        $owner = User::where('role', 'partner')->firstOrFail();
        $member = $owner->partner->members()->create(['name' => 'Anggota Terlindungi', 'position' => 'Sekretaris']);
        $url = '/partner/members/'.$member->id;
        $this->delete($url)->assertRedirect('/login');
        foreach (['admin', 'donor'] as $role) {
            $this->actingAs(User::where('role', $role)->firstOrFail())->delete($url)->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => 'partner']))->delete($url)->assertForbidden();
        $this->assertDatabaseHas('members', ['id' => $member->id]);
    }

    public function test_deleting_last_member_on_second_page_leaves_accessible_first_page(): void
    {
        $owner = User::where('role', 'partner')->firstOrFail();
        for ($i = 1; $i <= 9; $i++) {
            $owner->partner->members()->create(['name' => 'Anggota '.$i, 'position' => 'Relawan']);
        }
        $last = Member::latest('id')->firstOrFail();
        $this->actingAs($owner)->from('/partner/members?q=Anggota&page=2')->delete('/partner/members/'.$last->id)->assertRedirect('/partner/members?q=Anggota&page=2');
        $this->get('/partner/members?q=Anggota&page=2')->assertOk()->assertViewHas('members', fn ($members) => $members->currentPage() === 1 && $members->count() === 8);
    }
}
