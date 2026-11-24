<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Menyajikan foto profil dari disk privat; hanya untuk pengguna panel admin yang sudah login.
 */
class AvatarController extends Controller
{
    public function show(Request $request, User $user): BinaryFileResponse
    {
        abort_if($request->user('web') === null, 403);
        abort_if(blank($user->avatar_path) || ! Storage::disk('local')->exists($user->avatar_path), 404);

        $response = response()->file(Storage::disk('local')->path($user->avatar_path), [
            'Content-Type' => 'image/webp',
            'X-Content-Type-Options' => 'nosniff',
        ]);

        // Hanya cache browser pengguna (bukan cache bersama/proxy); URL berversi sehingga foto baru langsung terlihat.
        $response->setPrivate();
        $response->setMaxAge(604800);

        return $response;
    }
}
