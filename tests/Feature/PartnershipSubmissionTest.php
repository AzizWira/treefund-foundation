<?php

namespace Tests\Feature;

use App\Mail\TreeFundMail;
use App\Models\ActivityNotification;
use App\Models\Partner;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PartnershipSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);
    }

    private function newPartnerUser(): User
    {
        return User::factory()->create(['role' => 'partner']);
    }

    private function submission(array $changes = []): array
    {
        return array_replace([
            'group_name' => 'Kelompok Tani Harapan',
            'address' => 'Desa Harapan, Jawa Timur',
            'bank_name' => 'Bank Contoh',
            'bank_account' => '00123456789',
            'account_holder' => 'Kelompok Tani Harapan',
            'document' => UploadedFile::fake()->create('legalitas.pdf', 50, 'application/pdf'),
        ], $changes);
    }

    public function test_partner_form_uses_the_original_layout_and_all_submission_fields(): void
    {
        $this->actingAs($this->newPartnerUser())->get('/partner/partnership')->assertOk()
            ->assertSee('Profil Kelompok Tani & Legalitas', false)
            ->assertSee('sidebar', false)->assertSee('topbar', false)->assertSee('app-footer', false)
            ->assertSee('name="group_name"', false)->assertSee('name="address"', false)
            ->assertSee('name="bank_account"', false)->assertSee('name="document"', false)
            ->assertDontSee('foundation-placeholder', false);
    }

    public function test_valid_submission_saves_pending_profile_private_document_audit_and_admin_notification(): void
    {
        $user = $this->newPartnerUser();
        $this->actingAs($user)->from('/partner/partnership')->post('/partner/partnership', $this->submission())
            ->assertRedirect('/partner/partnership')->assertSessionHas('success');

        $partner = $user->fresh()->partner;
        $this->assertNotNull($partner);
        $this->assertSame('pending', $partner->status);
        $this->assertSame('00123456789', $partner->bank_account);
        Storage::disk('local')->assertExists($partner->document);
        Storage::disk('public')->assertMissing($partner->document);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id, 'action' => 'partnership.submitted',
            'subject_type' => Partner::class, 'subject_id' => $partner->id,
        ]);
        $admin = User::where('role', 'admin')->firstOrFail();
        $this->assertDatabaseHas('activity_notifications', [
            'user_id' => $admin->id, 'title' => 'Pengajuan mitra baru', 'url' => '/admin/partners',
        ]);
        Mail::assertQueued(TreeFundMail::class, fn ($mail) => $mail->kind === 'admin_request' && $mail->hasTo($admin->email));
        $mail = Mail::queued(TreeFundMail::class)->first();
        $this->assertStringContainsString('Pengajuan mitra baru', $mail->render());
        $this->actingAs($user->fresh())->get('/partner/partnership')->assertOk()
            ->assertSee('Kelompok Tani Harapan')->assertSee('Menunggu verifikasi');
    }

    public function test_required_fields_invalid_bank_account_and_unsupported_or_large_documents_are_rejected(): void
    {
        $user = $this->newPartnerUser();
        $this->actingAs($user)->post('/partner/partnership', [])->assertSessionHasErrors([
            'group_name', 'address', 'bank_name', 'bank_account', 'account_holder', 'document',
        ]);
        $this->post('/partner/partnership', $this->submission(['bank_account' => 'abc-123']))->assertSessionHasErrors('bank_account');
        $this->post('/partner/partnership', $this->submission([
            'document' => UploadedFile::fake()->create('program.exe', 10, 'application/octet-stream'),
        ]))->assertSessionHasErrors('document');
        $this->post('/partner/partnership', $this->submission([
            'document' => UploadedFile::fake()->create('besar.pdf', 10241, 'application/pdf'),
        ]))->assertSessionHasErrors('document');
        $this->assertDatabaseMissing('partners', ['user_id' => $user->id]);
        $this->assertSame([], Storage::disk('local')->allFiles());
        Mail::assertNothingQueued();
    }

    public function test_approved_partner_cannot_replace_profile_or_document(): void
    {
        $user = User::where('role', 'partner')->firstOrFail();
        $original = $user->partner->getAttributes();
        $this->actingAs($user)->get('/partner/partnership')->assertOk()->assertSee('disabled', false)
            ->assertSee('Kemitraan telah disetujui.');
        $this->post('/partner/partnership', $this->submission())->assertStatus(409);
        $this->assertSame($original, $user->partner->fresh()->getAttributes());
        $this->assertSame([], Storage::disk('local')->allFiles());
        Mail::assertNothingQueued();
    }

    public function test_revision_keeps_existing_document_when_no_replacement_is_uploaded_and_cannot_change_owner(): void
    {
        $user = User::where('role', 'partner')->firstOrFail();
        $partner = $user->partner;
        $partner->update(['status' => 'revision', 'review_note' => 'Perbaiki alamat kelompok.']);
        $other = $this->newPartnerUser();
        $this->actingAs($user)->get('/partner/partnership')->assertOk()->assertSee('Perbaiki alamat kelompok.');
        $this->from('/partner/partnership')->post('/partner/partnership', $this->submission([
            'document' => null, 'user_id' => $other->id, 'status' => 'approved',
        ]))->assertRedirect('/partner/partnership')->assertSessionHasNoErrors();
        $partner->refresh();
        $this->assertSame($user->id, $partner->user_id);
        $this->assertSame('pending', $partner->status);
        $this->assertNull($partner->review_note);
        $this->assertSame('contoh-belum-diunggah.pdf', $partner->document);
        $this->assertDatabaseMissing('partners', ['user_id' => $other->id]);
    }

    public function test_legal_documents_only_allow_owner_and_admin_with_preview_and_download(): void
    {
        $user = User::where('role', 'partner')->firstOrFail();
        $partner = $user->partner;
        Storage::disk('local')->put($partner->document, '%PDF-1.4 legal document');
        $path = '/files/legal/'.$partner->id;
        $this->actingAs($user)->get($path)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get($path.'?preview=1')->assertOk()->assertSee('Dokumen Legalitas Kelompok Tani')->assertSee('Unduh Berkas');
        $this->get($path.'?download=1')->assertDownload();
        $this->actingAs(User::where('role', 'admin')->firstOrFail())->get($path)->assertOk();
        $this->actingAs($this->newPartnerUser())->get($path)->assertForbidden();
        $this->actingAs(User::where('role', 'donor')->firstOrFail())->get($path)->assertForbidden();
        $this->get('/files/planting/'.$partner->id)->assertNotFound();
    }

    public function test_missing_documents_and_unauthorized_submissions_are_handled(): void
    {
        $user = User::where('role', 'partner')->firstOrFail();
        $this->get('/partner/partnership')->assertRedirect('/login');
        $this->get('/files/legal/'.$user->partner->id)->assertRedirect('/login');
        $this->actingAs($user)->get('/files/legal/'.$user->partner->id)->assertNotFound();
        $this->actingAs(User::where('role', 'donor')->firstOrFail())->get('/partner/partnership')->assertRedirect('/dashboard');
        $this->post('/partner/partnership', $this->submission())->assertForbidden();
        $this->actingAs(User::where('role', 'admin')->firstOrFail())->post('/partner/partnership', $this->submission())->assertForbidden();
        $this->actingAs(User::factory()->unverified()->create(['role' => 'partner']))
            ->post('/partner/partnership', $this->submission())->assertRedirect('/email/verify');
        $this->assertSame(1, Partner::count());
        $this->assertSame(0, ActivityNotification::count());
    }
}
