<?php

namespace App\Support;

use App\Models\LandingImage;
use App\Services\Settings\SettingService;

class CompanyBranding
{
    /**
     * Nama dan logo perusahaan untuk halaman publik di luar landing page.
     *
     * @return array{name: string, logo: string|null}
     */
    public static function data(SettingService $settings): array
    {
        $logo = LandingImage::where('slot', LandingImage::LOGO_SLOT)->first();

        return [
            'name' => (string) $settings->get('company', 'name', config('app.name')),
            'logo' => $logo ? route('landing-image.show', [LandingImage::LOGO_SLOT, 'v' => $logo->updated_at?->timestamp]) : null,
        ];
    }
}
