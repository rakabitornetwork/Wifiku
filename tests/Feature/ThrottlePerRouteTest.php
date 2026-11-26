<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class ThrottlePerRouteTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'throttle:2,1'])->group(function () {
            Route::get('/uji-a', fn () => 'a');
            Route::get('/uji-b', fn () => 'b');
            Route::post('/uji-a', fn () => 'a-post');
        });
    }

    public function test_guests_get_a_separate_counter_for_each_throttled_route(): void
    {
        $this->get('/uji-a')->assertOk();
        $this->get('/uji-a')->assertOk();
        $this->get('/uji-a')->assertStatus(429);

        $this->get('/uji-b')->assertOk();
        $this->get('/uji-b')->assertOk();
        $this->get('/uji-b')->assertStatus(429);

        $this->withoutMiddleware(VerifyCsrfToken::class)->post('/uji-a')->assertOk();
    }

    public function test_light_requests_no_longer_use_up_the_budget_of_strict_actions_for_a_logged_in_user(): void
    {
        $user = $this->owner();

        for ($i = 0; $i < 6; $i++) {
            $this->actingAs($user)->get('/gambar-landing/hero');
        }

        $this->actingAs($user)->get('/uji-a')->assertOk();
        $this->actingAs($user)->get('/uji-a')->assertOk();
        $this->actingAs($user)->get('/uji-a')->assertStatus(429);
    }

    public function test_the_limit_of_a_route_still_applies_to_that_route(): void
    {
        $this->post('/login', ['email' => 'x@example.com', 'password' => 'salah']);
        $this->post('/login', ['email' => 'x@example.com', 'password' => 'salah']);
        $this->post('/login', ['email' => 'x@example.com', 'password' => 'salah']);
        $this->post('/login', ['email' => 'x@example.com', 'password' => 'salah']);
        $this->post('/login', ['email' => 'x@example.com', 'password' => 'salah']);

        $this->post('/login', ['email' => 'x@example.com', 'password' => 'salah'])->assertStatus(429);
    }
}
