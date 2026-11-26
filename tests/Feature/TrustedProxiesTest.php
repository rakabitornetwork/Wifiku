<?php

namespace Tests\Feature;

use App\Support\TrustedProxies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxiesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('/uji-ip', fn (Request $request) => response()->json(['ip' => $request->ip(), 'secure' => $request->isSecure()]));
    }

    private function visit(string $remote, array $headers = [])
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $remote])->withHeaders($headers)->getJson('/uji-ip');
    }

    public function test_resolver_understands_keywords_lists_and_empty_values(): void
    {
        $this->assertNull(TrustedProxies::resolve(null));
        $this->assertNull(TrustedProxies::resolve('  , '));
        $this->assertSame('*', TrustedProxies::resolve('cloudflare, *'));
        $this->assertSame(['10.1.2.3', '192.0.2.0/24'], TrustedProxies::resolve('10.1.2.3, 192.0.2.0/24'));
        $this->assertContains('173.245.48.0/20', TrustedProxies::resolve('Cloudflare'));
        $this->assertContains('2400:cb00::/32', TrustedProxies::resolve('cloudflare'));
        $this->assertContains('127.0.0.0/8', TrustedProxies::resolve('cloudflare,private'));
        $this->assertSame(count(array_unique(TrustedProxies::resolve('cloudflare,cloudflare'))), count(TrustedProxies::resolve('cloudflare,cloudflare')));
    }

    public function test_without_trusted_proxies_forwarded_headers_are_ignored(): void
    {
        config(['trustedproxy.proxies' => null]);

        $this->visit('173.245.48.5', ['X-Forwarded-For' => '203.0.113.9'])->assertJson(['ip' => '173.245.48.5']);
    }

    public function test_the_real_visitor_ip_is_read_when_the_request_comes_from_cloudflare(): void
    {
        config(['trustedproxy.proxies' => TrustedProxies::resolve('cloudflare')]);

        $this->visit('173.245.48.5', ['X-Forwarded-For' => '203.0.113.9'])->assertJson(['ip' => '203.0.113.9']);
    }

    public function test_a_spoofed_header_from_an_untrusted_sender_is_ignored(): void
    {
        config(['trustedproxy.proxies' => TrustedProxies::resolve('cloudflare')]);

        $this->visit('198.51.100.77', ['X-Forwarded-For' => '203.0.113.9'])->assertJson(['ip' => '198.51.100.77']);
    }

    public function test_cloudflare_followed_by_a_local_proxy_still_resolves_the_visitor(): void
    {
        config(['trustedproxy.proxies' => TrustedProxies::resolve('cloudflare,private')]);

        $this->visit('127.0.0.1', ['X-Forwarded-For' => '203.0.113.9, 173.245.48.5'])->assertJson(['ip' => '203.0.113.9']);
    }

    public function test_the_login_throttle_is_per_visitor_once_proxies_are_trusted(): void
    {
        config(['trustedproxy.proxies' => TrustedProxies::resolve('cloudflare')]);

        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '173.245.48.5'])->withHeaders(['X-Forwarded-For' => '203.0.113.9'])->post('/login', ['email' => 'x@example.com', 'password' => 'salah']);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '173.245.48.5'])->withHeaders(['X-Forwarded-For' => '203.0.113.9'])->post('/login', ['email' => 'x@example.com', 'password' => 'salah'])->assertStatus(429);
        $this->withServerVariables(['REMOTE_ADDR' => '173.245.48.5'])->withHeaders(['X-Forwarded-For' => '203.0.113.50'])->post('/login', ['email' => 'x@example.com', 'password' => 'salah'])->assertSessionHasErrors();
    }
}
