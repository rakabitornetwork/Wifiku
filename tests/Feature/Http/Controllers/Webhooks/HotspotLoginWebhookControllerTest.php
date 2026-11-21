<?php

namespace Tests\Feature\Http\Controllers\Webhooks;

use App\Enums\VoucherStatus;
use App\Models\HotspotProfile;
use App\Models\Router;
use App\Models\Voucher;
use App\Models\VoucherBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HotspotLoginWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private function voucher(): Voucher
    {
        $router = Router::factory()->create(['webhook_token' => 'token-router']);
        $profile = HotspotProfile::factory()->for($router)->create(['validity_minutes' => 1440]);
        $batch = VoucherBatch::factory()->for($profile)->create();

        return Voucher::factory()->for($batch)->create(['code' => 'WF1234']);
    }

    public function test_first_login_activates_voucher_and_starts_validity(): void
    {
        $this->travelTo('2026-11-21 10:00');
        $voucher = $this->voucher();

        $this->get("/webhooks/hotspot/{$voucher->router_id}/login?token=token-router&user=WF1234&mac=AA:BB:CC:DD:EE:FF")
            ->assertOk()->assertJson(['activated' => true]);

        $voucher->refresh();
        $this->assertSame(VoucherStatus::Active, $voucher->status);
        $this->assertSame('2026-11-21 10:00:00', $voucher->first_login_at->toDateTimeString());
        $this->assertSame('2026-11-22 10:00:00', $voucher->expires_at->toDateTimeString());
        $this->assertSame('AA:BB:CC:DD:EE:FF', $voucher->mac_address);
    }

    public function test_later_logins_do_not_extend_validity(): void
    {
        $this->travelTo('2026-11-21 10:00');
        $voucher = $this->voucher();
        $url = "/webhooks/hotspot/{$voucher->router_id}/login?token=token-router&user=WF1234";

        $this->get($url);
        $this->travelTo('2026-11-21 18:00');
        $this->get($url)->assertJson(['activated' => false]);

        $this->assertSame('2026-11-22 10:00:00', $voucher->fresh()->expires_at->toDateTimeString());
    }

    public function test_wrong_token_is_rejected(): void
    {
        $voucher = $this->voucher();

        $this->get("/webhooks/hotspot/{$voucher->router_id}/login?token=salah&user=WF1234")->assertForbidden();

        $this->assertSame(VoucherStatus::Unused, $voucher->fresh()->status);
    }

    public function test_voucher_code_on_another_router_is_not_activated(): void
    {
        $voucher = $this->voucher();
        $other = Router::factory()->create(['webhook_token' => 'token-lain']);

        $this->get("/webhooks/hotspot/{$other->id}/login?token=token-lain&user=WF1234")->assertOk();

        $this->assertSame(VoucherStatus::Unused, $voucher->fresh()->status);
    }

    public function test_accepts_post_from_router_without_csrf_token(): void
    {
        $voucher = $this->voucher();

        $this->post("/webhooks/hotspot/{$voucher->router_id}/login", ['token' => 'token-router', 'user' => 'WF1234'])->assertOk();

        $this->assertSame(VoucherStatus::Active, $voucher->fresh()->status);
    }
}
