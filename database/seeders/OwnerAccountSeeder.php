<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class OwnerAccountSeeder extends Seeder
{
    /**
     * Buat atau perbarui akun super-admin default (Owner).
     */
    public function run(): void
    {
        $owner = User::updateOrCreate(
            ['email' => 'amon@teslatech.my.id'],
            [
                'name' => 'Amon',
                'password' => 'gantengmax',
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $owner->syncRoles(['Owner']);
    }
}
