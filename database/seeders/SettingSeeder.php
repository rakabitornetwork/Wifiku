<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Nilai awal pengaturan. Nilai yang sudah diubah admin tidak ditimpa.
     */
    public function run(): void
    {
        $settings = [
            ['group' => 'company', 'key' => 'name', 'value' => 'Wifi Teslatech', 'is_encrypted' => false],
            ['group' => 'company', 'key' => 'cs_whatsapp', 'value' => null, 'is_encrypted' => false],
            ['group' => 'billing', 'key' => 'default_due_days', 'value' => '7', 'is_encrypted' => false],
            ['group' => 'billing', 'key' => 'reminder_days_before', 'value' => '3', 'is_encrypted' => false],
            ['group' => 'whatsapp', 'key' => 'base_url', 'value' => null, 'is_encrypted' => false],
            ['group' => 'whatsapp', 'key' => 'api_key', 'value' => null, 'is_encrypted' => true],
            ['group' => 'isolir', 'key' => 'title', 'value' => 'Layanan Internet Anda Sedang Diisolir', 'is_encrypted' => false],
            ['group' => 'isolir', 'key' => 'message', 'value' => 'Mohon segera melunasi tagihan Anda agar layanan dapat digunakan kembali.', 'is_encrypted' => false],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(
                ['group' => $setting['group'], 'key' => $setting['key']],
                ['value' => $setting['value'], 'is_encrypted' => $setting['is_encrypted']],
            );
        }
    }
}
