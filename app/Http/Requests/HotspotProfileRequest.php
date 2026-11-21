<?php

namespace App\Http\Requests;

use App\Models\HotspotProfile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HotspotProfileRequest extends FormRequest
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
        /** @var HotspotProfile|null $profile */
        $profile = $this->route('profile');

        return [
            'router_id' => ['required', 'integer', 'exists:routers,id,deleted_at,NULL'],
            'name' => ['required', 'string', 'max:255', Rule::unique('hotspot_profiles')->where('router_id', $this->input('router_id'))->ignore($profile)],
            'mikrotik_profile' => ['required', 'string', 'max:255'],
            'price' => ['required', 'integer', 'min:0', 'max:10000000'],
            'reseller_price' => ['nullable', 'integer', 'min:0', 'lte:price'],
            'validity_minutes' => ['required', 'integer', 'min:1', 'max:5256000'],
            'uptime_limit_minutes' => ['nullable', 'integer', 'min:1'],
            'data_limit_mb' => ['nullable', 'integer', 'min:1'],
            'rate_limit' => ['nullable', 'string', 'max:50'],
            'shared_users' => ['required', 'integer', 'between:1,255'],
            'is_active' => ['boolean'],
        ];
    }
}
