<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PackageRequest extends FormRequest
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
            'router_id' => ['nullable', 'integer', 'exists:routers,id,deleted_at,NULL'],
            'name' => ['required', 'string', 'max:255'],
            'mikrotik_profile' => ['required', 'string', 'max:255'],
            'rate_limit' => ['nullable', 'string', 'max:50'],
            'price' => ['required', 'integer', 'min:0', 'max:100000000'],
            'is_active' => ['boolean'],
            'show_on_landing' => ['boolean'],
            'landing_highlight' => ['boolean'],
            'landing_description' => ['nullable', 'string', 'max:160'],
        ];
    }
}
