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

    public function test_import_creates_stock_onts_and_enriches_existing_ones(): void
    {
        (new SettingService)->set('genieacs', 'base_url', 'http://acs.local:7557');
        $existing = Asset::factory()->create(['serial_number' => 'ZTEG0001', 'brand' => null, 'genieacs_device_id' => null]);
        Http::fake(['*' => Http::response([
            ['_id' => 'dev-1', '_deviceId' => ['_SerialNumber' => 'ZTEG0001', '_Manufacturer' => 'ZTE', '_ProductClass' => 'F660'], '_lastInform' => now()->toIso8601String()],
            ['_id' => 'dev-2', '_deviceId' => ['_SerialNumber' => 'ZTEG0002', '_Manufacturer' => 'ZTE', '_ProductClass' => 'F609'], '_lastInform' => now()->toIso8601String()],
            ['_id' => 'dev-3', '_deviceId' => []],
            ['_id' => 'dev-4', '_deviceId' => ['_SerialNumber' => 'abcDEF1234', '_Manufacturer' => 'DISCOVERYSERVICE', '_ProductClass' => 'DISCOVERYSERVICE']],
            ['_id' => 'dev-5', '_deviceId' => ['_SerialNumber' => 'POC1297294418', '_Manufacturer' => 'PoC', '_ProductClass' => 'PoCLab']],
        ])]);

        $this->actingAs($this->admin())->post('/assets/import-genieacs')->assertRedirect();
        $this->actingAs($this->admin())->post('/assets/import-genieacs')->assertRedirect();

        $this->assertSame(2, Asset::count());
        $this->assertSame('dev-1', $existing->fresh()->genieacs_device_id);
        $this->assertSame('ZTE', $existing->fresh()->brand);
        $this->assertSame(AssetStatus::InStock, Asset::where('serial_number', 'ZTEG0002')->first()->status);
    }

    public function test_import_reports_acs_failure_without_crashing(): void
    {
        (new SettingService)->set('genieacs', 'base_url', 'http://acs.local:7557');
        Http::fake(['*' => Http::response('down', 502)]);

        $this->actingAs($this->admin())->post('/assets/import-genieacs')->assertRedirect();

        $this->assertSame(0, Asset::count());
    }

    public function test_wifi_details_return_ssid_password_and_only_active_devices(): void
    {
        $asset = $this->installedOnt();
        Http::fake(['*' => Http::response([[
            '_id' => 'dev-1',
            '_lastInform' => now()->toIso8601String(),
            'InternetGatewayDevice' => ['LANDevice' => ['1' => [
                'WLANConfiguration' => ['1' => ['SSID' => ['_value' => 'RumahBudi'], 'KeyPassphrase' => ['_value' => 'rahasia123']]],
                'Hosts' => ['Host' => [
                    '_object' => true,
                    '1' => ['HostName' => ['_value' => 'iphone'], 'IPAddress' => ['_value' => '192.168.1.10'], 'MACAddress' => ['_value' => 'AA:BB:CC:DD:EE:01'], 'InterfaceType' => ['_value' => '802.11'], 'Active' => ['_value' => true]],
                    '2' => ['HostName' => ['_value' => 'tv'], 'IPAddress' => ['_value' => '192.168.1.11'], 'MACAddress' => ['_value' => 'AA:BB:CC:DD:EE:02'], 'Active' => ['_value' => false]],
                ]],
            ]]],
        ]])]);

        $this->actingAs($this->admin())->getJson("/assets/{$asset->id}/wifi-details")
            ->assertOk()
            ->assertJsonPath('ssid', 'RumahBudi')
            ->assertJsonPath('password', 'rahasia123')
            ->assertJsonCount(1, 'devices')
            ->assertJsonPath('devices.0.hostname', 'iphone')
            ->assertJsonPath('devices.0.mac_address', 'AA:BB:CC:DD:EE:01');
    }

    public function test_wifi_details_report_acs_failure(): void
    {
        $asset = $this->installedOnt();
        Http::fake(['*' => Http::response('down', 502)]);

        $this->actingAs($this->admin())->getJson("/assets/{$asset->id}/wifi-details")->assertStatus(422)->assertJsonStructure(['error']);
    }
}
