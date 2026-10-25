<?php

namespace Tests\Feature;

use App\Models\Adoption;
use App\Models\Offer;
use App\Models\User;
use App\Services\AdoptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
