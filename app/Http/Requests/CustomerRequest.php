<?php

namespace App\Http\Requests;

use App\Enums\BillingCycle;
use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\Odp;
use App\Models\Package;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalisasi nomor WhatsApp ke format 62xxx sebelum divalidasi.
     */
    protected function prepareForValidation(): void
    {
        $whatsapp = preg_replace('/\D+/', '', (string) $this->input('whatsapp'));

        if (str_starts_with($whatsapp, '0')) {
            $whatsapp = '62'.substr($whatsapp, 1);
        }

        $this->merge(['whatsapp' => $whatsapp]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Customer|null $customer */
        $customer = $this->route('customer');

        return [
            'router_id' => ['required', 'integer', 'exists:routers,id,deleted_at,NULL'],
            'package_id' => ['required', 'integer', 'exists:packages,id,deleted_at,NULL'],
            'odp_id' => ['nullable', 'integer', 'exists:odps,id'],
            'odp_port' => ['nullable', 'integer', 'min:1', 'max:255'],
            'nik' => ['nullable', 'digits:16'],
            'name' => ['required', 'string', 'max:255'],
            'whatsapp' => ['nullable', 'regex:/^62\d{8,13}$/'],
            'address' => ['nullable', 'string', 'max:1000'],
            'village' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'pppoe_username' => [
                'required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._@-]+$/',
                Rule::unique('customers')->where('router_id', $this->input('router_id'))->ignore($customer),
            ],
            'pppoe_password' => [$customer ? 'nullable' : 'required', 'string', 'max:64'],
            'billing_cycle' => ['required', Rule::enum(BillingCycle::class)],
            'billing_day' => ['nullable', 'required_if:billing_cycle,fixed', 'integer', 'between:1,31'],
            'due_days' => ['required', 'integer', 'between:1,60'],
            'installed_at' => ['required', 'date'],
            'status' => ['required', Rule::enum(CustomerStatus::class)],
        ];
    }

    /**
     * Validasi lintas-kolom: paket, ODP, dan port harus konsisten dengan router yang dipilih.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['router_id', 'package_id', 'odp_id', 'odp_port'])) {
                    return;
                }

                $routerId = $this->integer('router_id');
                $package = Package::find($this->integer('package_id'));

                if ($package->router_id !== null && $package->router_id !== $routerId) {
                    $validator->errors()->add('package_id', 'Paket ini tidak tersedia pada router yang dipilih.');
                }

                if (! $this->filled('odp_id')) {
                    return;
                }

                $odp = Odp::find($this->integer('odp_id'));

                if ($odp->router_id !== $routerId) {
                    $validator->errors()->add('odp_id', 'ODP ini tidak berada pada router yang dipilih.');

                    return;
                }

                if (! $this->filled('odp_port')) {
                    return;
                }

                if ($this->integer('odp_port') > $odp->total_ports) {
                    $validator->errors()->add('odp_port', "Port melebihi kapasitas ODP ({$odp->total_ports}).");
                } elseif (
                    Customer::where('odp_id', $odp->id)
                        ->where('odp_port', $this->integer('odp_port'))
                        ->when($this->route('customer'), fn ($query, Customer $customer) => $query->whereKeyNot($customer->id))
                        ->exists()
                ) {
                    $validator->errors()->add('odp_port', 'Port ini sudah dipakai pelanggan lain.');
                }
            },
        ];
    }

    /**
     * Data siap simpan; password kosong saat edit tidak diubah, dan tanggal tagihan mengikuti siklus.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = $this->validated();

        if (blank($data['pppoe_password'] ?? null)) {
            unset($data['pppoe_password']);
        }

        if ($data['billing_cycle'] === BillingCycle::Anniversary->value) {
            $data['billing_day'] = (int) date('j', strtotime($data['installed_at']));
        }

        return $data;
    }
}
