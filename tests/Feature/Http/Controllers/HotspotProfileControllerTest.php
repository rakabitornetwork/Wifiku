<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\HotspotProfile;
use App\Models\Router;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class HotspotProfileControllerTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function validPayload(Router $router, array $overrides = []): array
    {
        return [
            'router_id' => $router->id,
            'name' => 'Voucher 1 Hari',
            'mikrotik_profile' => 'HS-1D',
            'price' => 5000,
            'validity_minutes' => 1440,
            'shared_users' => 1,
            'is_active' => true,
            ...$overrides,
        ];
    }

    public function test_lists_profiles_with_router_and_voucher_count(): void
    {
        Voucher::factory()->create();

        $this->actingAs($this->admin())->get('/hotspot/profiles')->assertInertia(
            fn (Assert $page) => $page->component('Hotspot/Profiles')->where('profiles.data.0.vouchers_count', 1)->has('profiles.data.0.router'),
        );
    }

    public function test_creates_profile(): void
    {
        $router = Router::factory()->create();

        $this->actingAs($this->admin())->post('/hotspot/profiles', $this->validPayload($router))->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('hotspot_profiles', ['router_id' => $router->id, 'name' => 'Voucher 1 Hari', 'validity_minutes' => 1440]);
    }

    public function test_profile_name_is_unique_per_router_only(): void
    {
        $router = Router::factory()->create();
        HotspotProfile::factory()->for($router)->create(['name' => 'Voucher 1 Hari']);

        $this->actingAs($this->admin())->post('/hotspot/profiles', $this->validPayload($router))->assertSessionHasErrors('name');
        $this->actingAs($this->admin())->post('/hotspot/profiles', $this->validPayload(Router::factory()->create()))->assertSessionDoesntHaveErrors();
    }

    public function test_reseller_price_cannot_exceed_selling_price(): void
    {
        $this->actingAs($this->admin())
            ->post('/hotspot/profiles', $this->validPayload(Router::factory()->create(), ['reseller_price' => 6000]))
            ->assertSessionHasErrors('reseller_price');
    }

    public function test_updates_profile_keeping_its_own_name(): void
    {
        $profile = HotspotProfile::factory()->create(['name' => 'Voucher 1 Hari']);

        $this->actingAs($this->admin())->put("/hotspot/profiles/{$profile->id}", $this->validPayload($profile->router, ['price' => 7000]))->assertSessionDoesntHaveErrors();

        $this->assertSame(7000, $profile->fresh()->price);
    }

    public function test_refuses_to_delete_profile_that_has_vouchers(): void
    {
        $voucher = Voucher::factory()->create();

        $this->actingAs($this->admin())->delete("/hotspot/profiles/{$voucher->hotspot_profile_id}");

        $this->assertModelExists($voucher->hotspotProfile);
    }

    public function test_deletes_unused_profile(): void
    {
        $profile = HotspotProfile::factory()->create();

        $this->actingAs($this->admin())->delete("/hotspot/profiles/{$profile->id}");

        $this->assertModelMissing($profile);
    }
}
