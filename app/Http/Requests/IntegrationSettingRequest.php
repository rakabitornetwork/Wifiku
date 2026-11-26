<?php

namespace App\Http\Requests;

use App\Support\SettingDefinitions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IntegrationSettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole('Owner') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [];
        // Tiap halaman pengaturan hanya mengirim grupnya sendiri; grup yang tidak dikirim tidak divalidasi dan tidak diubah.
        $sent = array_keys((array) $this->input('settings', []));

        foreach (SettingDefinitions::ALL as $name => $definition) {
            if (! in_array(explode('.', $name)[0], $sent, true)) {
                continue;
            }

            // Rahasia yang dikosongkan berarti "tidak diubah", jadi selalu boleh kosong.
            $rules["settings.{$name}"] = ($definition['secret'] ?? false)
                ? ['nullable', ...array_diff($definition['rules'], ['required', 'nullable'])]
                : $definition['rules'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return collect(SettingDefinitions::ALL)->mapWithKeys(fn (array $definition, string $name): array => ["settings.{$name}" => $definition['label']])->all();
    }
}
