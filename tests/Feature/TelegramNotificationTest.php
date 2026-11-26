<?php

namespace Tests\Feature;

use App\Actions\Outage\BroadcastOutage;
use App\Actions\Router\CheckRouterConnection;
use App\Enums\RouterStatus;
use App\Enums\WhatsappMessageStatus;
use App\Jobs\Notification\BroadcastOutageJob;
use App\Jobs\Notification\SendTelegramMessageJob;
use App\Jobs\Notification\SendWhatsappMessageJob;
use App\Models\Odp;
use App\Models\Router;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Services\Mikrotik\MikrotikConnectionException;
use App\Services\Settings\SettingService;
use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\TelegramDeliveryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesUsers;
use Tests\Support\FakesRouterOs;
use Tests\TestCase;

class TelegramNotificationTest extends TestCase
{
    use CreatesUsers;
    use FakesRouterOs;
    use RefreshDatabase;

    private const TOKEN = '123456789:ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    private function configure(array $extra = []): void
    {
        $settings = new SettingService;
        $settings->set('telegram', 'enabled', '1');
        $settings->set('telegram', 'bot_token', self::TOKEN, encrypted: true);
        $settings->set('telegram', 'chat_id', '-100123456789');

        foreach ($extra as $key => $value) {
            $settings->set('telegram', $key, $value);
        }
    }

    public function test_status_and_test_message_call_the_bot_api_with_the_stored_token(): void
    {
        $this->configure();
        Http::fake([
            'api.telegram.org/bot'.self::TOKEN.'/getMe' => Http::response(['ok' => true, 'result' => ['username' => 'teslatech_bot', 'first_name' => 'Tesla']]),
            'api.telegram.org/bot'.self::TOKEN.'/sendMessage' => Http::response(['ok' => true, 'result' => []]),
        ]);
        $owner = $this->owner();

        $this->actingAs($owner)->postJson('/settings/telegram/status')->assertOk()->assertJson(['ok' => true, 'message' => 'Terhubung ke bot @teslatech_bot.']);
        $this->actingAs($owner)->postJson('/settings/telegram/test')->assertOk()->assertJson(['ok' => true]);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/sendMessage') && $request['chat_id'] === '-100123456789' && str_contains($request['text'], 'Pesan uji'));
    }

    public function test_api_errors_become_readable_messages_and_never_leak_the_token(): void
    {
        $this->configure();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'Unauthorized'], 401)]);

        $response = $this->actingAs($this->owner())->postJson('/settings/telegram/test')->assertOk()->assertJson(['ok' => false]);
        $this->assertStringContainsString('Unauthorized', $response->json('message'));

        Http::fake(fn () => throw new ConnectionException('cURL error 28 for https://api.telegram.org/bot'.self::TOKEN.'/sendMessage'));

        $response = $this->actingAs($this->owner())->postJson('/settings/telegram/status')->assertOk();
        $this->assertStringNotContainsString(self::TOKEN, $response->getContent());
        $this->assertStringContainsString('***', $response->json('message'));
    }

    public function test_telegram_endpoints_are_owner_only_and_report_missing_configuration(): void
    {
        $this->actingAs($this->admin())->postJson('/settings/telegram/test')->assertForbidden();
        $this->actingAs($this->owner())->postJson('/settings/telegram/status')->assertOk()->assertJson(['ok' => false, 'message' => 'Token bot Telegram belum diisi.']);
    }

    public function test_the_job_sends_only_while_telegram_is_enabled(): void
    {
        $this->configure();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        (new SendTelegramMessageJob('halo'))->handle(app(TelegramClient::class));
        Http::assertSentCount(1);

        (new SettingService)->set('telegram', 'enabled', '0');
        (new SendTelegramMessageJob('halo lagi'))->handle(app(TelegramClient::class));
        Http::assertSentCount(1);
    }

    public function test_a_router_going_offline_and_recovering_notifies_once_per_change(): void
    {
        Queue::fake();
        $this->configure();
        $router = Router::factory()->create(['status' => RouterStatus::Online]);
        $fake = $this->fakeRouterOs($router);
        $check = fn () => app(CheckRouterConnection::class)->handle($router->fresh());

        $fake->failWith = new MikrotikConnectionException('timeout');
        $check();
        $check();
        Queue::assertPushed(SendTelegramMessageJob::class, 1);
        Queue::assertPushed(SendTelegramMessageJob::class, fn ($job) => str_contains($job->text, 'Router offline') && str_contains($job->text, $router->name));

        $fake->failWith = null;
        $check();
        $check();
        Queue::assertPushed(SendTelegramMessageJob::class, 2);
        Queue::assertPushed(SendTelegramMessageJob::class, fn ($job) => str_contains($job->text, 'Router pulih'));
    }

    public function test_alerts_respect_the_master_switch_and_each_alert_switch(): void
    {
        Queue::fake();
        $router = Router::factory()->create(['status' => RouterStatus::Online]);
        $this->fakeRouterOs($router)->failWith = new MikrotikConnectionException('timeout');

        app(CheckRouterConnection::class)->handle($router->fresh());
        Queue::assertNothingPushed();

        $this->configure(['alert_router' => '0']);
        $router->forceFill(['status' => RouterStatus::Online])->save();
        app(CheckRouterConnection::class)->handle($router->fresh());
        Queue::assertNothingPushed();
    }

    public function test_a_new_outage_is_announced_on_telegram(): void
    {
        Queue::fake();
        $this->configure();

        app(BroadcastOutage::class)->handle(Odp::factory()->create(['code' => 'ODP-01', 'name' => 'Tiang Utara']), 'FO putus', 'Mohon maaf.', User::factory()->create());

        Queue::assertPushed(BroadcastOutageJob::class);
        Queue::assertPushed(SendTelegramMessageJob::class, fn ($job) => str_contains($job->text, 'FO putus') && str_contains($job->text, 'ODP-01 · Tiang Utara'));
    }

    public function test_a_whatsapp_message_that_exhausts_its_retries_is_reported(): void
    {
        Queue::fake();
        $this->configure();
        $message = WhatsappMessage::factory()->create(['phone' => '6281234567890', 'status' => WhatsappMessageStatus::Failed, 'error' => 'Gateway mati']);

        (new SendWhatsappMessageJob($message))->failed(new TelegramDeliveryException('x'));

        Queue::assertPushed(SendTelegramMessageJob::class, fn ($job) => str_contains($job->text, '6281234567890') && str_contains($job->text, 'Gateway mati'));
    }
}
