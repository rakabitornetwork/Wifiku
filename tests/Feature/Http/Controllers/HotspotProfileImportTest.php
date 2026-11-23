<?php

namespace Tests\Feature\Http\Controllers;

use App\Jobs\Network\RemoveHotspotProfileJob;
use App\Jobs\Network\SyncHotspotProfileJob;
use App\Models\HotspotProfile;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikConnectionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesUsers;
use Tests\Support\FakesRouterOs;
use Tests\TestCase;

class HotspotProfileImportTest extends TestCase
{
    use CreatesUsers;
    use FakesRouterOs;
    use RefreshDatabase;

    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();

        $this->router = Router::factory()->create();
        $this->fakeRouterOs($this->router)->tables['/ip/hotspot/user/profile'] = [
            ['.id' => '*h0', 'name' => 'default', 'shared-users' => '1'],
            ['.id' => '*h1', 'name' => '1Hari', 'rate-limit' => '2M/2M', 'shared-users' => '2', 'on-login' => ':put (",rem,5000,1d,5000,,Disable,"); {script}'],
            ['.id' => '*h2', 'name' => 'Polos', 'shared-users' => '1', 'on-login' => ''],
            ['.id' => '*h3', 'name' => 'Mingguan', 'on-login' => ':put (",ntf,20000,1w2d,25000,,Enable,")'],
        ];
    }

    public function test_preview_hides_builtin_profiles_and_reads_mikhmon_price_and_validity(): void
    {
        $this->actingAs($this->admin())->getJson("/hotspot/profile-import/{$this->router->id}")
            ->assertOk()
            ->assertJsonCount(3, 'profiles')
            ->assertJsonFragment(['name' => '1Hari', 'price' => 5000, 'validity_minutes' => 1440, 'rate_limit' => '2M/2M', 'shared_users' => 2])
            ->assertJsonFragment(['name' => 'Mingguan', 'price' => 25000, 'validity_minutes' => 10080 + 2880])
            ->assertJsonFragment(['name' => 'Polos', 'price' => 0]);
    }

    public function test_import_creates_selected_profiles_only_once_and_inactive_without_price(): void
    {
        $payload = ['names' => ['1Hari', 'Polos']];

        $this->actingAs($this->admin())->post("/hotspot/profile-import/{$this->router->id}", $payload)->assertSessionDoesntHaveErrors();
        $this->actingAs($this->admin())->post("/hotspot/profile-import/{$this->router->id}", $payload)->assertSessionDoesntHaveErrors();

        $this->assertSame(2, HotspotProfile::count());
        $this->assertTrue(HotspotProfile::where('mikrotik_profile', '1Hari')->firstOrFail()->is_active);
        $this->assertFalse(HotspotProfile::where('mikrotik_profile', 'Polos')->firstOrFail()->is_active);
        $this->assertSame(0, HotspotProfile::where('mikrotik_profile', 'Mingguan')->count());
    }

    public function test_preview_reports_router_failure(): void
    {
        $this->fakeRouterOs($this->router)->failWith = new MikrotikConnectionException('timeout');

        $this->actingAs($this->admin())->getJson("/hotspot/profile-import/{$this->router->id}")->assertStatus(422)->assertJsonPath('error', 'Gagal membaca router: timeout');
    }

    public function test_saving_a_profile_queues_router_sync(): void
    {
        Queue::fake([SyncHotspotProfileJob::class]);

        $this->actingAs($this->admin())->post('/hotspot/profiles', [
            'router_id' => $this->router->id, 'name' => 'Baru', 'mikrotik_profile' => 'baru', 'price' => 5000, 'validity_minutes' => 1440, 'shared_users' => 1, 'rate_limit' => '3M/3M', 'is_active' => true,
        ])->assertSessionDoesntHaveErrors();

        Queue::assertPushed(SyncHotspotProfileJob::class);
    }

    public function test_sync_job_creates_missing_profile_and_updates_existing_one_keeping_other_settings(): void
    {
        $fake = $this->fakeRouterOs($this->router);
        $missing = HotspotProfile::factory()->for($this->router)->create(['mikrotik_profile' => 'baru', 'rate_limit' => '3M/3M', 'shared_users' => 2]);
        $existing = HotspotProfile::factory()->for($this->router)->create(['mikrotik_profile' => '1Hari', 'rate_limit' => '9M/9M', 'shared_users' => 3]);

        (new SyncHotspotProfileJob($missing))->handle();
        (new SyncHotspotProfileJob($missing))->handle();
        (new SyncHotspotProfileJob($existing))->handle();

        $profiles = collect($fake->tables['/ip/hotspot/user/profile']);
        $this->assertSame(1, $profiles->where('name', 'baru')->count());
        $this->assertSame('3M/3M', $profiles->firstWhere('name', 'baru')['rate-limit']);
        $this->assertSame('9M/9M', $profiles->firstWhere('name', '1Hari')['rate-limit']);
        $this->assertSame('3', $profiles->firstWhere('name', '1Hari')['shared-users']);
        $this->assertStringContainsString('{script}', $profiles->firstWhere('name', '1Hari')['on-login']);
    }

    public function test_renaming_a_profile_renames_it_on_the_router_instead_of_creating_a_duplicate(): void
    {
        $fake = $this->fakeRouterOs($this->router);
        $profile = HotspotProfile::factory()->for($this->router)->create(['mikrotik_profile' => '1Hari-baru', 'rate_limit' => '2M/2M']);

        (new SyncHotspotProfileJob($profile, '1Hari'))->handle();

        $names = collect($fake->tables['/ip/hotspot/user/profile'])->pluck('name')->all();
        $this->assertContains('1Hari-baru', $names);
        $this->assertNotContains('1Hari', $names);
        $this->assertCount(4, $names);
    }

    public function test_deleting_a_profile_queues_router_removal_and_job_removes_unused_profile(): void
    {
        Queue::fake([RemoveHotspotProfileJob::class]);
        $profile = HotspotProfile::factory()->for($this->router)->create(['mikrotik_profile' => 'Polos']);

        $this->actingAs($this->admin())->delete("/hotspot/profiles/{$profile->id}")->assertRedirect();

        Queue::assertPushed(RemoveHotspotProfileJob::class, fn ($job) => $job->profileName === 'Polos' && $job->routerId === $this->router->id);

        $fake = $this->fakeRouterOs($this->router);
        (new RemoveHotspotProfileJob($this->router->id, 'Polos'))->handle();
        (new RemoveHotspotProfileJob($this->router->id, 'default'))->handle();

        $names = collect($fake->tables['/ip/hotspot/user/profile'])->pluck('name')->all();
        $this->assertNotContains('Polos', $names);
        $this->assertContains('default', $names);
    }

    public function test_removal_skips_profile_still_used_by_hotspot_users(): void
    {
        $fake = $this->fakeRouterOs($this->router);
        $fake->tables['/ip/hotspot/user'] = [['.id' => '*hu1', 'name' => 'abc', 'profile' => 'Polos']];

        (new RemoveHotspotProfileJob($this->router->id, 'Polos'))->handle();

        $this->assertContains('Polos', collect($fake->tables['/ip/hotspot/user/profile'])->pluck('name')->all());
    }

    public function test_sync_fills_empty_on_login_appends_to_existing_script_and_never_duplicates(): void
    {
        $fake = $this->fakeRouterOs($this->router);
        $fresh = HotspotProfile::factory()->for($this->router)->create(['mikrotik_profile' => 'baru']);
        $mikhmon = HotspotProfile::factory()->for($this->router)->create(['mikrotik_profile' => '1Hari']);
        $polos = HotspotProfile::factory()->for($this->router)->create(['mikrotik_profile' => 'Polos']);

        foreach ([$fresh, $mikhmon, $polos, $mikhmon, $fresh] as $profile) {
            (new SyncHotspotProfileJob($profile))->handle();
        }

        $profiles = collect($fake->tables['/ip/hotspot/user/profile']);
        $marker = Router::HOTSPOT_SCRIPT_MARKER;
        $token = $this->router->fresh()->webhook_token;

        $this->assertNotEmpty($token);
        $this->assertStringStartsWith($marker, $profiles->firstWhere('name', 'baru')['on-login']);
        $this->assertSame(1, substr_count($profiles->firstWhere('name', 'baru')['on-login'], $marker));
        $this->assertStringStartsWith(':put (",rem,5000,1d,5000,,Disable,"); {script}', $profiles->firstWhere('name', '1Hari')['on-login']);
        $this->assertSame(1, substr_count($profiles->firstWhere('name', '1Hari')['on-login'], $marker));
        $this->assertStringContainsString("token={$token}", $profiles->firstWhere('name', '1Hari')['on-login']);
        $this->assertStringStartsWith($marker, $profiles->firstWhere('name', 'Polos')['on-login']);
    }

    public function test_lists_hotspot_servers_of_a_router(): void
    {
        $this->fakeRouterOs($this->router)->tables['/ip/hotspot'] = [['.id' => '*x1', 'name' => 'hs-vlan-hs'], ['.id' => '*x2', 'name' => 'hs-vlan-promo']];

        $this->actingAs($this->admin())->getJson("/hotspot/routers/{$this->router->id}/servers")->assertOk()->assertExactJson(['servers' => ['hs-vlan-hs', 'hs-vlan-promo']]);
    }
}
