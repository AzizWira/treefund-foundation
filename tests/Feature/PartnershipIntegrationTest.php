<?php

namespace Tests\Feature;

use App\Mail\TreeFundMail;
use App\Models\Partner;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PartnershipIntegrationTest extends TestCase
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

    private function profile(string $group = 'Kelompok Integrasi'): array
    {
        return [
            'group_name' => $group, 'address' => 'Malang, Jawa Timur',
            'bank_name' => 'Bank Contoh', 'bank_account' => '00123456789', 'account_holder' => $group,
        ];
    }

    private function loginAs(User $user, string $password = 'TreeFund!2026'): void
    {
        $this->post('/logout')->assertRedirect('/');
        $this->post('/login', ['email' => $user->email, 'password' => $password])->assertRedirect('/dashboard');
    }

    public function test_registration_to_revision_resubmission_and_approval_is_consistent_across_roles(): void
    {
        $password = 'Integration!2026';
        $this->post('/register', [
            'name' => 'Mitra Integrasi', 'email' => 'integrasi@treefund.test', 'role' => 'partner',
            'password' => $password, 'password_confirmation' => $password,
        ])->assertRedirect('/email/verify');
        $verificationMail = Mail::queued(TreeFundMail::class)->first();
        $this->post('/email/verify-code', ['code' => $verificationMail->payload['code']])->assertRedirect('/dashboard');
        $owner = User::where('email', 'integrasi@treefund.test')->firstOrFail();
        $this->assertTrue($owner->hasVerifiedEmail());
        app('auth')->forgetGuards();
        Mail::fake();

        $this->from('/partner/partnership')->post('/partner/partnership', $this->profile() + [
            'document' => UploadedFile::fake()->create('legalitas.pdf', 20, 'application/pdf'),
        ])->assertRedirect('/partner/partnership')->assertSessionHasNoErrors();
        $partner = $owner->fresh()->partner;
        $document = $partner->document;
        $this->assertSame('pending', $partner->status);
        Storage::disk('local')->assertExists($document);
        Storage::disk('public')->assertMissing($document);

        $admin = User::where('role', 'admin')->firstOrFail();
        $this->loginAs($admin);
        $this->get('/admin/partners?q=Integrasi&status=pending')->assertOk()
            ->assertSee('Kelompok Integrasi')->assertSee('data-review-target="review-'.$partner->id.'"', false);
        $this->get('/files/legal/'.$partner->id.'?preview=1')->assertOk();
        $this->from('/admin/partners')->post('/admin/partners/'.$partner->id.'/review', [
            'decision' => 'revision', 'review_note' => 'Lengkapi alamat sekretariat.',
        ])->assertRedirect('/admin/partners');

        $this->loginAs($owner, $password);
        $this->get('/partner/partnership')->assertOk()->assertSee('Lengkapi alamat sekretariat.')->assertSee('Kirim Perbaikan');
        $this->post('/partner/partnership', array_replace($this->profile(), ['address' => 'Alamat sekretariat lengkap']))
            ->assertSessionHasNoErrors();
        $partner->refresh();
        $this->assertSame('pending', $partner->status);
        $this->assertNull($partner->review_note);
        $this->assertNull($partner->reviewed_by);
        $this->assertSame($document, $partner->document);
        $this->get('/partner/partnership')->assertOk()->assertSee('Perbarui Pengajuan')->assertDontSee('Catatan Admin:');

        $this->loginAs($admin);
        $this->get('/admin/partners?status=pending&review='.$partner->id)->assertOk()->assertSee('Alamat sekretariat lengkap');
        $this->post('/admin/partners/'.$partner->id.'/review', ['decision' => 'approved'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $partner->fresh()->status);
        $this->assertSame($admin->id, $partner->fresh()->reviewed_by);
        $this->loginAs($owner, $password);
        $this->get('/partner/partnership')->assertOk()->assertSee('Kemitraan Disetujui')->assertSee('disabled', false);
        $this->post('/partner/partnership', $this->profile())->assertStatus(409);

        $this->assertSame([
            'partnership.submitted', 'partners.revision', 'partnership.submitted', 'partners.approved',
        ], DB::table('audit_logs')->where('subject_id', $partner->id)->orderBy('id')->pluck('action')->all());
        $this->assertDatabaseCount('activity_notifications', 4);
        $this->assertSame(2, DB::table('activity_notifications')->where('user_id', $owner->id)->count());
        $this->assertSame(2, DB::table('activity_notifications')->where('user_id', $admin->id)->count());
        Mail::assertQueuedCount(4);
    }

    public function test_missing_existing_document_requires_reupload_instead_of_accepting_a_broken_reference(): void
    {
        $partner = User::where('role', 'partner')->firstOrFail()->partner;
        $partner->update(['status' => 'revision', 'review_note' => 'Lengkapi dokumen legalitas.']);
        $this->actingAs($partner->user)->get('/partner/partnership')->assertOk()
            ->assertSee('Dokumen legalitas belum tersedia. Unggah kembali dokumen sebelum mengirim pengajuan.')
            ->assertDontSee('Lihat dokumen tersimpan');
        $this->post('/partner/partnership', $this->profile())->assertSessionHasErrors('document');
        $this->assertSame('revision', $partner->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->post('/partner/partnership', $this->profile() + [
            'document' => UploadedFile::fake()->create('unggah-ulang.pdf', 20, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $partner->refresh();
        $this->assertSame('pending', $partner->status);
        Storage::disk('local')->assertExists($partner->document);
        $this->get('/files/legal/'.$partner->id)->assertOk();
    }

    public function test_private_documents_stay_isolated_between_groups_even_after_approval(): void
    {
        $owners = collect([1, 2])->map(fn () => User::factory()->create(['role' => 'partner']));
        $partners = [];
        foreach ($owners as $index => $owner) {
            $this->actingAs($owner)->post('/partner/partnership', $this->profile('Kelompok Privat '.$index) + [
                'document' => UploadedFile::fake()->create('legal-'.$index.'.pdf', 20, 'application/pdf'),
            ])->assertSessionHasNoErrors();
            $partners[] = $owner->fresh()->partner;
        }
        $admin = User::where('role', 'admin')->firstOrFail();
        foreach ($partners as $index => $partner) {
            $path = '/files/legal/'.$partner->id;
            $this->actingAs($owners[$index])->get($path)->assertOk();
            $this->actingAs($owners[1 - $index])->get($path)->assertForbidden();
            $this->actingAs($admin)->post('/admin/partners/'.$partner->id.'/review', ['decision' => 'approved'])->assertSessionHasNoErrors();
            $response = $this->get($path)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            $this->actingAs(User::where('role', 'donor')->firstOrFail())->get($path)->assertForbidden();
            $this->actingAs($owners[1 - $index])->get($path)->assertForbidden();
            $this->get('/storage/'.$partner->document)->assertForbidden();
            Storage::disk('public')->assertMissing($partner->document);
        }
    }

    public function test_review_notes_are_escaped_in_partner_admin_and_email_views(): void
    {
        $partner = User::where('role', 'partner')->firstOrFail()->partner;
        $partner->update(['status' => 'pending']);
        $note = '<script>alert("example")</script>';
        $this->actingAs(User::where('role', 'admin')->firstOrFail())->post('/admin/partners/'.$partner->id.'/review', [
            'decision' => 'revision', 'review_note' => $note,
        ])->assertSessionHasNoErrors();
        $this->get('/admin/partners?review='.$partner->id)->assertOk()->assertSee($note)->assertDontSee($note, false);
        $this->actingAs($partner->user)->get('/partner/partnership')->assertOk()->assertSee($note)->assertDontSee($note, false);
        $html = Mail::queued(TreeFundMail::class)->first()->render();
        $this->assertStringContainsString(e($note), $html);
        $this->assertStringNotContainsString($note, $html);
    }
}
