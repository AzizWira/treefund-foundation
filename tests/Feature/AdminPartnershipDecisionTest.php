<?php

namespace Tests\Feature;

use App\Mail\TreeFundMail;
use App\Models\Partner;
use App\Models\User;
use App\Services\ActivityService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class AdminPartnershipDecisionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(DatabaseSeeder::class);
    }

    private function pendingPartner(): Partner
    {
        $user = User::factory()->create(['role' => 'partner']);

        return Partner::create([
            'user_id' => $user->id, 'group_name' => 'Kelompok Pengajuan Baru', 'address' => 'Malang',
            'bank_name' => 'Bank Contoh', 'bank_account' => '00123456789',
            'account_holder' => 'Kelompok Pengajuan Baru', 'document' => 'legal/kelompok.pdf', 'status' => 'pending',
        ]);
    }

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    public function test_admin_approves_pending_partner_and_notifies_the_correct_owner(): void
    {
        $partner = $this->pendingPartner();
        $admin = $this->admin();
        $this->actingAs($admin)->from('/admin/partners')->post('/admin/partners/'.$partner->id.'/review', [
            'decision' => 'approved', 'user_id' => $admin->id, 'bank_account' => '99999999',
        ])->assertRedirect('/admin/partners')->assertSessionHas('success');

        $partner->refresh();
        $this->assertSame('approved', $partner->status);
        $this->assertSame($admin->id, $partner->reviewed_by);
        $this->assertNull($partner->review_note);
        $this->assertNotSame($admin->id, $partner->user_id);
        $this->assertSame('00123456789', $partner->bank_account);
        $this->assertSame('legal/kelompok.pdf', $partner->document);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $admin->id, 'action' => 'partners.approved', 'subject_id' => $partner->id]);
        $this->assertDatabaseHas('activity_notifications', [
            'user_id' => $partner->user_id, 'title' => 'Hasil pemeriksaan pengajuan', 'url' => '/partner/partnership',
        ]);
        $this->assertDatabaseCount('activity_notifications', 1);
        Mail::assertQueuedCount(1);
        Mail::assertQueued(TreeFundMail::class, fn ($mail) => $mail->kind === 'partnership'
            && $mail->hasTo($partner->user->email) && $mail->payload['decision'] === 'approved');
        $this->assertStringContainsString('Hasil pemeriksaan pengajuan', Mail::queued(TreeFundMail::class)->first()->render());
        $this->get('/admin/partners?review='.$partner->id)->assertOk()
            ->assertSee('Keputusan sudah tercatat.')->assertDontSee('name="decision"', false);
    }

    public function test_revision_requires_a_nonempty_note_then_saves_decision_and_reviewer(): void
    {
        $partner = $this->pendingPartner();
        $this->actingAs($this->admin());
        foreach ([null, '', '   '] as $note) {
            $this->post('/admin/partners/'.$partner->id.'/review', [
                'decision' => 'revision', 'review_note' => $note,
            ])->assertSessionHasErrors('review_note');
        }
        $this->assertSame('pending', $partner->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
        Mail::assertNothingQueued();

        $this->from('/admin/partners')->post('/admin/partners/'.$partner->id.'/review', [
            'decision' => 'revision', 'review_note' => 'Lengkapi halaman kedua dokumen.',
        ])->assertRedirect('/admin/partners')->assertSessionHasNoErrors();
        $partner->refresh();
        $this->assertSame('revision', $partner->status);
        $this->assertSame('Lengkapi halaman kedua dokumen.', $partner->review_note);
        $this->assertSame($this->admin()->id, $partner->reviewed_by);
        $this->assertDatabaseHas('audit_logs', ['action' => 'partners.revision', 'subject_id' => $partner->id]);
        $mail = Mail::queued(TreeFundMail::class)->first();
        $this->assertSame('revision', $mail->payload['decision']);
        $this->assertStringContainsString('Perbaikan diperlukan', $mail->render());
        $this->actingAs($partner->user->fresh())->get('/partner/partnership')->assertOk()
            ->assertSee('Lengkapi halaman kedua dokumen.')->assertSee('Perlu perbaikan');
    }

    public function test_decision_cannot_be_applied_twice_or_replace_an_existing_review(): void
    {
        foreach (['approved', 'revision'] as $status) {
            $partner = $this->pendingPartner();
            $partner->update(['status' => $status, 'review_note' => 'Catatan sebelumnya.']);
            $before = $partner->fresh()->getAttributes();
            $this->actingAs($this->admin())->post('/admin/partners/'.$partner->id.'/review', [
                'decision' => 'approved', 'review_note' => 'Catatan baru.',
            ])->assertStatus(409);
            $this->assertSame($before, $partner->fresh()->getAttributes());
        }
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('activity_notifications', 0);
        Mail::assertNothingQueued();
    }

    public function test_invalid_decisions_long_notes_and_missing_records_are_rejected(): void
    {
        $partner = $this->pendingPartner();
        $this->actingAs($this->admin());
        foreach (['pending', 'rejected', ''] as $decision) {
            $this->post('/admin/partners/'.$partner->id.'/review', ['decision' => $decision])->assertSessionHasErrors('decision');
        }
        $this->post('/admin/partners/'.$partner->id.'/review', [
            'decision' => 'revision', 'review_note' => str_repeat('a', 2001),
        ])->assertSessionHasErrors('review_note');
        $this->post('/admin/partners/999999/review', ['decision' => 'approved'])->assertNotFound();
        $this->assertSame('pending', $partner->fresh()->status);
        Mail::assertNothingQueued();
    }

    public function test_guests_other_roles_and_unverified_admin_cannot_make_decisions(): void
    {
        $partner = $this->pendingPartner();
        $path = '/admin/partners/'.$partner->id.'/review';
        $this->post($path, ['decision' => 'approved'])->assertRedirect('/login');
        foreach (['partner', 'donor'] as $role) {
            $this->actingAs(User::where('role', $role)->firstOrFail())->post($path, ['decision' => 'approved'])->assertForbidden();
        }
        $this->actingAs(User::factory()->unverified()->create(['role' => 'admin']))
            ->post($path, ['decision' => 'approved'])->assertRedirect('/email/verify');
        $this->assertSame('pending', $partner->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
        Mail::assertNothingQueued();
    }

    public function test_a_failed_notification_rolls_back_the_decision_and_audit(): void
    {
        $partner = $this->pendingPartner();
        $this->partialMock(ActivityService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('notify')->once()->andThrow(new RuntimeException('Notification failure for testing'));
        });
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($this->admin())->post('/admin/partners/'.$partner->id.'/review', ['decision' => 'approved']);
            $this->fail('Expected the notification exception');
        } catch (RuntimeException $exception) {
            $this->assertSame('Notification failure for testing', $exception->getMessage());
        }
        $partner->refresh();
        $this->assertSame('pending', $partner->status);
        $this->assertNull($partner->reviewed_by);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('activity_notifications', 0);
    }
}
