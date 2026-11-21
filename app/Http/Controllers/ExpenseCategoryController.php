<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExpenseCategoryRequest;
use App\Models\ExpenseCategory;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;

class ExpenseCategoryController extends Controller
{
    public function store(ExpenseCategoryRequest $request): RedirectResponse
    {
        ExpenseCategory::create($request->validated());

        Toast::success('Kategori ditambahkan.');

        return back();
    }

    public function update(ExpenseCategoryRequest $request, ExpenseCategory $category): RedirectResponse
    {
        $category->update($request->validated());

        Toast::success('Kategori diperbarui.');

        return back();
    }

    public function destroy(ExpenseCategory $category): RedirectResponse
    {
        if ($category->expenses()->withTrashed()->exists()) {
            Toast::error('Kategori sudah dipakai pengeluaran sehingga tidak dapat dihapus.');

            return back();
        }

        $category->delete();

        Toast::success('Kategori dihapus.');

        return back();
    }
}
