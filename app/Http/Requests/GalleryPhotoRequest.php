<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GalleryPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'photo' => [$this->isMethod('POST') ? 'required' : 'prohibited', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:min_width=400,min_height=300'],
            'title' => ['required', 'string', 'max:80'],
            'caption' => ['nullable', 'string', 'max:160'],
            'is_published' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.required' => 'Pilih foto terlebih dahulu.',
            'photo.mimes' => 'Foto harus berformat JPG, PNG, atau WebP.',
            'photo.max' => 'Ukuran foto maksimal 8 MB.',
            'photo.dimensions' => 'Foto minimal 400 x 300 piksel.',
            'photo.uploaded' => 'Foto gagal diunggah. Periksa ukuran file (maksimal 8 MB).',
            'photo.prohibited' => 'Foto tidak dapat diganti; hapus lalu unggah ulang.',
        ];
    }
}
