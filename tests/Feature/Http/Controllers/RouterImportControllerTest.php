<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikConnectionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\Support\FakesRouterOs;
use Tests\TestCase;

class RouterImportControllerTest extends TestCase
{
    use CreatesUsers;
    use FakesRouterOs;
    use RefreshDatabase;

    private Router $router;

    private Package $realPackage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->router = Router::factory()->create(['isolir_profile' => 'ISOLIR']);
        $this->realPackage = Package::factory()->for($this->router)->create(['mikrotik_profile' => 'REAL', 'price' => 200000]);
        $fake = $this->fakeRouterOs($this->router);
        $fake->tables['/ppp/profile'] = [['.id' => '*p1', 'name' => '10M', 'rate-limit' => '10M/10M'], ['.id' => '*p2', 'name' => 'ISOLIR']];
        $fake->tables['/ppp/secret'] = [
            ['.id' => '*1', 'name' => 'budi', 'password' => 'pw-budi', 'service' => 'pppoe', 'profile' => '10M', 'disabled' => 'false', 'comment' => 'Budi Santoso 0812-3456-7890'],
            ['.id' => '*2', 'name' => 'siti', 'password' => 'pw-siti', 'service' => 'pppoe', 'profile' => 'ISOLIR', 'disabled' => 'false', 'comment' => ''],
            ['.id' => '*3', 'name' => 'andi', 'password' => 'pw-andi', 'service' => 'pppoe', 'profile' => '10M', 'disabled' => 'true', 'comment' => 'Andi'],
            ['.id' => '*4', 'name' => 'vpn-user', 'password' => 'x', 'service' => 'l2tp', 'profile' => '10M', 'disabled' => 'false', 'comment' => ''],
            ['.id' => '*5', 'name' => 'tanpa-pw', 'password' => '', 'service' => 'pppoe', 'profile' => '10M', 'disabled' => 'false', 'comment' => ''],
            ['.id' => '*6', 'name' => 'lain', 'password' => 'pw', 'service' => 'pppoe', 'profile' => 'GUEST', 'disabled' => 'false', 'comment' => ''],
        ];
    }

    private function payload(array $overrides = []): array
    {
        return [
            'mappings' => ['10M' => ['package' => 'new', 'price' => 150000], 'ISOLIR' => ['package' => (string) $this->realPackage->id]],
            'billing_day' => 15,
            'due_days' => 7,
            'bill_from' => 'next_cycle',
            ...$overrides,
        ];
    }

    public function test_preview_lists_secrets_without_passwords_and_profile_summary(): void
    {
        $existing = Package::factory()->for($this->router)->create(['mikrotik_profile' => '10M']);
        Customer::factory()->for($this->router)->create(['pppoe_username' => 'budi']);

        $response = $this->actingAs($this->admin())->getJson("/routers/{$this->router->id}/import-preview")->assertOk();

        $response->assertJsonCount(5, 'secrets')->assertJsonMissingPath('secrets.0.password');
        $response->assertJsonFragment(['username' => 'budi', 'display_name' => 'Budi Santoso', 'whatsapp' => '6281234567890', 'exists' => true]);
        $response->assertJsonFragment(['name' => '10M', 'rate_limit' => '10M/10M', 'suggested_package_id' => $existing->id, 'is_isolir' => false]);
        $this->assertStringNotContainsString('pw-budi', $response->getContent());
    }

    public function test_preview_reports_router_failure(): void
    {
        $this->fakeRouterOs($this->router)->failWith = new MikrotikConnectionException('timeout');

        $this->actingAs($this->admin())->getJson("/routers/{$this->router->id}/import-preview")->assertStatus(422)->assertJsonPath('error', 'Gagal membaca router: timeout');
    }

    public function test_import_creates_customers_with_status_package_and_encrypted_password_without_writing_to_router(): void
    {
        $this->actingAs($this->admin())->post("/routers/{$this->router->id}/import", $this->payload())->assertSessionDoesntHaveErrors();

        $this->assertSame(['budi', 'siti', 'andi'], Customer::orderBy('id')->pluck('pppoe_username')->all());

        $budi = Customer::where('pppoe_username', 'budi')->firstOrFail();
        $this->assertSame('pw-budi', $budi->pppoe_password);
        $this->assertSame('Budi Santoso', $budi->name);
        $this->assertSame('6281234567890', $budi->whatsapp);
        $this->assertSame(CustomerStatus::Active, $budi->status);
        $this->assertSame(15, $budi->billing_day);
        $this->assertSame('10M', $budi->package->mikrotik_profile);
        $this->assertSame('10M/10M', $budi->package->rate_limit);
        $this->assertSame(150000, $budi->package->price);

        $this->assertSame(CustomerStatus::Isolated, Customer::where('pppoe_username', 'siti')->firstOrFail()->status);
        $this->assertSame($this->realPackage->id, Customer::where('pppoe_username', 'siti')->firstOrFail()->package_id);
        $this->assertSame(CustomerStatus::Inactive, Customer::where('pppoe_username', 'andi')->firstOrFail()->status);
        $this->assertSame('siti', Customer::where('pppoe_username', 'siti')->value('name'));

        $writes = array_filter($this->fakeRouterOs($this->router)->commands, fn (array $command) => ! str_ends_with($command['path'], '/print'));
        $this->assertSame([], array_values($writes));
    }

    public function test_billing_starts_in_next_cycle_by_default_so_no_instant_prorata_invoice(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 21));

        $this->actingAs($this->admin())->post("/routers/{$this->router->id}/import", $this->payload(['billing_day' => 15]));
        $this->assertSame('2026-12-15', Customer::where('pppoe_username', 'budi')->firstOrFail()->installed_at->toDateString());

        Customer::withTrashed()->forceDelete();
        $this->actingAs($this->admin())->post("/routers/{$this->router->id}/import", $this->payload(['billing_day' => 25]));
        $this->assertSame('2026-11-25', Customer::where('pppoe_username', 'budi')->firstOrFail()->installed_at->toDateString());
    }

    public function test_bill_from_now_uses_today(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 21));

        $this->actingAs($this->admin())->post("/routers/{$this->router->id}/import", $this->payload(['bill_from' => 'now']));

        $this->assertSame('2026-11-21', Customer::where('pppoe_username', 'budi')->firstOrFail()->installed_at->toDateString());
    }

    public function test_unmapped_profiles_existing_users_and_passwordless_secrets_are_skipped_and_import_is_repeatable(): void
    {
        $package = Package::factory()->for($this->router)->create(['mikrotik_profile' => '10M']);

        $this->actingAs($this->admin())->post("/routers/{$this->router->id}/import", $this->payload(['mappings' => ['10M' => ['package' => (string) $package->id], 'ISOLIR' => ['package' => '']]]));

        $this->assertSame(['budi', 'andi'], Customer::orderBy('id')->pluck('pppoe_username')->all());
        $this->assertSame(2, Package::count());

        $this->actingAs($this->admin())->post("/routers/{$this->router->id}/import", $this->payload(['mappings' => ['10M' => ['package' => (string) $package->id]]]));

        $this->assertSame(2, Customer::count());
    }

    public function test_new_package_requires_price_and_existing_package_must_belong_to_router(): void
    {
        $other = Package::factory()->create();

        $this->actingAs($this->admin())->post("/routers/{$this->router->id}/import", $this->payload(['mappings' => ['10M' => ['package' => 'new']]]))->assertSessionHasErrors('mappings.10M.price');
        $this->actingAs($this->admin())->post("/routers/{$this->router->id}/import", $this->payload(['mappings' => ['10M' => ['package' => (string) $other->id]]]))->assertSessionHasErrors('mappings.10M.package');
        $this->assertSame(0, Customer::count());
    }

    public function test_isolir_profile_is_matched_ignoring_case(): void
    {
        $this->fakeRouterOs($this->router)->tables['/ppp/secret'] = [['.id' => '*1', 'name' => 'budi', 'password' => 'pw', 'service' => 'pppoe', 'profile' => 'isolir', 'disabled' => 'false', 'comment' => '']];

        $this->actingAs($this->admin())->getJson("/routers/{$this->router->id}/import-preview")->assertJsonFragment(['name' => 'isolir', 'is_isolir' => true]);
        $this->actingAs($this->admin())->post("/routers/{$this->router->id}/import", $this->payload(['mappings' => ['isolir' => ['package' => (string) $this->realPackage->id]]]));

        $this->assertSame(CustomerStatus::Isolated, Customer::where('pppoe_username', 'budi')->firstOrFail()->status);
    }

    public function test_isolir_profile_cannot_become_or_be_mapped_to_a_package(): void
    {
        $isolirPackage = Package::factory()->for($this->router)->create(['mikrotik_profile' => 'isolir']);

        $this->actingAs($this->admin())->post("/routers/{$this->router->id}/import", $this->payload(['mappings' => ['ISOLIR' => ['package' => 'new', 'price' => 1000]]]))->assertSessionHasErrors('mappings.ISOLIR.package');
        $this->actingAs($this->admin())->post("/routers/{$this->router->id}/import", $this->payload(['mappings' => ['10M' => ['package' => (string) $isolirPackage->id]]]))->assertSessionHasErrors('mappings.10M.package');
        $this->assertSame(0, Customer::count());

        $this->actingAs($this->admin())->getJson("/routers/{$this->router->id}/import-preview")->assertJsonMissing(['id' => $isolirPackage->id]);
    }

    public function test_preview_warns_when_router_isolir_profile_name_is_not_found_on_router(): void
    {
        $this->actingAs($this->admin())->getJson("/routers/{$this->router->id}/import-preview")->assertJsonPath('isolir_profile', 'ISOLIR')->assertJsonPath('isolir_profile_found', true);

        $this->router->update(['isolir_profile' => 'Isolir-Salah']);

        $this->actingAs($this->admin())->getJson("/routers/{$this->router->id}/import-preview")->assertJsonPath('isolir_profile_found', false);
    }

    public function test_requires_router_management_permission(): void
    {
        $this->actingAs($this->userWithRole('Teknisi'))->getJson("/routers/{$this->router->id}/import-preview")->assertForbidden();
    }
}
