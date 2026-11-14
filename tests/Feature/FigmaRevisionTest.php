<?php

namespace Tests\Feature;

use App\Models\ActivityNotification;
use App\Models\Adoption;
use App\Models\Planting;
use App\Models\User;
use Database\Seeders\CompleteDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FigmaRevisionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Mail::fake();
        Http::preventStrayRequests();
        Storage::fake('local');
        $this->seed(CompleteDemoSeeder::class);
    }

    public function test_file_preview_and_download_preserve_ownership_checks(): void
    {
        $record = Planting::where('status', 'approved')->firstOrFail();
        $owner = $record->tree->item->adoption->user;
        $path = '/files/planting/'.$record->id;
        $this->actingAs($owner)->get($path.'?preview=1')->assertOk()->assertSee('Dokumentasi Bukti Tanam')->assertSee('Unduh Berkas');
        $this->get($path.'?download=1')->assertOk()->assertDownload('tree.svg');
        $this->get($path)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $other = User::where('role', 'donor')->where('id', '!=', $owner->id)->firstOrFail();
        $this->actingAs($other);
        foreach (['', '?preview=1', '?download=1'] as $suffix) {
            $this->get($path.$suffix)->assertForbidden();
        }
    }

    public function test_mark_all_read_only_changes_current_users_notifications(): void
    {
        $donor = User::where('role', 'donor')->firstOrFail();
        $other = User::where('id', '!=', $donor->id)->firstOrFail();
        $mine = ActivityNotification::create(['user_id' => $donor->id, 'title' => 'Kabar saya', 'body' => 'Baru', 'url' => '/dashboard']);
        $theirs = ActivityNotification::create(['user_id' => $other->id, 'title' => 'Kabar orang lain', 'body' => 'Baru', 'url' => '/dashboard']);
        $this->actingAs($donor)->post('/notifications/read-all')->assertRedirect();
        $this->assertNotNull($mine->fresh()->read_at);
        $this->assertNull($theirs->fresh()->read_at);
    }

    public function test_certificate_preview_keeps_payment_and_ownership_restrictions(): void
    {
        $paid = Adoption::where('status', 'paid')->whereHas('certificate')->firstOrFail();
        $this->actingAs($paid->user)->get('/orders/'.$paid->id.'/certificate?preview=1')
            ->assertOk()->assertSee($paid->certificate->number)->assertSee('Cetak Sertifikat');
        $other = User::where('role', 'donor')->where('id', '!=', $paid->user_id)->firstOrFail();
        $this->actingAs($other)->get('/orders/'.$paid->id.'/certificate?preview=1')->assertForbidden();
        $pending = Adoption::where('status', 'pending')->firstOrFail();
        $this->actingAs($pending->user)->get('/orders/'.$pending->id.'/certificate?preview=1')->assertNotFound();
    }

    public function test_dashboard_review_targets_a_record_beyond_the_first_page(): void
    {
        $admin = User::where('role', 'admin')->firstOrFail();
        $record = Planting::oldest('id')->firstOrFail();
        $this->actingAs($admin)->get('/admin/plantings?review='.$record->id)
            ->assertOk()->assertViewHas('records', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $record->id)
            ->assertSee('id="review-'.$record->id.'"', false);
        $this->get('/admin/plantings?review=invalid')->assertSessionHasErrors('review');
    }

    public function test_filters_query_all_records_and_preserve_role_ownership(): void
    {
        $donor = User::where('role', 'donor')->firstOrFail();
        $this->actingAs($donor)->get('/orders?status=paid')->assertOk()->assertViewHas('orders', fn ($rows) => $rows->every(fn ($row) => $row->status === 'paid' && $row->user_id === $donor->id));
        $this->get('/orders?q=NO-SUCH-FIGMA-REFERENCE')->assertOk()->assertViewHas('orders', fn ($rows) => $rows->total() === 0);
        $partner = User::where('role', 'partner')->whereHas('partner', fn ($q) => $q->where('status', 'approved'))->firstOrFail();
        $this->actingAs($partner)->get('/partner/plots?status=approved')->assertOk()->assertViewHas('plots', fn ($rows) => $rows->every(fn ($row) => $row->status === 'approved' && $row->partner_id === $partner->partner->id));
        foreach (['/partner/work-orders', '/partner/monitoring', '/partner/funds', '/partner/members'] as $url) {
            $this->get($url.'?q=NO-SUCH-FIGMA-REFERENCE')->assertOk();
        }
        $admin = User::where('role', 'admin')->where('can_manage_finance', true)->firstOrFail();
        $this->actingAs($admin)->get('/admin/catalog?status=inactive')->assertOk()->assertViewHas('offers', fn ($rows) => $rows->every(fn ($row) => ! $row->active));
        foreach (['partners', 'plots', 'plantings', 'monitorings'] as $queue) {
            $this->get('/admin/'.$queue.'?q=NO-SUCH-FIGMA-REFERENCE')->assertOk()->assertViewHas('records', fn ($rows) => $rows->total() === 0);
        }
        $this->get('/admin/disbursements?allocation_q=NO-SUCH-FIGMA-REFERENCE&q=NO-SUCH-FIGMA-REFERENCE')->assertOk()->assertViewHas('works', fn ($rows) => $rows->total() === 0)->assertViewHas('records', fn ($rows) => $rows->total() === 0);
        $this->get('/admin/reports?status=paid')->assertOk()->assertViewHas('orders', fn ($rows) => $rows->every(fn ($row) => $row->status === 'paid'));
        $this->get('/admin/settings?q=NO-SUCH-FIGMA-REFERENCE')->assertOk()->assertViewHas('logs', fn ($rows) => $rows->total() === 0);
    }
}
