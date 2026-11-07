<?php

namespace Tests\Feature;

use App\Models\Adoption;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdoptionJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_donor_payment_to_verified_field_monitoring_and_certificate(): void
    {
        $this->seed();
        Storage::fake('local');
        Mail::fake();
        Http::preventStrayRequests();
        $key = 'SB-Mid-server-test';
        config(['treefund.midtrans.enabled' => true, 'treefund.midtrans.server_key' => $key, 'treefund.midtrans.client_key' => 'SB-Mid-client-test']);
        $donor = User::factory()->create(['role' => 'donor']);
        $offer = Offer::published()->firstOrFail();
        $this->actingAs($donor)->post('/orders', ['offer_id' => $offer->id, 'quantity' => 1, 'certificate_name' => 'Donatur Uji', 'agree' => true])->assertRedirect()->assertSessionHasNoErrors();
        $order = Adoption::firstOrFail();
        $payload = ['order_id' => $order->reference, 'status_code' => '200', 'gross_amount' => $order->total.'.00'];
        $payload['signature_key'] = hash('sha512', $order->reference.'200'.$payload['gross_amount'].$key);
        Http::fake([
            config('treefund.midtrans.snap_url') => Http::response(['token' => 'sandbox-test-token']),
            config('treefund.midtrans.api_url').'/'.$order->reference.'/status' => Http::response($payload + ['transaction_status' => 'settlement']),
        ]);
        $this->postJson(route('orders.pay', $order))->assertOk()->assertJsonPath('token', 'sandbox-test-token');
        $this->postJson(route('midtrans.notification'), $payload)->assertOk();
        $this->postJson(route('orders.sync', $order))->assertOk()->assertJsonPath('status', 'paid');
        $tree = $order->items()->firstOrFail()->trees()->firstOrFail();
        $work = $tree->item->workOrder;
        $admin = User::where('role', 'admin')->firstOrFail();
        $partner = $work->partner->user;
        $today = now()->toDateString();

        $this->actingAs($partner)->get(route('partner.work', $work))->assertOk()->assertSee($tree->code);
        $this->post(route('partner.planting', $tree), ['planted_at' => $today, 'latitude' => -7.2, 'longitude' => 112.7, 'photo' => UploadedFile::fake()->image('tanam.png')])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($donor)->getJson('/map/data')->assertOk()->assertExactJson([]);
        $this->actingAs($admin)->post(route('admin.plantings.review', $tree->planting()->firstOrFail()), ['decision' => 'approved', 'submission_version' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($partner)->post(route('partner.monitoring.store', $tree), ['observed_at' => $today, 'height' => 25, 'health' => 'healthy', 'care_note' => 'Pohon tumbuh sehat', 'photo' => UploadedFile::fake()->image('monitoring.png')])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($donor)->get('/trees')->assertOk()->assertDontSee('Pohon tumbuh sehat');
        $this->actingAs($admin)->post(route('admin.monitorings.review', $tree->monitorings()->firstOrFail()), ['decision' => 'approved', 'submission_version' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($donor)->get('/trees')->assertOk()->assertSee('Pohon tumbuh sehat');
        $this->getJson('/map/data')->assertOk()->assertJsonPath('0.code', $tree->code);
        $this->get(route('orders.certificate', $order))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }
}
