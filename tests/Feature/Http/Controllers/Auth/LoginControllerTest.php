<?php

namespace Tests\Feature\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class LoginControllerTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_protected_page(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_renders_login_page_for_guest(): void
    {
        $this->get('/login')->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
    }

    public function test_valid_credentials_log_in_and_record_last_login(): void
    {
        $user = User::factory()->create(['password' => 'rahasia123']);

        $this->post('/login', ['email' => $user->email, 'password' => 'rahasia123'])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'salah'])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_deactivated_account_cannot_log_in(): void
    {
        $user = User::factory()->create(['password' => 'rahasia123', 'is_active' => false]);

        $this->post('/login', ['email' => $user->email, 'password' => 'rahasia123'])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_authenticated_user_is_redirected_away_from_login_page(): void
    {
        $this->actingAs($this->owner())->get('/login')->assertRedirect();
    }

    public function test_logout_ends_the_session(): void
    {
        $this->actingAs($this->owner())->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_shares_user_name_and_roles_with_every_page(): void
    {
        $this->actingAs($this->owner())->get('/dashboard')->assertInertia(
            fn (Assert $page) => $page->where('auth.user.roles', ['Owner'])->has('auth.user.name'),
        );
    }
}
