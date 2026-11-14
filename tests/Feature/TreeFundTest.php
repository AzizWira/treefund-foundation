<?php

namespace Tests\Feature;

use App\Models\Adoption;
use App\Models\Certificate;
use App\Models\Disbursement;
use App\Models\Member;
use App\Models\Monitoring;
use App\Models\Offer;
use App\Models\Partner;
use App\Models\Planting;
use App\Models\TerminRule;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\AdoptionService;
use App\Services\DisbursementService;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TreeFundTest extends TestCase
{
    use RefreshDatabase;

    private User $donor;

    private User $farmer;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Mail::fake();
        $this->seed(DemoSeeder::class);
        $this->donor = User::where('role', 'donor')->firstOrFail();
        $this->farmer = User::where('role', 'partner')->firstOrFail();
        $this->admin = User::where('role', 'admin')->firstOrFail();
        config(['treefund.midtrans.server_key' => 'SB-Mid-server-test-only', 'treefund.midtrans.client_key' => 'SB-Mid-client-test-only']);
        Http::preventStrayRequests();
    }

    private function order(int $quantity = 2): Adoption
    {
        return app(AdoptionService::class)->checkout($this->donor, Offer::first(), $quantity, 'Nama Sertifikat');
    }

    public function test_public_navigation_stays_in_website_layout_for_every_role(): void
    {
        $pages = ['/', '/catalog', '/peta', '/faq', '/informasi/tentang', '/informasi/cara-kerja', '/informasi/transparansi', '/informasi/privasi', '/informasi/ketentuan'];
        foreach ($pages as $url) {
            $this->get($url)->assertOk()->assertSee('Navigasi website')->assertDontSee('id="sidebar"', false);
        }
        foreach ([$this->donor, $this->farmer, $this->admin] as $user) {
            $this->actingAs($user);
            foreach ($pages as $url) {
                $this->get($url)->assertOk()->assertSee('Navigasi website')->assertDontSee('id="sidebar"', false);
            }
        }
    }

    public function test_landing_uses_published_catalog_prices_instead_of_static_campaigns(): void
    {
        $offer = Offer::latest('id')->first();
        $offer->update(['price' => 98765]);
        $this->get('/')->assertOk()->assertSee('98.765')->assertSee($offer->plot->name)->assertDontSee('Restorasi DAS Citarum Hulu');
    }

    public function test_work_order_progress_follows_verified_evidence(): void
    {
        $work = WorkOrder::firstOrFail();
        $this->assertSame('pending', $work->progress_status);
        foreach ($work->item->trees as $tree) {
            $tree->planting->update(['status' => 'approved']);
        }
        $work = $work->fresh();
        $this->assertSame('waiting', $work->status);
        $this->assertSame('planted', $work->progress_status);
        $this->actingAs($this->farmer)->get('/partner/work-orders/'.$work->id)->assertOk()->assertSee('Telah ditanam');
        $work->item->trees->first()->planting->update(['status' => 'revision']);
        $this->assertSame('revision', $work->fresh()->progress_status);
    }

    public function test_catalog_species_filter_follows_published_offers(): void
    {
        $offer = Offer::firstOrFail();
        $offer->update(['species' => 'Durian Montong']);
        $hidden = Offer::where('id', '!=', $offer->id)->firstOrFail();
        $hidden->update(['species' => 'Bibit Rahasia', 'active' => false]);
        foreach (['/catalog', '/donor/catalog'] as $path) {
            $this->actingAs($this->donor)->get($path)->assertOk()->assertViewHas('speciesOptions', fn ($options): bool => $options->contains('Durian Montong') && ! $options->contains('Bibit Rahasia'));
            $this->get($path.'?species=Durian%20Montong')->assertOk()->assertViewHas('offers', fn ($offers): bool => $offers->count() === 1 && $offers->first()->id === $offer->id);
        }
    }

    public function test_public_map_only_exposes_published_land_information(): void
    {
        $this->getJson('/peta/data')->assertOk()->assertJsonCount(4)
            ->assertJsonStructure([['name', 'location', 'group', 'latitude', 'longitude', 'species', 'available']])
            ->assertDontSee($this->donor->email)->assertDontSee('TF-')->assertDontSee('bank_account');
        Offer::first()->update(['active' => false]);
        $this->getJson('/peta/data')->assertOk()->assertJsonCount(3);
    }

    public function test_certificate_and_transaction_menus_have_one_active_item(): void
    {
        foreach (['/certificates' => '/certificates', '/orders' => '/orders', '/donor/catalog?species=Alpukat' => '/donor/catalog'] as $path => $activePath) {
            $response = $this->actingAs($this->donor)->get($path)->assertOk();
            preg_match_all('/class="nav-link active" href="([^"]+)"/', $response->getContent(), $active);
            $this->assertCount(1, $active[1]);
            $this->assertSame(url($activePath), $active[1][0]);
        }
        $this->get('/orders?view=certificates')->assertRedirect('/certificates');
    }

    public function test_certificate_gallery_contains_only_own_confirmed_payments(): void
    {
        $pending = $this->order();
        $this->actingAs($this->donor)->get('/certificates')->assertOk()->assertSee('CERT-')->assertDontSee($pending->reference);
        $this->actingAs($this->farmer)->get('/certificates')->assertRedirect('/dashboard');
    }

    public function test_payment_watcher_uses_server_status_and_emits_a_machine_readable_result(): void
    {
        $order = $this->order(1);
        $order->update(['snap_token' => 'issued-sandbox-token']);
        $this->actingAs($this->donor)->get('/orders/'.$order->id)->assertOk()->assertSee('data-payment-watch', false)->assertSee('data-payment-issued="true"', false);
        Http::fake([config('treefund.midtrans.api_url').'/*' => Http::response($this->payment($order))]);
        $this->postJson('/orders/'.$order->id.'/sync')->assertOk()->assertJsonPath('status', 'paid');
        $this->postJson('/orders/'.$order->id.'/sync')->assertOk()->assertJsonPath('status', 'paid');
        $this->assertSame(1, Certificate::where('adoption_id', $order->id)->count());
        $this->get('/orders/'.$order->id)->assertOk()->assertDontSee('data-payment-watch', false)->assertSee('Unduh E-Sertifikat');
    }

    public function test_unavailable_payment_api_returns_json_for_automatic_retry(): void
    {
        $order = $this->order();
        Http::fake([config('treefund.midtrans.api_url').'/*' => Http::response([], 503)]);
        $this->actingAs($this->donor)->postJson('/orders/'.$order->id.'/sync')->assertStatus(503)->assertJsonStructure(['message']);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_mixed_midtrans_environments_cannot_create_a_transaction(): void
    {
        $order = $this->order();
        config(['treefund.midtrans.server_key' => 'Mid-server-production-example']);
        $this->actingAs($this->donor)->postJson('/orders/'.$order->id.'/pay')->assertUnprocessable()->assertJsonValidationErrors('payment');
        Http::assertNothingSent();
        config(['treefund.midtrans.server_key' => 'SB-Mid-server-test-only', 'treefund.midtrans.client_key' => 'Mid-client-production-example']);
        $this->postJson('/orders/'.$order->id.'/pay')->assertUnprocessable()->assertJsonValidationErrors('payment');
        Http::assertNothingSent();
    }

    public function test_partner_can_edit_own_member_but_not_another_group_member(): void
    {
        $member = Member::first();
        $this->actingAs($this->farmer)->put('/partner/members/'.$member->id, ['name' => 'Nama Diperbarui', 'position' => 'Sekretaris', 'phone' => '081234567890'])->assertRedirect();
        $this->assertDatabaseHas('members', ['id' => $member->id, 'name' => 'Nama Diperbarui']);
        $outsider = User::factory()->create();
        $outsider->forceFill(['role' => 'partner'])->save();
        $this->actingAs($outsider)->put('/partner/members/'.$member->id, ['name' => 'Tidak boleh', 'position' => 'Ketua'])->assertForbidden();
    }

    public function test_new_accounts_render_empty_states_without_demo_dependencies(): void
    {
        $donor = User::factory()->create();
        foreach (['/dashboard', '/donor/catalog', '/certificates', '/orders', '/trees', '/notifications', '/settings'] as $path) {
            $this->actingAs($donor)->get($path)->assertOk();
        }
        $partner = User::factory()->create();
        $partner->forceFill(['role' => 'partner'])->save();
        foreach (['/dashboard', '/partner/partnership', '/partner/plots', '/partner/work-orders', '/partner/monitoring', '/partner/funds', '/partner/members'] as $path) {
            $this->actingAs($partner)->get($path)->assertOk();
        }
    }

    public function test_admin_without_finance_access_does_not_receive_a_finance_navigation_link(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin', 'can_manage_finance' => false])->save();
        $this->actingAs($admin)->get('/dashboard')->assertOk()->assertDontSee('href="'.url('/admin/disbursements').'"', false);
    }

    public function test_payment_to_verified_planting_monitoring_and_manual_disbursement_flow(): void
    {
        Storage::fake('local');
        $order = $this->order(1);
        Http::fake([config('treefund.midtrans.api_url').'/*' => Http::response($this->payment($order))]);
        $this->actingAs($this->donor)->postJson('/orders/'.$order->id.'/sync')->assertOk()->assertJsonPath('status', 'paid');
        $work = WorkOrder::whereHas('item', fn ($query) => $query->where('adoption_id', $order->id))->firstOrFail();
        $tree = $work->item->trees->firstOrFail();
        $this->actingAs($this->farmer)->get('/partner/work-orders/'.$work->id)->assertOk();
        $this->post('/partner/trees/'.$tree->id.'/planting', ['planted_at' => today()->toDateString(), 'latitude' => -8.12, 'longitude' => 114.28, 'photo' => UploadedFile::fake()->image('planting.jpg')])->assertRedirect();
        $planting = Planting::where('tree_id', $tree->id)->firstOrFail();
        $this->actingAs($this->admin)->post('/admin/plantings/'.$planting->id.'/review', ['decision' => 'approved'])->assertRedirect();
        $this->actingAs($this->donor)->getJson('/map/data')->assertOk()->assertJsonFragment(['code' => $tree->code]);
        $this->actingAs($this->farmer)->post('/partner/trees/'.$tree->id.'/monitoring', ['observed_at' => today()->toDateString(), 'height' => 50, 'health' => 'healthy', 'care_note' => 'Pohon tumbuh sehat.', 'photo' => UploadedFile::fake()->image('monitoring.jpg')])->assertRedirect();
        $monitoring = Monitoring::where('tree_id', $tree->id)->firstOrFail();
        $this->actingAs($this->admin)->post('/admin/monitorings/'.$monitoring->id.'/review', ['decision' => 'approved'])->assertRedirect();
        $this->post('/admin/allocations/'.$work->id, ['allocated_amount' => 50000])->assertRedirect();
        $rule = TerminRule::where('milestone', 'monitored')->firstOrFail();
        $this->actingAs($this->farmer)->post('/partner/funds', ['work_order_id' => $work->id, 'termin_rule_id' => $rule->id])->assertRedirect();
        $fund = Disbursement::where('work_order_id', $work->id)->firstOrFail();
        $this->actingAs($this->admin)->post('/admin/disbursements/'.$fund->id.'/review', ['decision' => 'approved'])->assertRedirect();
        $this->post('/admin/disbursements/'.$fund->id.'/transfer', ['amount' => $fund->amount, 'transferred_at' => today()->toDateString(), 'proof' => UploadedFile::fake()->image('transfer.jpg')])->assertRedirect();
        $this->assertDatabaseHas('disbursements', ['id' => $fund->id, 'status' => 'paid']);
        $this->actingAs($this->donor)->get('/trees?tree='.$tree->id)->assertOk()->assertSee('Pohon tumbuh sehat.');
        $this->get('/orders/'.$order->id.'/certificate')->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    private function payment(Adoption $order, string $status = 'settlement'): array
    {
        return ['order_id' => $order->reference, 'status_code' => '200', 'gross_amount' => $order->total.'.00', 'transaction_status' => $status, 'fraud_status' => 'accept', 'transaction_id' => 'sandbox-test-'.$order->id];
    }

    private function signed(array $payment): array
    {
        $payment['signature_key'] = hash('sha512', $payment['order_id'].$payment['status_code'].$payment['gross_amount'].config('treefund.midtrans.server_key'));

        return $payment;
    }

    public function test_all_public_pages_and_role_pages_render(): void
    {
        foreach (['/', '/catalog', '/login', '/register', '/informasi/tentang'] as $url) {
            $this->get($url)->assertOk();
        }
        foreach ([
            [$this->donor, ['/dashboard', '/catalog', '/orders', '/trees', '/settings', '/notifications']],
            [$this->farmer, ['/dashboard', '/partner/partnership', '/partner/plots', '/partner/work-orders', '/partner/monitoring', '/partner/funds', '/partner/members', '/settings']],
            [$this->admin, ['/dashboard', '/admin/partners', '/admin/plots', '/admin/plantings', '/admin/monitorings', '/admin/catalog', '/admin/disbursements', '/admin/reports', '/admin/settings']],
        ] as [$user,$pages]) {
            $this->actingAs($user);
            foreach ($pages as $url) {
                $this->get($url)->assertOk();
            }
        }
    }

    public function test_new_account_cannot_request_admin_role(): void
    {
        $this->post('/register', ['name' => 'Attacker', 'email' => 'new@example.test', 'password' => 'LongPassword123!', 'password_confirmation' => 'LongPassword123!', 'role' => 'admin'])->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'new@example.test']);
    }

    public function test_login_lands_each_role_on_its_dashboard_despite_a_previous_portal_url(): void
    {
        foreach ([$this->donor, $this->farmer, $this->admin] as $user) {
            $this->withSession(['url.intended' => '/admin/partners'])
                ->post('/login', ['email' => $user->email, 'password' => 'TreeFund!2026'])
                ->assertRedirect('/dashboard')
                ->assertSessionMissing('url.intended');
            $this->assertAuthenticatedAs($user);
            $this->get('/dashboard')->assertOk();
            $this->post('/logout');
        }
    }

    public function test_catalog_only_offers_checkout_to_donors(): void
    {
        $checkout = route('checkout', Offer::first());
        foreach ([$this->admin, $this->farmer] as $user) {
            $this->actingAs($user)->get('/catalog')->assertOk()->assertDontSee($checkout, false)->assertSee('Adopsi melalui akun Donatur');
        }
        $this->actingAs($this->donor)->get('/catalog')->assertOk()->assertSee($checkout, false);
    }

    public function test_logout_invalidates_authenticated_session(): void
    {
        $this->actingAs($this->donor)->post('/logout')->assertRedirect('/');
        $this->assertGuest();
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_roles_cannot_access_each_others_portals(): void
    {
        $this->actingAs($this->donor)->get('/admin/plots')->assertRedirect('/dashboard');
        $this->get('/partner/work-orders')->assertRedirect('/dashboard');
        $this->actingAs($this->farmer)->get('/admin/disbursements')->assertRedirect('/dashboard');
        $this->get('/checkout/'.Offer::first()->id)->assertRedirect('/dashboard');
        $this->actingAs($this->admin)->get('/partner/plots')->assertRedirect('/dashboard');
    }

    public function test_wrong_role_json_and_write_requests_still_return_forbidden(): void
    {
        $this->actingAs($this->donor)->getJson('/admin/plots')->assertForbidden();
        $this->post('/admin/catalog', ['species' => 'Tidak boleh tersimpan'])->assertForbidden();
        $this->assertDatabaseMissing('offers', ['species' => 'Tidak boleh tersimpan']);
    }

    public function test_dashboard_renders_role_redirect_notice_and_success_notice(): void
    {
        $this->actingAs($this->donor)
            ->withSession(['status' => 'Kembali ke dashboard akun Anda.', 'success' => 'Data berhasil disimpan.'])
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Kembali ke dashboard akun Anda.')
            ->assertSee('Data berhasil disimpan.');

        $this->followingRedirects()->get('/admin/plots')
            ->assertOk()
            ->assertSee('Halaman tersebut tidak tersedia untuk peran akun Anda.');
    }

    public function test_checkout_uses_catalog_price_and_reserves_capacity(): void
    {
        $offer = Offer::first();
        $before = $offer->plot->reserved;
        $this->actingAs($this->donor)->post('/orders', ['offer_id' => $offer->id, 'quantity' => 2, 'certificate_name' => 'Dwi', 'agree' => 1, 'total' => 1])->assertRedirect();
        $order = Adoption::latest('id')->first();
        $this->assertSame($offer->price * 2, $order->total);
        $this->assertSame($before + 2, $offer->plot->fresh()->reserved);
        $this->assertSame('pending', $order->status);
    }

    public function test_checkout_cannot_exceed_shared_plot_quota(): void
    {
        $offer = Offer::first();
        $available = $offer->plot->available;
        $this->order($available);
        try {
            $this->order(1);
            $this->fail('Overselling should be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('quantity', $e->errors());
        }
        $plot = $offer->plot->fresh();
        $this->assertSame($plot->capacity, $plot->confirmed + $plot->reserved);
    }

    public function test_hidden_or_unapproved_offer_cannot_be_checked_out(): void
    {
        $offer = Offer::first();
        $offer->update(['active' => false]);
        $this->expectException(ValidationException::class);
        app(AdoptionService::class)->checkout($this->donor, $offer, 1, 'Test');
    }

    public function test_snap_token_is_created_on_server_and_reused(): void
    {
        $order = $this->order();
        Http::fake([config('treefund.midtrans.snap_url') => Http::response(['token' => 'sandbox-snap-token', 'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/sandbox-snap-token'])]);
        $this->actingAs($this->donor)->postJson('/orders/'.$order->id.'/pay')->assertOk()->assertJsonPath('token', 'sandbox-snap-token');
        $this->postJson('/orders/'.$order->id.'/pay')->assertOk();
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === config('treefund.midtrans.snap_url') && $request['transaction_details']['gross_amount'] === $order->total && ! str_contains($request->url(), 'app.midtrans.com'));
    }

    public function test_no_keys_produces_actionable_validation_error(): void
    {
        config(['treefund.midtrans.server_key' => '']);
        $order = $this->order();
        $this->actingAs($this->donor)->postJson('/orders/'.$order->id.'/pay')->assertUnprocessable()->assertJsonValidationErrors('payment');
        $this->assertSame('pending', $order->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_invalid_webhook_signature_does_not_change_order(): void
    {
        $order = $this->order();
        $payment = $this->payment($order);
        $payment['signature_key'] = str_repeat('0', 128);
        $this->postJson('/payments/midtrans/notification', $payment)->assertForbidden();
        $this->assertSame('pending', $order->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_replayed_valid_webhook_issues_each_tree_and_certificate_once(): void
    {
        $order = $this->order();
        $payment = $this->payment($order);
        Http::fake([config('treefund.midtrans.api_url').'/*' => Http::response($payment)]);
        $this->postJson('/payments/midtrans/notification', $this->signed($payment))->assertOk();
        $this->postJson('/payments/midtrans/notification', $this->signed($payment))->assertOk();
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(1, Certificate::where('adoption_id', $order->id)->count());
        $item = $order->items->first();
        $this->assertSame(2, $item->trees()->count());
        $this->assertSame(1, WorkOrder::where('adoption_item_id', $item->id)->count());
        $this->assertSame(0, $item->offer->plot->fresh()->reserved);
    }

    public function test_webhook_trusts_authenticated_status_not_unsigned_status_field(): void
    {
        $order = $this->order();
        $payment = $this->payment($order);
        $api = $this->payment($order, 'pending');
        Http::fake([config('treefund.midtrans.api_url').'/*' => Http::response($api)]);
        $this->postJson('/payments/midtrans/notification', $this->signed($payment))->assertOk();
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertNull($order->fresh()->certificate);
    }

    public function test_wrong_amount_is_rejected_even_with_valid_signature(): void
    {
        $order = $this->order();
        $payment = $this->payment($order);
        $payment['gross_amount'] = '1.00';
        $this->postJson('/payments/midtrans/notification', $this->signed($payment))->assertUnprocessable();
        $this->assertSame('pending', $order->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_expired_payment_releases_quota_once(): void
    {
        $order = $this->order();
        $payment = $this->payment($order, 'expire');
        Http::fake([config('treefund.midtrans.api_url').'/*' => Http::response($payment)]);
        $this->postJson('/payments/midtrans/notification', $this->signed($payment))->assertOk();
        $this->postJson('/payments/midtrans/notification', $this->signed($payment))->assertOk();
        $this->assertSame('expired', $order->fresh()->status);
        $this->assertSame(0, $order->items->first()->offer->plot->fresh()->reserved);
    }

    public function test_paid_order_cannot_be_downgraded_by_old_expiry_notification(): void
    {
        $order = $this->order();
        $service = app(AdoptionService::class);
        $service->applyProviderStatus($order, $this->payment($order));
        $confirmed = $order->items->first()->offer->plot->fresh()->confirmed;
        $service->applyProviderStatus($order, $this->payment($order, 'expire'));
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame($confirmed, $order->items->first()->offer->plot->fresh()->confirmed);
    }

    public function test_donor_cannot_read_other_users_order_or_pending_photo(): void
    {
        $order = Adoption::first();
        $other = User::factory()->create();
        $this->actingAs($other)->get('/orders/'.$order->id)->assertForbidden();
        $pending = Planting::where('status', 'pending')->first();
        $this->actingAs($this->donor)->get('/files/planting/'.$pending->id)->assertForbidden();
        $this->get('/files/legal/'.Partner::first()->id)->assertForbidden();
    }

    public function test_map_exposes_only_approved_coordinates_of_owned_trees(): void
    {
        $rows = $this->actingAs($this->donor)->getJson('/map/data')->assertOk()->json();
        $this->assertCount(2, $rows);
        $this->assertArrayNotHasKey('bank_account', $rows[0]);
        $this->assertArrayNotHasKey('document', $rows[0]);
        $this->actingAs(User::factory()->create())->getJson('/map/data')->assertOk()->assertExactJson([]);
    }

    public function test_partner_can_submit_photo_but_not_on_another_partners_tree(): void
    {
        Storage::fake('local');
        $order = $this->order(1);
        app(AdoptionService::class)->applyProviderStatus($order, $this->payment($order));
        $tree = $order->items->first()->trees->first();
        $data = ['planted_at' => today()->toDateString(), 'latitude' => -8.128, 'longitude' => 114.28, 'photo' => UploadedFile::fake()->image('tree.jpg')];
        $this->actingAs($this->farmer)->post('/partner/trees/'.$tree->id.'/planting', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('pending', $tree->fresh()->planting->status);
        $other = User::factory()->create();
        $other->forceFill(['role' => 'partner'])->save();
        $this->actingAs($other)->post('/partner/trees/'.$tree->id.'/planting', $data)->assertForbidden();
    }

    public function test_admin_approval_reveals_planting_to_donor_and_records_audit(): void
    {
        $pending = Planting::where('status', 'pending')->first();
        $this->actingAs($this->admin)->post('/admin/plantings/'.$pending->id.'/review', ['decision' => 'approved'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('audit_logs', ['action' => 'plantings.approved', 'subject_id' => $pending->id]);
        $this->actingAs($this->donor)->get('/files/planting/'.$pending->id)->assertOk();
    }

    public function test_revision_requires_explanation(): void
    {
        $pending = Planting::where('status', 'pending')->first();
        $this->actingAs($this->admin)->post('/admin/plantings/'.$pending->id.'/review', ['decision' => 'revision'])->assertSessionHasErrors('review_note');
        $this->assertSame('pending', $pending->fresh()->status);
    }

    public function test_disbursement_requires_allocation_and_milestone(): void
    {
        $work = WorkOrder::first();
        $rule = TerminRule::where('milestone', 'paid')->first();
        $this->actingAs($this->farmer)->post('/partner/funds', ['work_order_id' => $work->id, 'termin_rule_id' => $rule->id])->assertSessionHasErrors('termin_rule_id');
        $this->actingAs($this->admin)->post('/admin/allocations/'.$work->id, ['allocated_amount' => 180000])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->farmer)->post('/partner/funds', ['work_order_id' => $work->id, 'termin_rule_id' => $rule->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('disbursements', ['work_order_id' => $work->id, 'amount' => 54000, 'status' => 'pending']);
        $this->post('/partner/funds', ['work_order_id' => $work->id, 'termin_rule_id' => TerminRule::where('milestone', 'planted')->first()->id])->assertSessionHasErrors('termin_rule_id');
    }

    public function test_evidence_links_filter_by_work_order_and_keep_status_filter(): void
    {
        $work = WorkOrder::firstOrFail();
        foreach (['plantings', 'monitorings'] as $type) {
            $this->actingAs($this->admin)->get('/admin/'.$type.'?work_order_id='.$work->id.'&status=approved')
                ->assertOk()->assertSee('Menampilkan data WO-'.$work->id)
                ->assertSee('name="work_order_id"', false)
                ->assertViewHas('records', fn ($records): bool => $records->isNotEmpty() && $records->every(fn ($record): bool => $record->tree->adoption_item_id === $work->adoption_item_id && $record->status === 'approved'));
        }
        $other = $this->order();
        app(AdoptionService::class)->applyProviderStatus($other, ['transaction_status' => 'settlement']);
        $emptyWork = $other->items()->first()->workOrder;
        $this->get('/admin/plantings?work_order_id='.$emptyWork->id)->assertOk()->assertViewHas('records', fn ($records): bool => $records->isEmpty());
        $this->get('/admin/monitorings?work_order_id=999999')->assertNotFound();
    }

    public function test_locked_termin_settings_show_explanation_and_return_validation(): void
    {
        $work = WorkOrder::firstOrFail();
        $work->update(['allocated_amount' => 180000]);
        app(DisbursementService::class)->submit($work, TerminRule::where('milestone', 'paid')->firstOrFail());
        $before = TerminRule::orderBy('id')->pluck('percentage')->all();
        $this->actingAs($this->admin)->get('/admin/settings')->assertOk()->assertSee('Aturan termin dikunci karena sudah ada pengajuan pencairan.');
        $this->from('/admin/settings')->post('/admin/settings/terms', ['percentages' => [20, 40, 40]])->assertRedirect('/admin/settings')->assertSessionHasErrors('percentages');
        $this->assertSame($before, TerminRule::orderBy('id')->pluck('percentage')->all());
    }

    public function test_locked_allocation_returns_form_error_without_changing_funds(): void
    {
        $work = WorkOrder::firstOrFail();
        $work->update(['allocated_amount' => 180000]);
        app(DisbursementService::class)->submit($work, TerminRule::where('milestone', 'paid')->firstOrFail());
        $this->actingAs($this->admin)->get('/admin/disbursements')->assertOk()->assertSee('Alokasi dikunci karena sudah ada pengajuan pencairan.');
        $this->from('/admin/disbursements')->post('/admin/allocations/'.$work->id, ['allocated_amount' => 150000])->assertRedirect('/admin/disbursements')->assertSessionHasErrors('allocated_amount');
        $this->assertSame(180000, $work->fresh()->allocated_amount);
    }

    public function test_financial_permissions_and_transfer_idempotency(): void
    {
        Storage::fake('local');
        $work = WorkOrder::first();
        $work->update(['allocated_amount' => 180000]);
        $row = app(DisbursementService::class)->submit($work, TerminRule::where('milestone', 'paid')->first());
        $limited = User::factory()->create();
        $limited->forceFill(['role' => 'admin', 'can_manage_finance' => false])->save();
        $this->actingAs($limited)->get('/admin/disbursements')->assertForbidden();
        $this->actingAs($this->admin)->post('/admin/disbursements/'.$row->id.'/review', ['decision' => 'approved'])->assertRedirect();
        $data = ['amount' => $row->amount, 'transferred_at' => today()->toDateString(), 'proof' => UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf')];
        $this->post('/admin/disbursements/'.$row->id.'/transfer', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/admin/disbursements/'.$row->id.'/transfer', $data)->assertConflict();
        $this->assertSame('paid', $row->fresh()->status);
    }

    public function test_certificate_is_downloadable_pdf_for_owner_only(): void
    {
        $order = Adoption::where('status', 'paid')->first();
        $this->actingAs($this->donor)->get('/orders/'.$order->id.'/certificate')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs(User::factory()->create())->get('/orders/'.$order->id.'/certificate')->assertForbidden();
    }
}
