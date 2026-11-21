<?php

namespace Database\Seeders;

use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Role dasar aplikasi.
     *
     * @var list<string>
     */
    public const ROLES = ['Owner', 'Admin', 'Teknisi'];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        foreach (Permissions::all() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // Seeder berjalan tanpa model events, jadi cache permission harus dibuang manual setelah dibuat.
        $registrar->forgetCachedPermissions();

        foreach (Permissions::byRole() as $role => $permissions) {
            Role::findOrCreate($role, 'web')->syncPermissions($permissions);
        }
    }
}
