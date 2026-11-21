<?php

namespace Tests\Concerns;

use App\Models\User;
use Database\Seeders\RoleSeeder;

trait CreatesUsers
{
    protected function userWithRole(string $role): User
    {
        $this->seed(RoleSeeder::class);

        return User::factory()->create()->assignRole($role);
    }

    protected function owner(): User
    {
        return $this->userWithRole('Owner');
    }

    protected function admin(): User
    {
        return $this->userWithRole('Admin');
    }
}
