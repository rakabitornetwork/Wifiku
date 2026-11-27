<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\CustomerStatus;
use App\Enums\InvoiceStatus;
use App\Enums\WhatsappMessageType;
use App\Jobs\Notification\HandleChatMessageJob;
use App\Models\Asset;
use App\Models\ChatSession;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\WhatsappMessage;
use App\Services\Chat\ChatBot;
use App\Services\Chat\ChatDispatcher;
use App\Services\Chat\ChatMessage;
use App\Services\Chat\ChatReply;
use App\Services\Settings\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class ChatBotTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    private const PHONE = '6281234567890';

    protected function setUp(): void
    {
        parent::setUp();

        $settings = new SettingService;
        $settings->set('company', 'name', 'Teslatech Net');
        $settings->set('company', 'cs_whatsapp', '6287778888820');
        $settings->set('genieacs', 'base_url', 'http://acs.local:7557');
        $settings->set('whatsapp', 'bot_enabled', '1');
        $settings->set('telegram', 'bot_enabled', '1');
        $settings->set('telegram', 'bot_token', '123456789:ABCDEFGHIJKLMNOPQRSTUVWXYZ', encrypted: true);
    }

    private function customer(array $attributes = []): Customer
    {
        return Customer::factory()->create(['name' => 'Budi Santoso', 'customer_code' => 'TT-0001-0001', 'whatsapp' => self::PHONE, ...$attributes]);
    }

    private function ont(Customer $customer): Asset
    {
        return Asset::factory()->create(['customer_id' => $customer->id, 'status' => AssetStatus::Installed, 'genieacs_device_id' => 'dev-1']);
    }

    private function fakeAcs(): void
    {
        Http::fake([
            'acs.local:7557/devices/*/tasks*' => Http::response([], 200),
            'acs.local:7557/devices*' => Http::response([[
                '_id' => 'dev-1',
                '_lastInform' => now()->subMinutes(2)->toIso8601String(),
                'VirtualParameters' => ['RXPower' => ['_value' => '-22.4']],
                'InternetGatewayDevice' => ['LANDevice' => ['1' => [
                    'WLANConfiguration' => ['1' => ['SSID' => ['_value' => 'WifiRumah'], 'KeyPassphrase' => ['_value' => 'sandi-lama-123']]],
                    'Hosts' => ['Host' => ['1' => ['HostName' => ['_value' => 'iPhone-Budi'], 'IPAddress' => ['_value' => '192.168.1.5'], 'Active' => ['_value' => 'true']]]],
                ]]],
            ]]),
        ]);
    }

    private function wa(string $text, string $phone = self::PHONE, ?string $id = null): ChatMessage
    {
        return new ChatMessage(ChatMessage::CHANNEL_WHATSAPP, $phone, $text, $id);
    }

    private function say(string $text, string $phone = self::PHONE): string
    {
        return collect(app(ChatBot::class)->handle($this->wa($text, $phone)))->map(fn (ChatReply $reply) => $reply->text)->implode("\n---\n");
    }

    public function test_whatsapp_menu_greets_by_first_name_and_lists_every_function_with_icons(): void
    {
        $this->customer();

        $reply = $this->say('halo');

        foreach (['👋 Halo Budi!', '🧾', '📶', '💻', '🔑', '🎧'] as $expected) {
            $this->assertStringContainsString($expected, $reply);
        }

        $this->assertStringNotContainsString('🔁', $reply);
        $this->assertDatabaseHas('chat_sessions', ['channel' => 'whatsapp', 'sender_id' => self::PHONE]);
    }

    public function test_unknown_numbers_are_told_once_and_then_ignored(): void
    {
        $first = $this->say('halo', '6289999999999');
        $second = $this->say('halo', '6289999999999');

        $this->assertStringContainsString('belum terdaftar', $first);
        $this->assertSame('', $second);
    }

    public function test_inactive_customers_are_not_served(): void
    {
        $this->customer(['status' => CustomerStatus::Inactive]);

        $this->assertStringContainsString('belum terdaftar', $this->say('tagihan'));
    }

    public function test_billing_shows_outstanding_invoices_and_the_public_payment_link(): void
    {
        $customer = $this->customer();
        Invoice::factory()->create(['customer_id' => $customer->id, 'total' => 150000, 'amount_paid' => 50000, 'status' => InvoiceStatus::Partial, 'due_date' => now()->subDays(2), 'period_start' => now()->subMonth()->startOfMonth()]);

        $reply = $this->say('1');

        $this->assertStringContainsString('💰 Tunggakan: Rp 100.000', $reply);
        $this->assertStringContainsString('⚠️', $reply);
        $this->assertStringContainsString(route('isolir.show', 'TT-0001-0001'), $reply);
    }

    public function test_billing_celebrates_when_nothing_is_owed(): void
    {
        $this->customer();

        $this->assertStringContainsString('✅ Tidak ada tunggakan', $this->say('tagihan'));
    }

    public function test_onu_status_reads_live_data_and_devices_and_the_wifi_password_is_not_in_the_status(): void
    {
        $customer = $this->customer();
        $this->ont($customer);
        $this->fakeAcs();

        $status = $this->say('onu');
        $devices = $this->say('perangkat');

        $this->assertStringContainsString('🟢 Online', $status);
        $this->assertStringContainsString('-22.40 dBm ✅ Baik', $status);
        $this->assertStringContainsString('WifiRumah', $status);
        $this->assertStringNotContainsString('sandi-lama-123', $status);
        $this->assertStringContainsString('iPhone-Budi · 192.168.1.5', $devices);
    }

    public function test_wifi_flow_asks_for_confirmation_and_only_then_changes_the_onu(): void
    {
        $customer = $this->customer();
        $this->ont($customer);
        $this->fakeAcs();

        $this->assertStringContainsString('WifiRumah', $this->say('wifi'));
        $this->assertStringContainsString('Ketik nama WiFi baru', $this->say('1'));
        $this->assertStringContainsString('password WiFi baru', $this->say('Rumah Baru'));
        $confirm = $this->say('rahasia-baru-99');

        $this->assertStringContainsString('Rumah Baru', $confirm);
        $this->assertStringNotContainsString('rahasia-baru-99', $confirm);
        $this->assertStringContainsString('Balas YA', $confirm);
        Http::assertNotSent(fn (Request $request) => $request->method() === 'POST');
        $this->assertStringNotContainsString('rahasia-baru-99', (string) DB::table('chat_sessions')->value('payload'));
        $this->assertStringNotContainsString('Rumah Baru', (string) DB::table('chat_sessions')->value('payload'));

        $this->assertStringContainsString('✅ WiFi diperbarui', $this->say('ya'));

        Http::assertSent(fn (Request $request) => $request->method() === 'POST' && str_contains($request->url(), 'dev-1/tasks')
            && collect($request['parameterValues'])->contains(fn ($row) => $row[1] === 'Rumah Baru') && collect($request['parameterValues'])->contains(fn ($row) => $row[1] === 'rahasia-baru-99'));
        $this->assertNull(ChatSession::query()->value('payload'));
        $this->assertSame('idle', ChatSession::query()->value('state'));
        $this->assertStringNotContainsString('rahasia-baru-99', json_encode(Activity::all()->toArray()));
        $this->assertTrue(Activity::where('log_name', 'Portal Pelanggan')->exists());
    }

    public function test_wifi_flow_validates_input_and_can_be_cancelled(): void
    {
        $customer = $this->customer();
        $this->ont($customer);
        $this->fakeAcs();

        $this->say('wifi');
        $this->say('1');
        $this->assertStringContainsString('1–32 karakter', $this->say(str_repeat('a', 33)));
        $this->say('Rumah');
        $this->assertStringContainsString('8–63 karakter', $this->say('pendek'));
        $this->say('password-panjang-ok');

        $this->assertStringContainsString('❌ Dibatalkan', $this->say('batal'));
        $this->assertSame('idle', ChatSession::query()->value('state'));
        Http::assertNotSent(fn (Request $request) => $request->method() === 'POST');
    }

    public function test_a_wifi_name_like_a_greeting_does_not_interrupt_the_flow(): void
    {
        $customer = $this->customer();
        $this->ont($customer);
        $this->fakeAcs();

        $this->say('wifi');
        $this->say('1');

        $this->assertStringContainsString('password WiFi baru', $this->say('hai'));
        $this->assertSame('wifi_password', ChatSession::query()->value('state'));
    }

    public function test_wifi_cannot_be_changed_while_isolated_and_the_password_can_be_viewed(): void
    {
        $customer = $this->customer(['status' => CustomerStatus::Isolated]);
        $this->ont($customer);
        $this->fakeAcs();

        $menu = $this->say('wifi');
        $this->assertStringContainsString('hanya tersedia saat layanan aktif', $menu);
        $this->assertStringNotContainsString('1️⃣ ✏️', $menu);
        $this->assertStringContainsString('sandi-lama-123', $this->say('2'));
    }

    public function test_wifi_changes_are_throttled_per_customer(): void
    {
        $customer = $this->customer();
        $this->ont($customer);
        $this->fakeAcs();

        for ($i = 0; $i < 5; $i++) {
            $this->say('wifi');
            $this->say('1');
            $this->say("Rumah {$i}");
            $this->say('password-panjang-ok');
            $this->assertStringContainsString('✅ WiFi diperbarui', $this->say('ya'));
            $this->travel(61)->seconds();
        }

        $this->say('wifi');
        $this->say('1');
        $this->say('Rumah 6');
        $this->say('password-panjang-ok');

        $this->assertStringContainsString('Terlalu sering', $this->say('ya'));
    }

    public function test_one_number_with_several_connections_picks_one_and_can_switch(): void
    {
        $first = $this->customer(['name' => 'Rumah Utama']);
        $second = $this->customer(['name' => 'Ruko', 'customer_code' => 'TT-0002-0002']);

        $list = $this->say('halo');
        $this->assertStringContainsString('2 sambungan', $list);
        $this->assertStringContainsString('Ruko · TT-0002-0002', $list);

        $this->assertStringContainsString('Halo Ruko!', $this->say('1'));
        $this->assertSame($second->id, ChatSession::query()->value('customer_id'));
        $this->assertStringContainsString('6️⃣ 🔁', $this->say('menu'));

        $this->assertStringContainsString('2 sambungan', $this->say('6'));
        $this->assertStringContainsString('Halo Rumah!', $this->say('2'));
        $this->assertNotNull($first);
    }

    public function test_telegram_must_share_a_registered_contact_before_anything_is_served(): void
    {
        $this->customer();
        $bot = app(ChatBot::class);
        $telegram = fn (?string $text, ?string $phone = null) => $bot->handle(new ChatMessage(ChatMessage::CHANNEL_TELEGRAM, '777', $text, '1', contactPhone: $phone));

        $prompt = $telegram('tagihan')[0];
        $this->assertTrue($prompt->requestContact);
        $this->assertStringNotContainsString('Tagihan', $prompt->text);

        $rejected = $telegram(null, '+62 899-9999-9999')[0];
        $this->assertStringContainsString('belum terdaftar', $rejected->text);
        $this->assertTrue($rejected->requestContact);

        $foreign = $telegram(ChatMessage::FOREIGN_CONTACT)[0];
        $this->assertStringContainsString('kontak orang lain', $foreign->text);

        $linked = $telegram(null, '0812-3456-7890');
        $this->assertStringContainsString('tertaut', $linked[0]->text);
        $this->assertTrue($linked[0]->removeKeyboard);
        $this->assertStringContainsString('Halo Budi!', $linked[1]->text);
        $this->assertNotEmpty($linked[1]->buttons);
        $this->assertSame(self::PHONE, ChatSession::where('channel', 'telegram')->value('phone'));

        $this->assertStringContainsString('Tagihan', $telegram('tagihan')[0]->text);
    }

    public function test_telegram_can_be_unlinked_and_a_changed_number_revokes_access(): void
    {
        $customer = $this->customer();
        $bot = app(ChatBot::class);
        $telegram = fn (?string $text, ?string $phone = null) => $bot->handle(new ChatMessage(ChatMessage::CHANNEL_TELEGRAM, '777', $text, '1', contactPhone: $phone));
        $telegram(null, self::PHONE);

        $customer->update(['whatsapp' => '6285555555555']);
        $revoked = $telegram('tagihan')[0];
        $this->assertTrue($revoked->requestContact);
        $this->assertNull(ChatSession::where('channel', 'telegram')->value('phone'));

        $customer->update(['whatsapp' => self::PHONE]);
        $telegram(null, self::PHONE);
        $this->assertStringContainsString('diputus', $telegram('keluar')[0]->text);
        $this->assertTrue($telegram('tagihan')[0]->requestContact);
    }

    public function test_messages_are_rate_limited_per_sender(): void
    {
        $this->customer();

        for ($i = 0; $i < 20; $i++) {
            $this->say('menu');
        }

        $this->assertSame('', $this->say('menu'));
    }

    public function test_the_dispatcher_queues_whatsapp_replies_and_sends_telegram_ones_with_buttons(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);
        $dispatcher = app(ChatDispatcher::class);

        $dispatcher->send($this->wa('x'), [new ChatReply('Halo dari bot')]);

        $message = WhatsappMessage::sole();
        $this->assertSame(WhatsappMessageType::Chat, $message->type);
        $this->assertSame(self::PHONE, $message->phone);

        $telegram = new ChatMessage(ChatMessage::CHANNEL_TELEGRAM, '777', 'x', '55');
        $dispatcher->send($telegram, [
            new ChatReply('Menu', [[['label' => '🧾 Tagihan', 'data' => 'tagihan'], ['label' => '💳 Bayar', 'url' => 'https://pay.example.com']]]),
            new ChatReply('Kontak', requestContact: true),
            new ChatReply('Rahasia diterima', deleteIncoming: true),
        ]);

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/sendMessage') && ($request['reply_markup']['inline_keyboard'][0][0]['callback_data'] ?? null) === 'tagihan' && ($request['reply_markup']['inline_keyboard'][0][1]['url'] ?? null) === 'https://pay.example.com');
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/sendMessage') && ($request['reply_markup']['keyboard'][0][0]['request_contact'] ?? false) === true);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/deleteMessage') && $request['message_id'] === 55 && $request['chat_id'] === '777');
    }

    public function test_the_job_answers_the_callback_and_sends_the_replies(): void
    {
        Queue::fake();
        $this->customer();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);
        $message = new ChatMessage(ChatMessage::CHANNEL_TELEGRAM, '777', 'menu', 'cb1', callbackId: 'cb1');

        app()->call([new HandleChatMessageJob($message->toArray()), 'handle']);

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/answerCallbackQuery') && $request['callback_query_id'] === 'cb1');
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/sendMessage'));
    }

    public function test_unused_url_helper_keeps_routes_resolvable(): void
    {
        URL::forceRootUrl('https://portal.example.com');

        $this->assertStringStartsWith('https://portal.example.com/webhooks/chat/telegram', route('webhooks.chat.telegram'));
    }
}
