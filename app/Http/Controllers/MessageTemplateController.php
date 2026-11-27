<?php

namespace App\Http\Controllers;

use App\Services\Messaging\MessageTemplates;
use App\Services\Settings\SettingService;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            foreach (isset($definition['sample_next']) ? [false, true] : [false] as $next) {
                $sample = $templates->sample($key, $next);
                $body = $templates->body($key);

                $items[] = [
                    'key' => $next ? "{$key}_next" : $key,
                    'template' => $key,
                    'label' => $next ? "{$definition['label']} — bulan berikutnya" : (isset($definition['sample_next']) ? "{$definition['label']} — pertama (prorata)" : $definition['label']),
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
        }

        return Inertia::render('Settings/MessageTemplates', ['templates' => $items, 'maxLength' => MessageTemplates::MAX_LENGTH]);
    }

    /**
     * Simpan teks template; hanya Owner. Variabel tak dikenal atau variabel penting yang hilang ditolak.
     */
    public function update(Request $request, string $template, MessageTemplates $templates): RedirectResponse
    {
        $this->authorizeEditing($request, $templates, $template);

        $body = trim(str_replace("\r\n", "\n", (string) $request->input('body')));

        if ($errors = $templates->validate($template, $body)) {
            return back()->withErrors(['body' => implode(' ', $errors)]);
        }

        $templates->save($template, $body, $request->user());

        activity('Template Pesan')->causedBy($request->user())->withProperties(['template' => $template])->log("Mengubah template pesan: {$templates->definition($template)['label']}");

        Toast::success('Template pesan disimpan.');

        return back();
    }

    /**
     * Kembalikan template ke teks bawaan.
     */
    public function destroy(Request $request, string $template, MessageTemplates $templates): RedirectResponse
    {
        $this->authorizeEditing($request, $templates, $template);

        $templates->reset($template);

        activity('Template Pesan')->causedBy($request->user())->withProperties(['template' => $template])->log("Mengembalikan template pesan ke bawaan: {$templates->definition($template)['label']}");

        Toast::success('Template dikembalikan ke teks bawaan.');

        return back();
    }

    private function authorizeEditing(Request $request, MessageTemplates $templates, string $template): void
    {
        abort_unless($request->user()?->hasRole('Owner'), 403);
        abort_unless($templates->exists($template) && $templates->definition($template)['editable'], 404);
    }
}
