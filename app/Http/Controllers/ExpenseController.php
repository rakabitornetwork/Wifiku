<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExpenseRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Router;
use App\Support\Toast;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        $month = $this->month($request);
        $categoryId = $request->integer('category_id') ?: null;

        $query = Expense::query()
            ->whereBetween('expense_date', [$month->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString()])
            ->when($categoryId, fn ($query) => $query->where('expense_category_id', $categoryId));

        return Inertia::render('Expenses/Index', [
            'expenses' => (clone $query)
                ->with(['expenseCategory:id,name,type', 'router:id,name', 'creator:id,name'])
                ->orderByDesc('expense_date')
                ->orderByDesc('id')
                ->paginate($this->perPage(15))
                ->withQueryString(),
            'total' => (int) $query->sum('amount'),
            'categories' => ExpenseCategory::query()->withCount('expenses')->orderBy('name')->get(),
            'routers' => Router::orderBy('name')->get(['id', 'name']),
            'filters' => ['month' => $month->format('Y-m'), 'category_id' => $categoryId ? (string) $categoryId : ''],
        ]);
    }

    public function store(ExpenseRequest $request): RedirectResponse
    {
        Expense::create([
            ...$request->safe()->except(['receipt', 'remove_receipt']),
            'receipt_path' => $request->file('receipt')?->store('receipts', 'local'),
            'created_by' => $request->user()->id,
        ]);

        Toast::success('Pengeluaran dicatat.');

        return back();
    }

    public function update(ExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $data = $request->safe()->except(['receipt', 'remove_receipt']);

        if ($request->hasFile('receipt')) {
            $data['receipt_path'] = $request->file('receipt')->store('receipts', 'local');
        } elseif ($request->boolean('remove_receipt')) {
            $data['receipt_path'] = null;
        }

        $expense->update($data);

        Toast::success('Pengeluaran diperbarui.');

        return back();
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $expense->delete();

        Toast::success('Pengeluaran dihapus.');

        return back();
    }

    /**
     * Bukti pengeluaran disimpan di disk privat dan hanya dapat diunduh pengguna berizin.
     */
    public function receipt(Expense $expense): StreamedResponse
    {
        abort_unless($expense->receipt_path && Storage::disk('local')->exists($expense->receipt_path), 404);

        return Storage::disk('local')->download($expense->receipt_path);
    }

    private function month(Request $request): CarbonImmutable
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('month'))
            ? CarbonImmutable::createFromFormat('Y-m-d', $request->query('month').'-01')->startOfDay()
            : CarbonImmutable::today()->startOfMonth();
    }
}
