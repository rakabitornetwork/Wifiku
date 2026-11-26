<?php

namespace App\Http\Controllers;

use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\TelegramDeliveryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Uji koneksi Telegram dari halaman Notifikasi & Webhook. Hanya Owner (pengaturan berisi token bot).
 */
class TelegramSettingController extends Controller
{
    /**
     * Periksa token tersimpan ke Telegram dan tampilkan bot yang terhubung.
     */
    public function status(Request $request, TelegramClient $telegram): JsonResponse
    {
        abort_unless($request->user()->hasRole('Owner'), 403);

        try {
            $bot = $telegram->getMe();
        } catch (TelegramDeliveryException $exception) {
            return response()->json(['ok' => false, 'message' => $exception->getMessage()]);
        }

        return response()->json(['ok' => true, 'message' => 'Terhubung ke bot '.($bot['username'] ? "@{$bot['username']}" : $bot['name']).'.']);
    }

    /**
     * Kirim pesan uji langsung (tanpa antrean) agar kesalahan token atau chat ID langsung terlihat.
     */
    public function test(Request $request, TelegramClient $telegram): JsonResponse
    {
        abort_unless($request->user()->hasRole('Owner'), 403);

        try {
            $telegram->sendMessage('✅ Pesan uji dari panel '.config('app.name').'. Notifikasi Telegram berfungsi.');
        } catch (TelegramDeliveryException $exception) {
            return response()->json(['ok' => false, 'message' => $exception->getMessage()]);
        }

        return response()->json(['ok' => true, 'message' => 'Pesan uji terkirim. Periksa chat Telegram Anda.']);
    }
}
