<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfilePhoneRequest extends FormRequest
{
    /**
     * Hanya pengguna panel admin; sesi guard lain (mis. member VPN) ditolak sebelum validasi.
     */
    public function authorize(): bool
    {
        return $this->user('web') !== null;
    }

    /**
     * Nomor dinormalkan ke format 62xxx; kosong berarti nomor dihapus.
     */
    protected function prepareForValidation(): void
    {
        $phone = preg_replace('/\D+/', '', (string) $this->input('phone'));

        if (str_starts_with($phone, '0')) {
            $phone = '62'.substr($phone, 1);
        }

        $this->merge(['phone' => $phone === '' ? null : $phone]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'phone' => ['nullable', 'string', 'regex:/^62\d{8,13}$/'],
            'current_password' => ['required', 'current_password:web'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Nomor WhatsApp tidak valid (contoh: 081234567890).',
            'current_password.current_password' => 'Password saat ini salah.',
        ];
    }
}
