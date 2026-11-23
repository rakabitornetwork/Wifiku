<?php

namespace Tests\Feature\Http\Controllers;

use App\Jobs\Network\RemovePppProfileJob;
use App\Jobs\Network\SyncPppProfileJob;
use App\Models\Package;
use App\Models\Router;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesUsers;
use Tests\Support\FakesRouterOs;
use Tests\TestCase;

class PackageRouterSyncTest extends TestCase
{
    use CreatesUsers;
    use FakesRouterOs;
    use RefreshDatabase;

    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();

        $this->router = Router::factory()->create(['isolir_profile' => 'ISOLIR']);
        $this->fakeRouterOs($this->router)->tables['/ppp/profile'] = [
            ['.id' => '*h0', 'name' => 'default'],
            ['.id' => '*h1', 'name' => '10M', 'rate-limit' => '10M/10M', 'local-address' => '10.0.0.1'],
            ['.id' => '*h2', 'name' => 'ISOLIR', 'rate-limit' => '64k/64k'],
        ];
    }

    public function test_saving_queues_one_sync_per_router_in_scope(): void
    {
        Queue::fake([SyncPppProfileJob::class]);
        Router::factory()->create();

        $this->actingAs($this->admin())->post('/packages', ['router_id' => null, 'name' => 'Semua', 'mikrotik_profile' => 'semua', 'price' => 100000, 'is_active' => true])->assertSessionDoesntHaveErrors();

        Queue::assertPushed(SyncPppProfileJob::class, 2);
    }

    public function test_sync_creates_missing_profile_and_updates_existing_keeping_other_settings(): void
    {
        $fake = $this->fakeRouterOs($this->router);
        $new = Package::factory()->for($this->router)->create(['mikrotik_profile' => '20M', 'rate_limit' => '20M/20M']);
        $existing = Package::factory()->for($this->router)->create(['mikrotik_profile' => '10M', 'rate_limit' => '15M/15M']);

        (new SyncPppProfileJob($new, $this->router->id))->handle();
        (new SyncPppProfileJob($new, $this->router->id))->handle();
        (new SyncPppProfileJob($existing, $this->router->id))->handle();

        $profiles = collect($fake->tables['/ppp/profile']);
        $this->assertSame(1, $profiles->where('name', '20M')->count());
        $this->assertSame('20M/20M', $profiles->firstWhere('name', '20M')['rate-limit']);
        $this->assertSame('15M/15M', $profiles->firstWhere('name', '10M')['rate-limit']);
        $this->assertSame('10.0.0.1', $profiles->firstWhere('name', '10M')['local-address']);
    }

    public function test_blank_rate_limit_does_not_clear_router_rate_limit_and_rename_does_not_duplicate(): void
    {
        $fake = $this->fakeRouterOs($this->router);
        $package = Package::factory()->for($this->router)->create(['mikrotik_profile' => '10M-baru', 'rate_limit' => null]);

        (new SyncPppProfileJob($package, $this->router->id, '10M'))->handle();

        $profiles = collect($fake->tables['/ppp/profile']);
        $this->assertCount(3, $profiles);
        $this->assertNull($profiles->firstWhere('name', '10M'));
        $this->assertSame('10M/10M', $profiles->firstWhere('name', '10M-baru')['rate-limit']);
    }

    public function test_sync_never_touches_builtin_or_isolir_profiles(): void
    {
        $fake = $this->fakeRouterOs($this->router);
        $isolir = Package::factory()->for($this->router)->create(['mikrotik_profile' => 'ISOLIR', 'rate_limit' => '1M/1M']);
        $default = Package::factory()->for($this->router)->create(['mikrotik_profile' => 'default', 'rate_limit' => '1M/1M']);

        (new SyncPppProfileJob($isolir, $this->router->id))->handle();
        (new SyncPppProfileJob($default, $this->router->id))->handle();

        $this->assertSame('64k/64k', collect($fake->tables['/ppp/profile'])->firstWhere('name', 'ISOLIR')['rate-limit']);
        $this->assertArrayNotHasKey('rate-limit', collect($fake->tables['/ppp/profile'])->firstWhere('name', 'default'));
    }

    public function test_deleting_queues_removal_and_job_removes_only_unused_unprotected_profile(): void
    {
        Queue::fake([RemovePppProfileJob::class]);
        $package = Package::factory()->for($this->router)->create(['mikrotik_profile' => '10M']);

        $this->actingAs($this->admin())->delete("/packages/{$package->id}")->assertRedirect();

        Queue::assertPushed(RemovePppProfileJob::class, fn ($job) => $job->profileName === '10M' && $job->routerId === $this->router->id);

        $fake = $this->fakeRouterOs($this->router);
        $fake->tables['/ppp/secret'] = [['.id' => '*hs1', 'name' => 'budi', 'profile' => 'ISOLIR']];
        (new RemovePppProfileJob($this->router->id, '10M'))->handle();
        (new RemovePppProfileJob($this->router->id, 'ISOLIR'))->handle();
        (new RemovePppProfileJob($this->router->id, 'default'))->handle();

        $names = collect($fake->tables['/ppp/profile'])->pluck('name')->all();
        $this->assertNotContains('10M', $names);
        $this->assertContains('ISOLIR', $names);
        $this->assertContains('default', $names);
    }

    public function test_removal_skips_profile_in_use_by_secret_or_another_package(): void
    {
        $fake = $this->fakeRouterOs($this->router);
        $fake->tables['/ppp/secret'] = [['.id' => '*hs1', 'name' => 'budi', 'profile' => '10M']];

        (new RemovePppProfileJob($this->router->id, '10M'))->handle();
        $this->assertContains('10M', collect($fake->tables['/ppp/profile'])->pluck('name')->all());

        $fake->tables['/ppp/secret'] = [];
        Package::factory()->create(['router_id' => null, 'mikrotik_profile' => '10M']);
        (new RemovePppProfileJob($this->router->id, '10M'))->handle();
        $this->assertContains('10M', collect($fake->tables['/ppp/profile'])->pluck('name')->all());
    }
}
