<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AvatarRequest extends FormRequest
{
    /**
     * Hanya pengguna panel admin; sesi guard lain (mis. member VPN) ditolak sebelum validasi.
     */
    public function authorize(): bool
    {
        return $this->user('web') !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'avatar' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=64,min_height=64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'avatar.required' => 'Pilih foto terlebih dahulu.',
            'avatar.mimes' => 'Foto harus berformat JPG, PNG, atau WebP.',
            'avatar.max' => 'Ukuran foto maksimal 2 MB.',
            'avatar.dimensions' => 'Foto minimal 64 x 64 piksel.',
            'avatar.uploaded' => 'Foto gagal diunggah. Periksa ukuran file (maksimal 2 MB).',
        ];
    }
}
