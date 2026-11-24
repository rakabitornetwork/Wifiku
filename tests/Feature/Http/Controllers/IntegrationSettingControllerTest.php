<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\WhatsappMessageType;
use App\Models\Setting;
use App\Models\WhatsappMessage;
use App\Services\Settings\SettingService;
use App\Support\SettingDefinitions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class IntegrationSettingControllerTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    /**
     * @return array<string, array<string, string|null>>
     */
    private function payload(array $overrides = []): array
    {
        $settings = [];

        foreach (SettingDefinitions::ALL as $name => $definition) {
            [$group, $key] = explode('.', $name);
            $settings[$group][$key] = $definition['default'];
        }

        return ['settings' => array_replace_recursive($settings, $overrides)];
    }

    public function test_only_owner_can_open_settings(): void
    {
        $this->actingAs($this->admin())->get('/settings/integrations')->assertForbidden();
        $this->actingAs($this->owner())->get('/settings/integrations')->assertInertia(fn (Assert $page) => $page->component('Settings/Integrations')->has('groups', 7));
    }

    public function test_secret_values_are_never_sent_to_the_browser(): void
    {
        (new SettingService)->set('whatsapp', 'api_key', 'token-sangat-rahasia', encrypted: true);

        $response = $this->actingAs($this->owner())->get('/settings/integrations');

        $this->assertStringNotContainsString('token-sangat-rahasia', $response->getContent());
        $response->assertInertia(fn (Assert $page) => $page->where('groups.3.fields.3.name', 'whatsapp.api_key')->where('groups.3.fields.3.is_set', true)->where('groups.3.fields.3.value', null));
    }

    public function test_saves_values_and_encrypts_secrets(): void
    {
        $this->actingAs($this->owner())->put('/settings/integrations', $this->payload([
            'whatsapp' => ['base_url' => 'http://127.0.0.1:3000', 'api_key' => 'token-baru'],
            'company' => ['name' => 'Teslatech Net'],
        ]))->assertSessionDoesntHaveErrors();

        $settings = new SettingService;
        $this->assertSame('http://127.0.0.1:3000', $settings->get('whatsapp', 'base_url'));
        $this->assertSame('token-baru', $settings->get('whatsapp', 'api_key'));
        $this->assertSame('Teslatech Net', $settings->get('company', 'name'));
        $this->assertNotSame('token-baru', Setting::where('key', 'api_key')->value('value'));
    }

    public function test_blank_secret_keeps_the_stored_value(): void
    {
        (new SettingService)->set('genieacs', 'password', 'lama', encrypted: true);

        $this->actingAs($this->owner())->put('/settings/integrations', $this->payload(['genieacs' => ['password' => '']]));

        $this->assertSame('lama', (new SettingService)->get('genieacs', 'password'));
    }

    public function test_validates_urls_and_numbers(): void
    {
        $this->actingAs($this->owner())->put('/settings/integrations', $this->payload([
            'whatsapp' => ['base_url' => 'bukan url'],
            'billing' => ['reminder_days_before' => '99'],
        ]))->assertSessionHasErrors(['settings.whatsapp.base_url', 'settings.billing.reminder_days_before']);
    }

    public function test_whatsapp_test_message_is_queued(): void
    {
        $this->actingAs($this->owner())->post('/settings/integrations/whatsapp-test', ['phone' => '6281234567890'])->assertSessionDoesntHaveErrors();

        $this->assertSame(WhatsappMessageType::Test, WhatsappMessage::sole()->type);
    }

    private function configureEvolution(): void
    {
        $settings = new SettingService;
        $settings->set('whatsapp', 'base_url', 'http://evo.local:8080');
        $settings->set('whatsapp', 'instance', 'teslatech');
        $settings->set('whatsapp', 'api_key', 'kunci', encrypted: true);
    }

    public function test_whatsapp_status_reports_connected_instance(): void
    {
        $this->configureEvolution();
        Http::fake(['evo.local:8080/instance/connectionState/teslatech' => Http::response(['instance' => ['instanceName' => 'teslatech', 'state' => 'open']])]);

        $this->actingAs($this->owner())->postJson('/settings/integrations/whatsapp-status')
            ->assertOk()->assertJson(['connected' => true, 'state' => 'open', 'message' => 'WhatsApp terhubung. (open)']);
    }

    public function test_whatsapp_connect_returns_qr_when_not_connected(): void
    {
        $this->configureEvolution();
        Http::fake([
            'evo.local:8080/instance/connectionState/teslatech' => Http::response(['instance' => ['state' => 'close']]),
            'evo.local:8080/instance/connect/teslatech' => Http::response(['base64' => 'data:image/png;base64,AAA', 'pairingCode' => null]),
        ]);

        $this->actingAs($this->owner())->postJson('/settings/integrations/whatsapp-connect')
            ->assertOk()->assertJson(['connected' => false, 'qr' => 'data:image/png;base64,AAA']);
    }

    public function test_whatsapp_status_reports_error_and_is_owner_only(): void
    {
        $this->actingAs($this->owner())->postJson('/settings/integrations/whatsapp-status')
            ->assertOk()->assertJson(['connected' => false, 'state' => 'error', 'message' => 'WA Gateway belum dikonfigurasi.']);

        $this->actingAs($this->admin())->postJson('/settings/integrations/whatsapp-status')->assertForbidden();
    }

    public function test_reports_messages_stalled_in_queue(): void
    {
        WhatsappMessage::factory()->create(['status' => 'queued', 'created_at' => now()->subMinutes(5)]);
        WhatsappMessage::factory()->create(['status' => 'queued']);
        WhatsappMessage::factory()->create(['status' => 'sent', 'created_at' => now()->subMinutes(5)]);

        $this->actingAs($this->owner())->get('/settings/integrations')
            ->assertInertia(fn (Assert $page) => $page->where('whatsappQueue.stalled', 1)->has('whatsappQueue.oldest_at'));
    }
}
