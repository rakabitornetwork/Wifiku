<?php

namespace App\Http\Controllers;

use App\Actions\Customer\CreateCustomer;
use App\Actions\Customer\DeleteCustomer;
use App\Actions\Customer\UpdateCustomer;
use App\Enums\CustomerStatus;
use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use App\Models\Odp;
use App\Models\Package;
use App\Models\Router;
use App\Support\Toast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    private const BULK_LIMIT = 1000;

    /** Kolom yang boleh diurutkan dari judul tabel. */
    private const SORTABLE = ['customer_code', 'name', 'whatsapp', 'pppoe_username', 'package', 'router', 'billing_day', 'status'];

    public function index(Request $request): Response
    {
        $sort = in_array($request->query('sort'), self::SORTABLE, true) ? (string) $request->query('sort') : '';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        $customers = $this->filteredQuery($request)
            ->with(['router:id,name', 'package:id,name', 'odp:id,name'])
            ->tap(fn (Builder $query) => $this->applySorting($query, $sort, $direction))
            ->paginate($this->perPage(15))
            ->withQueryString();

        $status = CustomerStatus::tryFrom((string) $request->query('status'));
        $routerId = $request->integer('router_id') ?: null;

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'routers' => Router::orderBy('name')->get(['id', 'name']),
            'filters' => ['search' => trim((string) $request->query('search')), 'status' => $status?->value ?? '', 'router_id' => $routerId ? (string) $routerId : '', 'sort' => $sort, 'direction' => $direction],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Customers/Form', ['customer' => null, ...$this->formOptions()]);
    }

    public function store(CustomerRequest $request, CreateCustomer $createCustomer): RedirectResponse
    {
        $customer = $createCustomer->handle($request->payload());

        Toast::success("Pelanggan {$customer->customer_code} berhasil ditambahkan.");

        return to_route('customers.index');
    }

    public function edit(Customer $customer): Response
    {
        return Inertia::render('Customers/Form', [
            'customer' => $customer->only([
                'id', 'customer_code', 'router_id', 'package_id', 'odp_id', 'odp_port', 'nik', 'name', 'whatsapp', 'address', 'village',
                'latitude', 'longitude', 'pppoe_username', 'billing_cycle', 'billing_day', 'due_days', 'status',
            ]) + [
                'installed_at' => $customer->installed_at->toDateString(),
                'promise_to_pay_until' => $customer->promise_to_pay_until?->toDateString(),
            ],
            ...$this->formOptions($customer->package_id),
        ]);
    }

    public function update(CustomerRequest $request, Customer $customer, UpdateCustomer $updateCustomer): RedirectResponse
    {
        $updateCustomer->handle($customer, $request->payload());

        Toast::success('Data pelanggan berhasil diperbarui.');

        return to_route('customers.index');
    }

    public function destroy(Customer $customer, DeleteCustomer $deleteCustomer): RedirectResponse
    {
        $deleteCustomer->handle($customer);

        Toast::success('Pelanggan dihapus. Secret PPPoE di router akan dihapus lewat antrean.');

        return back();
    }

    /**
     * Urutkan berdasarkan kolom pilihan (whitelist); tanpa pilihan, yang terbaru di atas.
     *
     * @param  Builder<Customer>  $query
     */
    private function applySorting(Builder $query, string $sort, string $direction): void
    {
        match ($sort) {
            'package' => $query->orderBy(Package::select('name')->whereColumn('packages.id', 'customers.package_id'), $direction),
            'router' => $query->orderBy(Router::select('name')->whereColumn('routers.id', 'customers.router_id'), $direction),
            '' => null,
            default => $query->orderBy($sort, $direction),
        };

        $query->orderByDesc('id');
    }

    /**
     * Hapus banyak pelanggan sekaligus: berdasarkan ID terpilih, atau seluruh hasil filter saat select_all.
     */
    public function destroyMany(Request $request, DeleteCustomer $deleteCustomer): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required_without:select_all', 'array', 'max:'.self::BULK_LIMIT],
            'ids.*' => ['integer'],
            'select_all' => ['sometimes', 'boolean'],
        ]);

        $query = ($validated['select_all'] ?? false)
            ? $this->filteredQuery($request)
            : Customer::query()->whereIn('id', $validated['ids']);

        $customers = $query->limit(self::BULK_LIMIT + 1)->get();

        if ($customers->count() > self::BULK_LIMIT) {
            return back()->withErrors(['ids' => 'Maksimal '.self::BULK_LIMIT.' pelanggan per penghapusan. Persempit filter lalu ulangi.']);
        }

        foreach ($customers as $customer) {
            $deleteCustomer->handle($customer);
        }

        activity('Pelanggan')->causedBy($request->user())->withProperties(['count' => $customers->count()])->log("Hapus massal {$customers->count()} pelanggan");

        Toast::success("{$customers->count()} pelanggan dihapus. Secret PPPoE di router akan dihapus lewat antrean.");

        return back();
    }

    /**
     * Pelanggan sesuai filter pencarian, status, dan router pada daftar.
     *
     * @return Builder<Customer>
     */
    private function filteredQuery(Request $request): Builder
    {
        $search = trim((string) $request->input('search'));
        $status = CustomerStatus::tryFrom((string) $request->input('status'));
        $routerId = $request->integer('router_id') ?: null;

        return Customer::query()
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('customer_code', 'like', "%{$search}%")
                    ->orWhere('pppoe_username', 'like', "%{$search}%")
                    ->orWhere('whatsapp', 'like', "%{$search}%");
            }))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($routerId, fn ($query) => $query->where('router_id', $routerId));
    }

    /**
     * Pencarian ringan untuk CustomerPicker (mis. memasangkan ONT ke pelanggan).
     */
    public function search(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q'));

        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        return response()->json(
            Customer::query()
                ->where(fn ($query) => $query->where('name', 'like', "%{$term}%")->orWhere('customer_code', 'like', "%{$term}%"))
                ->orderBy('name')
                ->limit(20)
                ->get(['id', 'customer_code', 'name'])
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(?int $currentPackageId = null): array
    {
        return [
            'routers' => Router::orderBy('name')->get(['id', 'name']),
            'packages' => Package::where('is_active', true)->orWhere('id', $currentPackageId)->orderBy('price')->get(['id', 'router_id', 'name', 'price']),
            'odps' => Odp::orderBy('name')->get(['id', 'router_id', 'name', 'code', 'total_ports']),
        ];
    }
}
