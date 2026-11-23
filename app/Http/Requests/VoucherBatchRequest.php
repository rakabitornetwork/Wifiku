<?php

namespace App\Http\Requests;

use App\Services\Voucher\VoucherCodeGenerator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VoucherBatchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'hotspot_profile_id' => ['required', 'integer', Rule::exists('hotspot_profiles', 'id')->where('is_active', true)],
            'reseller_id' => ['nullable', 'integer', Rule::exists('resellers', 'id')->where('is_active', true)],
            'quantity' => ['required', 'integer', 'between:1,1000'],
            'prefix' => ['nullable', 'alpha_num', 'max:6'],
            'code_length' => ['required', 'integer', 'between:4,10'],
            'charset' => ['required', Rule::in(array_keys(VoucherCodeGenerator::CHARSETS))],
            'server' => ['nullable', 'string', 'max:100'],
        ];
    }
}
