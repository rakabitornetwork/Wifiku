<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\User;
use App\Services\Whatsapp\AdminNotifier;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Users/Index', [
            'users' => User::query()
                ->with('roles:id,name')
                ->orderBy('name')
                ->tap(fn ($query) => $this->applySort($query, $this->sortColumns()))
                ->paginate($this->perPage(20))
                ->through(fn (User $user): array => [
                    ...$user->only(['id', 'name', 'email', 'phone', 'is_active', 'last_login_at']),
                    'role' => $user->roles->first()?->name,
                ]),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = User::create([
            ...$request->safe()->only(['name', 'email', 'phone', 'password']),
            'is_active' => $request->boolean('is_active', true),
            'email_verified_at' => now(),
        ]);
        $user->syncRoles([$request->string('role')->toString()]);

        Toast::success("Pengguna {$user->name} dibuat.");

        return back();
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $role = $request->string('role')->toString();
        $isActive = $request->boolean('is_active');

        if ($this->wouldLeaveNoActiveOwner($user, $role, $isActive)) {
            Toast::error('Harus ada minimal satu Owner aktif.');

            return back();
        }

        if ($user->is($request->user()) && ! $isActive) {
            Toast::error('Anda tidak dapat menonaktifkan akun sendiri.');

            return back();
        }

        $data = $request->safe()->only(['name', 'email', 'phone']);

        if (filled($request->input('password'))) {
            $data['password'] = $request->input('password');
        }

        $user->update([...$data, 'is_active' => $isActive]);
        $user->syncRoles([$role]);

        Toast::success('Pengguna diperbarui.');

        return back();
    }

    /**
     * Kirim tautan reset password ke WhatsApp pengguna; hasilnya dilaporkan apa adanya kepada Owner.
     */
    public function sendResetLink(User $user, AdminNotifier $notifier): RedirectResponse
    {
        if (blank($user->phone)) {
            Toast::error('Pengguna belum punya nomor telepon. Isi nomornya (format 08xx) lebih dulu.');

            return back();
        }

        $status = Password::sendResetLink(
            ['email' => $user->email, 'is_active' => true],
            fn (User $target, string $token) => $notifier->passwordReset($target, route('password.reset', ['token' => $token, 'email' => $target->email])),
        );

        match ($status) {
            Password::RESET_LINK_SENT => Toast::success("Tautan reset dikirim ke WhatsApp {$user->phone}."),
            Password::RESET_THROTTLED => Toast::error('Tautan baru saja dikirim. Tunggu sekitar satu menit sebelum mengirim lagi.'),
            default => Toast::error('Tautan reset tidak dapat dikirim (akun mungkin dinonaktifkan).'),
        };

        return back();
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            Toast::error('Anda tidak dapat menghapus akun sendiri.');

            return back();
        }

        if ($this->wouldLeaveNoActiveOwner($user, '', false)) {
            Toast::error('Harus ada minimal satu Owner aktif.');

            return back();
        }

        if ($user->voucherBatchesCreated()->exists() || $user->expensesCreated()->exists() || $user->paymentsReceived()->exists()) {
            Toast::error('Pengguna memiliki riwayat transaksi. Nonaktifkan akun saja agar riwayat tetap utuh.');

            return back();
        }

        $user->delete();

        Toast::success('Pengguna dihapus.');

        return back();
    }

    /**
     * True bila perubahan ini membuat tidak ada lagi Owner yang aktif.
     */
    private function wouldLeaveNoActiveOwner(User $user, string $newRole, bool $newActive): bool
    {
        if (! $user->hasRole('Owner') || ($newRole === 'Owner' && $newActive)) {
            return false;
        }

        return ! User::role('Owner')->where('is_active', true)->whereKeyNot($user->id)->exists();
    }

    /**
     * @return array<string, string|Closure>
     */
    private function sortColumns(): array
    {
        return [
            'name' => 'name',
            'email' => 'email',
            'phone' => 'phone',
            'role' => fn () => DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.model_type', User::class)
                ->whereColumn('model_has_roles.model_id', 'users.id')
                ->select('roles.name')
                ->limit(1),
            'last_login' => 'last_login_at',
            'status' => 'is_active',
        ];
    }
}
