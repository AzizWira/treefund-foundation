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
}
