<?php

namespace Tests\Feature\Vpn;

use App\Actions\Payment\ApplyPayment;
use App\Actions\Payment\RecordCashPayment;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentGatewayDriver;
use App\Enums\VpnAccountStatus;
use App\Jobs\Vpn\ExpirePendingVpnAccountsJob;
use App\Jobs\Vpn\SyncVpnAccountJob;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\VpnAccount;
use App\Models\VpnMember;
use App\Models\VpnServer;
use App\Models\WhatsappMessage;
use App\Services\Settings\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesUsers;
use Tests\Support\FakesRouterOs;
use Tests\TestCase;

class VpnMemberTest extends TestCase
{
    use CreatesUsers;
    use FakesRouterOs;
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function registration(array $overrides = []): array
    {
        return ['name' => 'Budi Santoso', 'email' => 'Budi@Example.com', 'whatsapp' => '0812-3456-7890', 'password' => 'rahasia-123', 'password_confirmation' => 'rahasia-123', ...$overrides];
    }

    private function member(array $attributes = []): VpnMember
    {
        return VpnMember::factory()->create($attributes);
    }

    private function createAccount(VpnMember $member, array $overrides = [])
    {
        return $this->actingAs($member, 'member')->post('/vpn-tunnel/akun', ['label' => 'Router Rumah', 'protocol' => 'sstp', 'forwards' => [22, 80, 8291, 8728], ...$overrides]);
    }

    public function test_landing_page_is_public_and_shows_price_and_quota(): void
    {
        VpnServer::factory()->create(['sstp_enabled' => true, 'l2tp_enabled' => false]);

        $this->get('/vpn-tunnel')->assertInertia(
            fn (Assert $page) => $page->component('Public/Vpn/Landing')->where('price', 25000)->where('maxAccounts', 5)->where('registrationOpen', true)->where('protocols', ['SSTP'])->where('loggedIn', false),
        );
    }

    public function test_visitor_can_register_and_is_logged_in_as_member_not_admin(): void
    {
        $this->post('/vpn-tunnel/daftar', $this->registration())->assertRedirect('/vpn-tunnel/akun');

        $member = VpnMember::sole();
        $this->assertSame('budi@example.com', $member->email);
        $this->assertSame('62812345678'.'90', $member->whatsapp);
        $this->assertNotSame('rahasia-123', $member->password);
        $this->assertAuthenticatedAs($member, 'member');
        $this->assertGuest('web');
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_registration_validates_and_rejects_duplicate_email(): void
    {
        $this->member(['email' => 'budi@example.com']);

        $this->post('/vpn-tunnel/daftar', $this->registration())->assertSessionHasErrors('email');
        $this->post('/vpn-tunnel/daftar', $this->registration(['email' => 'baru@example.com', 'password_confirmation' => 'beda']))->assertSessionHasErrors('password');
        $this->post('/vpn-tunnel/daftar', $this->registration(['email' => 'baru@example.com', 'whatsapp' => '12345']))->assertSessionHasErrors('whatsapp');
    }

    public function test_registration_can_be_closed_from_settings(): void
    {
        app(SettingService::class)->set('vpn', 'member_registration_open', '0');

        $this->get('/vpn-tunnel/daftar')->assertRedirect('/vpn-tunnel');
        $this->post('/vpn-tunnel/daftar', $this->registration())->assertForbidden();
        $this->assertSame(0, VpnMember::count());
    }

    public function test_member_login_and_wrong_password(): void
    {
        $member = $this->member(['email' => 'budi@example.com', 'password' => 'rahasia-123']);

        $this->post('/vpn-tunnel/masuk', ['email' => 'budi@example.com', 'password' => 'salah'])->assertSessionHasErrors('email');
        $this->post('/vpn-tunnel/masuk', ['email' => 'BUDI@example.com', 'password' => 'rahasia-123'])->assertRedirect('/vpn-tunnel/akun');
        $this->assertAuthenticatedAs($member, 'member');
    }

    public function test_deactivated_member_cannot_login(): void
    {
        $this->member(['email' => 'budi@example.com', 'password' => 'rahasia-123', 'is_active' => false]);

        $this->post('/vpn-tunnel/masuk', ['email' => 'budi@example.com', 'password' => 'rahasia-123'])->assertSessionHasErrors('email');
    }

    public function test_guest_is_sent_to_the_member_login_and_admin_session_does_not_grant_access(): void
    {
        $this->get('/vpn-tunnel/akun')->assertRedirect('/portal?layanan=vpn');
        $this->actingAs($this->admin())->get('/vpn-tunnel/akun')->assertRedirect('/portal?layanan=vpn');
    }

    public function test_creating_an_account_is_pending_with_invoice_ip_ports_and_nothing_pushed_to_the_router(): void
    {
        Queue::fake([SyncVpnAccountJob::class]);
        $server = VpnServer::factory()->create(['pool_start' => '192.168.127.2', 'pool_end' => '192.168.127.254', 'local_address' => '192.168.127.1', 'port_block_offset' => 300]);
        $fake = $this->fakeRouterOs($server->router);
        $member = $this->member();

        $response = $this->createAccount($member);

        $account = VpnAccount::sole();
        $response->assertRedirect(route('vpn-portal.show', $account->portal_token));
        $this->assertSame(VpnAccountStatus::Pending, $account->status);
        $this->assertSame($member->id, $account->vpn_member_id);
        $this->assertSame('192.168.127.2', $account->remote_address);
        $this->assertSame(25000, $account->monthly_price);
        $this->assertNull($account->expires_at);
        $this->assertSame([30222, 30280, 30291, 30228], array_column($account->forwards, 'public_port'));
        $this->assertGreaterThanOrEqual(14, strlen($account->password));
        $this->assertSame($member->whatsapp, $account->whatsapp);

        $invoice = Invoice::sole();
        $this->assertSame($account->id, $invoice->vpn_account_id);
        $this->assertSame(25000, $invoice->total);
        $this->assertSame([], $fake->rows('/ppp/secret'));
        Queue::assertNotPushed(SyncVpnAccountJob::class);
        $this->assertStringContainsString('Tagihan aktivasi', WhatsappMessage::sole()->body);
    }

    public function test_only_selected_services_are_forwarded(): void
    {
        VpnServer::factory()->create();

        $this->createAccount($this->member(), ['forwards' => [8291]]);

        $this->assertSame([8291], array_column(VpnAccount::sole()->forwards, 'target_port'));
    }

    public function test_quota_is_enforced_per_member_and_configurable(): void
    {
        VpnServer::factory()->create();
        $member = $this->member();
        $other = $this->member();
        app(SettingService::class)->set('vpn', 'member_max_accounts', '2');

        $this->createAccount($member, ['label' => 'A']);
        $this->createAccount($member, ['label' => 'B']);
        $this->createAccount($member, ['label' => 'C'])->assertSessionHasErrors('label');
        $this->createAccount($other, ['label' => 'D'])->assertSessionDoesntHaveErrors();

        $this->assertSame(2, $member->accounts()->count());
        $this->assertSame(1, $other->accounts()->count());
    }

    public function test_default_quota_is_five_and_price_follows_the_setting(): void
    {
        VpnServer::factory()->create();
        $member = $this->member();
        app(SettingService::class)->set('vpn', 'member_price', '30000');

        foreach (range(1, 5) as $number) {
            $this->createAccount($member, ['label' => "Akun {$number}"])->assertSessionDoesntHaveErrors();
        }
        $this->createAccount($member, ['label' => 'Akun 6'])->assertSessionHasErrors('label');

        $this->assertSame(5, $member->accounts()->count());
        $this->assertSame(30000, $member->accounts()->first()->monthly_price);
        $this->assertSame(5, count(array_unique($member->accounts()->pluck('remote_address')->all())));
    }

    public function test_protocol_must_exist_on_an_active_server(): void
    {
        VpnServer::factory()->create(['sstp_enabled' => false, 'l2tp_enabled' => true]);
        VpnServer::factory()->create(['is_active' => false, 'sstp_enabled' => true]);

        $this->createAccount($this->member(), ['protocol' => 'sstp'])->assertSessionHasErrors('protocol');
        $this->assertSame(0, VpnAccount::count());
    }

    public function test_account_goes_to_the_next_server_when_the_first_pool_is_full(): void
    {
        $full = VpnServer::factory()->create(['pool_start' => '10.1.0.2', 'pool_end' => '10.1.0.2', 'local_address' => '10.1.0.1']);
        $free = VpnServer::factory()->create(['pool_start' => '10.2.0.2', 'pool_end' => '10.2.0.9', 'local_address' => '10.2.0.1']);
        VpnAccount::factory()->for($full, 'server')->create(['remote_address' => '10.1.0.2']);

        $this->createAccount($this->member());

        $this->assertSame($free->id, VpnAccount::where('vpn_member_id', '!=', null)->sole()->vpn_server_id);
    }

    public function test_payment_activates_the_account_installs_it_on_the_router_and_sets_one_month(): void
    {
        Queue::fake([SyncVpnAccountJob::class]);
        VpnServer::factory()->create();
        $member = $this->member();
        $this->createAccount($member);
        $account = VpnAccount::sole();

        app(RecordCashPayment::class)->handle(Invoice::sole(), 25000, $this->admin());

        $account->refresh();
        $this->assertSame(VpnAccountStatus::Active, $account->status);
        $this->assertTrue($account->expires_at->isSameDay(today()->addMonthNoOverflow()->subDay()));
        Queue::assertPushed(SyncVpnAccountJob::class, fn (SyncVpnAccountJob $job): bool => $job->accountId === $account->id);
        $this->assertStringContainsString('sudah aktif', WhatsappMessage::latest('id')->first()->body);
    }

    public function test_dashboard_lists_only_own_accounts_with_credentials_and_pay_link_for_pending(): void
    {
        $server = VpnServer::factory()->create();
        $member = $this->member();
        $mine = VpnAccount::factory()->for($server, 'server')->create(['vpn_member_id' => $member->id, 'status' => VpnAccountStatus::Pending, 'password' => 'pw-saya-123']);
        VpnAccount::factory()->for($server, 'server')->create(['vpn_member_id' => $this->member()->id]);

        $this->actingAs($member, 'member')->get('/vpn-tunnel/akun')->assertInertia(
            fn (Assert $page) => $page->component('Public/Vpn/Dashboard')
                ->has('accounts', 1)
                ->where('accounts.0.username', $mine->username)
                ->where('accounts.0.password', 'pw-saya-123')
                ->where('accounts.0.pay_url', route('vpn-portal.show', $mine->portal_token))
                ->where('maxAccounts', 5),
        );
    }

    public function test_script_is_only_for_own_active_accounts(): void
    {
        $server = VpnServer::factory()->create();
        $member = $this->member();
        $active = VpnAccount::factory()->for($server, 'server')->create(['vpn_member_id' => $member->id]);
        $pending = VpnAccount::factory()->for($server, 'server')->create(['vpn_member_id' => $member->id, 'status' => VpnAccountStatus::Pending]);
        $foreign = VpnAccount::factory()->for($server, 'server')->create(['vpn_member_id' => $this->member()->id]);
        $this->actingAs($member, 'member');

        $this->getJson("/vpn-tunnel/akun/{$active->id}/script")->assertOk()->assertJsonPath('script', fn (string $script): bool => str_contains($script, 'sstp-client'));
        $this->getJson("/vpn-tunnel/akun/{$pending->id}/script")->assertForbidden();
        $this->getJson("/vpn-tunnel/akun/{$foreign->id}/script")->assertNotFound();
    }

    public function test_pay_page_offers_a_way_back_only_to_the_owning_member(): void
    {
        $server = VpnServer::factory()->create();
        $member = $this->member();
        $account = VpnAccount::factory()->for($server, 'server')->create(['vpn_member_id' => $member->id]);

        $this->get("/layanan-vpn/{$account->portal_token}")->assertInertia(fn (Assert $page) => $page->where('memberUrl', null));
        $this->actingAs($member, 'member')->get("/layanan-vpn/{$account->portal_token}")->assertInertia(fn (Assert $page) => $page->where('memberUrl', route('vpn-member.dashboard')));
        $this->actingAs($this->member(), 'member')->get("/layanan-vpn/{$account->portal_token}")->assertInertia(fn (Assert $page) => $page->where('memberUrl', null));
    }

    public function test_online_payment_flow_from_account_creation_to_activation(): void
    {
        Queue::fake([SyncVpnAccountJob::class]);
        VpnServer::factory()->create();
        PaymentGateway::factory()->create(['driver' => PaymentGatewayDriver::Tripay, 'is_active' => true, 'enabled_channels' => ['QRIS'], 'credentials' => ['merchant_code' => 'T0001', 'api_key' => 'k', 'private_key' => 'p']]);
        Http::fake(['tripay.co.id/*' => Http::response(['success' => true, 'data' => ['reference' => 'T0001-REF', 'checkout_url' => 'https://tripay.co.id/checkout/T0001-REF', 'expired_time' => now()->addDay()->timestamp]])]);
        $this->createAccount($this->member());
        $account = VpnAccount::sole();

        $this->post("/layanan-vpn/{$account->portal_token}/pay", ['invoice_number' => Invoice::sole()->invoice_number, 'channel' => 'QRIS'])->assertRedirect('https://tripay.co.id/checkout/T0001-REF');
        app(ApplyPayment::class)->handle(Payment::sole(), 'T0001-REF');

        $this->assertSame(VpnAccountStatus::Active, $account->fresh()->status);
        $this->assertSame(InvoiceStatus::Paid, Invoice::sole()->status);
    }

    public function test_unpaid_pending_accounts_are_cleaned_up_after_three_days_and_paid_ones_never(): void
    {
        VpnServer::factory()->create();
        $member = $this->member();
        $this->createAccount($member, ['label' => 'Lama']);
        $this->createAccount($member, ['label' => 'Baru']);
        $stale = VpnAccount::where('name', 'Lama')->sole();
        $stale->forceFill(['created_at' => now()->subDays(4)])->save();
        $address = $stale->remote_address;

        (new ExpirePendingVpnAccountsJob)->handle();

        $this->assertSame(['Baru'], VpnAccount::pluck('name')->all());
        $this->assertSame(1, Invoice::count());
        // IP dari akun yang dibersihkan kembali bebas.
        $this->createAccount($member, ['label' => 'Pengganti']);
        $this->assertSame($address, VpnAccount::where('name', 'Pengganti')->sole()->remote_address);
    }

    public function test_admin_sees_the_member_on_the_account_list(): void
    {
        $member = $this->member(['name' => 'Budi Member']);
        VpnAccount::factory()->create(['vpn_member_id' => $member->id]);

        $this->actingAs($this->admin())->get('/vpn')->assertInertia(fn (Assert $page) => $page->where('accounts.data.0.member.name', 'Budi Member'));
    }
}
