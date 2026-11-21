<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ExpenseRequest extends FormRequest
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
            'expense_category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'router_id' => ['nullable', 'integer', 'exists:routers,id,deleted_at,NULL'],
            'amount' => ['required', 'integer', 'min:1', 'max:10000000000'],
            'expense_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
            'remove_receipt' => ['boolean'],
        ];
    }
}
