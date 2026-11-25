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

    public function test_root_is_the_public_landing_page_for_everyone(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Public/Landing')->where('staff', false));

        $this->actingAs($this->admin())->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Public/Landing')->where('staff', true));
    }

    public function test_a_vpn_member_session_is_not_treated_as_staff_on_the_landing_page(): void
    {
        $this->actingAs(VpnMember::factory()->create(), 'member');

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->where('staff', false));
    }

    public function test_admin_login_stays_reachable(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/dashboard')->assertRedirect('/login');
    }
}
