<?php

namespace Tests\Feature;

use App\Services\Updater\AppUpdater;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class AppUpdateTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        File::delete(storage_path('app/updater/state.json'));
    }

    protected function tearDown(): void
    {
        File::delete(storage_path('app/updater/state.json'));

        parent::tearDown();
    }

    /**
     * @param  array<string, ProcessResult>  $overrides  Potongan perintah => hasil.
     * @param  list<string>  $files  Berkas yang berubah di commit baru.
     */
    private function fakeGit(array $overrides = [], int $behind = 2, array $files = ['app/Models/User.php', 'database/migrations/2026_12_01_000000_x.php'], string $status = ''): void
    {
        Process::fake(function (PendingProcess $process) use ($overrides, $behind, $files, $status) {
            $command = implode(' ', (array) $process->command);

            foreach ($overrides as $needle => $result) {
                if (str_contains($command, $needle)) {
                    return $result;
                }
            }

            return match (true) {
                str_contains($command, '--symbolic-full-name') => Process::result("origin/main\n"),
                str_contains($command, 'status --porcelain') => Process::result($status),
                str_contains($command, 'rev-list --count HEAD..@{u}') => Process::result("{$behind}\n"),
                str_contains($command, 'rev-list --count @{u}..HEAD') => Process::result("0\n"),
                str_contains($command, 'log HEAD..@{u}') => Process::result("abc1234\tTambah fitur baru\t2026-12-01T10:00:00+07:00\ndef5678\tPerbaiki bug\t2026-12-01T09:00:00+07:00\n"),
                str_contains($command, 'diff --name-only') => Process::result(implode("\n", $files)."\n"),
                str_contains($command, 'rev-parse --short') => Process::result("abc1234\n"),
                str_contains($command, 'rev-parse HEAD') => Process::result("1111111111111111111111111111111111111111\n"),
                str_contains($command, 'rev-parse --abbrev-ref HEAD') => Process::result("main\n"),
                str_contains($command, 'log -1') => Process::result("Commit saat ini\n"),
                default => Process::result('ok'),
            };
        });
    }

    public function test_only_the_owner_can_open_the_update_page(): void
    {
        $this->fakeGit();

        $this->get('/settings/update')->assertRedirect('/login');
        $this->actingAs($this->admin())->get('/settings/update')->assertForbidden();
        $this->actingAs($this->admin()->givePermissionTo('settings.manage'))->get('/settings/update')->assertForbidden();
        $this->actingAs($this->owner())->get('/settings/update')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Settings/AppUpdate')->where('info.behind', 2)->where('info.can_update', true));
    }

    public function test_check_lists_incoming_commits_and_what_the_update_will_do(): void
    {
        $this->fakeGit(files: ['composer.lock', 'database/migrations/a.php', 'database/migrations/b.php', 'app/X.php']);

        $info = app(AppUpdater::class)->check();

        $this->assertSame(2, $info['behind']);
        $this->assertSame('Tambah fitur baru', $info['commits'][0]['subject']);
        $this->assertTrue($info['needs_composer']);
        $this->assertSame(2, $info['migration_count']);
        $this->assertSame(4, $info['file_count']);
        $this->assertTrue($info['can_update']);
    }

    public function test_a_dirty_working_tree_blocks_the_update(): void
    {
        $this->fakeGit(status: " M app/Models/User.php\n");

        $info = app(AppUpdater::class)->check();

        $this->assertTrue($info['dirty']);
        $this->assertFalse($info['can_update']);
        $this->assertStringContainsString('belum di-commit', $info['blocked']);
    }

    public function test_nothing_to_update_when_already_current(): void
    {
        $this->fakeGit(behind: 0);

        $this->assertFalse(app(AppUpdater::class)->check()['can_update']);
    }

    public function test_a_successful_run_pulls_migrates_clears_caches_and_restarts_the_queue(): void
    {
        $this->fakeGit();

        $this->assertTrue(app(AppUpdater::class)->run('tester'));

        $state = app(AppUpdater::class)->state();
        $this->assertSame('succeeded', $state['status']);
        $this->assertSame(['done', 'done', 'skipped', 'done', 'done', 'done'], array_column($state['steps'], 'status'));
        Process::assertRan(fn (PendingProcess $process) => implode(' ', (array) $process->command) === 'git pull --ff-only');
        Process::assertRan(fn (PendingProcess $process) => str_contains(implode(' ', (array) $process->command), 'migrate --force'));
        Process::assertRan(fn (PendingProcess $process) => str_contains(implode(' ', (array) $process->command), 'queue:restart'));
        Process::assertDidntRun(fn (PendingProcess $process) => str_contains(implode(' ', (array) $process->command), 'composer'));
    }

    public function test_a_failing_migration_stops_the_run_and_reports_the_previous_version(): void
    {
        $this->fakeGit(['migrate --force' => Process::result('', 'SQLSTATE: tabel sudah ada', 1)]);

        $this->assertFalse(app(AppUpdater::class)->run('tester'));

        $state = app(AppUpdater::class)->state();
        $this->assertSame('failed', $state['status']);
        $this->assertSame(['done', 'done', 'skipped', 'failed', 'skipped', 'skipped'], array_column($state['steps'], 'status'));
        $this->assertStringContainsString('SQLSTATE', $state['steps'][3]['output']);
        $this->assertStringContainsString('1111111', $state['error']);
        Process::assertDidntRun(fn (PendingProcess $process) => str_contains(implode(' ', (array) $process->command), 'queue:restart'));
    }

    public function test_a_failing_pull_does_not_touch_anything_else(): void
    {
        $this->fakeGit(['pull --ff-only' => Process::result('', 'fatal: Not possible to fast-forward', 128)]);

        $this->assertFalse(app(AppUpdater::class)->run('tester'));

        $this->assertSame(['done', 'failed', 'skipped', 'skipped', 'skipped', 'skipped'], array_column(app(AppUpdater::class)->state()['steps'], 'status'));
        Process::assertDidntRun(fn (PendingProcess $process) => str_contains(implode(' ', (array) $process->command), 'migrate'));
    }

    public function test_composer_runs_only_when_the_lockfile_changed(): void
    {
        $this->fakeGit(['command -v composer' => Process::result("/usr/local/bin/composer\n")], files: ['composer.lock', 'app/X.php']);

        $this->assertTrue(app(AppUpdater::class)->run('tester'));

        Process::assertRan(fn (PendingProcess $process) => str_contains(implode(' ', (array) $process->command), '/usr/local/bin/composer install'));
    }

    public function test_start_queues_the_update_in_the_background_and_refuses_a_second_one(): void
    {
        $this->fakeGit();
        $owner = $this->owner();

        $this->actingAs($owner)->postJson('/settings/update/start')->assertStatus(202)->assertJsonPath('state.status', 'queued');

        Process::assertRan(fn (PendingProcess $process) => str_contains(implode(' ', (array) $process->command), 'app:update'));
        $this->assertDatabaseHas('activity_log', ['log_name' => 'Sistem', 'causer_id' => $owner->id]);

        $this->actingAs($owner)->postJson('/settings/update/start')->assertStatus(422)->assertJsonPath('message', 'Pembaruan sedang berjalan.');
    }

    public function test_start_is_rejected_when_there_is_nothing_to_install(): void
    {
        $this->fakeGit(behind: 0);

        $this->actingAs($this->owner())->postJson('/settings/update/start')->assertStatus(422);
        $this->assertSame('idle', app(AppUpdater::class)->state()['status']);
    }

    public function test_a_stuck_running_state_is_reported_as_failed_so_it_can_be_retried(): void
    {
        $this->fakeGit();
        $this->actingAs($this->owner())->postJson('/settings/update/start')->assertStatus(202);

        $this->travel(30)->minutes();

        $this->assertSame('failed', app(AppUpdater::class)->state()['status']);
        $this->assertFalse(app(AppUpdater::class)->isBusy());
    }

    public function test_status_and_check_endpoints_are_owner_only_and_never_start_anything(): void
    {
        $this->fakeGit();

        $this->actingAs($this->admin())->getJson('/settings/update/status')->assertForbidden();
        $this->actingAs($this->admin())->postJson('/settings/update/check')->assertForbidden();
        $this->actingAs($this->admin())->postJson('/settings/update/start')->assertForbidden();
        $this->actingAs($this->owner())->getJson('/settings/update/status')->assertOk()->assertJsonPath('state.status', 'idle');
        Process::assertDidntRun(fn (PendingProcess $process) => str_contains(implode(' ', (array) $process->command), 'app:update'));
    }
}
