<?php

namespace Tests\Feature;

use App\Mail\TreeFundMail;
use App\Models\Plot;
use App\Models\User;
use App\Services\ActivityService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminPlotReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(DatabaseSeeder::class);
    }

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    private function plot(array $changes = []): Plot
    {
        return Plot::create(array_replace([
            'partner_id' => User::where('role', 'partner')->firstOrFail()->partner->id,
            'name' => 'Petak Pemeriksaan', 'location' => 'Desa Pemeriksaan',
            'latitude' => -8.128492, 'longitude' => 114.28391,
            'capacity' => 123, 'proposed_species' => 'Alpukat', 'status' => 'pending',
        ], $changes));
    }

    public function test_admin_queue_renders_original_details_coordinates_and_lazy_map(): void
    {
        $plot = $this->plot();
        $this->actingAs($this->admin())->get('/admin/plots?review='.$plot->id)->assertOk()
            ->assertSee('Verifikasi Lahan Baru')->assertSee('Petak Pemeriksaan')->assertSee($plot->partner->group_name)
            ->assertSee('Desa Pemeriksaan')->assertSee('123 pohon')->assertSee('Alpukat')
            ->assertSee('data-coordinate-map', false)->assertSee('data-lat="'.$plot->latitude.'"', false)
            ->assertSee('id="review-'.$plot->id.'"', false)
            ->assertSee('/admin/plots/'.$plot->id.'/review', false)
            ->assertDontSee('foundation-placeholder', false);
        $this->assertSame('pending', $plot->fresh()->status);
        Mail::assertNothingQueued();
    }

    public function test_search_by_plot_location_group_and_status_with_pagination(): void
    {
        for ($i = 1; $i <= 16; $i++) {
            $this->plot(['name' => 'Pencarian Lahan '.$i]);
        }
        $plot = $this->plot(['name' => 'Petak Unik', 'location' => 'Lokasi Unik', 'status' => 'revision']);
        foreach (['Petak Unik', 'Lokasi Unik', $plot->partner->group_name] as $query) {
            $this->actingAs($this->admin())->get('/admin/plots?status=revision&q='.urlencode($query))->assertOk()
                ->assertViewHas('records', fn ($records) => $records->pluck('id')->all() === [$plot->id]);
        }
        $this->get('/admin/plots?status=pending&q=Pencarian')->assertOk()
            ->assertViewHas('records', fn ($records) => $records->total() === 16 && $records->count() === 15 && str_contains($records->nextPageUrl(), 'status=pending'));
        $this->get('/admin/plots?status=pending&q=Pencarian&page=2')->assertOk()->assertViewHas('records', fn ($records) => $records->count() === 1);
        $this->get('/admin/plots?q=TidakAda')->assertOk()->assertSee('Tidak ada pengajuan pada filter ini');
        $this->get('/admin/plots?review=invalid')->assertSessionHasErrors('review');
    }

    public function test_approval_saves_reviewer_audit_and_notifies_only_the_plot_owner(): void
    {
        $plot = $this->plot();
        $before = $plot->refresh()->getAttributes();
        $admin = $this->admin();
        $this->actingAs($admin)->from('/admin/plots')->post('/admin/plots/'.$plot->id.'/review', [
            'decision' => 'approved', 'partner_id' => 99999, 'capacity' => 99999,
        ])->assertRedirect('/admin/plots')->assertSessionHas('success');
        $plot->refresh();
        $this->assertSame('approved', $plot->status);
        $this->assertEquals($admin->id, $plot->reviewed_by);
        foreach (['partner_id', 'capacity', 'reserved', 'confirmed', 'latitude', 'longitude'] as $field) {
            $this->assertEquals($before[$field], $plot->$field);
        }
        $this->assertDatabaseHas('audit_logs', ['action' => 'plots.approved', 'subject_type' => Plot::class, 'subject_id' => $plot->id, 'actor_id' => $admin->id]);
        $this->assertDatabaseHas('activity_notifications', ['user_id' => $plot->partner->user_id, 'url' => '/partner/plots']);
        Mail::assertQueuedCount(1);
        Mail::assertQueued(TreeFundMail::class, fn ($mail) => $mail->hasTo($plot->partner->user->email) && $mail->payload['decision'] === 'approved' && str_ends_with($mail->payload['url'], '/partner/plots'));
        $this->get('/admin/plots?review='.$plot->id)->assertOk()->assertSee('Keputusan sudah tercatat.')->assertDontSee('name="decision"', false);
        $this->actingAs($plot->partner->user)->get('/partner/plots')->assertOk()->assertSee('Disetujui');
    }

    public function test_revision_requires_note_and_partner_can_read_the_decision(): void
    {
        $plot = $this->plot();
        $this->actingAs($this->admin());
        foreach ([null, '', '   '] as $note) {
            $this->post('/admin/plots/'.$plot->id.'/review', ['decision' => 'revision', 'review_note' => $note])->assertSessionHasErrors('review_note');
        }
        $this->from('/admin/plots')->post('/admin/plots/'.$plot->id.'/review', [
            'decision' => 'revision', 'review_note' => 'Perbaiki koordinat batas petak.',
        ])->assertRedirect('/admin/plots')->assertSessionHasNoErrors();
        $this->assertSame('revision', $plot->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'plots.revision', 'subject_id' => $plot->id]);
        $mail = Mail::queued(TreeFundMail::class)->first();
        $this->assertStringContainsString('Perbaiki koordinat batas petak.', $mail->render());
        $this->actingAs($plot->partner->user)->get('/partner/plots')->assertOk()->assertSee('Perbaiki koordinat batas petak.')->assertSee('Perbaiki Pengajuan');
    }

    public function test_only_pending_plots_of_approved_partners_can_be_reviewed_once(): void
    {
        $plot = $this->plot();
        $this->actingAs($this->admin());
        foreach (['approved', 'revision', 'rejected'] as $status) {
            $plot->update(['status' => $status]);
            $this->post('/admin/plots/'.$plot->id.'/review', ['decision' => 'approved'])->assertStatus(409);
            $this->assertSame($status, $plot->fresh()->status);
        }
        $plot->update(['status' => 'pending']);
        $plot->partner->update(['status' => 'revision']);
        $this->post('/admin/plots/'.$plot->id.'/review', ['decision' => 'approved'])->assertStatus(409);
        $this->assertSame('pending', $plot->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
        Mail::assertNothingQueued();
    }

    public function test_invalid_decisions_missing_plots_and_unauthorized_access_are_rejected(): void
    {
        $plot = $this->plot();
        $url = '/admin/plots/'.$plot->id.'/review';
        $this->get('/admin/plots')->assertRedirect('/login');
        $this->post($url, ['decision' => 'approved'])->assertRedirect('/login');
        foreach (['partner', 'donor'] as $role) {
            $this->actingAs(User::where('role', $role)->firstOrFail())->get('/admin/plots')->assertRedirect('/dashboard');
            $this->post($url, ['decision' => 'approved'])->assertForbidden();
        }
        $this->actingAs(User::factory()->unverified()->create(['role' => 'admin']))->post($url, ['decision' => 'approved'])->assertRedirect('/email/verify');
        $this->actingAs($this->admin())->post($url, ['decision' => 'rejected'])->assertSessionHasErrors('decision');
        $this->post($url, ['decision' => 'revision', 'review_note' => str_repeat('x', 2001)])->assertSessionHasErrors('review_note');
        $this->post('/admin/plots/999999/review', ['decision' => 'approved'])->assertNotFound();
        Mail::assertNothingQueued();
    }

    public function test_failure_during_notification_rolls_back_decision_and_audit(): void
    {
        $plot = $this->plot();
        $this->partialMock(ActivityService::class, function ($mock): void {
            $mock->shouldReceive('notify')->once()->andThrow(new \RuntimeException('Test failure'));
        });
        $this->actingAs($this->admin())->post('/admin/plots/'.$plot->id.'/review', ['decision' => 'approved'])->assertStatus(500);
        $this->assertSame('pending', $plot->fresh()->status);
        $this->assertNull($plot->fresh()->reviewed_by);
        $this->assertDatabaseCount('audit_logs', 0);
        Mail::assertNothingQueued();
    }

    public function test_untrusted_plot_names_and_review_notes_are_escaped(): void
    {
        $plot = $this->plot(['name' => '<script>alert(1)</script>', 'status' => 'revision', 'review_note' => '<script>alert(2)</script>']);
        $this->actingAs($this->admin())->get('/admin/plots?review='.$plot->id)->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertSee('&lt;script&gt;alert(2)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(', false);
    }
}
