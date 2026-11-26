<?php

namespace App\Services\Telegram;

use App\Services\Settings\SettingService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Klien Telegram Bot API: cek bot (getMe) dan kirim pesan ke chat tujuan. Token tersimpan terenkripsi di Pengaturan dan
 * tidak pernah ikut dalam pesan galat (URL API memuat token, jadi setiap pesan dibersihkan dulu).
 */
class TelegramClient
{
    private const BASE_URL = 'https://api.telegram.org';

    public function __construct(private readonly SettingService $settings) {}

    public function isConfigured(): bool
    {
        return filled($this->token()) && filled($this->settings->get('telegram', 'chat_id'));
    }

    public function isEnabled(): bool
    {
        return $this->settings->get('telegram', 'enabled') === '1' && $this->isConfigured();
    }

    /**
     * Bot yang cocok dengan token tersimpan.
     *
     * @return array{username: string|null, name: string}
     *
     * @throws TelegramDeliveryException
     */
    public function getMe(): array
    {
        $result = $this->call('getMe')['result'] ?? [];

        return ['username' => $result['username'] ?? null, 'name' => (string) ($result['first_name'] ?? 'Bot')];
    }

    /**
     * @throws TelegramDeliveryException
     */
    public function sendMessage(string $text): void
    {
        $chatId = (string) $this->settings->get('telegram', 'chat_id');

        if ($chatId === '') {
            throw new TelegramDeliveryException('Chat ID Telegram belum diisi.');
        }

        $this->call('sendMessage', ['chat_id' => $chatId, 'text' => Str::limit($text, 3900, '…'), 'disable_web_page_preview' => true]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws TelegramDeliveryException
     */
    private function call(string $method, array $payload = []): array
    {
        $token = $this->token();

        if (blank($token)) {
            throw new TelegramDeliveryException('Token bot Telegram belum diisi.');
        }

        try {
            $response = $this->http()->post(self::BASE_URL."/bot{$token}/{$method}", $payload);
        } catch (ConnectionException $exception) {
            throw new TelegramDeliveryException('Tidak dapat menghubungi Telegram: '.$this->scrub($exception->getMessage(), $token), previous: $exception);
        }

        return $this->parse($response, $token);
    }

    /**
     * @return array<string, mixed>
     */
    private function parse(Response $response, string $token): array
    {
        $body = (array) $response->json();

        if ($response->failed() || ($body['ok'] ?? false) !== true) {
            throw new TelegramDeliveryException('Telegram menolak permintaan: '.$this->scrub((string) ($body['description'] ?? "HTTP {$response->status()}"), $token));
        }

        return $body;
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()->asJson()->connectTimeout(5)->timeout(10);
    }

    private function token(): ?string
    {
        return $this->settings->get('telegram', 'bot_token');
    }

    private function scrub(string $message, string $token): string
    {
        return str_replace($token, '***', $message);
    }
}
