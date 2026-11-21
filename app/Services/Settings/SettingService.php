<?php

namespace App\Services\Settings;

use App\Models\Setting;
use App\Support\SettingDefinitions;
use Illuminate\Support\Facades\Crypt;

class SettingService
{
    /**
     * Ambil nilai setting; nilai terenkripsi didekripsi otomatis.
     * Tanpa $default, nilai bawaan dari SettingDefinitions dipakai.
     */
    public function get(string $group, string $key, ?string $default = null): ?string
    {
        $default ??= SettingDefinitions::default($group, $key);
        $setting = Setting::where('group', $group)->where('key', $key)->first();

        if ($setting === null || blank($setting->value)) {
            return $default;
        }

        return $setting->is_encrypted ? Crypt::decryptString($setting->value) : $setting->value;
    }

    /**
     * Simpan nilai setting; nilai sensitif dienkripsi dengan APP_KEY.
     */
    public function set(string $group, string $key, ?string $value, bool $encrypted = false): Setting
    {
        return Setting::updateOrCreate(
            ['group' => $group, 'key' => $key],
            [
                'value' => $encrypted && $value !== null ? Crypt::encryptString($value) : $value,
                'is_encrypted' => $encrypted,
            ],
        );
    }
}
