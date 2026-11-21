<?php

namespace App\Http\Requests;

use App\Models\Router;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RouterRequest extends FormRequest
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
        /** @var Router|null $router */
        $router = $this->route('router');

        return [
            'name' => ['required', 'string', 'max:255'],
            'host' => [
                'required', 'string', 'max:255',
                Rule::unique('routers')->where('api_port', $this->integer('api_port'))->ignore($router),
            ],
            'api_port' => ['required', 'integer', 'between:1,65535'],
            'use_ssl' => ['boolean'],
            'username' => ['required', 'string', 'max:255'],
            'password' => [$router ? 'nullable' : 'required', 'string', 'max:255'],
            'isolir_profile' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Data siap simpan; password kosong saat edit berarti tidak diubah.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = $this->validated();

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        return $data;
    }
}
