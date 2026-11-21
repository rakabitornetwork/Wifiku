<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\User;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
}
