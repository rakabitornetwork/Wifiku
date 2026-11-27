<?php

namespace App\Http\Controllers;

use App\Services\Messaging\MessageTemplates;
use App\Services\Settings\SettingService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pratinjau (dan penyuntingan oleh Owner) template pesan otomatis ke pelanggan, dengan data contoh,
 * dalam tampilan WhatsApp dan Telegram.
 */
class MessageTemplateController extends Controller
{
    public function index(MessageTemplates $templates, SettingService $settings): Response
    {
        $items = [];

        foreach ($templates->definitions() as $key => $definition) {
            $sample = $templates->sample($key);
            $body = $templates->body($key);

            $items[] = [
                'key' => $key,
                'label' => $definition['label'],
                'icon' => $definition['icon'],
                'when' => $definition['when'],
                'editable' => $definition['editable'],
                'customised' => $templates->isCustomised($key),
                'enabled' => $definition['topic'] === null || $settings->get('notify', $definition['topic']) !== '0',
                'placeholders' => $definition['placeholders'],
                'required' => $definition['required'],
                'body' => $body,
                'default_body' => $definition['default'],
                'sample' => $sample,
                'whatsapp' => $templates->fill($body, $sample),
                'telegram' => "{$definition['icon']} ".$templates->fill($body, $sample),
            ];
        }

        return Inertia::render('Settings/MessageTemplates', ['templates' => $items, 'maxLength' => MessageTemplates::MAX_LENGTH]);
    }
}
