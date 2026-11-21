<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Models\Asset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class MonitoringControllerTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    public function test_lists_only_installed_onts_with_signal_summary(): void
    {
        Asset::factory()->create(['type' => AssetType::Ont, 'status' => AssetStatus::Installed, 'last_rx_power' => -20]);
        Asset::factory()->create(['type' => AssetType::Ont, 'status' => AssetStatus::Installed, 'last_rx_power' => -26]);
        Asset::factory()->create(['type' => AssetType::Ont, 'status' => AssetStatus::Installed, 'last_rx_power' => -29]);
        Asset::factory()->create(['type' => AssetType::Ont, 'status' => AssetStatus::Installed, 'last_rx_power' => null]);
        Asset::factory()->create(['type' => AssetType::Ont, 'status' => AssetStatus::InStock]);
        Asset::factory()->create(['type' => AssetType::Router, 'status' => AssetStatus::Installed]);

        $this->actingAs($this->admin())->get('/monitoring')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('Monitoring/Index')
                ->has('onts.data', 4)
                ->where('summary', ['total' => 4, 'good' => 1, 'warning' => 1, 'critical' => 1, 'unknown' => 1])
                ->where('genieacsConfigured', false),
        );
    }

    public function test_filters_by_signal_quality(): void
    {
        Asset::factory()->create(['type' => AssetType::Ont, 'status' => AssetStatus::Installed, 'last_rx_power' => -20]);
        Asset::factory()->create(['type' => AssetType::Ont, 'status' => AssetStatus::Installed, 'last_rx_power' => -29]);

        $this->actingAs($this->admin())->get('/monitoring?signal=critical')->assertInertia(
            fn (Assert $page) => $page->has('onts.data', 1)->where('onts.data.0.last_rx_power', '-29.00'),
        );
    }

    public function test_requires_assets_permission(): void
    {
        $this->get('/monitoring')->assertRedirect('/login');
        $this->actingAs($this->userWithRole('Teknisi'))->get('/monitoring')->assertOk();
    }
}
