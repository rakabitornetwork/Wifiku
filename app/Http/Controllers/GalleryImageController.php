<?php

namespace App\Http\Controllers;

use App\Models\GalleryPhoto;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Menyajikan foto galeri yang berstatus tayang ke publik; foto draft hanya untuk admin.
 */
class GalleryImageController extends Controller
{
    public function show(GalleryPhoto $photo, string $size): BinaryFileResponse
    {
        abort_unless(in_array($size, ['thumb', 'full'], true), 404);
        abort_unless($photo->is_published || request()->user('web') !== null, 404);

        $path = $size === 'thumb' ? $photo->thumb_path : $photo->path;
        abort_unless(Storage::disk('local')->exists($path), 404);

        $response = response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => 'image/webp',
            'X-Content-Type-Options' => 'nosniff',
        ]);

        // URL memuat versi (updated_at), jadi aman di-cache lama.
        $response->setPublic();
        $response->setMaxAge(604800);

        return $response;
    }
}
