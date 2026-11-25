<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Gambar ilustrasi untuk satu slot di landing page (hero, tentang, ajakan); satu gambar per slot.
 */
#[Fillable(['slot', 'path', 'prompt', 'is_ai'])]
class LandingImage extends Model
{
    public const SLOTS = [
        'hero' => [
            'label' => 'Hero (bagian paling atas)',
            'aspect' => '1:1',
            'prompt' => 'Ilustrasi flat modern bergaya vektor untuk layanan internet rumah: rumah keluarga dengan router WiFi dan sinyal melingkar, palet warna indigo dan ungu, latar bersih, tanpa teks, tanpa logo.',
        ],
        'about' => [
            'label' => 'Tentang kami',
            'aspect' => '4:3',
            'prompt' => 'Ilustrasi flat modern bergaya vektor tentang teknisi jaringan memasang kabel fiber optik dan perangkat WiFi, palet warna indigo dan ungu, tanpa teks, tanpa logo.',
        ],
        'cta' => [
            'label' => 'Banner ajakan (CTA)',
            'aspect' => '21:9',
            'prompt' => 'Latar abstrak lebar dengan gelombang sinyal WiFi dan garis jaringan bercahaya, gradasi indigo ke ungu, tanpa teks, tanpa logo, ruang kosong di tengah.',
        ],
    ];

    protected function casts(): array
    {
        return ['is_ai' => 'boolean'];
    }
}
