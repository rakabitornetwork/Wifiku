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
        'company.tagline' => ['label' => 'Tagline (landing page)', 'default' => 'Koneksi Rumah yang Stabil & Profesional', 'rules' => ['nullable', 'string', 'max:120']],
        'company.address' => ['label' => 'Alamat (landing page)', 'default' => null, 'rules' => ['nullable', 'string', 'max:255']],
        'company.operating_hours' => ['label' => 'Jam operasional', 'default' => 'Senin – Sabtu, 08.00 – 18.00', 'rules' => ['nullable', 'string', 'max:120']],
        'company.email' => ['label' => 'Email kontak', 'default' => null, 'rules' => ['nullable', 'email', 'max:255']],
        'company.instagram' => ['label' => 'URL Instagram', 'default' => null, 'rules' => ['nullable', 'url', 'max:255']],
        'company.facebook' => ['label' => 'URL Facebook', 'default' => null, 'rules' => ['nullable', 'url', 'max:255']],
        'isolir.title' => ['label' => 'Judul halaman isolir', 'default' => 'Layanan Internet Anda Sedang Diisolir', 'rules' => ['required', 'string', 'max:150']],
        'isolir.message' => ['label' => 'Pesan halaman isolir', 'default' => 'Mohon segera melunasi tagihan Anda agar layanan dapat digunakan kembali.', 'rules' => ['nullable', 'string', 'max:1000']],
        'billing.reminder_days_before' => ['label' => 'Pengingat sebelum jatuh tempo (hari)', 'default' => '3', 'rules' => ['required', 'integer', 'between:1,30']],
        'billing.extension_admin_max_days' => ['label' => 'Batas perpanjangan oleh Admin/Kasir (hari)', 'default' => '14', 'rules' => ['required', 'integer', 'between:1,365'], 'hint' => 'Perpanjangan (Janji Bayar) yang lebih lama dari ini hanya bisa diberikan Owner.'],
        'billing.extension_owner_max_days' => ['label' => 'Batas perpanjangan oleh Owner (hari)', 'default' => '62', 'rules' => ['required', 'integer', 'between:1,365'], 'hint' => 'Dihitung dari hari ini. 62 hari ≈ 2 bulan.'],
        'billing.vpn_invoice_days_before' => ['label' => 'Terbitkan tagihan VPN sebelum masa berlaku habis (hari)', 'default' => '5', 'rules' => ['required', 'integer', 'between:1,30'], 'hint' => 'Tagihan perpanjangan VPN Tunnel terbit otomatis sekian hari sebelum akun kedaluwarsa.'],
        'whatsapp.enabled' => ['label' => 'Aktifkan WhatsApp', 'default' => '1', 'rules' => ['nullable', 'in:0,1']],
        'whatsapp.base_url' => ['label' => 'Base URL', 'default' => null, 'rules' => ['nullable', 'url', 'max:255'], 'hint' => 'URL Evolution API, mis. https://evo.example.com'],
        'whatsapp.instance' => ['label' => 'Nama instance', 'default' => null, 'rules' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_.-]+$/']],
        'whatsapp.api_key' => ['label' => 'API key', 'default' => null, 'secret' => true, 'rules' => ['nullable', 'string', 'max:500'], 'hint' => 'Dikirim sebagai header apikey.'],
        'whatsapp.pacing_enabled' => ['label' => 'Atur ritme pengiriman (anti-blokir)', 'default' => '1', 'rules' => ['nullable', 'in:0,1'], 'hint' => 'Pesan massal dikirim satu per satu dengan jeda acak, bukan sekaligus.'],
        'whatsapp.delay_min' => ['label' => 'Jeda minimum antar pesan (detik)', 'default' => '10', 'rules' => ['nullable', 'integer', 'between:1,600']],
        'whatsapp.delay_max' => ['label' => 'Jeda maksimum antar pesan (detik)', 'default' => '40', 'rules' => ['nullable', 'integer', 'between:1,900', 'gte:settings.whatsapp.delay_min']],
        'whatsapp.batch_size' => ['label' => 'Istirahat panjang kira-kira tiap N pesan', 'default' => '15', 'rules' => ['nullable', 'integer', 'between:2,200'], 'hint' => 'Waktunya diacak, bukan tepat di pesan ke-N.'],
        'whatsapp.rest_min' => ['label' => 'Istirahat minimum (menit)', 'default' => '3', 'rules' => ['nullable', 'integer', 'between:1,120']],
        'whatsapp.rest_max' => ['label' => 'Istirahat maksimum (menit)', 'default' => '8', 'rules' => ['nullable', 'integer', 'between:1,180', 'gte:settings.whatsapp.rest_min']],
        'whatsapp.daily_limit' => ['label' => 'Batas pesan massal per hari', 'default' => '250', 'rules' => ['nullable', 'integer', 'between:0,5000'], 'hint' => '0 = tanpa batas. Kelebihannya dijadwalkan ke hari berikutnya.'],
        'whatsapp.window_start' => ['label' => 'Jam kirim mulai', 'default' => '07:00', 'rules' => ['nullable', 'date_format:H:i'], 'hint' => 'Pesan massal tidak dikirim di luar jam ini (zona waktu aplikasi).'],
        'whatsapp.window_end' => ['label' => 'Jam kirim selesai', 'default' => '20:30', 'rules' => ['nullable', 'date_format:H:i']],
        'whatsapp.typing_simulation' => ['label' => 'Tampilkan "sedang mengetik" sebelum pesan terkirim', 'default' => '1', 'rules' => ['nullable', 'in:0,1']],
        'whatsapp.bot_enabled' => ['label' => 'Aktifkan bot pelanggan', 'default' => '0', 'rules' => ['nullable', 'in:0,1'], 'hint' => 'Pelanggan terdaftar bisa cek tagihan, status ONU, dan ganti WiFi lewat chat.'],
        'whatsapp.test_phone' => ['label' => 'Nomor tes (62...)', 'default' => null, 'rules' => ['nullable', 'regex:/^62\d{8,13}$/']],
        'telegram.enabled' => ['label' => 'Aktifkan Telegram', 'default' => '0', 'rules' => ['nullable', 'in:0,1']],
        'telegram.bot_token' => ['label' => 'Token bot', 'default' => null, 'secret' => true, 'rules' => ['nullable', 'string', 'max:200', 'regex:/^\d{5,}:[A-Za-z0-9_-]{20,}$/'], 'hint' => 'Dari @BotFather, bentuknya 123456789:AAxxxx.'],
        'telegram.chat_id' => ['label' => 'Chat ID tujuan', 'default' => null, 'rules' => ['nullable', 'string', 'max:40', 'regex:/^(-?\d{5,20}|@[A-Za-z0-9_]{5,})$/'], 'hint' => 'ID pengguna, grup (diawali -), atau @namakanal. Kirim pesan ke bot lebih dulu agar bisa membalas.'],
        'telegram.bot_enabled' => ['label' => 'Aktifkan bot pelanggan', 'default' => '0', 'rules' => ['nullable', 'in:0,1'], 'hint' => 'Pelanggan terdaftar bisa cek tagihan, status ONU, dan ganti WiFi lewat chat dengan bot.'],
        'telegram.customer_delivery' => ['label' => 'Pesan otomatis ke pelanggan', 'default' => 'both', 'rules' => ['nullable', 'in:off,both,prefer'], 'hint' => 'Berlaku bagi pelanggan yang sudah menautkan Telegram: off = hanya WhatsApp; both = WhatsApp dan Telegram; prefer = Telegram saja bila tertaut.'],
        'telegram.alert_isolation' => ['label' => 'Isolir atau buka isolir gagal', 'default' => '1', 'rules' => ['nullable', 'in:0,1']],
        'telegram.alert_router' => ['label' => 'Router offline dan pulih', 'default' => '1', 'rules' => ['nullable', 'in:0,1']],
        'telegram.alert_outage' => ['label' => 'Gangguan ODP baru', 'default' => '1', 'rules' => ['nullable', 'in:0,1']],
        'telegram.alert_whatsapp' => ['label' => 'Pesan WhatsApp gagal terkirim', 'default' => '1', 'rules' => ['nullable', 'in:0,1']],
        'notify.welcome' => ['label' => 'Selamat datang pelanggan baru', 'default' => '1', 'rules' => ['nullable', 'in:0,1'], 'hint' => 'Sekali saat pelanggan aktif baru didaftarkan.'],
        'notify.invoice' => ['label' => 'Tagihan baru', 'default' => '1', 'rules' => ['nullable', 'in:0,1'], 'hint' => 'Dikirim saat invoice terbit, pukul 08.00.'],
        'notify.reminder' => ['label' => 'Pengingat sebelum jatuh tempo', 'default' => '1', 'rules' => ['nullable', 'in:0,1'], 'hint' => 'Pukul 09.00, sekian hari sebelum jatuh tempo (atur di Pengaturan, bagian Penagihan).'],
        'notify.due_today' => ['label' => 'Pengingat hari jatuh tempo', 'default' => '1', 'rules' => ['nullable', 'in:0,1'], 'hint' => 'Pukul 09.10 pada hari jatuh tempo; pelanggan dalam masa Janji Bayar tidak diingatkan.'],
        'notify.receipt' => ['label' => 'Bukti pembayaran', 'default' => '1', 'rules' => ['nullable', 'in:0,1'], 'hint' => 'Segera setelah pembayaran diterima, online maupun tunai.'],
        'notify.isolated' => ['label' => 'Pemberitahuan isolir', 'default' => '1', 'rules' => ['nullable', 'in:0,1'], 'hint' => 'Setelah layanan berhasil dipindah ke profil isolir.'],
        'notify.reopened' => ['label' => 'Layanan aktif kembali', 'default' => '1', 'rules' => ['nullable', 'in:0,1'], 'hint' => 'Setelah pembayaran melunasi tunggakan dan layanan dibuka kembali.'],
        'notify.extension' => ['label' => 'Janji Bayar', 'default' => '1', 'rules' => ['nullable', 'in:0,1'], 'hint' => 'Perpanjangan disetujui dan pengingat sehari sebelum masa janji berakhir.'],
        'notify.outage' => ['label' => 'Pengumuman gangguan', 'default' => '1', 'rules' => ['nullable', 'in:0,1'], 'hint' => 'Gangguan ODP dan pemberitahuan setelah teratasi.'],
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
        'genieacs.hosts_path' => ['label' => 'Parameter perangkat terhubung', 'default' => 'InternetGatewayDevice.LANDevice.1.Hosts.Host', 'rules' => ['nullable', 'string', 'max:255'], 'hint' => 'Daftar host LAN/Wi-Fi; ONT berbasis Device:2 memakai Device.Hosts.Host.'],
        'hotspot.login_url' => ['label' => 'URL login hotspot', 'default' => null, 'rules' => ['nullable', 'url', 'max:255'], 'hint' => 'Untuk QR auto-login voucher, mis. http://wifi.teslatech/login. Kosong = IP router.'],
        'gemini.api_key' => ['label' => 'API key Gemini', 'default' => null, 'secret' => true, 'rules' => ['nullable', 'string', 'max:500'], 'hint' => 'Dari Google AI Studio (butuh billing aktif untuk model gambar). Dipakai untuk membuat ilustrasi landing page.'],
        'gemini.model' => ['label' => 'Model gambar', 'default' => 'gemini-nano-banana-2.1', 'rules' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'], 'hint' => 'Contoh: gemini-nano-banana-2.1 atau gemini-3.1-flash-image.'],
        'vpn.member_registration_open' => ['label' => 'Buka pendaftaran member VPN', 'default' => '1', 'rules' => ['nullable', 'in:0,1'], 'hint' => '1 = pengunjung landing page boleh mendaftar; 0 = pendaftaran ditutup.'],
        'vpn.member_price' => ['label' => 'Harga akun VPN member per bulan (Rp)', 'default' => '25000', 'rules' => ['required', 'integer', 'between:1000,10000000']],
        'vpn.member_max_accounts' => ['label' => 'Maksimal akun VPN per member', 'default' => '5', 'rules' => ['required', 'integer', 'between:1,100']],
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
