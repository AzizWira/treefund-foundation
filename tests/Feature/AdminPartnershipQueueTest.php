<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminPartnershipQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(DatabaseSeeder::class);
    }

    private function partner(string $name, string $status = 'pending', string $address = 'Desa Contoh'): Partner
    {
        $user = User::factory()->create(['role' => 'partner']);

        return Partner::create([
            'user_id' => $user->id, 'group_name' => $name, 'status' => $status,
            'address' => $address, 'bank_name' => 'Bank Contoh', 'bank_account' => '00123456789',
            'account_holder' => $name, 'document' => 'legal/contoh.pdf',
            'review_note' => $status === 'revision' ? 'Lengkapi dokumen.' : null,
        ]);
    }

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    public function test_admin_can_read_queue_and_original_detail_dialog_without_changing_records(): void
    {
        $partner = $this->partner('Kelompok Tani Antrean');
        $this->actingAs($this->admin())->get('/admin/partners')->assertOk()
            ->assertSee('Verifikasi Profil Kemitraan')->assertSee('Kelompok Tani Antrean')
            ->assertSee('Desa Contoh')->assertSee('Pengaju')->assertSee($partner->user->name)
            ->assertSee('data-review-target="review-'.$partner->id.'"', false)
            ->assertSee('id="review-'.$partner->id.'"', false)
            ->assertSee('/files/legal/'.$partner->id.'?preview=1', false)
            ->assertSee('sidebar', false)->assertSee('topbar', false)->assertSee('app-footer', false)
            ->assertDontSee('foundation-placeholder', false)->assertDontSee('name="decision"', false);
        $this->assertSame('pending', $partner->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
        Mail::assertNothingQueued();
    }

    public function test_search_by_group_or_address_combines_with_status_filter(): void
    {
        $wanted = $this->partner('Kelompok Lestari', 'pending', 'Malang');
        $this->partner('Kelompok Lestari Revisi', 'revision', 'Malang');
        $this->partner('Kelompok Lain', 'pending', 'Batu');
        foreach (['Lestari', 'Malang'] as $query) {
            $this->actingAs($this->admin())->get('/admin/partners?q='.$query.'&status=pending')
                ->assertOk()->assertViewHas('records', fn ($records) => $records->pluck('id')->all() === [$wanted->id]);
        }
        $this->get('/admin/partners?q=TidakDitemukan')->assertOk()->assertSee('Tidak ada pengajuan pada filter ini');
    }

    public function test_revision_and_approved_details_display_existing_decisions_and_notes(): void
    {
        $revision = $this->partner('Kelompok Revisi', 'revision');
        $this->actingAs($this->admin())->get('/admin/partners?review='.$revision->id)->assertOk()
            ->assertSee('Kelompok Revisi')->assertSee('Lengkapi dokumen.')
            ->assertSee('Perlu perbaikan')->assertViewHas('records', fn ($records) => $records->total() === 1);
        $approved = Partner::where('status', 'approved')->firstOrFail();
        $this->get('/admin/partners?review='.$approved->id)->assertOk()->assertSee('Pengajuan telah disetujui.');
    }

    public function test_pagination_retains_filters_and_focused_review_is_validated(): void
    {
        for ($number = 1; $number <= 16; $number++) {
            $this->partner('Antrean Pagination '.$number, 'pending', 'Malang');
        }
        $this->actingAs($this->admin())->get('/admin/partners?q=Pagination&status=pending')
            ->assertOk()->assertViewHas('records', fn ($records) => $records->total() === 16
                && $records->count() === 15 && str_contains($records->nextPageUrl(), 'status=pending')
                && str_contains($records->nextPageUrl(), 'q=Pagination'));
        $this->get('/admin/partners?q=Pagination&status=pending&page=2')->assertOk()
            ->assertViewHas('records', fn ($records) => $records->count() === 1);
        $this->get('/admin/partners?review=0')->assertSessionHasErrors('review');
        $this->get('/admin/partners?review=invalid')->assertSessionHasErrors('review');
        $this->get('/admin/partners?review=999999')->assertOk()->assertSee('Tidak ada pengajuan pada filter ini');
    }

    public function test_only_verified_admin_can_open_queue(): void
    {
        $this->get('/admin/partners')->assertRedirect('/login');
        foreach (['partner', 'donor'] as $role) {
            $this->actingAs(User::where('role', $role)->firstOrFail())->get('/admin/partners')->assertRedirect('/dashboard');
            $this->getJson('/admin/partners')->assertForbidden();
        }
        $this->actingAs(User::factory()->unverified()->create(['role' => 'admin']))
            ->get('/admin/partners')->assertRedirect('/email/verify');
    }
}
