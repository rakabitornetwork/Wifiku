<?php

namespace App\Services;

use App\Models\GalleryPhoto;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Foto galeri tidak pernah disimpan apa adanya: didekode, diputar sesuai EXIF, lalu disimpan ulang
 * sebagai WebP (besar 1600 px dan thumbnail 640 px). Metadata seperti lokasi GPS rumah pelanggan ikut hilang.
 */
class GalleryPhotoService
{
    public const FULL = 1600;

    public const THUMB = 640;

    private const MAX_PIXELS = 16_000_000;

    private const ALLOWED_TYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

    /**
     * @param  array{title: string, caption?: string|null, is_published?: bool}  $data
     *
     * @throws ValidationException
     */
    public function add(UploadedFile $file, array $data): GalleryPhoto
    {
        $source = $this->decode($file);
        $name = 'gallery/'.Str::random(24);

        Storage::disk('local')->put("{$name}.webp", $this->encode($source, self::FULL));
        Storage::disk('local')->put("{$name}-thumb.webp", $this->encode($source, self::THUMB));

        return GalleryPhoto::create([
            'title' => $data['title'],
            'caption' => $data['caption'] ?? null,
            'is_published' => $data['is_published'] ?? true,
            'path' => "{$name}.webp",
            'thumb_path' => "{$name}-thumb.webp",
            'sort_order' => (int) GalleryPhoto::max('sort_order') + 1,
        ]);
    }

    public function delete(GalleryPhoto $photo): void
    {
        Storage::disk('local')->delete([$photo->path, $photo->thumb_path]);
        $photo->delete();
    }

    /**
     * Dekode bytes gambar apa pun yang didukung lalu simpan ulang sebagai WebP (sisi terpanjang maksimal $maxSide).
     *
     * @throws ValidationException
     */
    public function normalize(string $raw, int $maxSide = self::FULL): string
    {
        return $this->encode($this->decodeRaw($raw, null), $maxSide);
    }

    private function decode(UploadedFile $file): GdImage
    {
        return $this->decodeRaw((string) file_get_contents($file->getRealPath()), $file->getRealPath());
    }

    private function decodeRaw(string $raw, ?string $exifPath): GdImage
    {
        $info = @getimagesizefromstring($raw);

        if ($info === false || ! in_array($info[2], self::ALLOWED_TYPES, true)) {
            throw $this->error('File bukan gambar JPG, PNG, atau WebP yang valid.');
        }

        $pixels = $info[0] * $info[1];
        $limit = ini_parse_quantity((string) ini_get('memory_limit'));

        if ($pixels < 1 || $pixels > self::MAX_PIXELS || ($limit > 0 && $pixels * 4 * 2.5 >= $limit - memory_get_usage(true))) {
            throw $this->error('Resolusi foto terlalu besar untuk diproses. Kecilkan fotonya lalu coba lagi.');
        }

        $image = @imagecreatefromstring($raw);

        if (! $image instanceof GdImage) {
            throw $this->error('Gambar rusak atau tidak dapat dibaca.');
        }

        if ($exifPath !== null && $info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $angle = match (@exif_read_data($exifPath)['Orientation'] ?? 1) {
                3 => 180,
                6 => -90,
                8 => 90,
                default => 0,
            };

            if ($angle !== 0) {
                $image = imagerotate($image, $angle, 0) ?: $image;
            }
        }

        return $image;
    }

    private function encode(GdImage $source, int $maxSide): string
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, $maxSide / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $output = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($output, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        ob_start();
        imagewebp($output, null, 82);

        return (string) ob_get_clean();
    }

    private function error(string $message): ValidationException
    {
        return ValidationException::withMessages(['photo' => $message]);
    }
}
