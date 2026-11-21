<?php

namespace Tests\Feature\Services\Whatsapp;

use App\Enums\WhatsappMessageStatus;
use App\Enums\WhatsappMessageType;
use App\Jobs\Notification\SendWhatsappMessageJob;
use App\Models\WhatsappMessage;
use App\Services\Settings\SettingService;
use App\Services\Whatsapp\WhatsappDeliveryException;
use App\Services\Whatsapp\WhatsappNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WhatsappNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function configure(?string $token = 'token-rahasia'): void
    {
        $settings = new SettingService;
        $settings->set('whatsapp', 'base_url', 'http://wa.local:8080');
        $settings->set('whatsapp', 'instance', 'teslatech');
        $settings->set('whatsapp', 'api_key', $token, encrypted: true);
    }

    private function service(): WhatsappNotificationService
    {
        return $this->app->make(WhatsappNotificationService::class);
    }

    public function test_queue_records_message_in_outbox_and_dispatches_delivery(): void
    {
        Queue::fake();

        $message = $this->service()->queue('6281234567890', WhatsappMessageType::Invoice, 'Halo');

        $this->assertSame(WhatsappMessageStatus::Queued, $message->status);
        Queue::assertPushed(SendWhatsappMessageJob::class);
    }

    public function test_sends_text_to_evolution_instance_with_apikey_header(): void
    {
        $this->configure();
        Http::fake(['wa.local:8080/message/sendText/teslatech' => Http::response(['key' => ['id' => 'x']])]);
        $message = WhatsappMessage::factory()->create(['phone' => '6281234567890', 'body' => 'Halo Budi']);

        $this->service()->send($message);

        Http::assertSent(fn (Request $request) => $request->url() === 'http://wa.local:8080/message/sendText/teslatech'
            && $request['number'] === '6281234567890'
            && $request['text'] === 'Halo Budi'
            && $request->hasHeader('apikey', 'token-rahasia'));
        $message->refresh();
        $this->assertSame(WhatsappMessageStatus::Sent, $message->status);
        $this->assertNotNull($message->sent_at);
        $this->assertSame(1, $message->attempts);
    }

    public function test_gateway_error_marks_message_failed_and_throws_for_retry(): void
    {
        $this->configure();
        Http::fake(['*' => Http::response(['error' => 'not connected'], 500)]);
        $message = WhatsappMessage::factory()->create();

        try {
            $this->service()->send($message);
            $this->fail('Expected a delivery exception.');
        } catch (WhatsappDeliveryException) {
            // diharapkan
        }

        $this->assertSame(WhatsappMessageStatus::Failed, $message->fresh()->status);
        $this->assertNotNull($message->fresh()->error);
    }

    public function test_unconfigured_gateway_fails_message_without_calling_out(): void
    {
        $message = WhatsappMessage::factory()->create();

        $this->service()->send($message);

        Http::assertNothingSent();
        $this->assertSame(WhatsappMessageStatus::Failed, $message->fresh()->status);
        $this->assertSame('WA Gateway belum dikonfigurasi.', $message->fresh()->error);
    }

    public function test_disabled_gateway_fails_message_without_calling_out(): void
    {
        $this->configure();
        (new SettingService)->set('whatsapp', 'enabled', '0');
        $message = WhatsappMessage::factory()->create();

        $this->service()->send($message);

        Http::assertNothingSent();
        $this->assertSame(WhatsappMessageStatus::Failed, $message->fresh()->status);
    }

    public function test_already_sent_message_is_not_sent_again(): void
    {
        $this->configure();
        Http::fake();
        $message = WhatsappMessage::factory()->create(['status' => WhatsappMessageStatus::Sent]);

        $this->service()->send($message);

        Http::assertNothingSent();
    }
}
