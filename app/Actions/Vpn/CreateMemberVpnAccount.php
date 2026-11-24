<?php

namespace App\Actions\Vpn;

use App\Enums\VpnAccountStatus;
use App\Enums\VpnProtocol;
use App\Models\VpnAccount;
use App\Models\VpnMember;
use App\Models\VpnServer;
use App\Services\Settings\SettingService;
use App\Services\Vpn\VpnAddressAllocator;
use App\Services\Vpn\VpnInvoiceGenerator;
use App\Services\Vpn\VpnPortPlanner;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Member membuat akun VPN: dibatasi kuota per member, dipasang di server yang masih punya IP kosong,
 * dan langsung ditagih. Akun baru berstatus `pending` (belum dipasang di router) sampai invoice-nya lunas.
 */
class CreateMemberVpnAccount
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly VpnAddressAllocator $allocator,
        private readonly VpnPortPlanner $ports,
        private readonly VpnInvoiceGenerator $invoices,
        private readonly SettingService $settings,
    ) {}

    /**
     * @param  list<int>  $forwardTargets  Port tujuan layanan standar yang diteruskan (22, 80, 8291, 8728, ...).
     *
     * @throws ValidationException Kuota penuh atau tidak ada server yang tersedia.
     */
    public function handle(VpnMember $member, string $label, VpnProtocol $protocol, array $forwardTargets): VpnAccount
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(function () use ($member, $label, $protocol, $forwardTargets): VpnAccount {
                    // Kunci baris member agar dua permintaan bersamaan tidak melewati batas kuota.
                    VpnMember::query()->whereKey($member->id)->lockForUpdate()->first();

                    $max = (int) $this->settings->get('vpn', 'member_max_accounts');

                    if ($member->accounts()->count() >= $max) {
                        throw ValidationException::withMessages(['label' => "Batas {$max} akun VPN per pelanggan sudah tercapai."]);
                    }

                    $server = $this->serverFor($protocol);
                    $address = $this->allocator->next($server);

                    $account = VpnAccount::create([
                        'vpn_server_id' => $server->id,
                        'vpn_member_id' => $member->id,
                        'name' => $label,
                        'whatsapp' => $member->whatsapp,
                        'protocol' => $protocol,
                        'username' => $this->username($member),
                        'password' => Str::password(14, symbols: false),
                        'remote_address' => $address,
                        'forwards' => $this->forwards($server, $address, $forwardTargets),
                        'status' => VpnAccountStatus::Pending,
                        'monthly_price' => (int) $this->settings->get('vpn', 'member_price'),
                    ]);

                    $this->invoices->issueInitial($account, CarbonImmutable::today());

                    return $account;
                });
            } catch (UniqueConstraintViolationException $exception) {
                // IP atau username direbut proses lain di saat yang sama: ulangi dengan IP berikutnya.
                if ($attempt >= self::MAX_ATTEMPTS) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * Server aktif pertama yang mendukung protokol dan masih punya IP kosong.
     */
    private function serverFor(VpnProtocol $protocol): VpnServer
    {
        $server = VpnServer::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->first(fn (VpnServer $candidate): bool => $candidate->supports($protocol) && $this->allocator->next($candidate) !== null);

        return $server ?? throw ValidationException::withMessages(['protocol' => 'Layanan dengan protokol ini sedang tidak tersedia. Coba protokol lain atau hubungi CS.']);
    }

    private function username(VpnMember $member): string
    {
        do {
            $username = 'v'.$member->id.'-'.Str::lower(Str::random(5));
        } while (VpnAccount::query()->where('username', $username)->exists());

        return $username;
    }

    /**
     * @param  list<int>  $targets
     * @return list<array{name: string, public_port: int, target_port: int}>|null
     */
    private function forwards(VpnServer $server, string $address, array $targets): ?array
    {
        $forwards = [];

        foreach (VpnPortPlanner::PRESETS as $preset) {
            $port = in_array($preset['target_port'], $targets, true) ? $this->ports->publicPort($server, $address, $preset['target_port']) : null;

            if ($port !== null) {
                $forwards[] = ['name' => $preset['label'], 'public_port' => $port, 'target_port' => $preset['target_port']];
            }
        }

        return $forwards ?: null;
    }
}
