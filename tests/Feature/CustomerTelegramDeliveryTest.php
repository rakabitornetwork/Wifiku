<?php

namespace Tests\Feature;

use App\Enums\CustomerStatus;
use App\Enums\WhatsappMessageStatus;
use App\Jobs\Network\IsolateCustomerJob;
use App\Jobs\Network\ReopenCustomerJob;
use App\Jobs\Notification\SendTelegramCustomerMessageJob;
use App\Jobs\Notification\SendTelegramMessageJob;
use App\Models\ChatSession;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Outage;
use App\Models\Router;
use App\Models\TelegramMessage;
use App\Models\WhatsappMessage;
use App\Services\Settings\SettingService;
use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\TelegramDeliveryException;
use App\Services\Whatsapp\CustomerNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class CustomerTelegramDeliveryTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    private const TOKEN = '123456789:ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    protected function setUp(): void
    {
        parent::setUp();

        $settings = new SettingService;
        $settings->set('telegram', 'bot_enabled', '1');
        $settings->set('telegram', 'bot_token', self::TOKEN, encrypted: true);
        $settings->set('whatsapp', 'enabled', '1');
    }

    private function link(string $phone = '6281234567890', string $chat = '777'): void
    {
        ChatSession::create(['channel' => 'telegram', 'sender_id' => $chat, 'phone' => $phone]);
    }

    private function customer(array $attributes = []): Customer
    {
        return Customer::factory()->create(['name' => 'Budi', 'whatsapp' => '6281234567890', ...$attributes]);
    }

    public function test_a_linked_customer_gets_each_automatic_message_on_telegram_with_a_matching_icon_and_on_whatsapp(): void
    {
        Queue::fake();
        $this->link();
        $customer = $this->customer();
        $notifier = app(CustomerNotifier::class);

        $notifier->isolated($customer);
        $notifier->reopened($customer);
        $notifier->invoiceIssued(Invoice::factory()->create(['customer_id' => $customer->id]));
        $notifier->outageResolved($customer, Outage::factory()->create(['title' => 'FO putus']));

        $texts = TelegramMessage::query()->orderBy('id')->pluck('body');
        $this->assertCount(4, $texts);
        $this->assertTrue($texts->contains(fn ($text) => str_starts_with($text, '🔴 Halo Budi') && str_contains($text, 'diisolir')));
        $this->assertTrue($texts->contains(fn ($text) => str_starts_with($text, '🟢 Halo Budi') && str_contains($text, 'aktif kembali')));
        $this->assertTrue($texts->contains(fn ($text) => str_starts_with($text, '🧾 Halo Budi')));
        $this->assertTrue($texts->contains(fn ($text) => str_starts_with($text, '✅ Halo Budi')));
        Queue::assertPushed(SendTelegramCustomerMessageJob::class, 4);
        $this->assertSame(['777'], TelegramMessage::query()->pluck('chat_id')->unique()->values()->all());
        $this->assertSame('queued', TelegramMessage::first()->status);
        $this->assertSame(4, WhatsappMessage::count());
        $this->assertStringNotContainsString('🔴', WhatsappMessage::first()->body);
    }

    public function test_delivery_modes_off_both_and_prefer(): void
    {
        Queue::fake();
        $this->link();
        $customer = $this->customer();
        $notifier = app(CustomerNotifier::class);

        (new SettingService)->set('telegram', 'customer_delivery', 'off');
        $notifier->isolated($customer);
        Queue::assertNotPushed(SendTelegramCustomerMessageJob::class);
        $this->assertSame(1, WhatsappMessage::count());

        (new SettingService)->set('telegram', 'customer_delivery', 'prefer');
        $notifier->isolated($customer);
        Queue::assertPushed(SendTelegramCustomerMessageJob::class, 1);
        $this->assertSame(1, WhatsappMessage::count());

        $unlinked = $this->customer(['whatsapp' => '6285555555555']);
        $notifier->isolated($unlinked);
        Queue::assertPushed(SendTelegramCustomerMessageJob::class, 1);
        $this->assertSame(2, WhatsappMessage::count());
    }

    public function test_the_bot_switch_and_the_token_gate_telegram_delivery_and_numbers_match_in_any_format(): void
    {
        Queue::fake();
        $this->link();
        $customer = $this->customer(['whatsapp' => '0812-3456-7890']);
        $notifier = app(CustomerNotifier::class);

        $notifier->isolated($customer);
        Queue::assertPushed(SendTelegramCustomerMessageJob::class, 1);

        (new SettingService)->set('telegram', 'bot_enabled', '0');
        $notifier->isolated($customer);
        Queue::assertPushed(SendTelegramCustomerMessageJob::class, 1);
    }

    public function test_a_customer_without_whatsapp_or_a_link_receives_nothing(): void
    {
        Queue::fake();
        $this->link('6289999999999');

        app(CustomerNotifier::class)->isolated($this->customer(['whatsapp' => '']));
        app(CustomerNotifier::class)->isolated($this->customer(['whatsapp' => '6285555555555']));

        Queue::assertNotPushed(SendTelegramCustomerMessageJob::class);
        $this->assertSame(1, WhatsappMessage::count());
        $this->assertSame(WhatsappMessageStatus::Queued, WhatsappMessage::first()->status);
    }

    public function test_the_job_sends_via_the_bot_records_the_outcome_and_unlinks_chats_that_blocked_the_bot(): void
    {
        $this->link();
        Http::fake(['api.telegram.org/*' => Http::sequence()
            ->push(['ok' => true, 'result' => []])
            ->push(['ok' => false, 'description' => 'Forbidden: bot was blocked by the user'], 403)
            ->push(['ok' => false, 'description' => 'Too Many Requests'], 429)]);
        $make = fn (string $chat) => TelegramMessage::create(['chat_id' => $chat, 'body' => 'Halo', 'status' => 'queued']);

        $first = $make('777');
        (new SendTelegramCustomerMessageJob($first->id))->handle(app(TelegramClient::class));
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/sendMessage') && $request['chat_id'] === '777' && $request['text'] === 'Halo');
        $this->assertSame('sent', $first->fresh()->status);
        $this->assertNotNull($first->fresh()->sent_at);
        $this->assertSame('6281234567890', ChatSession::first()->phone);

        $blocked = $make('777');
        (new SendTelegramCustomerMessageJob($blocked->id))->handle(app(TelegramClient::class));
        $this->assertNull(ChatSession::first()->phone);
        $this->assertSame('failed', $blocked->fresh()->status);
        $this->assertStringContainsString('tautan Telegram pelanggan dicabut', $blocked->fresh()->error);

        $flaky = $make('999');

        try {
            (new SendTelegramCustomerMessageJob($flaky->id))->handle(app(TelegramClient::class));
            $this->fail('Galat sementara harus dilempar agar dicoba ulang.');
        } catch (TelegramDeliveryException) {
            $this->assertSame('failed', $flaky->fresh()->status);
            $this->assertSame(1, $flaky->fresh()->attempts);
        }

        (new SendTelegramCustomerMessageJob($first->id))->handle(app(TelegramClient::class));
        Http::assertSentCount(3);
    }

    public function test_a_failed_isolation_or_reopen_alerts_the_admin_and_respects_the_switch(): void
    {
        Queue::fake();
        $settings = new SettingService;
        $settings->set('telegram', 'enabled', '1');
        $settings->set('telegram', 'chat_id', '-100123456789');
        $customer = $this->customer(['name' => 'Siti', 'customer_code' => 'TT-0009-0001', 'router_id' => Router::factory()->create(['name' => 'ATNET'])->id]);

        (new IsolateCustomerJob($customer))->failed(new \RuntimeException('Router timeout'));
        (new ReopenCustomerJob($customer))->failed(new \RuntimeException('Router timeout'));

        Queue::assertPushed(SendTelegramMessageJob::class, fn ($job) => str_contains($job->text, 'GAGAL dibuka kembali') && str_contains($job->text, 'Siti (TT-0009-0001)') && str_contains($job->text, 'ATNET'));
        Queue::assertPushed(SendTelegramMessageJob::class, fn ($job) => str_contains($job->text, 'Gagal mengisolir pelanggan') && str_contains($job->text, 'Router timeout'));

        $settings->set('telegram', 'alert_isolation', '0');
        (new IsolateCustomerJob($customer))->failed(new \RuntimeException('x'));
        Queue::assertPushed(SendTelegramMessageJob::class, 2);
    }

    public function test_the_dashboard_and_customer_list_surface_customers_without_whatsapp(): void
    {
        $this->customer(['whatsapp' => '']);
        $this->customer(['whatsapp' => '6285555555555']);
        $this->customer(['whatsapp' => '', 'status' => CustomerStatus::Inactive]);
        $owner = $this->owner();

        $this->actingAs($owner)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('widgets.attention.4.key', 'no_whatsapp')
            ->where('widgets.attention.4.value', 1)
            ->where('widgets.attention.4.href', '/customers?kontak=tanpa-wa'));

        $this->actingAs($owner)->get('/customers?kontak=tanpa-wa')->assertInertia(fn (Assert $page) => $page
            ->where('customers.total', 2)
            ->where('filters.kontak', 'tanpa-wa'));
        $this->actingAs($owner)->get('/customers')->assertInertia(fn (Assert $page) => $page->where('customers.total', 3)->where('filters.kontak', ''));
    }
}
