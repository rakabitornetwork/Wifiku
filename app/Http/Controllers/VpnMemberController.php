<?php

namespace App\Http\Controllers;

use App\Enums\VpnAccountStatus;
use App\Http\Requests\VpnMemberUpdateRequest;
use App\Jobs\Vpn\SyncVpnAccountJob;
use App\Models\VpnAccount;
use App\Models\VpnMember;
use App\Services\Settings\SettingService;
use App\Services\Whatsapp\VpnNotifier;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin: mengelola pelanggan VPN Tunnel yang mendaftar sendiri lewat landing page.
 */
class VpnMemberController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search'));
        $status = in_array($request->query('status'), ['active', 'inactive'], true) ? (string) $request->query('status') : '';

        $members = VpnMember::query()
            ->withCount([
                'accounts',
                'accounts as active_accounts_count' => fn ($query) => $query->where('status', VpnAccountStatus::Active),
                'accounts as pending_accounts_count' => fn ($query) => $query->where('status', VpnAccountStatus::Pending),
            ])
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('whatsapp', 'like', "%{$search}%")))
            ->when($status !== '', fn ($query) => $query->where('is_active', $status === 'active'))
            ->latest('id')
            ->tap(fn ($query) => $this->applySort($query, [
                'name' => 'name',
                'email' => 'email',
                'accounts' => 'accounts_count',
                'created_at' => 'created_at',
                'last_login_at' => 'last_login_at',
                'status' => 'is_active',
            ]))
            ->paginate($this->perPage(15))
            ->withQueryString();

        return Inertia::render('Vpn/Members', [
            'members' => $members,
            'search' => $search,
            'status' => $status,
            'maxAccounts' => (int) app(SettingService::class)->get('vpn', 'member_max_accounts'),
        ]);
    }

    public function update(VpnMemberUpdateRequest $request, VpnMember $member): RedirectResponse
    {
        $data = $request->safe()->only(['name', 'email', 'whatsapp', 'is_active']);
        $deactivating = $member->is_active && ! $data['is_active'];

        $member->update($data);

        $cut = 0;

        if ($deactivating && $request->boolean('disable_accounts')) {
            $cut = $this->disableAccounts($member);
        }

        Toast::success($cut > 0
            ? "Member diperbarui dan {$cut} akun VPN-nya dinonaktifkan (tunnel diputus lewat antrean)."
            : 'Data member diperbarui.');

        return back();
    }

    /**
     * Nonaktifkan manual semua akun aktif milik member; tidak akan dibuka otomatis oleh pembayaran.
     */
    private function disableAccounts(VpnMember $member): int
    {
        $accounts = $member->accounts()->with('server:id,router_id')->where('status', VpnAccountStatus::Active)->get();

        $accounts->each(function (VpnAccount $account): void {
            $account->update(['status' => VpnAccountStatus::Disabled, 'auto_disabled_at' => null]);

            SyncVpnAccountJob::dispatch($account->id, $account->server->router_id);
        });

        return $accounts->count();
    }

    /**
     * Kirim tautan reset password ke WhatsApp member, hasilnya dilaporkan apa adanya kepada admin.
     */
    public function sendResetLink(VpnMember $member, VpnNotifier $notifier): RedirectResponse
    {
        $status = Password::broker('members')->sendResetLink(
            ['email' => $member->email],
            fn (VpnMember $target, string $token) => $notifier->passwordReset($target, route('vpn-member.password.reset', ['token' => $token, 'email' => $target->email])),
        );

        match ($status) {
            Password::RESET_LINK_SENT => Toast::success("Tautan reset dikirim ke WhatsApp {$member->whatsapp}."),
            Password::RESET_THROTTLED => Toast::error('Tautan baru saja dikirim. Tunggu sekitar satu menit sebelum mengirim lagi.'),
            default => Toast::error('Tautan reset tidak dapat dikirim.'),
        };

        return back();
    }

    public function destroy(VpnMember $member): RedirectResponse
    {
        if ($member->accounts()->exists()) {
            Toast::error('Member masih memiliki akun VPN. Hapus akunnya lebih dulu, atau nonaktifkan member.');

            return back();
        }

        $member->delete();

        Toast::success('Member dihapus.');

        return back();
    }
}
