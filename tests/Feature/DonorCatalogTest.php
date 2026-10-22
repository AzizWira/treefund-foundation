<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\Plot;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DonorCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(DatabaseSeeder::class);
    }

    private function donor(): User
    {
        return User::where('role', 'donor')->firstOrFail();
    }

    public function test_donor_catalog_uses_portal_layout_and_public_catalog_keeps_website_layout(): void
    {
        $offer = Offer::firstOrFail();
        $this->actingAs($this->donor())->get('/donor/catalog')->assertOk()
            ->assertSee('sidebar', false)->assertSee('topbar', false)->assertSee('app-footer', false)
            ->assertSee('Temukan lahan. Tumbuhkan harapan.')->assertSee($offer->plot->name)
            ->assertSee('125.000')->assertSee('/checkout/'.$offer->id, false)
            ->assertDontSee('foundation-placeholder', false)->assertDontSee('class="site-header"', false);
        $this->get('/catalog')->assertOk()->assertSee('site-header', false)->assertDontSee('class="app-shell"', false);
        $this->assertDatabaseCount('audit_logs', 0);
        Mail::assertNothingQueued();
    }

    public function test_search_by_species_plot_or_location_combines_with_species_filter(): void
    {
        $offer = Offer::with('plot')->where('species', 'Alpukat')->firstOrFail();
        foreach ([$offer->species, $offer->plot->name, $offer->plot->location] as $query) {
            $this->actingAs($this->donor())->get('/donor/catalog?q='.urlencode(' '.$query.' ').'&species=Alpukat')->assertOk()
                ->assertViewHas('offers', fn ($offers) => $offers->pluck('id')->all() === [$offer->id]);
        }
        $this->get('/donor/catalog?q=Durian&species=Alpukat')->assertOk()->assertSee('Penawaran belum ditemukan');
        $this->get('/donor/catalog?q=TidakAda')->assertOk()->assertViewHas('offers', fn ($offers) => $offers->isEmpty());
    }

    public function test_unpublished_and_unverified_offers_do_not_leak_into_results_or_species_options(): void
    {
        $offer = Offer::firstOrFail();
        $this->actingAs($this->donor());
        foreach (['pending', 'revision', 'rejected'] as $status) {
            $offer->plot->update(['status' => $status]);
            $this->get('/donor/catalog')->assertOk()
                ->assertViewHas('offers', fn ($offers) => ! $offers->contains('id', $offer->id))
                ->assertViewHas('speciesOptions', fn ($options) => ! $options->contains($offer->species));
        }
        $offer->plot->update(['status' => 'approved']);
        $offer->update(['active' => false]);
        $this->get('/donor/catalog')->assertOk()->assertViewHas('offers', fn ($offers) => ! $offers->contains('id', $offer->id));
        $offer->update(['active' => true]);
        $offer->plot->partner->update(['status' => 'revision']);
        $this->get('/donor/catalog')->assertOk()->assertViewHas('offers', fn ($offers) => $offers->isEmpty())->assertViewHas('speciesOptions', fn ($options) => $options->isEmpty());
    }

    public function test_pagination_retains_filters_and_reset_stays_inside_donor_portal(): void
    {
        $plot = Plot::firstOrFail();
        for ($i = 1; $i <= 11; $i++) {
            Offer::create(['plot_id' => $plot->id, 'species' => 'Bibit Pagination', 'description' => 'Contoh bibit.', 'price' => 120000, 'active' => true]);
        }
        $this->actingAs($this->donor())->get('/donor/catalog?q=Pagination&species=Bibit%20Pagination')->assertOk()
            ->assertViewHas('offers', fn ($offers) => $offers->total() === 11 && $offers->count() === 9
                && str_contains($offers->nextPageUrl(), '/donor/catalog?') && str_contains($offers->nextPageUrl(), 'q=Pagination')
                && str_contains($offers->nextPageUrl(), 'species=Bibit'))
            ->assertSee('href="'.route('donor.catalog').'"', false);
        $this->get('/donor/catalog?q=Pagination&species=Bibit%20Pagination&page=2')->assertOk()->assertViewHas('offers', fn ($offers) => $offers->count() === 2);
    }

    public function test_sold_out_quota_has_no_checkout_link_and_available_quota_is_shared_by_plot(): void
    {
        $offer = Offer::firstOrFail();
        $offer->plot->update(['reserved' => 35, 'confirmed' => 65]);
        $this->actingAs($this->donor())->get('/donor/catalog?species='.urlencode($offer->species))->assertOk()
            ->assertSee('Kuota penuh')->assertSee('Sisa 0')->assertDontSee('/checkout/'.$offer->id, false);
        $offer->plot->update(['reserved' => 30, 'confirmed' => 60]);
        $this->get('/donor/catalog?species='.urlencode($offer->species))->assertOk()->assertSee('Sisa 10')->assertSee('/checkout/'.$offer->id, false);
    }

    public function test_only_verified_donors_enter_portal_while_public_catalog_remains_accessible(): void
    {
        $this->get('/donor/catalog')->assertRedirect('/login');
        foreach (['partner', 'admin'] as $role) {
            $this->actingAs(User::where('role', $role)->firstOrFail())->get('/donor/catalog')->assertRedirect('/dashboard');
            $this->getJson('/donor/catalog')->assertForbidden();
            $this->get('/catalog')->assertOk()->assertSee('Adopsi melalui akun Donatur')->assertDontSee('/checkout/', false);
        }
        $this->actingAs(User::factory()->unverified()->create(['role' => 'donor']))->get('/donor/catalog')->assertRedirect('/email/verify');
    }

    public function test_malformed_search_input_is_validated_and_untrusted_descriptions_are_escaped(): void
    {
        $this->actingAs($this->donor());
        foreach (['/donor/catalog?q[]=test', '/donor/catalog?species[]=test'] as $url) {
            $this->getJson($url)->assertUnprocessable();
        }
        $this->getJson('/donor/catalog?q='.str_repeat('a', 191))->assertUnprocessable()->assertJsonValidationErrors('q');
        $offer = Offer::firstOrFail();
        $offer->update(['description' => '<script>alert(1)</script>']);
        $this->get('/donor/catalog')->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }
}
