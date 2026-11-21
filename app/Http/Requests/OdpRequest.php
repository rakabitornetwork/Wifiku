<?php

namespace App\Http\Requests;

use App\Models\Odp;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class OdpRequest extends FormRequest
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
        /** @var Odp|null $odp */
        $odp = $this->route('odp');

        return [
            'router_id' => ['required', 'integer', 'exists:routers,id,deleted_at,NULL'],
            'parent_odp_id' => ['nullable', 'integer', 'exists:odps,id', Rule::notIn(array_filter([$odp?->id]))],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('odps')->ignore($odp)],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'total_ports' => ['required', 'integer', 'between:1,144'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Kapasitas tidak boleh lebih kecil dari port yang sudah dipakai pelanggan.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Odp|null $odp */
                $odp = $this->route('odp');

                if ($odp === null || $validator->errors()->has('total_ports')) {
                    return;
                }

                $highestPort = (int) $odp->customers()->max('odp_port');

                if ($this->integer('total_ports') < $highestPort) {
                    $validator->errors()->add('total_ports', "Port {$highestPort} sudah dipakai pelanggan.");
                }
            },
        ];
    }
}
