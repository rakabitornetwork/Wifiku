<?php

namespace App\Http\Requests;

use App\Enums\VpnAccountStatus;
use App\Enums\VpnProtocol;
use App\Models\VpnAccount;
use App\Models\VpnServer;
use App\Services\Vpn\VpnAddressAllocator;
use App\Services\Vpn\VpnPortPlanner;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class VpnAccountRequest extends FormRequest
{
    /**
     * Port publik yang tidak boleh dipakai port forward (layanan router server sendiri).
     *
     * @var list<int>
     */
    private const RESERVED_PORTS = [21, 22, 23, 53, 80, 443, 500, 1701, 4500, 8291, 8728, 8729];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $whatsapp = preg_replace('/\D+/', '', (string) $this->input('whatsapp'));

        if ($whatsapp !== '' && str_starts_with($whatsapp, '0')) {
            $whatsapp = '62'.substr($whatsapp, 1);
        }

        $this->merge(['whatsapp' => $whatsapp === '' ? null : $whatsapp]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var VpnAccount|null $account */
        $account = $this->route('account');

        return [
            'vpn_server_id' => ['required', 'integer', 'exists:vpn_servers,id'],
            'name' => ['required', 'string', 'max:255'],
            'whatsapp' => ['nullable', 'string', 'regex:/^62\d{8,13}$/'],
            'protocol' => ['required', Rule::enum(VpnProtocol::class)],
            'username' => [
                'required', 'string', 'regex:/^[A-Za-z0-9._-]{3,40}$/',
                Rule::unique('vpn_accounts')->where('vpn_server_id', $this->integer('vpn_server_id'))->ignore($account),
            ],
            'password' => ['nullable', 'string', 'min:6', 'max:64', 'regex:/^[^"\\\\]+$/'],
            'remote_address' => ['nullable', 'ipv4'],
            'forwards' => ['nullable', 'array', 'max:10'],
            'forwards.*.name' => ['required', 'string', 'max:40'],
            'forwards.*.public_port' => ['nullable', 'integer', 'between:1024,65535'],
            'forwards.*.target_port' => ['required', 'integer', 'between:1,65535'],
            'status' => ['required', Rule::enum(VpnAccountStatus::class)],
            'expires_at' => ['nullable', 'date'],
            'monthly_price' => ['required', 'integer', 'min:0', 'max:100000000'],
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

                /** @var VpnAccount|null $account */
                $account = $this->route('account');
                $server = VpnServer::findOrFail($this->integer('vpn_server_id'));
                $allocator = app(VpnAddressAllocator::class);

                if ($account !== null && $account->vpn_server_id !== $server->id) {
                    $validator->errors()->add('vpn_server_id', 'Server akun tidak dapat dipindahkan. Buat akun baru di server lain.');

                    return;
                }

                if (! $server->supports(VpnProtocol::from($this->string('protocol')->toString()))) {
                    $validator->errors()->add('protocol', 'Protokol ini tidak diaktifkan di server yang dipilih.');
                }

                if ($account === null && ! $server->is_active) {
                    $validator->errors()->add('vpn_server_id', 'Server VPN ini sedang dinonaktifkan.');
                }

                $address = $this->input('remote_address');

                if (filled($address)) {
                    // Akun hasil impor boleh tetap memakai IP lamanya yang berada di luar pool server ini.
                    if ($account?->adopted && $address === $account->remote_address) {
                        // dibiarkan
                    } elseif (! $allocator->inPool($server, $address)) {
                        $validator->errors()->add('remote_address', "IP harus berada di pool server ({$server->pool_start} - {$server->pool_end}).");
                    } elseif ($server->accounts()->where('remote_address', $address)->when($account, fn ($query) => $query->whereKeyNot($account->id))->exists()) {
                        $validator->errors()->add('remote_address', 'IP tunnel ini sudah dipakai akun lain.');
                    }
                } elseif ($account === null && $allocator->next($server) === null) {
                    $validator->errors()->add('vpn_server_id', 'Pool IP server ini sudah penuh.');
                }

                $this->validateForwards($validator, $server, $account);
            },
        ];
    }

    /**
     * IP tunnel akhir akun: isian, IP lama saat edit, atau IP bebas berikutnya di pool.
     */
    private function resolvedAddress(VpnServer $server, ?VpnAccount $account): ?string
    {
        return filled($this->input('remote_address'))
            ? $this->string('remote_address')->toString()
            : ($account?->remote_address ?? app(VpnAddressAllocator::class)->next($server));
    }

    /**
     * Forward dengan port publik terisi; port kosong dihitung dari pola server (butuh IP tunnel akhir).
     *
     * @return list<array{name: string, public_port: int|null, target_port: int}>
     */
    private function resolvedForwards(VpnServer $server, ?VpnAccount $account): array
    {
        $address = $this->resolvedAddress($server, $account);

        return collect((array) $this->input('forwards', []))->map(fn (array $forward): array => [
            'name' => (string) $forward['name'],
            'public_port' => filled($forward['public_port'] ?? null)
                ? (int) $forward['public_port']
                : ($address !== null ? app(VpnPortPlanner::class)->publicPort($server, $address, (int) $forward['target_port']) : null),
            'target_port' => (int) $forward['target_port'],
        ])->values()->all();
    }

    private function validateForwards(Validator $validator, VpnServer $server, ?VpnAccount $account): void
    {
        $taken = $server->accounts()
            ->when($account, fn ($query) => $query->whereKeyNot($account->id))
            ->pluck('forwards')
            ->flatten(1)
            ->pluck('public_port')
            ->map(fn (mixed $port): int => (int) $port);

        $seen = [];

        foreach ($this->resolvedForwards($server, $account) as $index => $forward) {
            $key = "forwards.{$index}.public_port";
            $port = $forward['public_port'];

            if ($port === null) {
                $validator->errors()->add($key, 'Port ini bukan layanan standar atau di luar rentang; isi port publik secara manual.');
            } elseif (in_array($port, self::RESERVED_PORTS, true)) {
                $validator->errors()->add($key, 'Port ini dipakai layanan router server.');
            } elseif (in_array($port, $seen, true)) {
                $validator->errors()->add($key, 'Port publik ini dipakai dua kali.');
            } elseif ($taken->contains($port)) {
                $validator->errors()->add($key, 'Port publik ini sudah dipakai akun lain.');
            }

            $seen[] = $port;
        }
    }

    /**
     * Data siap simpan: IP tunnel dan password diisi otomatis bila kosong (saat edit, password kosong = tidak diubah).
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = $this->validated();
        /** @var VpnAccount|null $account */
        $account = $this->route('account');

        if (blank($data['password'] ?? null)) {
            unset($data['password']);

            if ($account === null) {
                $data['password'] = Str::password(14, symbols: false);
            }
        }

        if ($account !== null && $data['status'] !== $account->status->value) {
            $data['auto_disabled_at'] = null;
        }

        if (blank($data['remote_address'] ?? null)) {
            $data['remote_address'] = $account?->remote_address
                ?? app(VpnAddressAllocator::class)->next(VpnServer::findOrFail($data['vpn_server_id']));
        }

        $server = VpnServer::findOrFail($data['vpn_server_id']);
        $this->merge(['remote_address' => $data['remote_address']]);
        $data['forwards'] = $this->resolvedForwards($server, $account) ?: null;

        return $data;
    }
}
