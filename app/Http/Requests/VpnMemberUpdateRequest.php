<?php

namespace App\Http\Requests;

use App\Models\VpnMember;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VpnMemberUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $whatsapp = preg_replace('/\D+/', '', (string) $this->input('whatsapp'));

        if (str_starts_with($whatsapp, '0')) {
            $whatsapp = '62'.substr($whatsapp, 1);
        }

        $this->merge(['whatsapp' => $whatsapp, 'email' => strtolower(trim((string) $this->input('email')))]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var VpnMember $member */
        $member = $this->route('member');

        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('vpn_members', 'email')->ignore($member)],
            'whatsapp' => ['required', 'string', 'regex:/^62\d{8,13}$/'],
            'is_active' => ['required', 'boolean'],
            'disable_accounts' => ['boolean'],
        ];
    }
}
