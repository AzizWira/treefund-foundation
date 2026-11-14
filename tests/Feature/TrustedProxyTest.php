<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost:8000']);
        Route::get('/proxy-url-test', fn (Request $request) => [
            'secure' => $request->isSecure(),
            'asset' => asset('build/example.css'),
            'ip' => $request->ip(),
        ]);
    }

    public function test_local_tunnel_generates_https_asset_urls(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->withHeaders([
            'Host' => 'treefund.ngrok-free.dev',
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Port' => '443',
            'X-Forwarded-For' => '203.0.113.10',
        ])->get('http://treefund.ngrok-free.dev/proxy-url-test')->assertOk()->assertJson([
            'secure' => true,
            'asset' => 'https://treefund.ngrok-free.dev/build/example.css',
            'ip' => '203.0.113.10',
        ]);
    }

    public function test_direct_localhost_remains_http(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get('http://localhost:8000/proxy-url-test')->assertOk()->assertJsonPath('secure', false)
            ->assertJsonPath('asset', 'http://localhost:8000/build/example.css');
    }

    public function test_forwarded_headers_from_an_untrusted_remote_address_are_ignored(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.25'])->withHeaders([
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-For' => '203.0.113.10',
        ])->get('/proxy-url-test')->assertOk()->assertJson([
            'secure' => false,
            'ip' => '203.0.113.25',
        ]);
    }
}
