<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Customer;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    /**
     * Halaman GET dan role yang boleh membukanya.
     *
     * @return array<string, array{string, list<string>}>
     */
    public static function pages(): array
    {
        $staff = ['Owner', 'Admin'];

        return [
            'dashboard' => ['/dashboard', ['Owner', 'Admin', 'Teknisi']],
            'customers' => ['/customers', ['Owner', 'Admin', 'Teknisi']],
            'customer form' => ['/customers/create', $staff],
            'packages' => ['/packages', $staff],
            'routers' => ['/routers', $staff],
            'assets' => ['/assets', ['Owner', 'Admin', 'Teknisi']],
            'hotspot' => ['/hotspot/batches', $staff],
            'invoices' => ['/invoices', $staff],
            'topology' => ['/topology', ['Owner', 'Admin', 'Teknisi']],
            'outages' => ['/outages', ['Owner', 'Admin', 'Teknisi']],
            'expenses' => ['/expenses', $staff],
            'profit loss' => ['/reports/profit-loss', $staff],
            'voucher sales' => ['/reports/voucher-sales', $staff],
            'users' => ['/users', ['Owner']],
            'audit log' => ['/audit-log', ['Owner']],
            'payment gateways' => ['/settings/payment-gateways', ['Owner']],
            'integrations' => ['/settings/integrations', ['Owner']],
        ];
    }

    /**
     * @param  list<string>  $allowed
     */
    #[DataProvider('pages')]
    public function test_each_role_reaches_only_its_pages(string $url, array $allowed): void
    {
        foreach (['Owner', 'Admin', 'Teknisi'] as $role) {
            $status = $this->actingAs($this->userWithRole($role))->get($url)->status();

            in_array($role, $allowed, true)
                ? $this->assertSame(200, $status, "{$role} should open {$url}")
                : $this->assertSame(403, $status, "{$role} should be forbidden from {$url}");
        }
    }

    public function test_technician_cannot_modify_customers_or_assets_they_may_not_manage(): void
    {
        $customer = Customer::factory()->create();
        $technician = $this->actingAs($this->userWithRole('Teknisi'));

        $technician->post('/customers', [])->assertForbidden();
        $technician->delete("/customers/{$customer->id}")->assertForbidden();
        $this->assertNotSoftDeleted($customer);
        $technician->post('/packages', [])->assertForbidden();
        $technician->post('/odps', [])->assertForbidden();
        $technician->post('/expenses', [])->assertForbidden();
    }

    public function test_admin_cannot_manage_users_or_settings(): void
    {
        $admin = $this->actingAs($this->admin());

        $admin->post('/users', [])->assertForbidden();
        $admin->put('/settings/integrations', [])->assertForbidden();
    }

    public function test_shares_the_permissions_a_user_holds_with_the_frontend(): void
    {
        $this->actingAs($this->userWithRole('Teknisi'))->get('/dashboard')->assertInertia(
            fn (Assert $page) => $page->where('auth.user.permissions', fn ($permissions) => collect($permissions)->sort()->values()->all() === collect(Permissions::byRole()['Teknisi'])->sort()->values()->all()),
        );
    }

    public function test_owner_receives_every_permission_including_ones_added_later(): void
    {
        $this->actingAs($this->owner())->get('/dashboard')->assertInertia(
            fn (Assert $page) => $page->where('auth.user.permissions', Permissions::all()),
        );
    }

    public function test_owner_passes_gate_checks_for_permissions_not_assigned_to_the_role(): void
    {
        $owner = $this->owner();
        $owner->roles->first()->revokePermissionTo(Permissions::AUDIT_VIEW);

        $this->assertTrue($owner->fresh()->can(Permissions::AUDIT_VIEW));
    }

    public function test_deactivated_session_user_still_needs_the_permission(): void
    {
        $this->actingAs($this->userWithRole('Teknisi'))->get('/users')->assertForbidden();
    }
}
