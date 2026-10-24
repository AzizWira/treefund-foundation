<?php

namespace Tests\Feature;

use App\Mail\TreeFundMail;
use App\Models\Offer;
use App\Models\Partner;
use App\Models\Plot;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PlotRevisionIntegrationTest extends TestCase
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

    private function data(array $changes = []): array
    {
        return array_replace([
            'name' => 'Petak Integrasi', 'location' => 'Malang', 'latitude' => -8.1,
            'longitude' => 112.6, 'capacity' => 100, 'proposed_species' => 'Alpukat',
        ], $changes);
    }

    public function test_land_submission_revision_resubmission_and_approval_keep_the_same_record_and_owner(): void
    {
        $owner = $this->owner();
        $admin = User::where('role', 'admin')->firstOrFail();
        $this->actingAs($owner)->from('/partner/plots')->post('/partner/plots', $this->data())->assertRedirect('/partner/plots')->assertSessionHasNoErrors();
        $plot = Plot::where('name', 'Petak Integrasi')->firstOrFail();
        $total = Plot::count();
        $this->actingAs($admin)->from('/admin/plots')->post('/admin/plots/'.$plot->id.'/review', [
            'decision' => 'revision', 'review_note' => 'Perbaiki koordinat dan kapasitas.',
        ])->assertRedirect('/admin/plots');
        $this->actingAs($owner)->get('/partner/plots')->assertOk()->assertSee('Perbaiki koordinat dan kapasitas.');
        $this->post('/partner/plots', $this->data(['plot_id' => $plot->id, 'latitude' => -8.2, 'capacity' => 150]))->assertSessionHasNoErrors();
        $plot->refresh();
        $this->assertSame('pending', $plot->status);
        $this->assertNull($plot->review_note);
        $this->assertNull($plot->reviewed_by);
        $this->assertEquals(-8.2, $plot->latitude);
        $this->assertSame($total, Plot::count());
        $this->assertEquals($owner->partner->id, $plot->partner_id);
        $this->actingAs($admin)->post('/admin/plots/'.$plot->id.'/review', ['decision' => 'approved'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $plot->fresh()->status);
        $this->actingAs($owner)->post('/partner/plots', $this->data(['plot_id' => $plot->id]))->assertStatus(409);
        $this->assertDatabaseHas('audit_logs', ['action' => 'plots.revision', 'subject_id' => $plot->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'plots.approved', 'subject_id' => $plot->id]);
        Mail::assertQueuedCount(4);
    }

    public function test_failed_revision_restores_only_the_target_form_and_opens_its_details(): void
    {
        $owner = $this->owner();
        $first = Plot::create($this->data(['partner_id' => $owner->partner->id, 'status' => 'revision', 'name' => 'Petak Satu']));
        $second = Plot::create($this->data(['partner_id' => $owner->partner->id, 'status' => 'revision', 'name' => 'Petak Dua']));
        $this->actingAs($owner)->from('/partner/plots')->post('/partner/plots', $this->data([
            'plot_id' => $first->id, 'name' => 'Nama Koreksi', 'capacity' => 0,
        ]))->assertRedirect('/partner/plots')->assertSessionHasErrors('capacity');
        $response = $this->get('/partner/plots')->assertOk();
        $dom = new \DOMDocument();
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        foreach (['' => '', (string) $first->id => 'Nama Koreksi', (string) $second->id => 'Petak Dua'] as $id => $expected) {
            $form = $id === ''
                ? $xpath->query('//form[@action="/partner/plots"][not(input[@name="plot_id"])]')->item(0)
                : $xpath->query('//form[@action="/partner/plots"][input[@name="plot_id" and @value="'.$id.'"]]')->item(0);
            $this->assertNotNull($form);
            $name = $xpath->query('.//input[@name="name"]', $form)->item(0);
            $this->assertSame($expected, $name->getAttribute('value'));
        }
        $this->assertSame(1, $xpath->query('//details[@open]')->length);
        $this->assertSame('Petak Satu', $first->fresh()->name);
    }

    public function test_other_approved_group_cannot_revise_someone_elses_land(): void
    {
        $owner = $this->owner();
        $plot = Plot::create($this->data(['partner_id' => $owner->partner->id, 'status' => 'revision']));
        $otherUser = User::factory()->create(['role' => 'partner']);
        $attributes = $owner->partner->getAttributes();
        unset($attributes['id'], $attributes['created_at'], $attributes['updated_at']);
        Partner::create(array_replace($attributes, ['user_id' => $otherUser->id, 'group_name' => 'Kelompok Kedua']));
        $this->actingAs($otherUser)->get('/partner/plots')->assertOk()->assertDontSee('Petak Integrasi');
        $this->post('/partner/plots', $this->data(['plot_id' => $plot->id, 'name' => 'Dicoba Diubah']))->assertNotFound();
        $this->assertSame('Petak Integrasi', $plot->fresh()->name);
        Mail::assertNothingQueued();
    }

    public function test_revision_cannot_reduce_capacity_below_existing_reservations_and_confirmations(): void
    {
        $owner = $this->owner();
        $plot = Plot::create($this->data(['partner_id' => $owner->partner->id, 'status' => 'revision', 'reserved' => 20, 'confirmed' => 30]));
        $this->actingAs($owner)->post('/partner/plots', $this->data(['plot_id' => $plot->id, 'capacity' => 49]))->assertSessionHasErrors('capacity');
        $this->assertSame('revision', $plot->fresh()->status);
        $this->assertEquals(100, $plot->capacity);
        Mail::assertNothingQueued();
        $this->post('/partner/plots', $this->data(['plot_id' => $plot->id, 'capacity' => 50]))->assertSessionHasNoErrors();
        $plot->refresh();
        $this->assertEquals(20, $plot->reserved);
        $this->assertEquals(30, $plot->confirmed);
        $this->assertSame(0, $plot->available);
    }

    public function test_pending_revision_rejected_or_inactive_offers_stay_out_of_catalog_landing_and_map(): void
    {
        $offer = Offer::with('plot.partner')->firstOrFail();
        $plot = $offer->plot;
        $cases = [
            ['pending', 'approved', true], ['revision', 'approved', true], ['rejected', 'approved', true],
            ['approved', 'pending', true], ['approved', 'revision', true], ['approved', 'rejected', true],
            ['approved', 'approved', false], ['approved', 'approved', true],
        ];
        foreach ($cases as [$plotStatus, $partnerStatus, $active]) {
            $plot->update(['status' => $plotStatus]);
            $plot->partner->update(['status' => $partnerStatus]);
            $offer->update(['active' => $active]);
            $visible = $plotStatus === 'approved' && $partnerStatus === 'approved' && $active;
            $catalog = $this->get('/catalog?q='.urlencode($plot->name))->assertOk();
            $this->assertSame($visible, $catalog->viewData('offers')->contains('id', $offer->id));
            $landing = $this->get('/')->assertOk();
            $this->assertSame($visible, $landing->viewData('featuredOffers')->contains('id', $offer->id));
            $data = $this->getJson('/peta/data')->assertOk()->json();
            $this->assertSame($visible, collect($data)->contains('name', $plot->name));
            foreach ($data as $row) {
                foreach (['bank_account', 'document', 'user_id', 'review_note', 'reviewed_by'] as $privateField) {
                    $this->assertArrayNotHasKey($privateField, $row);
                }
            }
        }
    }

    public function test_verified_land_without_published_offer_is_not_listed_and_unapproved_owner_cannot_revise(): void
    {
        $owner = $this->owner();
        $plot = Plot::create($this->data(['partner_id' => $owner->partner->id, 'status' => 'approved']));
        $this->get('/catalog?q=Petak%20Integrasi')->assertOk()->assertViewHas('offers', fn ($offers) => $offers->isEmpty());
        $this->assertFalse(collect($this->getJson('/peta/data')->assertOk()->json())->contains('name', $plot->name));
        $plot->update(['status' => 'revision']);
        $owner->partner->update(['status' => 'revision']);
        $this->actingAs($owner->fresh())->get('/partner/plots')->assertOk()->assertDontSee('Perbaiki Pengajuan');
        $this->post('/partner/plots', $this->data(['plot_id' => $plot->id]))->assertForbidden();
        $this->assertSame('revision', $plot->fresh()->status);
    }
}
