<?php

namespace Tests\Feature\Services\GenieAcs;

use App\Models\Asset;
use App\Services\GenieAcs\GenieAcsException;
use App\Services\GenieAcs\GenieAcsService;
use App\Services\Settings\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GenieAcsServiceTest extends TestCase
{
    use RefreshDatabase;

    private function configure(): void
    {
        $settings = new SettingService;
        $settings->set('genieacs', 'base_url', 'http://acs.local:7557');
        $settings->set('genieacs', 'username', 'admin');
        $settings->set('genieacs', 'password', 'rahasia', encrypted: true);
    }

    private function service(): GenieAcsService
    {
        return $this->app->make(GenieAcsService::class);
    }

    public function test_finds_device_by_serial_number_and_remembers_its_id(): void
    {
        $this->configure();
        Http::fake(['acs.local:7557/devices*' => Http::sequence()
            ->push([['_id' => 'ZTE-F609-ZTEG123']])
            ->push([['_id' => 'ZTE-F609-ZTEG123', 'VirtualParameters' => ['RXPower' => ['_value' => '-21.5']], '_lastInform' => '2026-11-21T01:00:00.000Z']])]);
        $asset = Asset::factory()->create(['serial_number' => 'ZTEG123', 'genieacs_device_id' => null]);

        $status = $this->service()->readStatus($asset);

        $this->assertSame(-21.5, $status['rx_power']);
        $this->assertSame('2026-11-21T01:00:00+00:00', $status['last_inform']->toIso8601String());
        $this->assertSame('ZTE-F609-ZTEG123', $asset->fresh()->genieacs_device_id);
        Http::assertSent(fn (Request $request) => str_contains(urldecode($request->url()), '"_deviceId._SerialNumber":"ZTEG123"')
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('admin:rahasia')));
    }

    public function test_uses_the_first_configured_rx_parameter_that_has_a_value(): void
    {
        $this->configure();
        Http::fake(['*' => Http::response([[
            '_id' => 'dev-1',
            'InternetGatewayDevice' => ['WANDevice' => ['1' => ['X_ZTE-COM_WANPONInterfaceConfig' => ['RXPower' => ['_value' => -19.2]]]]],
        ]])]);

        $this->assertSame(-19.2, $this->service()->getRxPower(Asset::factory()->create(['genieacs_device_id' => 'dev-1'])));
    }

    public function test_returns_null_rx_power_when_device_does_not_report_it(): void
    {
        $this->configure();
        Http::fake(['*' => Http::response([['_id' => 'dev-1']])]);

        $this->assertNull($this->service()->getRxPower(Asset::factory()->create(['genieacs_device_id' => 'dev-1'])));
    }

    public function test_unknown_device_raises_exception(): void
    {
        $this->configure();
        Http::fake(['*' => Http::response([])]);

        $this->expectException(GenieAcsException::class);

        $this->service()->readStatus(Asset::factory()->create(['genieacs_device_id' => null]));
    }

    public function test_update_wifi_posts_set_parameter_values_task_with_connection_request(): void
    {
        $this->configure();
        Http::fake(['*' => Http::response([], 200)]);
        $asset = Asset::factory()->create(['genieacs_device_id' => 'ZTE-F609/ZTEG123']);

        $this->assertTrue($this->service()->updateWifi($asset, 'Rumah Budi', 'password123'));

        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'http://acs.local:7557/devices/ZTE-F609%2FZTEG123/tasks?connection_request')
            && $request['name'] === 'setParameterValues'
            && $request['parameterValues'] === [
                ['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID', 'Rumah Budi', 'xsd:string'],
                ['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.KeyPassphrase', 'password123', 'xsd:string'],
            ]);
    }

    public function test_update_wifi_reports_task_queued_for_offline_device(): void
    {
        $this->configure();
        Http::fake(['*' => Http::response([], 202)]);

        $this->assertFalse($this->service()->updateWifi(Asset::factory()->create(['genieacs_device_id' => 'dev-1']), 'X', 'password123'));
    }

    public function test_unconfigured_or_unreachable_acs_raises_exception(): void
    {
        $asset = Asset::factory()->create(['genieacs_device_id' => 'dev-1']);

        $this->assertFalse($this->service()->isConfigured());

        try {
            $this->service()->readStatus($asset);
            $this->fail('Expected exception for unconfigured GenieACS.');
        } catch (GenieAcsException $exception) {
            $this->assertSame('GenieACS belum dikonfigurasi.', $exception->getMessage());
        }
    }
}
