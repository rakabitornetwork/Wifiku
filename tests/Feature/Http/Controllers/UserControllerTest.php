<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return ['name' => 'Rudi Teknisi', 'email' => 'rudi@teslatech.my.id', 'phone' => '081234567890', 'role' => 'Teknisi', 'password' => 'rahasia123', 'is_active' => true, ...$overrides];
    }

    public function test_lists_users_with_their_role_and_never_their_password(): void
    {
        $owner = $this->owner();

        $response = $this->actingAs($owner)->get('/users');

        $response->assertInertia(fn (Assert $page) => $page->component('Users/Index')->where('users.data.0.role', 'Owner')->missing('users.data.0.password'));
        $this->assertStringNotContainsString('"password"', $response->getContent());
    }

    public function test_creates_user_with_hashed_password_and_role(): void
    {
        $this->actingAs($this->owner())->post('/users', $this->payload())->assertSessionDoesntHaveErrors();

        $user = User::where('email', 'rudi@teslatech.my.id')->sole();
        $this->assertTrue(Hash::check('rahasia123', $user->password));
        $this->assertTrue($user->hasRole('Teknisi'));
        $this->assertTrue($user->is_active);
    }

    public function test_validates_email_uniqueness_password_length_and_role(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->post('/users', $this->payload(['email' => $owner->email, 'password' => 'pendek', 'role' => 'Dewa']))->assertSessionHasErrors(['email', 'password', 'role']);
    }

    public function test_password_is_required_on_create_but_optional_on_update(): void
    {
        $owner = $this->owner();
        $user = User::factory()->create(['password' => 'lama-password'])->assignRole('Teknisi');

        $this->actingAs($owner)->post('/users', $this->payload(['password' => '']))->assertSessionHasErrors('password');
        $this->actingAs($owner)->put("/users/{$user->id}", $this->payload(['email' => $user->email, 'password' => '']))->assertSessionDoesntHaveErrors();
        $this->assertTrue(Hash::check('lama-password', $user->fresh()->password));
    }

    public function test_update_changes_role_status_and_password(): void
    {
        $owner = $this->owner();
        $user = User::factory()->create()->assignRole('Teknisi');

        $this->actingAs($owner)->put("/users/{$user->id}", $this->payload(['email' => $user->email, 'role' => 'Admin', 'is_active' => false, 'password' => 'password-baru-1']));

        $user->refresh();
        $this->assertSame(['Admin'], $user->getRoleNames()->all());
        $this->assertFalse($user->is_active);
        $this->assertTrue(Hash::check('password-baru-1', $user->password));
    }

    public function test_deactivated_user_cannot_log_in_afterwards(): void
    {
        $owner = $this->owner();
        $user = User::factory()->create(['password' => 'rahasia123'])->assignRole('Admin');

        $this->actingAs($owner)->put("/users/{$user->id}", $this->payload(['email' => $user->email, 'role' => 'Admin', 'is_active' => false, 'password' => '']));
        auth()->logout();

        $this->post('/login', ['email' => $user->email, 'password' => 'rahasia123'])->assertSessionHasErrors('email');
    }

    public function test_owner_cannot_deactivate_or_delete_themselves(): void
    {
        $owner = $this->owner();
        $this->userWithRole('Owner');

        $this->actingAs($owner)->put("/users/{$owner->id}", $this->payload(['email' => $owner->email, 'role' => 'Owner', 'is_active' => false]));
        $this->actingAs($owner)->delete("/users/{$owner->id}");

        $this->assertTrue($owner->fresh()->is_active);
        $this->assertModelExists($owner);
    }

    public function test_the_last_active_owner_cannot_be_demoted_or_removed(): void
    {
        $owner = $this->owner();
        $otherOwner = User::factory()->create(['is_active' => false])->assignRole('Owner');

        $this->actingAs($owner)->put("/users/{$otherOwner->id}", $this->payload(['email' => $otherOwner->email, 'role' => 'Teknisi']));
        $this->actingAs($owner)->put("/users/{$owner->id}", $this->payload(['email' => $owner->email, 'role' => 'Admin']));

        $this->assertTrue($owner->fresh()->hasRole('Owner'));
    }

    public function test_an_owner_can_be_demoted_when_another_active_owner_exists(): void
    {
        $owner = $this->owner();
        $second = User::factory()->create()->assignRole('Owner');

        $this->actingAs($owner)->put("/users/{$second->id}", $this->payload(['email' => $second->email, 'role' => 'Admin', 'password' => '']));

        $this->assertTrue($second->fresh()->hasRole('Admin'));
    }

    public function test_user_with_transaction_history_cannot_be_deleted(): void
    {
        $owner = $this->owner();
        $cashier = User::factory()->create()->assignRole('Admin');
        Expense::factory()->create(['created_by' => $cashier->id]);

        $this->actingAs($owner)->delete("/users/{$cashier->id}");

        $this->assertModelExists($cashier);
    }

    public function test_user_without_history_can_be_deleted(): void
    {
        $owner = $this->owner();
        $technician = User::factory()->create()->assignRole('Teknisi');

        $this->actingAs($owner)->delete("/users/{$technician->id}");

        $this->assertModelMissing($technician);
    }
}
