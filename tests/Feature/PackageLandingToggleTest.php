<?php

namespace Tests\Feature;

use App\Jobs\Network\SyncPppProfileJob;
use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class PackageLandingToggleTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    public function test_toggle_shows_and_hides_a_package_on_the_landing_without_touching_the_router(): void
    {
        Queue::fake();
        $package = Package::factory()->create(['name' => 'Home 20', 'price' => 150000, 'is_active' => true, 'show_on_landing' => false]);
        $owner = $this->owner();

        $this->actingAs($owner)->patch("/packages/{$package->id}/landing")->assertRedirect();
        $this->get('/')->assertInertia(fn (Assert $page) => $page->has('plans', 1)->where('plans.0.name', 'Home 20'));

        $this->actingAs($owner)->patch("/packages/{$package->id}/landing");
        $this->get('/')->assertInertia(fn (Assert $page) => $page->has('plans', 0));
        Queue::assertNotPushed(SyncPppProfileJob::class);
    }

    public function test_users_without_package_permission_cannot_toggle(): void
    {
        $package = Package::factory()->create(['show_on_landing' => false]);

        $this->actingAs($this->userWithRole('Teknisi'))->patch("/packages/{$package->id}/landing")->assertForbidden();
        $this->assertFalse($package->fresh()->show_on_landing);
    }
}
