<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\CustomerStatus;
use App\Enums\InvoiceStatus;
use App\Models\Asset;
use App\Models\Customer;
use App\Models\CustomerLoginCode;
use App\Models\Invoice;
use App\Models\VpnMember;
use App\Models\WhatsappMessage;
use App\Services\Portal\CustomerLoginService;
use App\Services\Settings\SettingService;
use App\Services\Whatsapp\CustomerNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class CustomerPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function customer(array $attributes = []): Customer
    {
        return Customer::factory()->create(['customer_code' => 'TT-0001-0001', 'whatsapp' => '6281234567890', ...$attributes]);
    }

    private function lastOtp(): string
    {
        preg_match('/\*(\d{6})\*/', (string) WhatsappMessage::latest('id')->value('body'), $match);

        return $match[1] ?? '';
    }

    private function login(Customer $customer): void
    {
        $this->post('/portal/kode', ['whatsapp' => $customer->whatsapp]);
        $this->post('/portal/verifikasi', ['otp' => $this->lastOtp()])->assertRedirect('/pelanggan');
    }

    private function configureGenieAcs(): void
    {
        $settings = new SettingService;
        $settings->set('genieacs', 'base_url', 'http://acs.local:7557');
    }

    private function ont(Customer $customer): Asset
    {
        return Asset::factory()->create(['customer_id' => $customer->id, 'status' => AssetStatus::Installed, 'genieacs_device_id' => 'dev-1']);
    }

    public function test_otp_login_sends_a_six_digit_code_to_the_whatsapp_number_and_opens_the_portal(): void
    {
        $customer = $this->customer();

        $this->post('/portal/kode', ['whatsapp' => '0812-3456-7890'])->assertRedirect('/portal');

        $message = WhatsappMessage::sole();
        $this->assertSame('6281234567890', $message->phone);
        $this->assertMatchesRegularExpression('/\*\d{6}\*/', $message->body);
        $this->assertNotSame($this->lastOtp(), CustomerLoginCode::sole()->code_hash);
        $this->get('/portal')->assertInertia(fn (Assert $page) => $page->where('pendingPhone', '0812****7890'));

        $this->post('/portal/verifikasi', ['otp' => $this->lastOtp()])->assertRedirect('/pelanggan');
        $this->assertSame(0, CustomerLoginCode::count());
        $this->get('/pelanggan')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Public/Portal/Dashboard')->where('customer.customer_code', $customer->customer_code)->where('hasMultipleAccounts', false));
    }

    public function test_phone_numbers_are_normalised_and_invalid_ones_rejected(): void
    {
        $this->assertSame('6281234567890', CustomerLoginService::normalizePhone('081234567890'));
        $this->assertSame('6281234567890', CustomerLoginService::normalizePhone('+62 812-3456-7890'));
        $this->assertSame('6281234567890', CustomerLoginService::normalizePhone('81234567890'));
        $this->assertNull(CustomerLoginService::normalizePhone('12345'));
        $this->assertNull(CustomerLoginService::normalizePhone('abc'));

        $this->post('/portal/kode', ['whatsapp' => '12345'])->assertSessionHasErrors('whatsapp');
        $this->assertSame(0, WhatsappMessage::count());
    }

    public function test_numbers_stored_in_local_format_still_match(): void
    {
        $this->customer(['whatsapp' => '0812-3456-7890']);

        $this->post('/portal/kode', ['whatsapp' => '6281234567890']);

        $this->assertSame(1, WhatsappMessage::count());
    }

    public function test_unknown_inactive_or_blank_numbers_get_the_same_response_but_no_code(): void
    {
        $this->customer(['whatsapp' => '6281111111111', 'status' => CustomerStatus::Inactive]);
        $this->customer(['customer_code' => 'TT-B', 'whatsapp' => '']);

        foreach (['082222222222', '081111111111'] as $number) {
            $this->post('/portal/kode', ['whatsapp' => $number])->assertRedirect('/portal')->assertSessionHasNoErrors();
        }

        $this->assertSame(0, WhatsappMessage::count());
        $this->post('/portal/verifikasi', ['otp' => '123456'])->assertSessionHasErrors('otp');
    }

    public function test_wrong_codes_are_limited_and_resend_is_throttled(): void
    {
        $this->customer();
        $this->post('/portal/kode', ['whatsapp' => '081234567890']);
        $this->post('/portal/kode', ['whatsapp' => '081234567890']);
        $this->post('/portal/kirim-ulang');
        $this->assertSame(1, WhatsappMessage::count());

        $real = $this->lastOtp();
        $wrong = $real === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $this->post('/portal/verifikasi', ['otp' => $wrong])->assertSessionHasErrors('otp');
        }

        $this->post('/portal/verifikasi', ['otp' => $real])->assertSessionHasErrors('otp');
        $this->get('/pelanggan')->assertRedirect('/portal');
    }

    public function test_resend_after_the_wait_sends_again_but_codes_per_hour_are_capped(): void
    {
        $this->customer();
        $this->post('/portal/kode', ['whatsapp' => '081234567890']);

        for ($i = 0; $i < 6; $i++) {
            $this->travel(61)->seconds();
            $this->post('/portal/kirim-ulang');
        }

        $this->assertSame(5, WhatsappMessage::count());
    }

    public function test_expired_code_is_rejected(): void
    {
        $this->customer();
        $this->post('/portal/kode', ['whatsapp' => '081234567890']);
        $otp = $this->lastOtp();
        CustomerLoginCode::query()->update(['expires_at' => now()->subMinute()]);

        $this->post('/portal/verifikasi', ['otp' => $otp])->assertSessionHasErrors('otp');
    }

    public function test_one_number_with_several_connections_chooses_one_and_cannot_pick_foreign_ones(): void
    {
        $first = $this->customer(['name' => 'Rumah Utama']);
        $second = $this->customer(['customer_code' => 'TT-0002-0002', 'name' => 'Ruko']);
        $foreign = $this->customer(['customer_code' => 'TT-9', 'whatsapp' => '6289999999999']);

        $this->post('/portal/kode', ['whatsapp' => '081234567890']);
        $this->post('/portal/verifikasi', ['otp' => $this->lastOtp()])->assertRedirect('/pelanggan/pilih');

        $this->get('/pelanggan')->assertRedirect('/pelanggan/pilih');
        $this->get('/pelanggan/pilih')->assertOk()->assertInertia(fn (Assert $page) => $page->has('accounts', 2)->where('accounts.0.name', 'Ruko'));
        $this->post('/pelanggan/pilih', ['customer_id' => $foreign->id])->assertNotFound();
        $this->get('/pelanggan')->assertRedirect('/pelanggan/pilih');

        $this->post('/pelanggan/pilih', ['customer_id' => $second->id])->assertRedirect('/pelanggan');
        $this->get('/pelanggan')->assertOk()->assertInertia(fn (Assert $page) => $page->where('customer.name', 'Ruko')->where('hasMultipleAccounts', true));
        $this->assertNotSame($first->id, $second->id);
    }

    public function test_session_ends_when_the_customers_number_no_longer_matches(): void
    {
        $customer = $this->customer();
        $this->login($customer);
        $this->get('/pelanggan')->assertOk();

        $customer->update(['whatsapp' => '6285555555555']);

        $this->get('/pelanggan')->assertRedirect('/portal');
    }

    public function test_portal_requires_login_and_ends_when_account_is_deactivated_or_session_is_too_old(): void
    {
        $this->get('/pelanggan')->assertRedirect('/portal');
        $this->getJson('/pelanggan/onu')->assertUnauthorized();

        $customer = $this->customer();
        $this->login($customer);
        $this->get('/pelanggan')->assertOk();

        $this->travel(13)->hours();
        $this->get('/pelanggan')->assertRedirect('/portal');
        $this->travelBack();

        $this->login($customer);
        $customer->update(['status' => CustomerStatus::Inactive]);
        $this->get('/pelanggan')->assertRedirect('/portal');
    }

    public function test_dashboard_only_shows_own_invoices_and_never_secrets(): void
    {
        $customer = $this->customer();
        $mine = Invoice::factory()->create(['customer_id' => $customer->id, 'total' => 150000]);
        Invoice::factory()->create(['customer_id' => $customer->id, 'status' => InvoiceStatus::Cancelled, 'period_start' => now()->subMonth()->startOfMonth()]);
        $other = Invoice::factory()->create(['total' => 999000]);
        $this->login($customer);

        $response = $this->get('/pelanggan')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('invoices', 1)
            ->where('invoices.0.invoice_number', $mine->invoice_number)
            ->where('outstanding', 150000));

        $content = $response->getContent();
        $this->assertStringNotContainsString($other->invoice_number, $content);
        $this->assertStringNotContainsString($customer->pppoe_username, $content);
        $this->assertStringNotContainsString('pppoe_password', $content);
    }

    public function test_pay_rejects_invoices_of_other_customers(): void
    {
        $customer = $this->customer();
        $other = Invoice::factory()->create();
        $this->login($customer);

        $this->post('/pelanggan/bayar', ['invoice_number' => $other->invoice_number, 'channel' => 'QRIS'])->assertNotFound();
    }

    public function test_onu_status_comes_from_genieacs_including_the_current_wifi_password_but_never_cached(): void
    {
        $this->configureGenieAcs();
        $customer = $this->customer();
        $this->ont($customer);
        Asset::factory()->create(['customer_id' => null, 'status' => AssetStatus::Installed, 'genieacs_device_id' => 'dev-other']);
        Http::fake(['acs.local:7557/devices*' => Http::response([[
            '_id' => 'dev-1',
            '_lastInform' => now()->subMinutes(2)->toIso8601String(),
            'VirtualParameters' => ['RXPower' => ['_value' => '-22.4']],
            'InternetGatewayDevice' => ['LANDevice' => ['1' => ['WLANConfiguration' => ['1' => [
                'SSID' => ['_value' => 'WifiRumah'],
                'KeyPassphrase' => ['_value' => 'rahasia-wifi-lama'],
            ]]]]],
        ]])]);
        $this->login($customer);

        $response = $this->getJson('/pelanggan/onu')->assertOk()->assertJson(['available' => true, 'online' => true, 'rx_power' => -22.4, 'rx_quality' => 'good', 'ssid' => 'WifiRumah', 'wifi_password' => 'rahasia-wifi-lama']);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->get('/pelanggan')->assertOk();
        $this->assertStringNotContainsString('rahasia-wifi-lama', $this->get('/pelanggan')->getContent());
        Http::assertSent(fn (Request $request) => str_contains(urldecode($request->url()), 'dev-1'));
        Http::assertNotSent(fn (Request $request) => str_contains(urldecode($request->url()), 'dev-other'));
    }

    public function test_onu_status_degrades_gracefully_when_genieacs_is_down_or_there_is_no_ont(): void
    {
        $this->configureGenieAcs();
        $customer = $this->customer();
        $this->login($customer);

        $this->getJson('/pelanggan/onu')->assertOk()->assertJson(['available' => false, 'online' => null]);

        $this->ont($customer);
        Http::fake(['*' => Http::response('error', 500)]);

        $this->getJson('/pelanggan/onu')->assertOk()->assertJson(['available' => false])->assertJsonPath('message', fn ($message) => is_string($message));
    }

    public function test_customer_can_change_wifi_on_their_own_ont_only(): void
    {
        $this->configureGenieAcs();
        $customer = $this->customer();
        $this->ont($customer);
        Asset::factory()->create(['customer_id' => null, 'status' => AssetStatus::Installed, 'genieacs_device_id' => 'dev-other']);
        Http::fake(['*' => Http::response([], 200)]);
        $this->login($customer);

        $this->post('/pelanggan/wifi', ['ssid' => 'Rumah Baru', 'password' => 'password-baru-1', 'password_confirmation' => 'password-baru-1'])->assertSessionHasNoErrors();

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && str_contains($request->url(), 'dev-1/tasks')
            && $request['name'] === 'setParameterValues'
            && collect($request['parameterValues'])->contains(fn ($row) => $row[1] === 'Rumah Baru')
            && collect($request['parameterValues'])->contains(fn ($row) => $row[1] === 'password-baru-1'));
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'dev-other'));
        $this->assertDatabaseHas('activity_log', ['log_name' => 'Portal Pelanggan']);
        $this->assertStringNotContainsString('password-baru-1', (string) json_encode(Activity::all()->toArray()));
    }

    public function test_wifi_form_is_validated_and_blocked_for_isolated_customers(): void
    {
        $this->configureGenieAcs();
        $customer = $this->customer();
        $this->ont($customer);
        Http::fake(['*' => Http::response([], 200)]);
        $this->login($customer);

        $this->post('/pelanggan/wifi', ['ssid' => '', 'password' => 'pendek', 'password_confirmation' => 'beda'])->assertSessionHasErrors(['ssid', 'password']);
        $this->post('/pelanggan/wifi', ['ssid' => "Bad\nName", 'password' => 'password-baru-1', 'password_confirmation' => 'password-baru-1'])->assertSessionHasErrors('ssid');
        $this->post('/pelanggan/wifi', ['ssid' => str_repeat('a', 33), 'password' => 'password-baru-1', 'password_confirmation' => 'password-baru-1'])->assertSessionHasErrors('ssid');
        Http::assertNothingSent();

        $customer->update(['status' => CustomerStatus::Isolated]);
        $this->post('/pelanggan/wifi', ['ssid' => 'Rumah', 'password' => 'password-baru-1', 'password_confirmation' => 'password-baru-1']);
        Http::assertNothingSent();
        $this->get('/pelanggan')->assertInertia(fn (Assert $page) => $page->where('canChangeWifi', false));
    }

    public function test_logout_clears_the_portal_session_and_admin_login_stays_separate(): void
    {
        $customer = $this->customer();
        $this->login($customer);

        $this->post('/pelanggan/keluar')->assertRedirect('/portal');
        $this->get('/pelanggan')->assertRedirect('/portal');
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_invoice_and_reminder_messages_include_the_customer_code_and_portal_link(): void
    {
        $customer = $this->customer();
        $invoice = Invoice::factory()->create(['customer_id' => $customer->id]);

        app(CustomerNotifier::class)->invoiceIssued($invoice);
        app(CustomerNotifier::class)->invoiceReminder($invoice);

        $bodies = WhatsappMessage::orderBy('id')->pluck('body');
        $this->assertCount(2, $bodies);
        $bodies->each(fn (string $body) => $this->assertStringContainsString('Kode pelanggan: TT-0001-0001', $body));
        $this->assertStringContainsString(route('portal.login'), $bodies[0]);
    }

    public function test_the_shared_login_page_serves_both_services_and_old_urls_redirect(): void
    {
        $this->get('/portal')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Public/Portal/Login')->where('service', 'pppoe'));
        $this->get('/portal?layanan=vpn')->assertOk()->assertInertia(fn (Assert $page) => $page->where('service', 'vpn')->has('vpnRegistrationOpen'));
        $this->get('/portal?layanan=lainnya')->assertInertia(fn (Assert $page) => $page->where('service', 'pppoe'));

        $this->get('/pelanggan/masuk')->assertRedirect('/portal')->assertStatus(301);
        $this->get('/vpn-tunnel/masuk')->assertRedirect('/portal?layanan=vpn')->assertStatus(301);
    }

    public function test_sessions_of_each_service_stay_separate_on_the_shared_page(): void
    {
        $this->login($this->customer());

        $this->get('/portal')->assertRedirect('/pelanggan');
        $this->get('/portal?layanan=vpn')->assertOk();

        $member = VpnMember::factory()->create();
        $this->actingAs($member, 'member')->get('/portal?layanan=vpn')->assertRedirect('/vpn-tunnel/akun');
    }
}
