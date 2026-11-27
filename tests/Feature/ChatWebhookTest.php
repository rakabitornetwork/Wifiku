<?php

namespace Tests\Feature;

use App\Jobs\Notification\HandleChatMessageJob;
use App\Services\Chat\ChatMessage;
use App\Services\Chat\ChatWebhookSecrets;
use App\Services\Settings\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class ChatWebhookTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    private const TOKEN = '123456789:ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    protected function setUp(): void
    {
        parent::setUp();

        $settings = new SettingService;
        $settings->set('whatsapp', 'bot_enabled', '1');
        $settings->set('whatsapp', 'instance', 'teslatech');
        $settings->set('whatsapp', 'base_url', 'http://evo.local:8080');
        $settings->set('whatsapp', 'api_key', 'kunci', encrypted: true);
        $settings->set('telegram', 'bot_enabled', '1');
        $settings->set('telegram', 'bot_token', self::TOKEN, encrypted: true);
    }

    private function whatsappPayload(array $data = [], array $top = []): array
    {
        return ['event' => 'messages.upsert', 'instance' => 'teslatech', 'data' => array_replace_recursive([
            'key' => ['remoteJid' => '6281234567890@s.whatsapp.net', 'fromMe' => false, 'id' => 'ABC123'],
            'message' => ['conversation' => 'tagihan'],
        ], $data), ...$top];
    }

    private function postWhatsapp(array $payload, ?string $secret = null)
    {
        return $this->postJson('/webhooks/chat/whatsapp/'.($secret ?? app(ChatWebhookSecrets::class)->whatsapp()), $payload);
    }

    private function postTelegram(array $update, ?string $secret = null)
    {
        return $this->postJson('/webhooks/chat/telegram', $update, ['X-Telegram-Bot-Api-Secret-Token' => $secret ?? app(ChatWebhookSecrets::class)->telegram()]);
    }

    public function test_whatsapp_rejects_wrong_secrets_and_queues_a_normalised_message_for_the_right_one(): void
    {
        Queue::fake();

        $this->postWhatsapp($this->whatsappPayload(), 'salah-total')->assertForbidden();
        Queue::assertNothingPushed();

        $this->postWhatsapp($this->whatsappPayload())->assertOk();
        Queue::assertPushed(HandleChatMessageJob::class, fn ($job) => $job->message['channel'] === 'whatsapp' && $job->message['senderId'] === '6281234567890' && $job->message['text'] === 'tagihan');
    }

    public function test_whatsapp_ignores_own_messages_groups_media_and_foreign_instances(): void
    {
        Queue::fake();

        $this->postWhatsapp($this->whatsappPayload(['key' => ['fromMe' => true, 'id' => 'a']]))->assertOk();
        $this->postWhatsapp($this->whatsappPayload(['key' => ['remoteJid' => '120363@g.us', 'id' => 'b']]))->assertOk();
        $this->postWhatsapp($this->whatsappPayload(['key' => ['remoteJid' => 'status@broadcast', 'id' => 'c']]))->assertOk();
        $this->postWhatsapp(['instance' => 'teslatech', 'data' => ['key' => ['remoteJid' => '6281234567890@s.whatsapp.net', 'id' => 'd'], 'message' => ['imageMessage' => ['caption' => 'x']]]])->assertOk();
        $this->postWhatsapp($this->whatsappPayload([], ['instance' => 'instance-lain']))->assertOk();
        $this->postWhatsapp($this->whatsappPayload(['key' => ['remoteJid' => 'bukan-nomor@s.whatsapp.net', 'id' => 'e']]))->assertOk();

        Queue::assertNothingPushed();
    }

    public function test_whatsapp_lid_senders_need_a_real_number_and_extended_text_is_read(): void
    {
        Queue::fake();

        $this->postWhatsapp($this->whatsappPayload(['key' => ['remoteJid' => '9988776655@lid', 'id' => 'x1']]))->assertOk();
        Queue::assertNothingPushed();

        $this->postWhatsapp($this->whatsappPayload(['key' => ['remoteJid' => '9988776655@lid', 'senderPn' => '6281234567890@s.whatsapp.net', 'id' => 'x2'], 'message' => ['conversation' => null, 'extendedTextMessage' => ['text' => 'onu']]]))->assertOk();
        Queue::assertPushed(HandleChatMessageJob::class, fn ($job) => $job->message['senderId'] === '6281234567890' && $job->message['text'] === 'onu');
    }

    public function test_redelivered_messages_are_processed_once_and_a_disabled_bot_ignores_everything(): void
    {
        Queue::fake();

        $this->postWhatsapp($this->whatsappPayload())->assertOk();
        $this->postWhatsapp($this->whatsappPayload())->assertOk();
        Queue::assertPushed(HandleChatMessageJob::class, 1);

        (new SettingService)->set('whatsapp', 'bot_enabled', '0');
        $this->postWhatsapp($this->whatsappPayload(['key' => ['id' => 'baru']]))->assertOk();
        Queue::assertPushed(HandleChatMessageJob::class, 1);
    }

    public function test_telegram_requires_the_secret_header_and_only_serves_private_chats(): void
    {
        Queue::fake();
        $private = ['message' => ['message_id' => 10, 'from' => ['id' => 777], 'chat' => ['id' => 777, 'type' => 'private'], 'text' => '/start']];

        $this->postTelegram($private, 'salah')->assertForbidden();
        $this->postJson('/webhooks/chat/telegram', $private)->assertForbidden();
        Queue::assertNothingPushed();

        $this->postTelegram($private)->assertOk();
        $this->postTelegram(['message' => ['message_id' => 11, 'from' => ['id' => 5], 'chat' => ['id' => -100123, 'type' => 'group'], 'text' => 'halo']])->assertOk();
        Queue::assertPushed(HandleChatMessageJob::class, 1);
        Queue::assertPushed(HandleChatMessageJob::class, fn ($job) => $job->message['senderId'] === '777' && $job->message['text'] === '/start' && $job->message['messageId'] === '10');
    }

    public function test_telegram_buttons_and_contacts_are_translated_and_foreign_contacts_are_flagged(): void
    {
        Queue::fake();

        $this->postTelegram(['callback_query' => ['id' => 'cb9', 'data' => 'tagihan', 'from' => ['id' => 777], 'message' => ['message_id' => 3, 'chat' => ['id' => 777, 'type' => 'private']]]])->assertOk();
        Queue::assertPushed(HandleChatMessageJob::class, fn ($job) => $job->message['callbackId'] === 'cb9' && $job->message['text'] === 'tagihan');

        $this->postTelegram(['message' => ['message_id' => 20, 'from' => ['id' => 777], 'chat' => ['id' => 777, 'type' => 'private'], 'contact' => ['phone_number' => '+6281234567890', 'user_id' => 777]]])->assertOk();
        Queue::assertPushed(HandleChatMessageJob::class, fn ($job) => ($job->message['contactPhone'] ?? null) === '+6281234567890');

        $this->postTelegram(['message' => ['message_id' => 21, 'from' => ['id' => 777], 'chat' => ['id' => 777, 'type' => 'private'], 'contact' => ['phone_number' => '+6285555555555', 'user_id' => 888]]])->assertOk();
        Queue::assertPushed(HandleChatMessageJob::class, fn ($job) => ($job->message['text'] ?? null) === ChatMessage::FOREIGN_CONTACT && ($job->message['contactPhone'] ?? null) === null);

        $this->postTelegram(['message' => ['message_id' => 22, 'from' => ['id' => 777], 'chat' => ['id' => 777, 'type' => 'private'], 'contact' => ['phone_number' => '+6285555555555']]])->assertOk();
        Queue::assertPushed(HandleChatMessageJob::class, 4);
    }

    public function test_installing_the_whatsapp_webhook_calls_evolution_with_the_secret_url(): void
    {
        URL::forceRootUrl('https://portal.example.com');
        Http::fake(['evo.local:8080/webhook/set/teslatech' => Http::response(['ok' => true])]);
        $owner = $this->owner();

        $this->actingAs($owner)->postJson('/settings/whatsapp/webhook')->assertOk()->assertJson(['ok' => true]);

        $secret = app(ChatWebhookSecrets::class)->whatsapp();
        Http::assertSent(fn (Request $request) => $request['webhook']['url'] === "https://portal.example.com/webhooks/chat/whatsapp/{$secret}" && $request['webhook']['events'] === ['MESSAGES_UPSERT'] && $request['webhook']['enabled'] === true);
        $this->assertSame(40, strlen($secret));
    }

    public function test_installing_the_telegram_webhook_sends_the_secret_token_and_reports_status(): void
    {
        URL::forceRootUrl('https://portal.example.com');
        $base = 'api.telegram.org/bot'.self::TOKEN;
        Http::fake([
            "{$base}/setWebhook" => Http::response(['ok' => true, 'result' => true]),
            "{$base}/getWebhookInfo" => Http::response(['ok' => true, 'result' => ['url' => 'https://portal.example.com/webhooks/chat/telegram', 'pending_update_count' => 2]]),
        ]);
        $owner = $this->owner();

        $this->actingAs($owner)->postJson('/settings/telegram/webhook')->assertOk()->assertJson(['ok' => true]);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/setWebhook') && $request['url'] === 'https://portal.example.com/webhooks/chat/telegram' && $request['secret_token'] === app(ChatWebhookSecrets::class)->telegram() && $request['allowed_updates'] === ['message', 'callback_query']);

        $this->actingAs($owner)->postJson('/settings/telegram/webhook-info')->assertOk()->assertJson(['ok' => true])->assertJsonPath('message', fn ($message) => str_contains($message, 'terpasang') && str_contains($message, '2 pesan'));
    }

    public function test_webhook_installation_refuses_local_or_plain_http_addresses_and_is_owner_only(): void
    {
        Http::fake();
        $owner = $this->owner();

        $this->actingAs($owner)->postJson('/settings/telegram/webhook')->assertOk()->assertJson(['ok' => false]);
        URL::forceScheme('http');
        URL::forceRootUrl('http://app.example.com');
        $this->actingAs($owner)->postJson('/settings/whatsapp/webhook')->assertOk()->assertJson(['ok' => false]);
        Http::assertNothingSent();

        $this->actingAs($this->admin())->postJson('/settings/telegram/webhook')->assertForbidden();
        $this->actingAs($this->admin())->postJson('/settings/whatsapp/webhook')->assertForbidden();
    }

    public function test_the_notifications_page_exposes_the_webhook_urls_to_the_owner_only(): void
    {
        $this->actingAs($this->admin())->get('/settings/notifications')->assertForbidden();

        $this->actingAs($this->owner())->get('/settings/notifications')->assertInertia(fn (Assert $page) => $page
            ->where('webhooks.telegram', fn ($url) => str_ends_with($url, '/webhooks/chat/telegram'))
            ->where('webhooks.whatsapp', fn ($url) => str_contains($url, '/webhooks/chat/whatsapp/')));
    }
}
