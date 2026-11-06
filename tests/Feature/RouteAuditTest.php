<?php

namespace Tests\Feature;

use App\Models\Adoption;
use App\Models\Offer;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\CompleteDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RouteAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_portals_details_filters_and_internal_links_render_with_complete_demo(): void
    {
        $this->withoutVite();
        Storage::fake('local');
        Mail::fake();
        Http::preventStrayRequests();
        $this->seed(CompleteDemoSeeder::class);
        $public = ['/', '/catalog', '/catalog?q=Alpukat', '/peta', '/peta/data', '/faq', '/informasi/tentang', '/informasi/cara-kerja', '/informasi/transparansi', '/informasi/privasi', '/informasi/ketentuan'];
        foreach ([...$public, '/login', '/register', '/forgot-password', '/reset-password/audit-token?email=donatur%40treefund.test'] as $url) {
            $this->checkPage($url);
        }
        foreach (User::all() as $user) {
            $this->actingAs($user);
            foreach ([...$public, '/dashboard', '/settings', '/notifications'] as $url) {
                $this->checkPage($url);
            }
            if ($user->role === 'donor') {
                foreach (['/donor/catalog', '/orders', '/orders?page=2', '/certificates', '/trees', '/map/data'] as $url) {
                    $this->checkPage($url);
                }
                foreach (Offer::where('active', true)->get() as $offer) {
                    $this->checkPage('/checkout/'.$offer->id);
                }
                foreach (Adoption::where('user_id', $user->id)->get() as $order) {
                    $this->checkPage('/orders/'.$order->id);
                    if ($order->status === 'paid') {
                        $this->get('/orders/'.$order->id.'/certificate')->assertOk()->assertHeader('content-type', 'application/pdf');
                    }
                }
            } elseif ($user->role === 'partner') {
                foreach (['/partner/partnership', '/partner/plots', '/partner/work-orders', '/partner/monitoring', '/partner/funds', '/partner/members'] as $url) {
                    $this->checkPage($url);
                }
                foreach (WorkOrder::whereHas('partner', fn ($q) => $q->where('user_id', $user->id))->get() as $work) {
                    $this->checkPage('/partner/work-orders/'.$work->id);
                }
            } else {
                foreach (['/admin/catalog', '/admin/reports', '/admin/settings', '/admin/orders', '/admin/reports/export'] as $url) {
                    $this->checkPage($url);
                }
                if ($user->can_manage_finance) {
                    $this->checkPage('/admin/disbursements');
                    $this->checkPage('/admin/disbursements?page=2');
                } else {
                    $this->get('/admin/disbursements')->assertForbidden();
                }
                foreach (['partners', 'plots', 'plantings', 'monitorings'] as $type) {
                    foreach (['', '?status=pending', '?status=approved', '?status=revision', '?page=2'] as $filter) {
                        $this->checkPage('/admin/'.$type.$filter);
                    }
                }
                foreach (WorkOrder::all() as $work) {
                    $this->checkPage('/admin/plantings?work_order_id='.$work->id);
                    $this->checkPage('/admin/monitorings?work_order_id='.$work->id);
                }
            }
        }
        Mail::assertNothingOutgoing();
        Http::assertNothingSent();
    }

    private function checkPage(string $url): void
    {
        $response = $this->get($url)->assertOk();
        if (! str_contains($response->headers->get('Content-Type', ''), 'text/html')) {
            return;
        }
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
        foreach ($document->getElementsByTagName('a') as $link) {
            $href = html_entity_decode($link->getAttribute('href'));
            $path = parse_url($href, PHP_URL_PATH);
            $host = parse_url($href, PHP_URL_HOST);
            if (! $path || str_starts_with($href, '#') || ($host && $host !== parse_url(url('/'), PHP_URL_HOST)) || preg_match('/^(mailto|tel|javascript):/i', $href)) {
                continue;
            }
            if (str_starts_with($path, '/assets/') || str_starts_with($path, '/storage/')) {
                $this->assertFileExists(public_path(ltrim($path, '/')), $url.' links to '.$href);

                continue;
            }
            $route = Route::getRoutes()->match(Request::create($path, 'GET'));
            $this->assertContains('GET', $route->methods(), $url.' links to '.$href);
            if (str_starts_with($path, '/files/')) {
                $this->get($href)->assertOk();
            }
        }
        foreach ($document->getElementsByTagName('form') as $form) {
            $method = strtoupper($form->getAttribute('method') ?: 'GET');
            if ($method === 'DIALOG') {
                continue;
            }
            foreach ($form->getElementsByTagName('input') as $input) {
                if ($input->getAttribute('name') === '_method') {
                    $method = strtoupper($input->getAttribute('value'));
                }
            }
            $action = $form->getAttribute('action') ?: $url;
            $route = Route::getRoutes()->match(Request::create($action, $method));
            $this->assertContains($method, $route->methods(), $url.' form '.$action);
        }
    }
}
