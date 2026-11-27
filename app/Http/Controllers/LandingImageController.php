<?php

namespace App\Http\Controllers;

use App\Models\LandingImage;
use App\Services\GalleryPhotoService;
use App\Services\Gemini\GeminiException;
use App\Services\Gemini\GeminiImageService;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Ilustrasi landing page: dibuat dengan Gemini atau diunggah sendiri. Satu gambar per slot.
 */
class LandingImageController extends Controller
{
    public function index(GeminiImageService $gemini): Response
    {
        $images = LandingImage::all()->keyBy('slot');

        return Inertia::render('LandingImages/Index', [
            'geminiConfigured' => $gemini->isConfigured(),
            'slots' => collect(LandingImage::SLOTS)->map(fn (array $slot, string $name) => [
                'slot' => $name,
                'label' => $slot['label'],
                'aspect' => $slot['aspect'],
                'default_prompt' => $slot['prompt'],
                'prompt' => $images[$name]->prompt ?? $slot['prompt'],
                'url' => isset($images[$name]) ? route('landing-image.show', [$name, 'v' => $images[$name]->updated_at?->timestamp]) : null,
                'is_ai' => (bool) ($images[$name]->is_ai ?? false),
            ])->values(),
        ]);
    }

    public function generate(Request $request, string $slot, GeminiImageService $gemini, GalleryPhotoService $images): RedirectResponse
    {
        abort_unless(isset(LandingImage::SLOTS[$slot]), 404);
        $data = $request->validate(['prompt' => ['required', 'string', 'min:10', 'max:1000']]);

        try {
            $raw = $gemini->generate($data['prompt'], LandingImage::SLOTS[$slot]['aspect']);
            $encoded = $images->normalize($raw);
        } catch (GeminiException $exception) {
            Toast::error($exception->getMessage());

            return back();
        }

        $this->store($slot, $encoded, $data['prompt'], true);
        activity('Landing')->causedBy($request->user())->log("Membuat ilustrasi {$slot} dengan AI");
        Toast::success('Ilustrasi dibuat dan sudah tampil di landing page.');

        return back();
    }

    public function upload(Request $request, string $slot, GalleryPhotoService $images): RedirectResponse
    {
        abort_unless(isset(LandingImage::SLOTS[$slot]), 404);
        $request->validate(['image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:min_width=400,min_height=200']]);

        $this->store($slot, $images->normalize((string) file_get_contents($request->file('image')->getRealPath())), null, false);
        activity('Landing')->causedBy($request->user())->log("Mengunggah gambar {$slot}");
        Toast::success('Gambar diperbarui.');

        return back();
    }

    public function destroy(Request $request, string $slot): RedirectResponse
    {
        abort_unless(isset(LandingImage::SLOTS[$slot]), 404);

        if ($image = LandingImage::where('slot', $slot)->first()) {
            Storage::disk('local')->delete($image->path);
            $image->delete();
            activity('Landing')->causedBy($request->user())->log("Menghapus gambar {$slot}");
        }

        Toast::success('Gambar dihapus; landing kembali memakai ilustrasi bawaan.');

        return back();
    }

    public function show(string $slot): BinaryFileResponse
    {
        $image = LandingImage::where('slot', $slot)->firstOrFail();
        abort_unless(Storage::disk('local')->exists($image->path), 404);

        $response = response()->file(Storage::disk('local')->path($image->path), ['Content-Type' => 'image/webp', 'X-Content-Type-Options' => 'nosniff']);
        $response->setPublic();
        $response->setMaxAge(604800);

        return $response;
    }

    private function store(string $slot, string $encoded, ?string $prompt, bool $ai): void
    {
        $path = 'landing/'.$slot.'-'.Str::random(16).'.webp';
        Storage::disk('local')->put($path, $encoded);

        $previous = LandingImage::where('slot', $slot)->first();
        LandingImage::updateOrCreate(['slot' => $slot], ['path' => $path, 'prompt' => $prompt, 'is_ai' => $ai]);

        if ($previous) {
            Storage::disk('local')->delete($previous->path);
        }
    }
}
