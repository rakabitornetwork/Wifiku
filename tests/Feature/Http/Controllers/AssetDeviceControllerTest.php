<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\Customer;
use App\Services\Settings\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class AssetDeviceControllerTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    private function installedOnt(): Asset
    {
        (new SettingService)->set('genieacs', 'base_url', 'http://acs.local:7557');

        return Asset::factory()->for(Customer::factory())->create(['type' => AssetType::Ont, 'status' => AssetStatus::Installed, 'genieacs_device_id' => 'dev-1']);
    }

    public function test_refresh_rx_power_stores_latest_reading(): void
    {
        $asset = $this->installedOnt();
        Http::fake(['*' => Http::response([['_id' => 'dev-1', 'VirtualParameters' => ['RXPower' => ['_value' => '-23.1']], '_lastInform' => now()->toIso8601String()]])]);

        $this->actingAs($this->admin())->post("/assets/{$asset->id}/rx-power")->assertRedirect();

        $this->assertSame('-23.10', $asset->fresh()->last_rx_power);
        $this->assertNotNull($asset->fresh()->last_seen_at);
    }

    public function test_acs_failure_is_reported_without_crashing(): void
    {
        $asset = $this->installedOnt();
        Http::fake(['*' => Http::response('down', 502)]);

        $this->actingAs($this->admin())->post("/assets/{$asset->id}/rx-power")->assertRedirect();

        $this->assertNull($asset->fresh()->last_rx_power);
    }

    public function test_change_wifi_validates_wpa_password_length(): void
    {
        $asset = $this->installedOnt();

        $this->actingAs($this->admin())->post("/assets/{$asset->id}/wifi", ['ssid' => 'Rumah', 'password' => 'pendek'])->assertSessionHasErrors('password');

        Http::assertNothingSent();
    }

    public function test_change_wifi_sends_task_to_acs(): void
    {
        $asset = $this->installedOnt();
        Http::fake(['*' => Http::response([], 200)]);

        $this->actingAs($this->admin())->post("/assets/{$asset->id}/wifi", ['ssid' => 'Rumah', 'password' => 'password123'])->assertSessionDoesntHaveErrors();

        Http::assertSentCount(1);
    }

    public function test_only_installed_onts_can_be_managed(): void
    {
        $stock = Asset::factory()->create(['status' => AssetStatus::InStock]);

        $this->actingAs($this->admin())->post("/assets/{$stock->id}/rx-power")->assertStatus(422);
    }
}
