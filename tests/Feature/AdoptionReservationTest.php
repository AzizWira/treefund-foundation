<?php

namespace Tests\Feature;

use App\Models\Adoption;
use App\Models\Offer;
use App\Models\User;
use App\Services\AdoptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdoptionReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_reserves_shared_plot_quota_and_ignores_a_forged_price(): void
    {
        $this->seed();
        $offer = Offer::published()->firstOrFail();
        $offer->plot->update(['capacity' => 3, 'reserved' => 0, 'confirmed' => 0]);
        $other = $offer->replicate();
        $other->save();
        $this->actingAs(User::factory()->create(['role' => 'donor']));
        $data = ['offer_id' => $offer->id, 'quantity' => 2, 'certificate_name' => 'Donatur Demo', 'agree' => '1', 'total' => 1];
        $this->post('/orders', $data)->assertRedirect(route('orders.show', Adoption::firstOrFail()));
        $this->assertDatabaseHas('adoptions', ['total' => $offer->price * 2, 'status' => 'pending']);
        $this->assertDatabaseHas('adoption_items', ['offer_id' => $offer->id, 'unit_price' => $offer->price, 'quantity' => 2]);
        $this->postJson('/orders', array_replace($data, ['offer_id' => $other->id]))->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->assertDatabaseCount('adoptions', 1);
        $this->assertEquals(2, $offer->plot->fresh()->reserved);
        $this->travel(61)->minutes();
        $order = Adoption::firstOrFail();
        app(AdoptionService::class)->expireUnissued($order);
        app(AdoptionService::class)->expireUnissued($order);
        $this->assertEquals(0, $offer->plot->fresh()->reserved);
        $this->assertSame('expired', $order->fresh()->status);
    }

    public function test_donors_can_search_and_open_only_their_own_orders(): void
    {
        $this->seed();
        $owner = User::factory()->create(['role' => 'donor']);
        $other = User::factory()->create(['role' => 'donor']);
        $offer = Offer::published()->firstOrFail();
        $mine = app(AdoptionService::class)->checkout($owner, $offer, 1, 'Pemilik');
        $theirs = app(AdoptionService::class)->checkout($other, $offer, 1, 'Pengguna Lain');
        $this->actingAs($owner)->get('/orders')->assertOk()->assertSee($mine->reference)->assertDontSee($theirs->reference);
        $this->get('/orders?status=expired')->assertOk()->assertDontSee($mine->reference);
        $this->get('/orders?q='.$theirs->reference)->assertOk()->assertViewHas('orders', fn ($orders) => $orders->isEmpty());
        $this->get(route('orders.show', $mine))->assertOk()->assertSee($mine->reference);
        $this->get(route('orders.show', $theirs))->assertForbidden();
    }

    public function test_snap_is_gated_and_uses_saved_order_value(): void
    {
        $this->seed();
        Http::preventStrayRequests();
        $donor = User::factory()->create(['role' => 'donor']);
        $offer = Offer::published()->firstOrFail();
        $order = app(AdoptionService::class)->checkout($donor, $offer, 2, 'Donatur');
        $url = route('orders.pay', $order);
        $this->actingAs($donor)->postJson($url)->assertNotFound();
        config(['treefund.midtrans.enabled' => true, 'treefund.midtrans.server_key' => 'SB-Mid-server-test', 'treefund.midtrans.client_key' => 'SB-Mid-client-test']);
        Http::fake([config('treefund.midtrans.snap_url') => Http::response(['token' => 'sandbox-test-token'])]);
        $this->postJson($url, ['total' => 1])->assertOk()->assertJson(['token' => 'sandbox-test-token']);
        $this->postJson($url)->assertOk();
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['transaction_details']['gross_amount'] === $offer->price * 2);
    }

    public function test_webhook_checks_signature_and_official_amount_before_releasing_quota(): void
    {
        $this->seed();
        Http::preventStrayRequests();
        $donor = User::factory()->create(['role' => 'donor']);
        $offer = Offer::published()->firstOrFail();
        $order = app(AdoptionService::class)->checkout($donor, $offer, 1, 'Donatur');
        $order->update(['snap_token' => 'sandbox-test-token']);
        $key = 'SB-Mid-server-test';
        config(['treefund.midtrans.enabled' => true, 'treefund.midtrans.server_key' => $key]);
        $payload = ['order_id' => $order->reference, 'status_code' => '202', 'gross_amount' => $order->total.'.00'];
        $payload['signature_key'] = str_repeat('0', 128);
        $this->postJson('/payments/midtrans/notification', $payload)->assertForbidden();
        Http::assertNothingSent();
        $payload['signature_key'] = hash('sha512', $order->reference.'202'.$payload['gross_amount'].$key);
        Http::fakeSequence()->push([
            'order_id' => $order->reference, 'gross_amount' => '1.00', 'transaction_status' => 'expire',
        ])->push([
            'order_id' => $order->reference, 'gross_amount' => $payload['gross_amount'], 'transaction_status' => 'expire',
        ])->push([
            'order_id' => $order->reference, 'gross_amount' => $payload['gross_amount'], 'transaction_status' => 'expire',
        ]);
        $this->postJson('/payments/midtrans/notification', $payload)->assertUnprocessable();
        $this->assertSame('pending', $order->fresh()->status);
        $this->postJson('/payments/midtrans/notification', $payload)->assertOk();
        $this->postJson('/payments/midtrans/notification', $payload)->assertOk();
        $this->assertSame('expired', $order->fresh()->status);
        $this->assertEquals($offer->plot->reserved, $offer->plot->fresh()->reserved);
    }

    public function test_settlement_issues_adoption_records_only_once_and_handles_late_capacity(): void
    {
        $this->seed();
        $service = app(AdoptionService::class);
        $offer = Offer::published()->firstOrFail();
        $offer->plot->update(['capacity' => 3, 'reserved' => 0, 'confirmed' => 0]);
        $donor = User::factory()->create(['role' => 'donor']);
        $late = $service->checkout($donor, $offer, 3, 'Donatur');
        $this->travel(61)->minutes();
        $service->expireUnissued($late);
        $order = $service->checkout($donor, $offer, 3, 'Donatur');
        $payment = ['transaction_status' => 'settlement'];
        $service->applyProviderStatus($order, $payment);
        $service->applyProviderStatus($order, $payment);
        $service->applyProviderStatus($late, $payment);
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame('review', $late->fresh()->status);
        $this->assertDatabaseCount('trees', 3);
        $this->assertDatabaseCount('work_orders', 1);
        $this->assertDatabaseCount('certificates', 1);
        $this->assertEquals(0, $offer->plot->fresh()->reserved);
        $this->assertEquals(3, $offer->plot->fresh()->confirmed);
    }

    public function test_work_order_is_visible_only_to_the_assigned_partner(): void
    {
        $this->seed();
        $offer = Offer::published()->firstOrFail();
        $donor = User::factory()->create(['role' => 'donor']);
        $service = app(AdoptionService::class);
        $order = $service->checkout($donor, $offer, 1, 'Donatur');
        $service->applyProviderStatus($order, ['transaction_status' => 'settlement']);
        $work = $order->items()->firstOrFail()->workOrder()->firstOrFail();
        $url = route('partner.work', $work);
        $this->actingAs($offer->plot->partner->user)->get('/partner/work-orders?q=WO-'.$work->id)
            ->assertOk()->assertSee($url, false);
        $this->get($url)->assertOk()->assertSee($work->item->trees->first()->code);
        $other = User::factory()->create(['role' => 'partner']);
        $this->actingAs($other)->get('/partner/work-orders')->assertOk()->assertDontSee($url, false);
        $this->get($url)->assertForbidden();
    }

    public function test_planting_is_private_and_only_assigned_partner_can_submit(): void
    {
        $this->seed();
        Storage::fake('local');
        $offer = Offer::published()->firstOrFail();
        $donor = User::factory()->create(['role' => 'donor']);
        $service = app(AdoptionService::class);
        $order = $service->checkout($donor, $offer, 1, 'Donatur');
        $service->applyProviderStatus($order, ['transaction_status' => 'settlement']);
        $tree = $order->items()->firstOrFail()->trees()->firstOrFail();
        $url = route('partner.planting', $tree);
        $payload = ['planted_at' => now()->toDateString(), 'latitude' => -7.2, 'longitude' => 112.7, 'photo' => UploadedFile::fake()->image('bukti.png')];
        $this->actingAs(User::factory()->create(['role' => 'partner']))->post($url, $payload)->assertForbidden();
        $this->actingAs($offer->plot->partner->user)->post($url, $payload)->assertRedirect();
        $planting = $tree->planting()->firstOrFail();
        Storage::disk('local')->assertExists($planting->photo);
        $file = route('files', ['type' => 'planting', 'id' => $planting->id]);
        $this->get($file)->assertOk();
        $this->actingAs($donor)->get($file)->assertForbidden();
        $this->actingAs($offer->plot->partner->user)->post($url, $payload)->assertConflict();
        $planting->update(['status' => 'revision']);
        $payload['photo'] = UploadedFile::fake()->image('revisi.png');
        $this->post($url, $payload)->assertRedirect();
        $this->assertEquals(2, $planting->fresh()->submission_version);
        Storage::disk('local')->assertMissing($planting->photo);
    }
}
