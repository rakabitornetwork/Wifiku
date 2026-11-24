<?php

namespace App\Http\Requests;

use App\Enums\VpnProtocol;
use App\Services\Vpn\VpnPortPlanner;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VpnMemberAccountRequest extends FormRequest
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
            'label' => ['required', 'string', 'max:40'],
            'protocol' => ['required', Rule::enum(VpnProtocol::class)],
            'forwards' => ['nullable', 'array', 'max:'.count(VpnPortPlanner::PRESETS)],
            'forwards.*' => ['integer', Rule::in(array_column(VpnPortPlanner::PRESETS, 'target_port'))],
        ];
    }
}
