<?php

namespace Tests\Feature;

use App\Mail\TreeFundMail;
use App\Models\Partner;
use App\Models\Plot;
use App\Models\User;
use App\Services\ActivityService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PlotSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(DatabaseSeeder::class);
    }

    private function partnerUser(): User
    {
        return User::where('role', 'partner')->firstOrFail();
    }

    private function submission(array $changes = []): array
    {
        return array_replace([
            'name' => 'Petak Harapan Baru', 'location' => 'Desa Harapan, Malang',
            'latitude' => '-8.1284920', 'longitude' => '114.2839100',
            'capacity' => 120, 'proposed_species' => 'Alpukat dan Kopi',
        ], $changes);
    }

    public function test_approved_partner_sees_original_form_list_filters_and_gps_controls(): void
    {
        $this->actingAs($this->partnerUser())->get('/partner/plots')->assertOk()
            ->assertSee('Pengajuan Petak Lahan & Kuota', false)
            ->assertSee('sidebar', false)->assertSee('topbar', false)->assertSee('app-footer', false)
            ->assertSee('name="latitude"', false)->assertSee('name="longitude"', false)
            ->assertSee('name="capacity"', false)->assertSee('data-gps', false)
            ->assertSee('plot-preview-map', false)->assertSee('Daftar Pengajuan Lahan')
            ->assertDontSee('foundation-placeholder', false);
    }

    public function test_submission_records_pending_plot_audit_and_admin_notification_without_trusting_extra_fields(): void
    {
        $user = $this->partnerUser();
        $this->actingAs($user)->from('/partner/plots')->post('/partner/plots', $this->submission([
            'partner_id' => 99999, 'status' => 'approved', 'reserved' => 99,
            'confirmed' => 88, 'review_note' => 'Approved', 'reviewed_by' => $user->id,
        ]))->assertRedirect('/partner/plots')->assertSessionHasNoErrors()->assertSessionHas('success');
        $plot = Plot::where('name', 'Petak Harapan Baru')->firstOrFail();
        $this->assertEquals($user->partner->id, $plot->partner_id);
        $this->assertSame('pending', $plot->status);
        $this->assertEquals(120, $plot->available);
        $this->assertEquals(0, $plot->reserved);
        $this->assertEquals(0, $plot->confirmed);
        $this->assertNull($plot->review_note);
        $this->assertNull($plot->reviewed_by);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $user->id, 'action' => 'plot.submitted', 'subject_type' => Plot::class, 'subject_id' => $plot->id]);
        $admin = User::where('role', 'admin')->firstOrFail();
        $this->assertDatabaseHas('activity_notifications', ['user_id' => $admin->id, 'title' => 'Pengajuan lahan baru', 'url' => '/admin/plots']);
        Mail::assertQueued(TreeFundMail::class, fn ($mail) => $mail->kind === 'admin_request' && $mail->hasTo($admin->email));
        $this->actingAs($user)->get('/partner/plots')->assertOk()->assertSee($plot->name)->assertSee('Menunggu verifikasi');
    }

    public function test_unapproved_or_missing_partnership_cannot_submit_and_gets_guidance(): void
    {
        $user = $this->partnerUser();
        foreach (['pending', 'revision', 'rejected'] as $status) {
            $user->partner()->update(['status' => $status]);
            $this->actingAs($user->fresh())->get('/partner/plots')->assertOk()
                ->assertSee('Kemitraan perlu disetujui')->assertDontSee('data-gps', false);
            $this->post('/partner/plots', $this->submission())->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => 'partner']))->get('/partner/plots')->assertOk()->assertSee('Belum ada pengajuan lahan');
        $this->post('/partner/plots', $this->submission())->assertForbidden();
        $this->assertDatabaseMissing('plots', ['name' => 'Petak Harapan Baru']);
        Mail::assertNothingQueued();
    }

    public function test_required_fields_coordinate_ranges_and_capacity_are_validated(): void
    {
        $this->actingAs($this->partnerUser())->post('/partner/plots', [])->assertSessionHasErrors([
            'name', 'location', 'latitude', 'longitude', 'capacity', 'proposed_species',
        ]);
        foreach ([['latitude' => -91], ['latitude' => 91], ['longitude' => -181], ['longitude' => 181], ['latitude' => 'abc'], ['capacity' => 0], ['capacity' => 100001], ['capacity' => 1.5], ['name' => str_repeat('x', 151)], ['plot_id' => 'abc']] as $invalid) {
            $this->post('/partner/plots', $this->submission($invalid))->assertSessionHasErrors(array_keys($invalid));
        }
        $this->assertDatabaseMissing('plots', ['name' => 'Petak Harapan Baru']);
        Mail::assertNothingQueued();
    }

    public function test_list_search_status_pagination_and_group_isolation(): void
    {
        $user = $this->partnerUser();
        $otherUser = User::factory()->create(['role' => 'partner']);
        $other = Partner::create(array_merge($user->partner->getAttributes(), ['id' => null, 'user_id' => $otherUser->id, 'group_name' => 'Kelompok Lain']));
        Plot::create(array_merge($this->submission(), ['partner_id' => $other->id, 'name' => 'Rahasia Kelompok Lain', 'status' => 'pending']));
        for ($i = 1; $i <= 10; $i++) {
            Plot::create(array_merge($this->submission(), ['partner_id' => $user->partner->id, 'name' => 'Petak Tambahan '.$i, 'status' => 'pending']));
        }
        $response = $this->actingAs($user)->get('/partner/plots?status=pending');
        $response->assertOk()->assertDontSee('Rahasia Kelompok Lain')->assertViewHas('plots', fn ($plots) => $plots->total() === 10 && $plots->count() === 8);
        $this->get('/partner/plots?status=pending&page=2')->assertOk()->assertViewHas('plots', fn ($plots) => $plots->count() === 2);
        $this->get('/partner/plots?q=Petak%20Tambahan%201&status=pending')->assertOk()->assertViewHas('plots', fn ($plots) => $plots->total() === 2);
        $this->get('/partner/plots?q=TidakAda')->assertOk()->assertSee('Belum ada pengajuan lahan');
    }

    public function test_only_own_revision_can_be_resubmitted_and_preserves_quota_counters(): void
    {
        $user = $this->partnerUser();
        $plot = $user->partner->plots()->firstOrFail();
        $admin = User::where('role', 'admin')->firstOrFail();
        foreach (['pending', 'approved', 'rejected'] as $status) {
            $plot->update(['status' => $status]);
            $this->actingAs($user)->post('/partner/plots', $this->submission(['plot_id' => $plot->id]))->assertStatus(409);
        }
        $plot->update(['status' => 'revision', 'review_note' => 'Perbaiki lokasi', 'reviewed_by' => $admin->id]);
        $reserved = $plot->reserved;
        $confirmed = $plot->confirmed;
        $this->actingAs(User::factory()->create(['role' => 'partner']))->post('/partner/plots', $this->submission(['plot_id' => $plot->id]))->assertForbidden();
        $this->actingAs($user)->from('/partner/plots')->post('/partner/plots', $this->submission(['plot_id' => $plot->id]))->assertRedirect('/partner/plots')->assertSessionHasNoErrors();
        $plot->refresh();
        $this->assertSame('pending', $plot->status);
        $this->assertNull($plot->review_note);
        $this->assertNull($plot->reviewed_by);
        $this->assertSame($reserved, $plot->reserved);
        $this->assertSame($confirmed, $plot->confirmed);
    }

    public function test_guest_wrong_roles_and_unverified_users_cannot_submit(): void
    {
        $this->post('/partner/plots', $this->submission())->assertRedirect('/login');
        foreach (['admin', 'donor'] as $role) {
            $this->actingAs(User::where('role', $role)->firstOrFail())->post('/partner/plots', $this->submission())->assertForbidden();
        }
        $this->actingAs(User::factory()->unverified()->create(['role' => 'partner']))->post('/partner/plots', $this->submission())->assertRedirect('/email/verify');
        Mail::assertNothingQueued();
    }

    public function test_failed_activity_rolls_back_the_new_plot(): void
    {
        $this->mock(ActivityService::class, function ($mock): void {
            $mock->shouldReceive('audit')->once()->andThrow(new \RuntimeException('Activity failed'));
        });
        $this->actingAs($this->partnerUser())->post('/partner/plots', $this->submission())->assertStatus(500);
        $this->assertDatabaseMissing('plots', ['name' => 'Petak Harapan Baru']);
        Mail::assertNothingQueued();
    }
}
