<?php

namespace App\Http\Requests;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
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
        /** @var User|null $user */
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::in(RoleSeeder::ROLES)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'max:100'],
            'is_active' => ['boolean'],
        ];
    }
}
