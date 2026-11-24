<?php

namespace Tests\Feature\Vpn;

use App\Enums\VpnAccountStatus;
use App\Jobs\Vpn\SyncVpnAccountJob;
use App\Models\VpnAccount;
use App\Models\VpnMember;
use App\Models\VpnServer;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class VpnMemberAdminTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    public function test_only_users_with_vpn_permission_can_open_the_member_page(): void
    {
        $this->get('/vpn/members')->assertRedirect('/login');
        $this->actingAs($this->userWithRole('Teknisi'))->get('/vpn/members')->assertForbidden();
        $this->actingAs($this->admin())->get('/vpn/members')->assertOk();
    }

    public function test_a_member_session_cannot_open_the_admin_page(): void
    {
        $this->actingAs(VpnMember::factory()->create(), 'member')->get('/vpn/members')->assertForbidden();
    }

    public function test_lists_members_with_account_counts_search_and_status_filter(): void
    {
        $budi = VpnMember::factory()->create(['name' => 'Budi', 'email' => 'budi@example.com']);
        VpnMember::factory()->create(['name' => 'Sari', 'is_active' => false]);
        $server = VpnServer::factory()->create();
        VpnAccount::factory()->for($server, 'server')->create(['vpn_member_id' => $budi->id]);
        VpnAccount::factory()->for($server, 'server')->create(['vpn_member_id' => $budi->id, 'status' => VpnAccountStatus::Pending]);
        $admin = $this->admin();

        $this->actingAs($admin)->get('/vpn/members')->assertInertia(
            fn (Assert $page) => $page->component('Vpn/Members')->has('members.data', 2)->where('maxAccounts', 5),
        );
        $this->actingAs($admin)->get('/vpn/members?search=budi')->assertInertia(
            fn (Assert $page) => $page->has('members.data', 1)->where('members.data.0.accounts_count', 2)->where('members.data.0.active_accounts_count', 1)->where('members.data.0.pending_accounts_count', 1),
        );
        $this->actingAs($admin)->get('/vpn/members?status=inactive')->assertInertia(fn (Assert $page) => $page->has('members.data', 1)->where('members.data.0.name', 'Sari'));
        $this->actingAs($admin)->get('/vpn/members?sort=accounts&direction=desc')->assertInertia(fn (Assert $page) => $page->where('members.data.0.name', 'Budi'));
    }

    public function test_account_list_can_be_filtered_by_member(): void
    {
        $member = VpnMember::factory()->create(['name' => 'Budi']);
        $server = VpnServer::factory()->create();
        VpnAccount::factory()->for($server, 'server')->create(['vpn_member_id' => $member->id]);
        VpnAccount::factory()->for($server, 'server')->create();

        $this->actingAs($this->admin())->get("/vpn?member_id={$member->id}")->assertInertia(
            fn (Assert $page) => $page->has('accounts.data', 1)->where('memberFilter.name', 'Budi'),
        );
    }

    public function test_admin_can_edit_member_details_and_validation_applies(): void
    {
        $member = VpnMember::factory()->create();
        VpnMember::factory()->create(['email' => 'dipakai@example.com']);
        $admin = $this->admin();
        $payload = ['name' => 'Nama Baru', 'email' => 'Baru@Example.com', 'whatsapp' => '0899-1234-5678', 'is_active' => true];

        $this->actingAs($admin)->put("/vpn/members/{$member->id}", [...$payload, 'email' => 'dipakai@example.com'])->assertSessionHasErrors('email');
        $this->actingAs($admin)->put("/vpn/members/{$member->id}", [...$payload, 'whatsapp' => '123'])->assertSessionHasErrors('whatsapp');
        $this->actingAs($admin)->put("/vpn/members/{$member->id}", $payload)->assertSessionDoesntHaveErrors();

        $member->refresh();
        $this->assertSame('Nama Baru', $member->name);
        $this->assertSame('baru@example.com', $member->email);
        $this->assertSame('6289912345678', $member->whatsapp);
    }

    public function test_keeping_the_same_email_is_not_a_duplicate(): void
    {
        $member = VpnMember::factory()->create(['email' => 'budi@example.com']);

        $this->actingAs($this->admin())->put("/vpn/members/{$member->id}", ['name' => 'Budi', 'email' => 'budi@example.com', 'whatsapp' => $member->whatsapp, 'is_active' => true])->assertSessionDoesntHaveErrors();
    }

    public function test_deactivating_blocks_login_but_leaves_running_tunnels_unless_asked(): void
    {
        Queue::fake([SyncVpnAccountJob::class]);
        $member = VpnMember::factory()->create(['email' => 'budi@example.com', 'password' => 'rahasia-123']);
        $account = VpnAccount::factory()->create(['vpn_member_id' => $member->id]);

        $this->actingAs($this->admin())->put("/vpn/members/{$member->id}", ['name' => $member->name, 'email' => $member->email, 'whatsapp' => $member->whatsapp, 'is_active' => false])->assertSessionDoesntHaveErrors();

        $this->assertFalse($member->fresh()->is_active);
        $this->assertSame(VpnAccountStatus::Active, $account->fresh()->status);
        Queue::assertNotPushed(SyncVpnAccountJob::class);
        $this->post('/vpn-tunnel/masuk', ['email' => 'budi@example.com', 'password' => 'rahasia-123'])->assertSessionHasErrors('email');
    }

    public function test_deactivating_with_the_option_disables_active_accounts_manually_and_queues_router_sync(): void
    {
        Queue::fake([SyncVpnAccountJob::class]);
        $member = VpnMember::factory()->create();
        $server = VpnServer::factory()->create();
        $active = VpnAccount::factory()->for($server, 'server')->create(['vpn_member_id' => $member->id]);
        $pending = VpnAccount::factory()->for($server, 'server')->create(['vpn_member_id' => $member->id, 'status' => VpnAccountStatus::Pending]);
        $other = VpnAccount::factory()->for($server, 'server')->create();

        $this->actingAs($this->admin())->put("/vpn/members/{$member->id}", ['name' => $member->name, 'email' => $member->email, 'whatsapp' => $member->whatsapp, 'is_active' => false, 'disable_accounts' => true])->assertSessionDoesntHaveErrors();

        $this->assertSame(VpnAccountStatus::Disabled, $active->fresh()->status);
        $this->assertNull($active->fresh()->auto_disabled_at);
        $this->assertSame(VpnAccountStatus::Pending, $pending->fresh()->status);
        $this->assertSame(VpnAccountStatus::Active, $other->fresh()->status);
        Queue::assertPushed(SyncVpnAccountJob::class, 1);
    }

    public function test_reactivating_a_member_does_not_touch_accounts(): void
    {
        Queue::fake([SyncVpnAccountJob::class]);
        $member = VpnMember::factory()->create(['is_active' => false]);
        $account = VpnAccount::factory()->create(['vpn_member_id' => $member->id, 'status' => VpnAccountStatus::Disabled]);

        $this->actingAs($this->admin())->put("/vpn/members/{$member->id}", ['name' => $member->name, 'email' => $member->email, 'whatsapp' => $member->whatsapp, 'is_active' => true, 'disable_accounts' => true])->assertSessionDoesntHaveErrors();

        $this->assertTrue($member->fresh()->is_active);
        $this->assertSame(VpnAccountStatus::Disabled, $account->fresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_admin_can_send_a_reset_link_and_sees_the_throttle(): void
    {
        $member = VpnMember::factory()->create(['whatsapp' => '6281234567890']);
        $admin = $this->admin();

        $this->actingAs($admin)->post("/vpn/members/{$member->id}/reset-link")->assertRedirect();
        $this->actingAs($admin)->post("/vpn/members/{$member->id}/reset-link")->assertRedirect();

        $this->assertSame(1, WhatsappMessage::count());
        $this->assertSame('6281234567890', WhatsappMessage::sole()->phone);
        $this->assertStringContainsString('/vpn-tunnel/reset-password/', WhatsappMessage::sole()->body);
    }

    public function test_member_with_accounts_cannot_be_deleted_but_empty_one_can(): void
    {
        $withAccount = VpnMember::factory()->create();
        VpnAccount::factory()->create(['vpn_member_id' => $withAccount->id]);
        $empty = VpnMember::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->delete("/vpn/members/{$withAccount->id}")->assertRedirect();
        $this->actingAs($admin)->delete("/vpn/members/{$empty->id}")->assertRedirect();

        $this->assertModelExists($withAccount);
        $this->assertModelMissing($empty);
    }
}
