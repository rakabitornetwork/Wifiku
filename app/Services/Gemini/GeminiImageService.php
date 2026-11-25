<?php

namespace App\Services\Gemini;

use App\Services\Settings\SettingService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Membuat gambar lewat Gemini API (Interactions API). API key dibaca dari Pengaturan (terenkripsi),
 * dan tidak pernah ikut tertulis di pesan error.
 */
class GeminiImageService
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/interactions';

    public function __construct(private readonly SettingService $settings) {}

    public function isConfigured(): bool
    {
        return filled($this->settings->get('gemini', 'api_key'));
    }

    /**
     * @return string Bytes gambar mentah (belum disanitasi).
     *
     * @throws GeminiException
     */
    public function generate(string $prompt, string $aspectRatio): string
    {
        $key = (string) $this->settings->get('gemini', 'api_key');

        if ($key === '') {
            throw new GeminiException('API key Gemini belum diisi di Pengaturan → Gemini.');
        }

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $key, 'Api-Revision' => '2026-05-20'])
                ->acceptJson()
                ->timeout(90)
                ->post(self::ENDPOINT, [
                    'model' => (string) $this->settings->get('gemini', 'model', 'gemini-nano-banana-2.1'),
                    'input' => [['type' => 'text', 'text' => $prompt]],
                    'response_format' => ['type' => 'image', 'aspect_ratio' => $aspectRatio, 'image_size' => '2K'],
                ]);
        } catch (ConnectionException) {
            throw new GeminiException('Tidak dapat terhubung ke Gemini (timeout atau jaringan). Coba lagi.');
        }

        if ($response->failed()) {
            throw new GeminiException($this->describeFailure($response->status(), (string) $response->json('error.message')));
        }

        $encoded = $this->findImageData($response->json());

        if ($encoded === null || ($bytes = base64_decode($encoded, true)) === false || $bytes === '') {
            throw new GeminiException('Gemini tidak mengembalikan gambar. Perbaiki prompt (mungkin ditolak filter keamanan) lalu coba lagi.');
        }

        return $bytes;
    }

    private function describeFailure(int $status, string $detail): string
    {
        return match (true) {
            in_array($status, [401, 403], true) => 'API key Gemini ditolak. Periksa key dan pastikan billing project aktif.',
            $status === 429 => 'Kuota atau batas permintaan Gemini terlampaui. Coba lagi nanti.',
            $status === 404 => 'Model gambar tidak ditemukan. Periksa nama model di Pengaturan → Gemini.',
            $status === 400 => 'Permintaan ditolak Gemini: '.mb_substr($detail !== '' ? $detail : 'parameter tidak valid', 0, 200),
            default => "Gemini sedang bermasalah (HTTP {$status}). Coba lagi nanti.",
        };
    }

    /**
     * Bentuk respons belum seragam antar versi API, jadi cari blok bertipe "image" (atau inlineData/inline_data)
     * yang memuat base64 di mana pun dalam struktur.
     *
     * @param  array<mixed>|null  $node
     */
    private function findImageData(?array $node): ?string
    {
        foreach ($node ?? [] as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            $isImageBlock = ($value['type'] ?? null) === 'image' || in_array($key, ['inlineData', 'inline_data'], true);

            if ($isImageBlock && is_string($value['data'] ?? null) && $value['data'] !== '') {
                return $value['data'];
            }

            if (($found = $this->findImageData($value)) !== null) {
                return $found;
            }
        }

        return null;
    }
}
