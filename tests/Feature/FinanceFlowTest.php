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

    private function pendingFunds(): \App\Models\Disbursement
    {
        $work = $this->paidWork();
        $work->update(['allocated_amount' => 10000]);

        return app(\App\Services\DisbursementService::class)->submit($work, TerminRule::where('milestone', 'paid')->firstOrFail());
    }

    public function test_partner_can_claim_only_eligible_terms_and_locks_allocation(): void
    {
        $this->seed();
        $work = $this->paidWork();
        $work->update(['allocated_amount' => 10000]);
        $this->actingAs($work->partner->user)->get('/partner/funds')->assertOk();
        $data = ['work_order_id' => $work->id, 'termin_rule_id' => TerminRule::where('milestone', 'planted')->value('id')];
        $this->postJson('/partner/funds', $data)->assertUnprocessable();
        $data['termin_rule_id'] = TerminRule::where('milestone', 'paid')->value('id');
        $this->post('/partner/funds', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->postJson('/partner/funds', $data)->assertUnprocessable();
        $this->actingAs(User::where('role', 'admin')->firstOrFail())->postJson('/admin/allocations/'.$work->id, ['allocated_amount' => 9000])->assertUnprocessable();
    }

    public function test_finance_review_rejects_stale_submission(): void
    {
        $this->seed();
        $fund = $this->pendingFunds();
        $url = '/admin/disbursements/'.$fund->id.'/review';
        $this->actingAs(User::where('role', 'admin')->firstOrFail());
        $this->post($url, ['decision' => 'approved', 'submission_version' => 2])->assertConflict();
        $this->post($url, ['decision' => 'approved', 'submission_version' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('approved', $fund->fresh()->status);
    }

    public function test_transfer_requires_approval_correct_amount_and_private_proof(): void
    {
        $this->seed();
        \Illuminate\Support\Facades\Storage::fake('local');
        $fund = $this->pendingFunds();
        $url = '/admin/disbursements/'.$fund->id.'/transfer';
        $data = ['amount' => $fund->amount, 'transferred_at' => now()->toDateString(), 'proof' => \Illuminate\Http\UploadedFile::fake()->image('bukti.png')];
        $this->actingAs(User::where('role', 'admin')->firstOrFail())->post($url, $data)->assertConflict();
        $fund->update(['status' => 'approved']);
        $this->post($url, array_replace($data, ['amount' => 1]))->assertUnprocessable();
        $this->post($url, $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->post($url, $data)->assertConflict();
        $this->assertSame('paid', $fund->fresh()->status);
        $file = route('files', ['type' => 'transfer', 'id' => $fund->id]);
        $this->actingAs($fund->workOrder->partner->user)->get($file)->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'partner']))->get($file)->assertForbidden();
    }

    public function test_report_totals_only_include_paid_orders_and_recorded_transfers(): void
    {
        $this->seed();
        $fund = $this->pendingFunds();
        $this->actingAs(User::where('role', 'admin')->firstOrFail())
            ->get('/admin/reports')->assertOk()->assertViewHas('transferred', fn ($amount) => (int) $amount === 0);
        $fund->update(['status' => 'paid']);
        $this->get('/admin/reports')->assertOk()->assertViewHas('transferred', fn ($amount) => (int) $amount === (int) $fund->amount);
        $response = $this->get('/admin/reports/export')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString($fund->workOrder->item->adoption->reference, $response->streamedContent());
    }

    public function test_three_terms_match_paid_funds_dashboard_reports_and_export(): void
    {
        $this->seed();
        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Mail::fake();
        $work = $this->paidWork();
        $work->update(['allocated_amount' => 10000]);
        $tree = $work->item->trees()->firstOrFail();
        $tree->planting()->create(['planted_at' => now()->toDateString(), 'latitude' => -7, 'longitude' => 112, 'photo' => 'simulasi.png', 'status' => 'approved']);
        $tree->monitorings()->create(['observed_at' => now()->toDateString(), 'height' => 25, 'health' => 'healthy', 'care_note' => 'Sehat', 'photo' => 'simulasi.png', 'status' => 'approved']);
        $admin = User::where('role', 'admin')->firstOrFail();
        $partner = $work->partner->user;
        foreach (TerminRule::orderBy('id')->get() as $rule) {
            $this->actingAs($partner)->post('/partner/funds', ['work_order_id' => $work->id, 'termin_rule_id' => $rule->id])->assertRedirect()->assertSessionHasNoErrors();
            $fund = $work->disbursements()->where('termin_rule_id', $rule->id)->firstOrFail();
            $this->actingAs($admin)->post('/admin/disbursements/'.$fund->id.'/review', ['decision' => 'approved', 'submission_version' => 1])->assertRedirect()->assertSessionHasNoErrors();
            $this->post('/admin/disbursements/'.$fund->id.'/transfer', ['amount' => $fund->amount, 'transferred_at' => now()->toDateString(), 'proof' => \Illuminate\Http\UploadedFile::fake()->image('bukti.png')])->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->get('/admin/reports')->assertOk()->assertViewHas('transferred', fn ($value) => (int) $value === 10000);
        $csv = $this->get('/admin/reports/export')->assertOk()->streamedContent();
        $this->assertStringContainsString('Dana disalurkan IDR', $csv);
        $this->assertMatchesRegularExpression('/,10000\\R/', $csv);
        $this->actingAs($partner)->get('/dashboard')->assertOk()->assertSee('Rp 10.000');
    }
}
