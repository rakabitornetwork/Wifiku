<?php

namespace App\Http\Requests;

use App\Enums\AssetOwnership;
use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Models\Asset;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Samakan format serial dan MAC (huruf besar, MAC berpemisah titik dua).
     */
    protected function prepareForValidation(): void
    {
        $mac = preg_replace('/[^0-9A-Fa-f]/', '', (string) $this->input('mac_address'));

        $this->merge([
            'serial_number' => strtoupper(trim((string) $this->input('serial_number'))),
            'mac_address' => $mac === '' ? null : strtoupper(implode(':', str_split($mac, 2))),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Asset|null $asset */
        $asset = $this->route('asset');

        return [
            'type' => ['required', Rule::enum(AssetType::class)],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['required', 'string', 'max:100', Rule::unique('assets')->ignore($asset)],
            'mac_address' => ['nullable', 'mac_address', Rule::unique('assets')->ignore($asset)],
            'ownership' => ['required', Rule::enum(AssetOwnership::class)],
            'status' => ['required', Rule::enum(AssetStatus::class)],
            'customer_id' => [
                'nullable', 'integer', 'exists:customers,id,deleted_at,NULL',
                Rule::requiredIf($this->input('status') === AssetStatus::Installed->value),
                Rule::prohibitedIf($this->input('status') === AssetStatus::InStock->value),
            ],
            'installed_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
