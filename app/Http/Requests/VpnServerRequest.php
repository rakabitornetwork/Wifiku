<?php

namespace App\Http\Requests;

use App\Models\VpnServer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class VpnServerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var VpnServer|null $server */
        $server = $this->route('server');

        return [
            'router_id' => ['required', 'integer', 'exists:routers,id,deleted_at,NULL'],
            'name' => ['required', 'string', 'max:255'],
            'public_host' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?$/'],
            'local_address' => ['required', 'ipv4'],
            'pool_start' => ['required', 'ipv4'],
            'pool_end' => ['required', 'ipv4'],
            'ipsec_secret' => ['nullable', 'string', 'min:8', 'max:128', 'regex:/^[^"\\\\]+$/'],
            'use_ipsec' => ['boolean'],
            'port_block_offset' => ['required', 'integer', 'between:1,400'],
            'sstp_port' => ['required', 'integer', 'between:1,65535'],
            'l2tp_enabled' => ['boolean'],
            'sstp_enabled' => ['boolean'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var VpnServer|null $server */
                $server = $this->route('server');
                $start = ip2long($this->string('pool_start')->toString());
                $end = ip2long($this->string('pool_end')->toString());
                $local = ip2long($this->string('local_address')->toString());

                if (! $this->boolean('l2tp_enabled') && ! $this->boolean('sstp_enabled')) {
                    $validator->errors()->add('l2tp_enabled', 'Aktifkan minimal satu protokol.');
                }

                if ($start > $end) {
                    $validator->errors()->add('pool_end', 'Akhir pool harus sama atau lebih besar dari awal pool.');
                }

                if ($local >= $start && $local <= $end) {
                    $validator->errors()->add('local_address', 'IP lokal server tidak boleh berada di dalam pool.');
                }

                $needsSecret = $this->boolean('l2tp_enabled') && $this->boolean('use_ipsec');

                if ($needsSecret && blank($this->input('ipsec_secret')) && blank($server?->ipsec_secret)) {
                    $validator->errors()->add('ipsec_secret', 'IPsec secret wajib diisi, atau matikan opsi IPsec untuk memakai L2TP polos.');
                }

                $outside = $server?->accounts()->get(['remote_address'])
                    ->filter(fn ($account): bool => ip2long($account->remote_address) < $start || ip2long($account->remote_address) > $end || ip2long($account->remote_address) === $local);

                if ($outside !== null && $outside->isNotEmpty()) {
                    $validator->errors()->add('pool_start', "Pool baru tidak mencakup IP akun yang sudah ada ({$outside->first()->remote_address}).");
                }
            },
        ];
    }

    /**
     * Data siap simpan; IPsec secret kosong saat edit berarti tidak diubah.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = $this->validated();

        if (blank($data['ipsec_secret'] ?? null)) {
            unset($data['ipsec_secret']);
        }

        return $data;
    }
}
