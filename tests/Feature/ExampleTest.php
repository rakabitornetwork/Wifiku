<?php

namespace Tests\Feature;

use App\Models\VpnMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    public function test_root_sends_visitors_to_the_landing_page(): void
    {
        $this->get('/')->assertRedirect('/vpn-tunnel');
    }

    public function test_root_sends_logged_in_staff_to_the_dashboard(): void
    {
        $this->actingAs($this->admin())->get('/')->assertRedirect('/dashboard');
    }

    public function test_root_sends_vpn_members_to_the_landing_page_not_the_admin_dashboard(): void
    {
        $this->actingAs(VpnMember::factory()->make(['id' => 1]), 'member');

        $this->get('/')->assertRedirect('/vpn-tunnel');
    }

    public function test_landing_page_is_public_and_links_staff_to_the_admin_login(): void
    {
        $this->followingRedirects()->get('/')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('Public/Vpn/Landing')->where('loggedIn', false),
        );
        $this->get('/login')->assertOk();
    }
}
