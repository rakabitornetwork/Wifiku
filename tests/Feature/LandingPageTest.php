<?php

namespace Tests\Feature;

use App\Http\Controllers\LandingController;
use App\Models\Package;
use App\Models\VpnServer;
use App\Services\Settings\SettingService;
use App\Support\LandingContent;
use App\Support\SettingDefinitions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    private function setting(string $key, ?string $value): void
    {
        [$group, $name] = explode('.', $key);
        app(SettingService::class)->set($group, $name, $value);
    }

    public function test_company_identity_and_contacts_come_from_settings(): void
    {
        $this->setting('company.name', 'Wifi Contoh');
        $this->setting('company.cs_whatsapp', '6281234567890');
        $this->setting('company.email', 'halo@contoh.test');
        $this->setting('company.address', 'Jl. Contoh No. 1, Indramayu');
        $this->setting('company.instagram', 'https://instagram.com/contoh');

        $this->get('/')->assertInertia(
            fn (Assert $page) => $page
                ->where('company.name', 'Wifi Contoh')
                ->where('company.whatsapp', '6281234567890')
                ->where('company.whatsapp_display', '081234567890')
                ->where('company.email', 'halo@contoh.test')
                ->where('company.address', 'Jl. Contoh No. 1, Indramayu')
                ->where('company.instagram', 'https://instagram.com/contoh')
                ->where('company.facebook', null)
                ->where('sections.about.eyebrow', 'Tentang Wifi Contoh'),
        );
    }

    public function test_unfilled_contacts_are_null_so_the_page_can_hide_them(): void
    {
        $this->get('/')->assertInertia(
            fn (Assert $page) => $page->where('company.whatsapp', null)->where('company.whatsapp_display', null)->where('company.email', null)->where('company.address', null),
        );
    }

    public function test_only_active_packages_marked_for_the_landing_are_listed_cheapest_first_without_duplicates(): void
    {
        Package::factory()->create(['name' => 'Keluarga', 'price' => 150000, 'rate_limit' => '20M/20M', 'show_on_landing' => true, 'landing_highlight' => true, 'landing_description' => 'Buat keluarga.']);
        Package::factory()->create(['name' => 'Hemat', 'price' => 120000, 'rate_limit' => '10M/10M', 'show_on_landing' => true]);
        Package::factory()->create(['name' => 'Hemat', 'price' => 120000, 'rate_limit' => '10M/10M', 'show_on_landing' => true]);
        Package::factory()->create(['name' => 'Internal', 'price' => 50000, 'show_on_landing' => false]);
        Package::factory()->create(['name' => 'Nonaktif', 'price' => 90000, 'show_on_landing' => true, 'is_active' => false]);

        $this->get('/')->assertInertia(
            fn (Assert $page) => $page
                ->has('plans', 2)
                ->where('plans.0', ['name' => 'Hemat', 'price' => 120000, 'speed' => '10 Mbps', 'description' => null, 'highlight' => false])
                ->where('plans.1', ['name' => 'Keluarga', 'price' => 150000, 'speed' => '20 Mbps', 'description' => 'Buat keluarga.', 'highlight' => true]),
        );
    }

    public function test_without_flagged_packages_the_plan_list_is_empty(): void
    {
        Package::factory()->create(['show_on_landing' => false]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page->has('plans', 0));
    }

    public function test_speed_labels_are_friendly_and_unknown_formats_are_hidden(): void
    {
        $this->assertSame('20 Mbps', LandingController::speedLabel('20M/20M'));
        $this->assertSame('512 Kbps', LandingController::speedLabel('512k/512k'));
        $this->assertSame('1 Gbps', LandingController::speedLabel('1G/1G'));
        $this->assertSame('1.5 Mbps', LandingController::speedLabel('1.5M/1.5M'));
        $this->assertNull(LandingController::speedLabel('abc'));
        $this->assertNull(LandingController::speedLabel(null));
    }

    public function test_vpn_section_reflects_price_registration_and_active_protocols(): void
    {
        VpnServer::factory()->create(['l2tp_enabled' => true, 'sstp_enabled' => false]);
        $this->setting('vpn.member_price', '30000');
        $this->setting('vpn.member_registration_open', '0');

        $this->get('/')->assertInertia(
            fn (Assert $page) => $page->where('vpn.price', 30000)->where('vpn.registrationOpen', false)->where('vpn.protocols', ['L2TP']),
        );
    }

    public function test_no_secret_settings_or_credentials_are_sent_to_visitors(): void
    {
        $this->setting('whatsapp.api_key', 'RAHASIA-API-KEY-WA');
        app(SettingService::class)->set('whatsapp', 'api_key', 'RAHASIA-API-KEY-WA', encrypted: true);
        $this->setting('genieacs.password', 'RAHASIA-GENIEACS');
        $this->setting('whatsapp.base_url', 'https://rahasia-gateway.test');

        $html = $this->get('/')->getContent();

        foreach (['RAHASIA-API-KEY-WA', 'RAHASIA-GENIEACS', 'rahasia-gateway.test', 'api_key', 'private_key', 'merchant_code'] as $secret) {
            $this->assertStringNotContainsString($secret, $html);
        }
    }

    public function test_page_props_are_limited_to_what_the_landing_needs(): void
    {
        $this->get('/')->assertInertia(fn (Assert $page) => $page->has('company')->has('sections')->has('plans')->has('vpn')->has('staff')->has('seo')->missing('settings'));
    }

    public function test_seo_json_ld_only_contains_filled_details(): void
    {
        $this->setting('company.name', 'Wifi Contoh');
        $this->setting('company.cs_whatsapp', '6281234567890');
        $this->setting('company.instagram', 'https://instagram.com/contoh');

        $this->get('/')->assertInertia(
            fn (Assert $page) => $page
                ->where('seo.title', 'Wifi Contoh — Pemasangan WiFi Rumahan Profesional')
                ->where('seo.canonical', url('/'))
                ->where('seo.jsonLd.name', 'Wifi Contoh')
                ->where('seo.jsonLd.telephone', '+6281234567890')
                ->where('seo.jsonLd.sameAs', ['https://instagram.com/contoh'])
                ->missing('seo.jsonLd.email')
                ->missing('seo.jsonLd.address'),
        );
    }

    public function test_default_content_has_every_section_and_no_unreplaced_placeholders(): void
    {
        $sections = LandingContent::sections('Wifi Contoh');

        foreach (['hero', 'services', 'about', 'benefits', 'process', 'pricing', 'testimonials', 'cta', 'contact', 'vpn', 'footer'] as $key) {
            $this->assertArrayHasKey($key, $sections);
        }
        $this->assertCount(4, $sections['services']['items']);
        $this->assertStringNotContainsString('{company}', json_encode($sections));
        $this->assertStringContainsString('Wifi Contoh', json_encode($sections));
        $this->assertStringNotContainsString('{company}', json_encode(LandingContent::terms('Wifi Contoh')));
    }

    public function test_terms_of_service_page_is_public_and_names_the_company(): void
    {
        $this->setting('company.name', 'Wifi Contoh');

        $this->get('/terms-of-service')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('Public/Terms')->where('company.name', 'Wifi Contoh')->has('terms.paragraphs', 9),
        );
        $this->assertStringContainsString('Wifi Contoh', json_encode(LandingContent::terms('Wifi Contoh')['intro']));
    }

    public function test_packages_can_be_flagged_for_the_landing_from_the_admin_form(): void
    {
        $package = Package::factory()->create(['show_on_landing' => false]);
        $payload = ['router_id' => null, 'name' => $package->name, 'mikrotik_profile' => $package->mikrotik_profile, 'rate_limit' => '20M/20M', 'price' => 150000, 'is_active' => true];

        $this->actingAs($this->admin())->put("/packages/{$package->id}", [...$payload, 'show_on_landing' => true, 'landing_highlight' => true, 'landing_description' => 'Buat keluarga.'])->assertSessionDoesntHaveErrors();
        $this->assertTrue($package->fresh()->show_on_landing);
        $this->assertSame('Buat keluarga.', $package->fresh()->landing_description);

        $this->put("/packages/{$package->id}", [...$payload, 'landing_description' => str_repeat('a', 161)])->assertSessionHasErrors('landing_description');
    }

    public function test_company_contact_settings_validate_urls_and_emails(): void
    {
        $settings = [];

        foreach (SettingDefinitions::ALL as $name => $definition) {
            [$group, $key] = explode('.', $name);
            $settings[$group][$key] = $definition['default'];
        }

        $owner = $this->owner();
        $this->actingAs($owner)->put('/settings/integrations', ['settings' => array_replace_recursive($settings, ['company' => ['instagram' => 'bukan-url']])])->assertSessionHasErrors('settings.company.instagram');
        $this->put('/settings/integrations', ['settings' => array_replace_recursive($settings, ['company' => ['email' => 'bukan-email']])])->assertSessionHasErrors('settings.company.email');
        $this->put('/settings/integrations', ['settings' => array_replace_recursive($settings, ['company' => ['email' => 'halo@contoh.test', 'instagram' => 'https://instagram.com/contoh']])])->assertSessionDoesntHaveErrors();
        $this->assertSame('halo@contoh.test', app(SettingService::class)->get('company', 'email'));
    }
}
