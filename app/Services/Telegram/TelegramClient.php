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

    /** Bot pelanggan hanya butuh token; chat ID tujuan peringatan admin tidak diperlukan. */
    public function hasToken(): bool
    {
        return filled($this->token());
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

        $this->sendTo($chatId, $text);
    }

    /**
     * Kirim pesan ke chat tertentu (balasan bot pelanggan), dengan papan tombol opsional.
     *
     * @param  array<string, mixed>|null  $replyMarkup
     *
     * @throws TelegramDeliveryException
     */
    public function sendTo(string $chatId, string $text, ?array $replyMarkup = null): void
    {
        $this->call('sendMessage', array_filter([
            'chat_id' => $chatId,
            'text' => Str::limit($text, 3900, '…'),
            'disable_web_page_preview' => true,
            'reply_markup' => $replyMarkup,
        ], fn (mixed $value): bool => $value !== null));
    }

    /**
     * @throws TelegramDeliveryException
     */
    public function answerCallbackQuery(string $callbackId): void
    {
        $this->call('answerCallbackQuery', ['callback_query_id' => $callbackId]);
    }

    /**
     * Hapus pesan pengguna di chat pribadi (mis. yang berisi password WiFi); gagal tidak fatal bagi pemanggil.
     *
     * @throws TelegramDeliveryException
     */
    public function deleteMessage(string $chatId, string $messageId): void
    {
        $this->call('deleteMessage', ['chat_id' => $chatId, 'message_id' => (int) $messageId]);
    }

    /**
     * Arahkan pembaruan bot ke $url; Telegram menyertakan $secret pada header X-Telegram-Bot-Api-Secret-Token.
     *
     * @throws TelegramDeliveryException
     */
    public function setWebhook(string $url, string $secret): void
    {
        $this->call('setWebhook', ['url' => $url, 'secret_token' => $secret, 'allowed_updates' => ['message', 'callback_query'], 'drop_pending_updates' => true]);
    }

    /**
     * @return array{url: string, pending: int, last_error: string|null}
     *
     * @throws TelegramDeliveryException
     */
    public function webhookInfo(): array
    {
        $info = (array) ($this->call('getWebhookInfo')['result'] ?? []);

        return ['url' => (string) ($info['url'] ?? ''), 'pending' => (int) ($info['pending_update_count'] ?? 0), 'last_error' => $info['last_error_message'] ?? null];
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
