<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCableRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'path' => ['nullable', 'array', 'max:60'],
            'path.*' => ['array', 'size:2'],
            'path.*.0' => ['numeric', 'between:-90,90'],
            'path.*.1' => ['numeric', 'between:-180,180'],
        ];
    }
}
