<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\Notification\HandleChatMessageJob;
use App\Services\Chat\ChatMessage;
use App\Services\Chat\ChatWebhookSecrets;
use App\Services\Settings\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Pintu masuk pesan pelanggan untuk bot WhatsApp (Evolution API) dan Telegram. Pesan tidak diproses di sini: setelah
 * diverifikasi dan disaring, pesan diantrekan dan webhook langsung dijawab 200 agar pengirim tidak mengulang.
 */
class ChatWebhookController extends Controller
{
    public function __construct(private readonly SettingService $settings, private readonly ChatWebhookSecrets $secrets) {}

    public function whatsapp(Request $request, string $secret): JsonResponse
    {
        abort_unless(hash_equals($this->secrets->whatsapp(), $secret), 403);

        if ($this->settings->get('whatsapp', 'bot_enabled') !== '1') {
            return response()->json(['ok' => true]);
        }

        $payload = (array) $request->json()->all();
        $instance = (string) ($payload['instance'] ?? '');

        if ($instance !== '' && $instance !== (string) $this->settings->get('whatsapp', 'instance')) {
            return response()->json(['ok' => true]);
        }

        $message = $this->whatsappMessage((array) ($payload['data'] ?? []));

        if ($message !== null && $this->firstTime($message)) {
            HandleChatMessageJob::dispatch($message->toArray());
        }

        return response()->json(['ok' => true]);
    }

    public function telegram(Request $request): JsonResponse
    {
        $header = (string) $request->header('X-Telegram-Bot-Api-Secret-Token');

        abort_unless($header !== '' && hash_equals($this->secrets->telegram(), $header), 403);

        if ($this->settings->get('telegram', 'bot_enabled') !== '1') {
            return response()->json(['ok' => true]);
        }

        $message = $this->telegramMessage((array) $request->json()->all());

        if ($message !== null && $this->firstTime($message)) {
            HandleChatMessageJob::dispatch($message->toArray());
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Pesan teks pribadi dari pelanggan; pesan sendiri, grup, siaran status, dan media diabaikan.
     *
     * @param  array<string, mixed>  $data
     */
    private function whatsappMessage(array $data): ?ChatMessage
    {
        $key = (array) ($data['key'] ?? []);
        $jid = (string) ($key['remoteJid'] ?? '');

        if (($key['fromMe'] ?? false) === true || str_contains($jid, '@g.us') || str_contains($jid, 'status@broadcast')) {
            return null;
        }

        // Akun WhatsApp baru memakai ID @lid; nomor aslinya ada di kolom tambahan bila disediakan.
        $number = str_ends_with($jid, '@s.whatsapp.net') ? $jid : (string) ($key['senderPn'] ?? $key['remoteJidAlt'] ?? $data['senderPn'] ?? '');
        $phone = preg_replace('/\D+/', '', explode('@', $number)[0]) ?? '';

        if (! preg_match('/^62\d{8,13}$/', $phone)) {
            return null;
        }

        $content = (array) ($data['message'] ?? []);
        $text = $content['conversation'] ?? $content['extendedTextMessage']['text'] ?? $content['buttonsResponseMessage']['selectedButtonId'] ?? $content['listResponseMessage']['singleSelectReply']['selectedRowId'] ?? null;

        if (! is_string($text) || trim($text) === '') {
            return null;
        }

        return new ChatMessage(ChatMessage::CHANNEL_WHATSAPP, $phone, mb_substr($text, 0, 500), isset($key['id']) ? (string) $key['id'] : null);
    }

    /**
     * @param  array<string, mixed>  $update
     */
    private function telegramMessage(array $update): ?ChatMessage
    {
        if (isset($update['callback_query'])) {
            $callback = (array) $update['callback_query'];
            $chat = (array) ($callback['message']['chat'] ?? []);

            if (($chat['type'] ?? '') !== 'private') {
                return null;
            }

            return new ChatMessage(ChatMessage::CHANNEL_TELEGRAM, (string) $chat['id'], mb_substr((string) ($callback['data'] ?? ''), 0, 60), (string) ($callback['id'] ?? ''), callbackId: (string) ($callback['id'] ?? ''));
        }

        $message = (array) ($update['message'] ?? []);
        $chat = (array) ($message['chat'] ?? []);

        if (($chat['type'] ?? '') !== 'private' || ! isset($chat['id'])) {
            return null;
        }

        $messageId = isset($message['message_id']) ? (string) $message['message_id'] : null;

        if (isset($message['contact'])) {
            $contact = (array) $message['contact'];
            $own = isset($contact['user_id'], $message['from']['id']) && (string) $contact['user_id'] === (string) $message['from']['id'];

            return $own
                ? new ChatMessage(ChatMessage::CHANNEL_TELEGRAM, (string) $chat['id'], null, $messageId, contactPhone: (string) ($contact['phone_number'] ?? ''))
                : new ChatMessage(ChatMessage::CHANNEL_TELEGRAM, (string) $chat['id'], ChatMessage::FOREIGN_CONTACT, $messageId);
        }

        $text = $message['text'] ?? null;

        return is_string($text) && trim($text) !== '' ? new ChatMessage(ChatMessage::CHANNEL_TELEGRAM, (string) $chat['id'], mb_substr($text, 0, 500), $messageId) : null;
    }

    /**
     * Pengirim webhook bisa mengulang pengiriman; satu id pesan hanya diproses sekali.
     */
    private function firstTime(ChatMessage $message): bool
    {
        return $message->messageId === null || Cache::add("chat:seen:{$message->channel}:{$message->messageId}", 1, now()->addMinutes(10));
    }
}
