<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\Plot;
use App\Models\User;
use App\Services\ActivityService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);
    }

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    private function data(array $changes = []): array
    {
        return array_replace([
            'plot_id' => Plot::firstOrFail()->id, 'species' => 'Bibit Uji Katalog',
            'description' => 'Penawaran bibit untuk agroforestri.', 'price' => 135000, 'active' => '1',
        ], $changes);
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('bibit.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
    }

    public function test_original_admin_layout_forms_and_verified_plot_choices_are_available(): void
    {
        $plot = Plot::firstOrFail();
        $plot->update(['status' => 'revision']);
        $response = $this->actingAs($this->admin())->get('/admin/catalog')->assertOk()
            ->assertSee('Pengelolaan Katalog Bibit & Lahan', false)->assertSee('name="photo"', false)
            ->assertSee('name="active"', false)->assertSee('data-open-details', false)
            ->assertDontSee('foundation-placeholder', false);
        $this->assertFalse($response->viewData('plots')->contains('id', $plot->id));
        $plot->partner->update(['status' => 'revision']);
        $this->get('/admin/catalog')->assertOk()->assertViewHas('plots', fn ($plots) => $plots->isEmpty())
            ->assertSee('Belum ada lahan dan Mitra terverifikasi.');
    }

    public function test_admin_creates_offer_with_public_photo_audit_and_preserves_plot_quota(): void
    {
        $plot = Plot::firstOrFail();
        $this->actingAs($this->admin())->from('/admin/catalog')->post('/admin/catalog', $this->data([
            'photo' => $this->photo(), 'confirmed' => 99, 'reserved' => 88, 'id' => 999,
        ]))->assertRedirect('/admin/catalog')->assertSessionHasNoErrors()->assertSessionHas('success');
        $offer = Offer::where('species', 'Bibit Uji Katalog')->firstOrFail();
        $this->assertSame(135000, $offer->price);
        $this->assertTrue($offer->active);
        Storage::disk('public')->assertExists($offer->photo);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $this->admin()->id, 'action' => 'catalog.saved', 'subject_type' => Offer::class, 'subject_id' => $offer->id]);
        $this->assertEquals(0, $plot->fresh()->reserved);
        $this->assertEquals(0, $plot->confirmed);
        $this->get('/catalog?q=Bibit%20Uji%20Katalog')->assertOk()->assertSee('135.000')->assertSee('storage/'.$offer->photo, false);
        Mail::assertNothingQueued();
    }

    public function test_edit_keeps_same_offer_and_existing_photo_and_can_unpublish(): void
    {
        $offer = Offer::firstOrFail();
        Storage::disk('public')->put('offers/existing.png', 'existing photo');
        $offer->update(['photo' => 'offers/existing.png']);
        $count = Offer::count();
        $data = $this->data(['offer_id' => $offer->id, 'species' => 'Bibit Diperbarui', 'price' => 140000]);
        unset($data['active']);
        $this->actingAs($this->admin())->post('/admin/catalog', $data)->assertSessionHasNoErrors();
        $offer->refresh();
        $this->assertSame($count, Offer::count());
        $this->assertSame('Bibit Diperbarui', $offer->species);
        $this->assertSame('offers/existing.png', $offer->photo);
        Storage::disk('public')->assertExists($offer->photo);
        $this->assertFalse($offer->active);
        $this->get('/catalog?q=Bibit%20Diperbarui')->assertOk()->assertViewHas('offers', fn ($offers) => $offers->isEmpty());
        $this->post('/admin/catalog', $this->data(['offer_id' => $offer->id, 'photo' => $this->photo()]))->assertSessionHasNoErrors();
        $offer->refresh();
        $this->assertTrue($offer->active);
        $this->assertNotSame('offers/existing.png', $offer->photo);
        Storage::disk('public')->assertExists($offer->photo);
    }

    public function test_pending_land_or_unapproved_partner_cannot_be_used_even_with_a_forged_request(): void
    {
        $plot = Plot::firstOrFail();
        foreach (['pending', 'revision', 'rejected'] as $status) {
            $plot->update(['status' => $status]);
            $this->actingAs($this->admin())->post('/admin/catalog', $this->data(['photo' => $this->photo()]))->assertSessionHasErrors('plot_id');
        }
        $plot->update(['status' => 'approved']);
        $plot->partner->update(['status' => 'revision']);
        $this->post('/admin/catalog', $this->data())->assertSessionHasErrors('plot_id');
        $this->assertDatabaseMissing('offers', ['species' => 'Bibit Uji Katalog']);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_invalid_fields_photo_types_and_sizes_are_rejected(): void
    {
        $this->actingAs($this->admin())->post('/admin/catalog', [])->assertSessionHasErrors(['plot_id', 'species', 'description', 'price']);
        foreach ([['price' => 999], ['price' => 100000001], ['price' => 1000.5], ['plot_id' => 999999], ['species' => str_repeat('x', 121)], ['description' => str_repeat('x', 3001)], ['active' => 'yes'], ['offer_id' => 'abc']] as $invalid) {
            $this->post('/admin/catalog', $this->data($invalid))->assertSessionHasErrors(array_keys($invalid));
        }
        $this->post('/admin/catalog', $this->data(['photo' => UploadedFile::fake()->create('program.exe', 10)]))->assertSessionHasErrors('photo');
        $this->post('/admin/catalog', $this->data(['photo' => UploadedFile::fake()->create('large.png', 5121, 'image/png')]))->assertSessionHasErrors('photo');
        $this->post('/admin/catalog', $this->data(['offer_id' => 999999]))->assertNotFound();
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertDatabaseMissing('offers', ['species' => 'Bibit Uji Katalog']);
    }

    public function test_search_status_and_pagination_work_and_retain_filters(): void
    {
        for ($i = 1; $i <= 16; $i++) {
            Offer::create($this->data(['species' => 'Bibit Pencarian '.$i, 'active' => false]));
        }
        $this->actingAs($this->admin())->get('/admin/catalog?q=Pencarian&status=inactive')->assertOk()
            ->assertViewHas('offers', fn ($offers) => $offers->total() === 16 && $offers->count() === 15 && str_contains($offers->nextPageUrl(), 'status=inactive'));
        $this->get('/admin/catalog?q=Pencarian&status=inactive&page=2')->assertOk()->assertViewHas('offers', fn ($offers) => $offers->count() === 1);
        $this->get('/admin/catalog?q=TidakAda')->assertOk()->assertViewHas('offers', fn ($offers) => $offers->isEmpty());
    }

    public function test_only_verified_admin_can_save_offers(): void
    {
        $this->post('/admin/catalog', $this->data())->assertRedirect('/login');
        foreach (['partner', 'donor'] as $role) {
            $this->actingAs(User::where('role', $role)->firstOrFail())->post('/admin/catalog', $this->data())->assertForbidden();
        }
        $this->actingAs(User::factory()->unverified()->create(['role' => 'admin']))->post('/admin/catalog', $this->data())->assertRedirect('/email/verify');
        $this->assertDatabaseMissing('offers', ['species' => 'Bibit Uji Katalog']);
    }

    public function test_audit_failure_rolls_back_offer_and_cleans_new_photo(): void
    {
        $this->mock(ActivityService::class, function ($mock): void {
            $mock->shouldReceive('audit')->once()->andThrow(new \RuntimeException('Audit failed'));
        });
        $this->actingAs($this->admin())->post('/admin/catalog', $this->data(['photo' => $this->photo()]))->assertStatus(500);
        $this->assertDatabaseMissing('offers', ['species' => 'Bibit Uji Katalog']);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
