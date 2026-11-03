<?php

namespace Tests\Feature;

use App\Models\TerminRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_admin_can_update_valid_term_percentages_and_audit_them(): void
    {
        $this->seed();
        $admin = User::where('role', 'admin')->firstOrFail();
        $this->actingAs($admin)->get('/admin/settings')->assertOk();
        $this->postJson('/admin/settings/terms', ['percentages' => [30, 30, 30]])->assertUnprocessable();
        $this->post('/admin/settings/terms', ['percentages' => [20, 50, 30]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame([20, 50, 30], TerminRule::orderBy('id')->pluck('percentage')->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'termin.updated']);
        $admin->forceFill(['can_manage_finance' => false])->save();
        $this->actingAs($admin)->post('/admin/settings/terms', ['percentages' => [30, 40, 30]])->assertForbidden();
    }

    private function paidWork(): \App\Models\WorkOrder
    {
        $offer = \App\Models\Offer::published()->firstOrFail();
        $service = app(\App\Services\AdoptionService::class);
        $order = $service->checkout(User::factory()->create(['role' => 'donor']), $offer, 1, 'Donatur Uji');
        $service->applyProviderStatus($order, ['transaction_status' => 'settlement']);

        return $order->items()->firstOrFail()->workOrder()->firstOrFail();
    }

    public function test_allocation_is_bounded_by_the_paid_item(): void
    {
        $this->seed();
        $work = $this->paidWork();
        $admin = User::where('role', 'admin')->firstOrFail();
        $this->actingAs($admin)->get('/admin/disbursements')->assertOk()->assertSee('WO-'.$work->id);
        $this->postJson('/admin/allocations/'.$work->id, ['allocated_amount' => $work->item->unit_price + 1])->assertUnprocessable();
        $this->post('/admin/allocations/'.$work->id, ['allocated_amount' => 10000])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals(10000, $work->fresh()->allocated_amount);
    }
}
