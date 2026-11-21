<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class AssetControllerTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return [
            'type' => 'ont',
            'brand' => 'ZTE',
            'model' => 'F609',
            'serial_number' => 'zteg12345678',
            'mac_address' => 'aa-bb-cc-dd-ee-ff',
            'ownership' => 'company',
            'status' => 'in_stock',
            ...$overrides,
        ];
    }

    public function test_lists_assets_with_customer_and_filters_by_status(): void
    {
        Asset::factory()->create(['status' => AssetStatus::InStock]);
        Asset::factory()->for(Customer::factory())->create(['status' => AssetStatus::Installed]);

        $this->actingAs($this->admin())->get('/assets?status=installed')->assertInertia(
            fn (Assert $page) => $page->component('Assets/Index')->has('assets.data', 1)->has('assets.data.0.customer'),
        );
    }

    public function test_normalizes_serial_and_mac_address_on_create(): void
    {
        $this->actingAs($this->admin())->post('/assets', $this->validPayload())->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('assets', ['serial_number' => 'ZTEG12345678', 'mac_address' => 'AA:BB:CC:DD:EE:FF']);
    }

    public function test_rejects_duplicate_serial_number_and_invalid_mac(): void
    {
        Asset::factory()->create(['serial_number' => 'ZTEG12345678']);

        $this->actingAs($this->admin())->post('/assets', $this->validPayload(['mac_address' => 'bukan-mac']))
            ->assertSessionHasErrors(['serial_number', 'mac_address']);
    }

    public function test_installed_asset_requires_a_customer(): void
    {
        $this->actingAs($this->admin())->post('/assets', $this->validPayload(['status' => 'installed']))->assertSessionHasErrors('customer_id');
    }

    public function test_asset_in_stock_cannot_belong_to_a_customer(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->admin())->post('/assets', $this->validPayload(['customer_id' => $customer->id]))->assertSessionHasErrors('customer_id');
    }

    public function test_assigns_asset_to_customer_when_installed(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->admin())->post('/assets', $this->validPayload(['status' => 'installed', 'customer_id' => $customer->id]))->assertSessionDoesntHaveErrors();

        $this->assertSame($customer->id, Asset::sole()->customer_id);
    }

    public function test_updates_asset_keeping_its_own_serial_number(): void
    {
        $asset = Asset::factory()->create(['serial_number' => 'ZTEG12345678', 'mac_address' => 'AA:BB:CC:DD:EE:FF']);

        $this->actingAs($this->admin())->put("/assets/{$asset->id}", $this->validPayload(['status' => 'broken', 'notes' => 'Port LAN mati']))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(AssetStatus::Broken, $asset->fresh()->status);
    }

    public function test_deletes_asset(): void
    {
        $asset = Asset::factory()->create();

        $this->actingAs($this->admin())->delete("/assets/{$asset->id}");

        $this->assertModelMissing($asset);
    }
}
