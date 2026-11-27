<?php

namespace App\Http\Controllers;

use App\Services\Chat\ChatWebhookSecrets;
use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\TelegramDeliveryException;
use App\Services\Whatsapp\EvolutionApiClient;
use App\Services\Whatsapp\WhatsappDeliveryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Memasang webhook pesan masuk bot pelanggan di Evolution API dan Telegram dari halaman Notifikasi & Webhook. Hanya Owner.
 */
class ChatWebhookSettingController extends Controller
{
    public function whatsappInstall(Request $request, EvolutionApiClient $evolution, ChatWebhookSecrets $secrets): JsonResponse
    {
        abort_unless($request->user()->hasRole('Owner'), 403);

        $url = $secrets->whatsappUrl();

        if ($problem = $this->publicUrlProblem($url)) {
            return response()->json(['ok' => false, 'message' => $problem]);
        }

        try {
            $evolution->setWebhook($url);
        } catch (WhatsappDeliveryException $exception) {
            return response()->json(['ok' => false, 'message' => $exception->getMessage()]);
        }

        return response()->json(['ok' => true, 'message' => 'Webhook terpasang di instance Evolution. Kirim pesan ke nomor WhatsApp bisnis untuk mencoba.']);
    }

    public function telegramInstall(Request $request, TelegramClient $telegram, ChatWebhookSecrets $secrets): JsonResponse
    {
        abort_unless($request->user()->hasRole('Owner'), 403);

        $url = $secrets->telegramUrl();

        if ($problem = $this->publicUrlProblem($url)) {
            return response()->json(['ok' => false, 'message' => $problem]);
        }

        try {
            $telegram->setWebhook($url, $secrets->telegram());
        } catch (TelegramDeliveryException $exception) {
            return response()->json(['ok' => false, 'message' => $exception->getMessage()]);
        }

        return response()->json(['ok' => true, 'message' => 'Webhook Telegram terpasang. Kirim /start ke bot untuk mencoba.']);
    }

    public function telegramInfo(Request $request, TelegramClient $telegram, ChatWebhookSecrets $secrets): JsonResponse
    {
        abort_unless($request->user()->hasRole('Owner'), 403);

        try {
            $info = $telegram->webhookInfo();
        } catch (TelegramDeliveryException $exception) {
            return response()->json(['ok' => false, 'message' => $exception->getMessage()]);
        }

        if ($info['url'] === '') {
            return response()->json(['ok' => false, 'message' => 'Webhook belum terpasang di Telegram.']);
        }

        $matches = $info['url'] === $secrets->telegramUrl();

        return response()->json([
            'ok' => $matches && $info['last_error'] === null,
            'message' => ($matches ? 'Webhook terpasang ke aplikasi ini.' : 'Webhook mengarah ke alamat lain: '.$info['url']).($info['pending'] > 0 ? " Antrean Telegram: {$info['pending']} pesan." : '').($info['last_error'] ? " Galat terakhir: {$info['last_error']}" : ''),
        ]);
    }

    /**
     * Webhook hanya bisa dijangkau pengirimnya bila alamat aplikasi publik dan HTTPS.
     */
    private function publicUrlProblem(string $url): ?string
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $privateIp = filter_var($host, FILTER_VALIDATE_IP) !== false && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;

        if (! str_starts_with($url, 'https://') || $host === 'localhost' || str_ends_with($host, '.test') || str_ends_with($host, '.local') || $privateIp) {
            return "Alamat webhook harus HTTPS dan bisa dijangkau dari internet, sedangkan APP_URL saat ini {$url}. Atur APP_URL ke domain publik lalu coba lagi.";
        }

        return null;
    }
}
