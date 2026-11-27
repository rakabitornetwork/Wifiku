<?php

namespace App\Services\Chat;

use App\Services\Settings\SettingService;
use Illuminate\Support\Str;

/**
 * Rahasia pengesah webhook pesan masuk, dibuat otomatis saat pertama dibutuhkan dan disimpan terenkripsi.
 * WhatsApp: bagian dari alamat webhook. Telegram: header X-Telegram-Bot-Api-Secret-Token.
 */
class ChatWebhookSecrets
{
    public function __construct(private readonly SettingService $settings) {}

    public function whatsapp(): string
    {
        return $this->ensure('whatsapp');
    }

    public function telegram(): string
    {
        return $this->ensure('telegram');
    }

    public function whatsappUrl(): string
    {
        return route('webhooks.chat.whatsapp', $this->whatsapp());
    }

    public function telegramUrl(): string
    {
        return route('webhooks.chat.telegram');
    }

    private function ensure(string $group): string
    {
        $secret = $this->settings->get($group, 'webhook_secret');

        if (blank($secret)) {
            $secret = Str::random(40);
            $this->settings->set($group, 'webhook_secret', $secret, encrypted: true);
        }

        return $secret;
    }
}
