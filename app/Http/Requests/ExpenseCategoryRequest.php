<?php

namespace App\Http\Requests;

use App\Enums\ExpenseType;
use App\Models\ExpenseCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseCategoryRequest extends FormRequest
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
        /** @var ExpenseCategory|null $category */
        $category = $this->route('category');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('expense_categories')->ignore($category)],
            'type' => ['required', Rule::enum(ExpenseType::class)],
        ];
    }
}
