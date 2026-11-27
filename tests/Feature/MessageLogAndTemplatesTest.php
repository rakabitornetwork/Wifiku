<?php

namespace Tests\Feature;

use App\Enums\WhatsappMessageType;
use App\Models\Customer;
use App\Models\MessageTemplate;
use App\Models\TelegramMessage;
use App\Models\WhatsappMessage;
use App\Services\Messaging\MessageTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class MessageLogAndTemplatesTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    public function test_the_template_preview_lists_every_message_with_sample_data_for_both_channels(): void
    {
        $this->actingAs($this->owner())->get('/settings/message-templates')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Settings/MessageTemplates')
                ->has('templates', count(app(MessageTemplates::class)->definitions()))
                ->where('templates.0.key', 'invoice_issued')
                ->where('templates.0.whatsapp', fn ($text) => str_contains($text, 'Halo Budi Santoso') && ! str_contains($text, '{nama}'))
                ->where('templates.0.telegram', fn ($text) => str_starts_with($text, '🧾 Halo Budi Santoso')));
    }

    public function test_an_owner_override_replaces_the_default_text_and_unknown_or_missing_variables_are_rejected(): void
    {
        $templates = app(MessageTemplates::class);

        $this->assertNotSame([], $templates->validate('isolated', 'Halo {nama}, bayar di sini.'));
        $this->assertNotSame([], $templates->validate('isolated', 'Halo {nama} {tidak_ada} {link_bayar}'));
        $this->assertSame([], $templates->validate('isolated', 'Halo {nama}, bayar: {link_bayar}'));

        $templates->save('isolated', 'Halo {nama}!', null);
        $this->assertSame('Halo {nama}!', $templates->body('isolated'));
        $this->assertTrue($templates->isCustomised('isolated'));

        $templates->reset('isolated');
        $this->assertFalse($templates->isCustomised('isolated'));
    }

    public function test_the_locked_otp_template_ignores_overrides(): void
    {
        MessageTemplate::create(['key' => 'login_code', 'body' => 'palsu']);

        $this->assertStringContainsString('{kode}', app(MessageTemplates::class)->body('login_code'));
    }

    public function test_the_message_log_merges_both_channels_and_hides_sensitive_bodies(): void
    {
        $customer = Customer::factory()->create(['name' => 'Budi Santoso']);
        WhatsappMessage::factory()->create(['customer_id' => $customer->id, 'type' => WhatsappMessageType::Invoice, 'body' => 'Tagihan terbit', 'status' => 'sent']);
        WhatsappMessage::factory()->create(['type' => WhatsappMessageType::AccountSecurity, 'body' => 'Kode masuk: 987654', 'status' => 'sent']);
        TelegramMessage::create(['customer_id' => $customer->id, 'chat_id' => '777', 'type' => 'chat', 'body' => 'Password: rahasia123', 'status' => 'sent', 'is_sensitive' => true]);
        TelegramMessage::create(['chat_id' => '888', 'type' => 'reminder', 'body' => 'Pengingat', 'status' => 'failed', 'error' => 'blocked']);

        $response = $this->actingAs($this->owner())->get('/pesan')->assertOk();
        $json = json_encode($response->viewData('page')['props']['messages']);

        $this->assertStringNotContainsString('987654', $json);
        $this->assertStringNotContainsString('rahasia123', $json);
        $this->assertStringContainsString('Tagihan terbit', $json);
        $response->assertInertia(fn (Assert $page) => $page->where('messages.total', 4)->where('summary.failed_24h', 1));

        $this->get('/pesan?kanal=telegram&status=failed')->assertInertia(fn (Assert $page) => $page->where('messages.total', 1));
        $this->get('/pesan?search=Budi')->assertInertia(fn (Assert $page) => $page->where('messages.total', 2));
        $this->get('/pesan?search=987654')->assertInertia(fn (Assert $page) => $page->where('messages.total', 0));
        $this->get('/pesan?search=rahasia123')->assertInertia(fn (Assert $page) => $page->where('messages.total', 0));
    }

    public function test_a_sensitive_whatsapp_message_is_redacted_once_sent(): void
    {
        $message = WhatsappMessage::factory()->create(['type' => WhatsappMessageType::AccountSecurity, 'body' => 'Kode: 123456', 'is_sensitive' => true]);

        $message->redactIfSensitive();

        $this->assertSame(TelegramMessage::REDACTED, $message->fresh()->body);
    }

    public function test_only_owners_can_open_the_pages(): void
    {
        $this->get('/pesan')->assertRedirect();
        $this->get('/settings/message-templates')->assertRedirect();
    }
}
