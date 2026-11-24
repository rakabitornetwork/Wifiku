<?php

namespace App\Services\Auth;

use App\Models\User;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Foto profil: file unggahan tidak pernah disimpan apa adanya. Gambar didekode, diputar sesuai EXIF,
 * dipotong persegi di tengah, dikecilkan, lalu disimpan ulang sebagai WebP 256x256, sehingga metadata
 * (lokasi GPS dll.) dan data tersembunyi di dalam file asli ikut hilang.
 */
class AvatarService
{
    public const SIZE = 256;

    /** Batas piksel mutlak; batas sebenarnya juga mengikuti sisa memori PHP. */
    private const MAX_PIXELS = 16_000_000;

    private const ALLOWED_TYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

    /**
     * Simpan foto baru untuk $user, hapus foto lamanya, dan kembalikan path barunya.
     *
     * @throws ValidationException Bukan gambar yang didukung, atau terlalu besar untuk diproses.
     */
    public function replace(User $user, UploadedFile $file): string
    {
        $encoded = $this->process($file);
        $path = 'avatars/'.$user->id.'-'.Str::random(16).'.webp';

        Storage::disk('local')->put($path, $encoded);

        $previous = $user->avatar_path;
        $user->update(['avatar_path' => $path]);

        if (filled($previous)) {
            Storage::disk('local')->delete($previous);
        }

        return $path;
    }

    public function remove(User $user): void
    {
        if (filled($user->avatar_path)) {
            Storage::disk('local')->delete($user->avatar_path);
        }

        $user->update(['avatar_path' => null]);
    }

    private function process(UploadedFile $file): string
    {
        $raw = (string) file_get_contents($file->getRealPath());
        $info = @getimagesizefromstring($raw);

        if ($info === false || ! in_array($info[2], self::ALLOWED_TYPES, true)) {
            throw $this->error('File bukan gambar JPG, PNG, atau WebP yang valid.');
        }

        if (! $this->fitsInMemory($info[0], $info[1])) {
            throw $this->error('Resolusi foto terlalu besar untuk diproses. Kecilkan fotonya lalu coba lagi.');
        }

        $source = @imagecreatefromstring($raw);

        if (! $source instanceof GdImage) {
            throw $this->error('Gambar rusak atau tidak dapat dibaca.');
        }

        if ($info[2] === IMAGETYPE_JPEG) {
            $source = $this->applyExifOrientation($source, $file->getRealPath());
        }

        $square = $this->centerSquare($source);
        unset($source);

        $output = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagealphablending($output, false);
        imagesavealpha($output, true);
        imagefill($output, 0, 0, (int) imagecolorallocatealpha($output, 255, 255, 255, 127));
        imagecopyresampled($output, $square, 0, 0, 0, 0, self::SIZE, self::SIZE, imagesx($square), imagesy($square));

        ob_start();
        imagewebp($output, null, 85);

        return (string) ob_get_clean();
    }

    /**
     * Decode butuh sekitar 4 byte per piksel dan beberapa salinan saat memutar/memotong;
     * tolak gambar yang berisiko melewati memory_limit alih-alih membuat server error.
     */
    private function fitsInMemory(int $width, int $height): bool
    {
        $pixels = $width * $height;

        if ($pixels < 1 || $pixels > self::MAX_PIXELS) {
            return false;
        }

        $limit = ini_parse_quantity((string) ini_get('memory_limit'));

        return $limit <= 0 || $pixels * 4 * 2.5 < $limit - memory_get_usage(true);
    }

    private function applyExifOrientation(GdImage $image, string $path): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $angle = match (@exif_read_data($path)['Orientation'] ?? 1) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $angle === 0 ? $image : (imagerotate($image, $angle, 0) ?: $image);
    }

    private function centerSquare(GdImage $image): GdImage
    {
        $side = min(imagesx($image), imagesy($image));

        return imagecrop($image, [
            'x' => intdiv(imagesx($image) - $side, 2),
            'y' => intdiv(imagesy($image) - $side, 2),
            'width' => $side,
            'height' => $side,
        ]) ?: $image;
    }

    private function error(string $message): ValidationException
    {
        return ValidationException::withMessages(['avatar' => $message]);
    }
}
