<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\Plot;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogPublicationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);
    }

    private function plot(): Plot
    {
        return Plot::create([
            'partner_id' => User::where('role', 'partner')->firstOrFail()->partner->id,
            'name' => 'Petak Publikasi Integrasi', 'location' => 'Lokasi Integrasi',
            'latitude' => -8.2, 'longitude' => 112.6, 'capacity' => 150,
            'proposed_species' => 'Bibit Integrasi', 'status' => 'approved',
        ]);
    }

    private function data(Plot $plot, array $changes = []): array
    {
        return array_replace([
            'plot_id' => $plot->id, 'species' => 'Bibit Integrasi', 'description' => 'Deskripsi integrasi publikasi.',
            'price' => 145000, 'active' => '1',
        ], $changes);
    }

    private function assertPublication(Offer $offer, bool $visible): void
    {
        $query = '?q='.urlencode($offer->species).'&species='.urlencode($offer->species);
        $this->assertSame($visible, Offer::published()->whereKey($offer->id)->exists());
        $this->actingAs(User::where('role', 'donor')->firstOrFail());
        foreach (['/catalog', '/donor/catalog'] as $path) {
            $response = $this->get($path.$query)->assertOk();
            $this->assertSame($visible, $response->viewData('offers')->contains('id', $offer->id));
            $this->assertSame($visible, $response->viewData('speciesOptions')->contains($offer->species));
        }
        $this->assertSame($visible, $this->get('/')->assertOk()->viewData('featuredOffers')->contains('id', $offer->id));
        $this->assertSame($visible, collect($this->getJson('/peta/data')->assertOk()->json())->contains('name', $offer->plot->name));
    }

    public function test_admin_create_update_unpublish_and_republish_are_reflected_across_all_publication_surfaces(): void
    {
        $plot = $this->plot();
        $admin = User::where('role', 'admin')->firstOrFail();
        $photo = UploadedFile::fake()->createWithContent('bibit.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
        $this->actingAs($admin)->post('/admin/catalog', $this->data($plot, ['photo' => $photo]))->assertSessionHasNoErrors();
        $offer = Offer::where('species', 'Bibit Integrasi')->firstOrFail();
        $originalPhoto = $offer->photo;
        $this->assertPublication($offer, true);
        $this->actingAs($admin)->post('/admin/catalog', $this->data($plot, ['offer_id' => $offer->id, 'price' => 158000]))->assertSessionHasNoErrors();
        $offer->refresh();
        $this->assertSame($originalPhoto, $offer->photo);
        Storage::disk('public')->assertExists($originalPhoto);
        foreach (['/catalog', '/donor/catalog'] as $path) {
            $this->actingAs(User::where('role', 'donor')->firstOrFail())->get($path.'?q=Bibit%20Integrasi')->assertOk()->assertSee('158.000');
        }
        $this->actingAs($admin)->post('/admin/catalog', $this->data($plot, ['offer_id' => $offer->id, 'active' => '0']))->assertSessionHasNoErrors();
        $this->assertPublication($offer->fresh(), false);
        $this->actingAs($admin)->get('/admin/catalog?q=Bibit%20Integrasi&status=inactive')->assertOk()->assertViewHas('offers', fn ($offers) => $offers->contains('id', $offer->id));
        $this->post('/admin/catalog', $this->data($plot, ['offer_id' => $offer->id]))->assertSessionHasNoErrors();
        $this->assertPublication($offer->fresh(), true);
        $this->assertDatabaseCount('audit_logs', 4);
        Mail::assertNothingQueued();
    }

    public function test_verification_changes_hide_offers_on_every_surface_and_approval_restores_them(): void
    {
        $plot = $this->plot();
        $offer = Offer::create($this->data($plot));
        foreach (['pending', 'revision', 'rejected'] as $status) {
            $plot->update(['status' => $status]);
            $this->assertPublication($offer, false);
        }
        $plot->update(['status' => 'approved']);
        foreach (['pending', 'revision', 'rejected'] as $status) {
            $plot->partner->update(['status' => $status]);
            $this->assertPublication($offer, false);
        }
        $plot->partner->update(['status' => 'approved']);
        $this->assertPublication($offer, true);
    }

    public function test_map_aggregates_active_species_once_per_plot_and_keeps_private_data_out(): void
    {
        $plot = $this->plot();
        Offer::create($this->data($plot));
        Offer::create($this->data($plot, ['species' => 'Bibit Kedua']));
        Offer::create($this->data($plot, ['species' => 'Bibit Tidak Aktif', 'active' => false]));
        $rows = collect($this->getJson('/peta/data')->assertOk()->json());
        $matches = $rows->where('name', $plot->name);
        $this->assertCount(1, $matches);
        $row = $matches->first();
        $this->assertEqualsCanonicalizing(['Bibit Integrasi', 'Bibit Kedua'], $row['species']);
        $this->assertSame(['name', 'location', 'group', 'latitude', 'longitude', 'species', 'available'], array_keys($row));
        $this->assertSame(150, $row['available']);
        $this->assertEquals(-8.2, $row['latitude']);
        $this->assertEquals(112.6, $row['longitude']);
    }

    public function test_full_quota_preserves_publication_but_disables_adoption_action_in_both_catalogs(): void
    {
        $plot = $this->plot();
        $plot->update(['reserved' => 50, 'confirmed' => 100]);
        $offer = Offer::create($this->data($plot));
        $this->assertPublication($offer, true);
        foreach (['/catalog', '/donor/catalog'] as $path) {
            $this->get($path.'?q=Bibit%20Integrasi')->assertOk()->assertSee('Kuota penuh')->assertSee('Sisa 0')->assertDontSee('/checkout/'.$offer->id, false);
        }
    }
}
