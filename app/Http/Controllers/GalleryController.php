<?php

namespace App\Http\Controllers;

use App\Http\Requests\GalleryPhotoRequest;
use App\Models\GalleryPhoto;
use App\Services\GalleryPhotoService;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengelolaan galeri foto instalasi untuk landing page.
 */
class GalleryController extends Controller
{
    public const LIMIT = 30;

    public function index(): Response
    {
        return Inertia::render('Gallery/Index', [
            'photos' => GalleryPhoto::orderBy('sort_order')->orderBy('id')->get()->map(fn (GalleryPhoto $photo) => [
                'id' => $photo->id,
                'title' => $photo->title,
                'caption' => $photo->caption,
                'is_published' => $photo->is_published,
                'thumb' => route('gallery.image', [$photo, 'thumb', 'v' => $photo->updated_at?->timestamp]),
            ]),
            'limit' => self::LIMIT,
        ]);
    }

    public function store(GalleryPhotoRequest $request, GalleryPhotoService $service): RedirectResponse
    {
        if (GalleryPhoto::count() >= self::LIMIT) {
            Toast::error('Galeri sudah penuh ('.self::LIMIT.' foto). Hapus foto lama terlebih dahulu.');

            return back();
        }

        $photo = $service->add($request->file('photo'), $request->safe()->only(['title', 'caption', 'is_published']));

        activity('Galeri')->performedOn($photo)->causedBy($request->user())->log('Menambah foto galeri');
        Toast::success('Foto ditambahkan ke galeri.');

        return back();
    }

    public function update(GalleryPhotoRequest $request, GalleryPhoto $photo): RedirectResponse
    {
        $photo->update($request->safe()->only(['title', 'caption', 'is_published']));
        activity('Galeri')->performedOn($photo)->causedBy($request->user())->log('Mengubah foto galeri');
        Toast::success('Foto galeri diperbarui.');

        return back();
    }

    public function destroy(GalleryPhoto $photo, GalleryPhotoService $service): RedirectResponse
    {
        $service->delete($photo);
        activity('Galeri')->performedOn($photo)->causedBy(request()->user())->log('Menghapus foto galeri');
        Toast::success('Foto dihapus dari galeri.');

        return back();
    }
}
