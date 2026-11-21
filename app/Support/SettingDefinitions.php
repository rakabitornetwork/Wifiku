<?php

namespace App\Support;

/**
 * Daftar pengaturan yang dapat diubah Owner di Panel Admin, beserta nilai bawaannya.
 */
class SettingDefinitions
{
    /**
     * @var array<string, array{label: string, default: string|null, secret?: bool, rules: list<string>, hint?: string}>
     */
    public const ALL = [
        'company.name' => ['label' => 'Nama perusahaan', 'default' => 'Wifi Teslatech', 'rules' => ['required', 'string', 'max:100']],
        'company.cs_whatsapp' => ['label' => 'No. WhatsApp CS', 'default' => null, 'rules' => ['nullable', 'string', 'max:20'], 'hint' => 'Ditampilkan di halaman isolir, format 62xxx.'],
        'isolir.title' => ['label' => 'Judul halaman isolir', 'default' => 'Layanan Internet Anda Sedang Diisolir', 'rules' => ['required', 'string', 'max:150']],
        'isolir.message' => ['label' => 'Pesan halaman isolir', 'default' => 'Mohon segera melunasi tagihan Anda agar layanan dapat digunakan kembali.', 'rules' => ['nullable', 'string', 'max:1000']],
        'billing.reminder_days_before' => ['label' => 'Pengingat sebelum jatuh tempo (hari)', 'default' => '3', 'rules' => ['required', 'integer', 'between:1,30']],
        'whatsapp.enabled' => ['label' => 'Aktifkan WhatsApp', 'default' => '1', 'rules' => ['nullable', 'in:0,1']],
        'whatsapp.base_url' => ['label' => 'Base URL', 'default' => null, 'rules' => ['nullable', 'url', 'max:255'], 'hint' => 'URL Evolution API, mis. https://evo.example.com'],
        'whatsapp.instance' => ['label' => 'Nama instance', 'default' => null, 'rules' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_.-]+$/']],
        'whatsapp.api_key' => ['label' => 'API key', 'default' => null, 'secret' => true, 'rules' => ['nullable', 'string', 'max:500'], 'hint' => 'Dikirim sebagai header apikey.'],
        'whatsapp.test_phone' => ['label' => 'Nomor tes (62...)', 'default' => null, 'rules' => ['nullable', 'regex:/^62\d{8,13}$/']],
        'genieacs.base_url' => ['label' => 'URL GenieACS NBI', 'default' => null, 'rules' => ['nullable', 'url', 'max:255'], 'hint' => 'Contoh: http://10.0.0.5:7557'],
        'genieacs.username' => ['label' => 'Username GenieACS', 'default' => null, 'rules' => ['nullable', 'string', 'max:100']],
        'genieacs.password' => ['label' => 'Password GenieACS', 'default' => null, 'secret' => true, 'rules' => ['nullable', 'string', 'max:255']],
        'genieacs.rx_power_paths' => [
            'label' => 'Parameter RX Power',
            'default' => 'VirtualParameters.RXPower,InternetGatewayDevice.WANDevice.1.X_ZTE-COM_WANPONInterfaceConfig.RXPower,InternetGatewayDevice.WANDevice.1.X_GponInterafceConfig.RXPower',
            'rules' => ['required', 'string', 'max:1000'],
            'hint' => 'Dipisah koma; parameter pertama yang bernilai dipakai.',
        ],
        'genieacs.ssid_path' => ['label' => 'Parameter SSID', 'default' => 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID', 'rules' => ['required', 'string', 'max:255']],
        'genieacs.wifi_password_path' => ['label' => 'Parameter password Wi-Fi', 'default' => 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.KeyPassphrase', 'rules' => ['required', 'string', 'max:255']],
        'hotspot.login_url' => ['label' => 'URL login hotspot', 'default' => null, 'rules' => ['nullable', 'url', 'max:255'], 'hint' => 'Untuk QR auto-login voucher, mis. http://wifi.teslatech/login. Kosong = IP router.'],
    ];

    public static function default(string $group, string $key): ?string
    {
        return self::ALL["{$group}.{$key}"]['default'] ?? null;
    }

    public static function isSecret(string $group, string $key): bool
    {
        return self::ALL["{$group}.{$key}"]['secret'] ?? false;
    }
}
