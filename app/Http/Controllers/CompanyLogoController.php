<?php

namespace App\Http\Controllers;

use App\Models\LandingImage;
use App\Services\GalleryPhotoService;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Logo perusahaan: satu gambar yang dipakai di seluruh aplikasi (panel admin, login, portal pelanggan, dan landing page).
 * Disimpan di tabel landing_images dengan slot khusus agar disajikan lewat /gambar-landing/logo.
 */
class CompanyLogoController extends Controller
{
    public function store(Request $request, GalleryPhotoService $images): RedirectResponse
    {
        $request->validate(['logo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:min_width=64,min_height=64']]);

        $encoded = $images->normalize((string) file_get_contents($request->file('logo')->getRealPath()), 512, true);
        $path = 'landing/'.LandingImage::LOGO_SLOT.'-'.Str::random(16).'.webp';
        Storage::disk('local')->put($path, $encoded);

        $previous = LandingImage::where('slot', LandingImage::LOGO_SLOT)->first();
        LandingImage::updateOrCreate(['slot' => LandingImage::LOGO_SLOT], ['path' => $path, 'prompt' => null, 'is_ai' => false]);

        if ($previous) {
            Storage::disk('local')->delete($previous->path);
        }

        activity('Pengaturan')->causedBy($request->user())->log('Mengganti logo perusahaan');
        Toast::success('Logo diperbarui.');

        return back();
    }

    public function destroy(Request $request): RedirectResponse
    {
        if ($image = LandingImage::where('slot', LandingImage::LOGO_SLOT)->first()) {
            Storage::disk('local')->delete($image->path);
            $image->delete();
            activity('Pengaturan')->causedBy($request->user())->log('Menghapus logo perusahaan');
        }

        Toast::success('Logo dihapus; aplikasi kembali memakai ikon bawaan.');

        return back();
    }
}
