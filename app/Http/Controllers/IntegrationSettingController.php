<?php

namespace App\Http\Controllers;

use App\Enums\WhatsappMessageStatus;
use App\Enums\WhatsappMessageType;
use App\Http\Requests\IntegrationSettingRequest;
use App\Models\WhatsappMessage;
use App\Services\Chat\ChatWebhookSecrets;
use App\Services\Settings\SettingService;
use App\Services\Whatsapp\EvolutionApiClient;
use App\Services\Whatsapp\WhatsappDeliveryException;
use App\Services\Whatsapp\WhatsappNotificationService;
use App\Support\SettingDefinitions;
use App\Support\Toast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationSettingController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const GROUP_TITLES = [
        'company' => 'Perusahaan',
        'isolir' => 'Template Isolir',
        'billing' => 'Penagihan',
        'whatsapp' => 'WhatsApp Gateway',
        'telegram' => 'Telegram',
        'genieacs' => 'GenieACS (TR-069)',
        'hotspot' => 'Hotspot',
        'vpn' => 'VPN Tunnel (pendaftaran member)',
        'gemini' => 'Gemini (ilustrasi AI)',
    ];

    /**
     * Grup yang dipindah ke halaman Notifikasi & Webhook.
     *
     * @var list<string>
     */
    private const NOTIFICATION_GROUPS = ['whatsapp', 'telegram'];

    public function index(SettingService $settings): Response
    {
        return Inertia::render('Settings/Integrations', [
            'groups' => $this->groups($settings, fn (string $group): bool => ! in_array($group, self::NOTIFICATION_GROUPS, true)),
        ]);
    }

    /**
     * Halaman Notifikasi & Webhook: WhatsApp (Evolution API) dan Telegram.
     */
    public function notifications(SettingService $settings, ChatWebhookSecrets $secrets): Response
    {
        // Pesan yang tertahan lebih dari semenit menandakan queue worker tidak berjalan.
        $stalled = WhatsappMessage::query()->where('status', WhatsappMessageStatus::Queued)->where('created_at', '<=', now()->subMinute());

        return Inertia::render('Settings/Notifications', [
            'groups' => $this->groups($settings, fn (string $group): bool => in_array($group, self::NOTIFICATION_GROUPS, true)),
            'whatsappQueue' => ['stalled' => $stalled->count(), 'oldest_at' => $stalled->min('created_at')],
            // Alamat webhook memuat rahasia; halaman ini khusus Owner.
            'webhooks' => ['whatsapp' => $secrets->whatsappUrl(), 'telegram' => $secrets->telegramUrl()],
        ]);
    }

    /**
     * @param  callable(string): bool  $include
     * @return list<array{group: string, title: string, fields: list<array<string, mixed>>}>
     */
    private function groups(SettingService $settings, callable $include): array
    {
        $groups = [];

        foreach (SettingDefinitions::ALL as $name => $definition) {
            [$group, $key] = explode('.', $name);

            if (! $include($group)) {
                continue;
            }

            $secret = $definition['secret'] ?? false;
            $value = $settings->get($group, $key);

            $groups[$group]['group'] = $group;
            $groups[$group]['title'] = self::GROUP_TITLES[$group];
            $groups[$group]['fields'][] = [
                'name' => $name,
                'label' => $definition['label'],
                'hint' => $definition['hint'] ?? null,
                'secret' => $secret,
                // Nilai rahasia tidak pernah dikirim ke browser.
                'value' => $secret ? null : $value,
                'is_set' => filled($value),
            ];
        }

        return array_values($groups);
    }

    public function update(IntegrationSettingRequest $request, SettingService $settings): RedirectResponse
    {
        $changed = [];

        $sent = array_keys((array) $request->input('settings', []));

        foreach (SettingDefinitions::ALL as $name => $definition) {
            [$group, $key] = explode('.', $name);

            // Halaman pengaturan hanya mengirim grupnya sendiri; grup lain tidak boleh ikut terhapus.
            if (! in_array($group, $sent, true)) {
                continue;
            }

            $value = $request->input("settings.{$group}.{$key}");
            $secret = $definition['secret'] ?? false;

            if ($secret && blank($value)) {
                continue;
            }

            $new = filled($value) ? (string) $value : null;

            if ($settings->get($group, $key) !== $new) {
                $changed[] = $name;
            }

            $settings->set($group, $key, $new, encrypted: $secret);
        }

        // Hanya nama pengaturan yang dicatat, bukan nilainya (bisa berisi token).
        if ($changed !== []) {
            activity('Pengaturan')->causedBy($request->user())->withProperties(['keys' => $changed])->log('Pengaturan diubah: '.implode(', ', $changed));
        }

        Toast::success('Pengaturan disimpan.');

        return back();
    }

    /**
     * Kirim pesan uji ke nomor tertentu lewat WA Gateway yang tersimpan.
     */
    public function testWhatsapp(Request $request, WhatsappNotificationService $whatsapp): RedirectResponse
    {
        abort_unless($request->user()->hasRole('Owner'), 403);

        $validated = $request->validate(['phone' => ['required', 'regex:/^62\d{8,13}$/']]);

        $whatsapp->queue($validated['phone'], WhatsappMessageType::Test, 'Pesan uji dari panel '.config('app.name').'. WA Gateway berfungsi.');

        Toast::success('Pesan uji diantrekan. Periksa WhatsApp tujuan dalam beberapa detik.');

        return back();
    }

    /**
     * Cek status koneksi instance Evolution dengan pengaturan yang tersimpan.
     */
    public function whatsappStatus(Request $request, EvolutionApiClient $evolution): JsonResponse
    {
        abort_unless($request->user()->hasRole('Owner'), 403);

        try {
            $state = $evolution->connectionState();
        } catch (WhatsappDeliveryException $exception) {
            return response()->json(['connected' => false, 'state' => 'error', 'message' => $exception->getMessage()]);
        }

        return response()->json([...$state, 'message' => $state['connected'] ? "WhatsApp terhubung. ({$state['state']})" : "WhatsApp belum terhubung. ({$state['state']})"]);
    }

    /**
     * Ambil QR untuk menghubungkan WhatsApp ke instance Evolution.
     */
    public function whatsappConnect(Request $request, EvolutionApiClient $evolution): JsonResponse
    {
        abort_unless($request->user()->hasRole('Owner'), 403);

        try {
            $result = $evolution->connect();
        } catch (WhatsappDeliveryException $exception) {
            return response()->json(['connected' => false, 'state' => 'error', 'qr' => null, 'pairing_code' => null, 'message' => $exception->getMessage()]);
        }

        return response()->json([...$result, 'message' => $result['connected'] ? 'WhatsApp sudah terhubung.' : ($result['qr'] ? 'Scan QR dari WhatsApp > Perangkat tertaut.' : 'QR belum tersedia, coba lagi sebentar.')]);
    }
}
