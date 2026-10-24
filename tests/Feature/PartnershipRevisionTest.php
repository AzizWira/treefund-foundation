<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\User;
use App\Services\ActivityService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class PartnershipRevisionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
    }

    private function revisionPartner(): Partner
    {
        $partner = User::where('role', 'partner')->firstOrFail()->partner;
        $partner->update([
            'status' => 'revision', 'review_note' => 'Perbaiki alamat kelompok.',
            'reviewed_by' => User::where('role', 'admin')->firstOrFail()->id,
            'document' => 'legal/dokumen-sebelumnya.pdf',
        ]);
        Storage::disk('local')->put($partner->document, '%PDF-1.4 example');

        return $partner;
    }

    private function formData(): array
    {
        return [
            'group_name' => 'Kelompok Perbaikan', 'address' => 'Alamat kelompok yang diperbaiki',
            'bank_name' => 'Bank Contoh', 'bank_account' => '00123456789',
            'account_holder' => 'Kelompok Perbaikan',
        ];
    }

    public function test_revision_returns_to_pending_clears_previous_reviewer_and_keeps_existing_document(): void
    {
        $partner = $this->revisionPartner();
        $this->actingAs($partner->user)->from('/partner/partnership')
            ->post('/partner/partnership', $this->formData())->assertRedirect('/partner/partnership');
        $partner->refresh();
        $this->assertSame('pending', $partner->status);
        $this->assertNull($partner->review_note);
        $this->assertNull($partner->reviewed_by);
        $this->assertSame('legal/dokumen-sebelumnya.pdf', $partner->document);
        Storage::disk('local')->assertExists($partner->document);
        $this->actingAs($partner->user->fresh())->get('/partner/partnership')->assertOk()
            ->assertSee('Pengajuan Anda sedang menunggu pemeriksaan Admin.')
            ->assertSee('Perbarui Pengajuan')->assertDontSee('Catatan Admin:');
    }

    public function test_replacement_legal_document_is_saved_privately_for_the_same_group(): void
    {
        $partner = $this->revisionPartner();
        $oldDocument = $partner->document;
        $data = $this->formData() + ['document' => UploadedFile::fake()->create('perbaikan.pdf', 10, 'application/pdf')];
        $this->actingAs($partner->user)->post('/partner/partnership', $data)->assertSessionHasNoErrors();
        $partner->refresh();
        $this->assertNotSame($oldDocument, $partner->document);
        $this->assertSame('pending', $partner->status);
        $this->assertNull($partner->reviewed_by);
        Storage::disk('local')->assertExists($partner->document);
        Storage::disk('local')->assertExists($oldDocument);
        $this->get('/files/legal/'.$partner->id)->assertOk();
    }

    public function test_failed_activity_record_does_not_save_partial_revision_or_leave_new_upload(): void
    {
        $partner = $this->revisionPartner();
        $before = $partner->fresh()->getAttributes();
        $this->partialMock(ActivityService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('audit')->once()->andThrow(new RuntimeException('Audit failure for testing'));
        });
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($partner->user)->post('/partner/partnership', $this->formData() + [
                'document' => UploadedFile::fake()->create('pengganti.pdf', 10, 'application/pdf'),
            ]);
            $this->fail('Expected audit failure');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit failure for testing', $exception->getMessage());
        }
        $this->assertSame($before, $partner->fresh()->getAttributes());
        $this->assertSame([$before['document']], Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('activity_notifications', 0);
        Mail::assertNothingQueued();
    }

    public function test_each_status_has_clear_guidance_and_the_approved_form_remains_locked(): void
    {
        $partner = $this->revisionPartner();
        $this->actingAs($partner->user)->get('/partner/partnership')->assertOk()
            ->assertSee('Kirim Perbaikan')->assertSee('Perbaiki data sesuai catatan Admin, lalu kirim ulang pengajuan.')
            ->assertSee('Perbaiki alamat kelompok.');
        $partner->update(['status' => 'approved']);
        $this->actingAs($partner->user->fresh())->get('/partner/partnership')->assertOk()
            ->assertSee('Kemitraan Disetujui')->assertSee('disabled', false);
        $this->post('/partner/partnership', $this->formData())->assertStatus(409);
    }
}
