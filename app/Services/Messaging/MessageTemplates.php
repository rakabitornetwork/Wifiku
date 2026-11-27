<?php

namespace App\Services\Messaging;

use App\Enums\WhatsappMessageType;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Services\Settings\SettingService;

/**
 * Daftar template pesan otomatis ke pelanggan: teks bawaan, variabel yang tersedia, dan penggantian oleh Owner.
 * Satu teks dipakai untuk WhatsApp dan Telegram (Telegram menambahkan ikon di depannya). Variabel ditulis {nama_variabel}
 * dan diisi saat pesan dikirim; template yang diubah wajib mempertahankan variabel yang ditandai penting (mis. tautan bayar).
 */
class MessageTemplates
{
    public const MAX_LENGTH = 1500;

    /**
     * @var array<string, array{label: string, topic: string|null, type: WhatsappMessageType, icon: string, when: string, editable: bool, placeholders: array<string, string>, required: list<string>, sample: array<string, string>, default: string}>
     */
    private const DEFINITIONS = [
        'invoice_issued' => [
            'label' => 'Tagihan baru',
            'topic' => 'invoice',
            'type' => WhatsappMessageType::Invoice,
            'icon' => '🧾',
            'when' => 'Saat invoice terbit, pukul 08.00 pada tanggal tagihan pelanggan.',
            'editable' => true,
            'placeholders' => ['nama' => 'Nama pelanggan', 'perusahaan' => 'Nama perusahaan', 'periode_mulai' => 'Awal periode tagihan', 'periode_selesai' => 'Akhir periode tagihan', 'no_invoice' => 'Nomor invoice', 'total' => 'Total tagihan', 'jatuh_tempo' => 'Tanggal jatuh tempo', 'kode_pelanggan' => 'Kode pelanggan', 'link_bayar' => 'Tautan bayar online', 'link_portal' => 'Tautan login portal pelanggan'],
            'required' => ['link_bayar'],
            'sample' => ['periode_mulai' => '03/12/2026', 'periode_selesai' => '02/01/2027', 'no_invoice' => 'INV/2026/00123', 'total' => 'Rp 150.000', 'jatuh_tempo' => '10/12/2026'],
            'default' => "Halo {nama},\n\nTagihan internet {perusahaan} periode {periode_mulai} s.d. {periode_selesai} sudah terbit.\nNo. invoice: {no_invoice}\nTotal: {total}\nJatuh tempo: {jatuh_tempo}\nKode pelanggan: {kode_pelanggan}\n\nBayar online (QRIS/VA): {link_bayar}\nCek status ONU & ganti WiFi: {link_portal} (masuk dengan nomor WhatsApp ini)\nTerima kasih.",
        ],
        'invoice_reminder' => [
            'label' => 'Pengingat sebelum jatuh tempo',
            'topic' => 'reminder',
            'type' => WhatsappMessageType::Reminder,
            'icon' => '⏰',
            'when' => 'Pukul 09.00, sekian hari sebelum jatuh tempo (bawaan 3 hari).',
            'editable' => true,
            'placeholders' => ['nama' => 'Nama pelanggan', 'no_invoice' => 'Nomor invoice', 'sisa' => 'Sisa tagihan', 'jatuh_tempo' => 'Tanggal jatuh tempo', 'kode_pelanggan' => 'Kode pelanggan', 'link_bayar' => 'Tautan bayar online'],
            'required' => ['link_bayar'],
            'sample' => ['no_invoice' => 'INV/2026/00123', 'sisa' => 'Rp 150.000', 'jatuh_tempo' => '10/12/2026'],
            'default' => "Halo {nama},\n\nMengingatkan tagihan {no_invoice} sebesar {sisa} jatuh tempo pada {jatuh_tempo}.\nMohon lakukan pembayaran sebelum jatuh tempo agar layanan tidak terisolir otomatis.\nKode pelanggan: {kode_pelanggan}\n\nBayar online: {link_bayar}",
        ],
        'invoice_due_today' => [
            'label' => 'Pengingat hari jatuh tempo',
            'topic' => 'due_today',
            'type' => WhatsappMessageType::Reminder,
            'icon' => '⏳',
            'when' => 'Pukul 09.10 pada hari jatuh tempo; pelanggan dalam masa Janji Bayar dilewati.',
            'editable' => true,
            'placeholders' => ['nama' => 'Nama pelanggan', 'no_invoice' => 'Nomor invoice', 'sisa' => 'Sisa tagihan', 'jatuh_tempo' => 'Tanggal jatuh tempo (hari ini)', 'kode_pelanggan' => 'Kode pelanggan', 'link_bayar' => 'Tautan bayar online'],
            'required' => ['link_bayar'],
            'sample' => ['no_invoice' => 'INV/2026/00123', 'sisa' => 'Rp 150.000', 'jatuh_tempo' => '10/12/2026'],
            'default' => "Halo {nama},\n\nTagihan {no_invoice} sebesar {sisa} jatuh tempo HARI INI ({jatuh_tempo}).\nMohon lunasi hari ini agar layanan internet tidak diisolir otomatis.\nKode pelanggan: {kode_pelanggan}\n\nBayar online (QRIS/VA): {link_bayar}",
        ],
        'payment_received' => [
            'label' => 'Bukti pembayaran',
            'topic' => 'receipt',
            'type' => WhatsappMessageType::Receipt,
            'icon' => '💳',
            'when' => 'Segera setelah pembayaran diterima, online maupun tunai.',
            'editable' => true,
            'placeholders' => ['nama' => 'Nama pelanggan', 'jumlah' => 'Jumlah yang dibayar', 'no_invoice' => 'Nomor invoice', 'waktu' => 'Waktu pembayaran', 'status_tagihan' => 'Kalimat LUNAS atau sisa tagihan', 'referensi' => 'Kode referensi pembayaran'],
            'required' => [],
            'sample' => ['jumlah' => 'Rp 150.000', 'no_invoice' => 'INV/2026/00123', 'waktu' => '08/12/2026 14:32', 'status_tagihan' => 'Status tagihan: LUNAS.', 'referensi' => 'PAY-20261208-AB12'],
            'default' => "Halo {nama},\n\nPembayaran {jumlah} untuk {no_invoice} telah kami terima pada {waktu}.\n{status_tagihan}\nRef: {referensi}\nTerima kasih.",
        ],
        'isolated' => [
            'label' => 'Pemberitahuan isolir',
            'topic' => 'isolated',
            'type' => WhatsappMessageType::Isolir,
            'icon' => '🔴',
            'when' => 'Setelah layanan berhasil dipindah ke profil isolir (tagihan lewat jatuh tempo).',
            'editable' => true,
            'placeholders' => ['nama' => 'Nama pelanggan', 'perusahaan' => 'Nama perusahaan', 'link_bayar' => 'Tautan bayar online'],
            'required' => ['link_bayar'],
            'sample' => [],
            'default' => "Halo {nama},\n\nLayanan internet {perusahaan} Anda sementara diisolir karena tagihan telah melewati jatuh tempo.\nLihat tagihan dan bayar online: {link_bayar}\nLayanan aktif kembali otomatis setelah pembayaran diterima.",
        ],
        'reopened' => [
            'label' => 'Layanan aktif kembali',
            'topic' => 'reopened',
            'type' => WhatsappMessageType::Isolir,
            'icon' => '🟢',
            'when' => 'Setelah pembayaran melunasi tunggakan dan layanan dibuka kembali di router.',
            'editable' => true,
            'placeholders' => ['nama' => 'Nama pelanggan', 'perusahaan' => 'Nama perusahaan'],
            'required' => [],
            'sample' => [],
            'default' => "Halo {nama},\n\nLayanan internet {perusahaan} Anda telah aktif kembali. Bila belum tersambung, silakan matikan lalu nyalakan ulang modem.\nTerima kasih.",
        ],
        'extension_granted' => [
            'label' => 'Janji Bayar disetujui',
            'topic' => 'extension',
            'type' => WhatsappMessageType::Reminder,
            'icon' => '🤝',
            'when' => 'Saat admin menyetujui perpanjangan pembayaran (Janji Bayar).',
            'editable' => true,
            'placeholders' => ['nama' => 'Nama pelanggan', 'perusahaan' => 'Nama perusahaan', 'sampai_tanggal' => 'Tanggal akhir perpanjangan', 'total' => 'Total tagihan yang harus dilunasi', 'link_bayar' => 'Tautan bayar online'],
            'required' => ['link_bayar'],
            'sample' => ['sampai_tanggal' => '15/12/2026', 'total' => 'Rp 150.000'],
            'default' => "Halo {nama},\n\nPermintaan perpanjangan pembayaran Anda disetujui. Layanan internet {perusahaan} tetap aktif sampai {sampai_tanggal}.\nTotal tagihan yang harus dilunasi: {total}.\nLihat tagihan dan bayar online: {link_bayar}\nMohon lunasi sebelum tanggal tersebut agar layanan tidak terisolir.",
        ],
        'extension_ending' => [
            'label' => 'Janji Bayar hampir berakhir',
            'topic' => 'extension',
            'type' => WhatsappMessageType::Reminder,
            'icon' => '⏰',
            'when' => 'Pukul 09.05, sehari sebelum masa perpanjangan berakhir.',
            'editable' => true,
            'placeholders' => ['nama' => 'Nama pelanggan', 'total' => 'Tagihan yang belum lunas', 'link_bayar' => 'Tautan bayar online'],
            'required' => ['link_bayar'],
            'sample' => ['total' => 'Rp 150.000'],
            'default' => "Halo {nama},\n\nMasa perpanjangan pembayaran Anda berakhir besok. Tagihan yang belum lunas: {total}.\nBayar online: {link_bayar}\nBila belum dibayar, layanan akan diisolir otomatis.",
        ],
        'outage' => [
            'label' => 'Pengumuman gangguan',
            'topic' => 'outage',
            'type' => WhatsappMessageType::Outage,
            'icon' => '⚠️',
            'when' => 'Saat admin mengumumkan gangguan pada sebuah ODP; dikirim ke semua pelanggan di ODP itu.',
            'editable' => true,
            'placeholders' => ['nama' => 'Nama pelanggan', 'pesan' => 'Isi pengumuman yang diketik admin', 'perusahaan' => 'Nama perusahaan'],
            'required' => ['pesan'],
            'sample' => ['pesan' => 'Terjadi gangguan fiber optik di area Anda. Teknisi sedang menangani, mohon maaf atas ketidaknyamanannya.'],
            'default' => "Halo {nama},\n\n{pesan}\n\n— {perusahaan}",
        ],
        'outage_resolved' => [
            'label' => 'Gangguan teratasi',
            'topic' => 'outage',
            'type' => WhatsappMessageType::Outage,
            'icon' => '✅',
            'when' => 'Saat admin menandai gangguan selesai.',
            'editable' => true,
            'placeholders' => ['nama' => 'Nama pelanggan', 'judul' => 'Judul gangguan', 'perusahaan' => 'Nama perusahaan'],
            'required' => [],
            'sample' => ['judul' => 'Gangguan fiber optik'],
            'default' => "Halo {nama},\n\nGangguan jaringan di area Anda ({judul}) telah teratasi. Bila internet belum tersambung, mohon matikan lalu nyalakan ulang modem.\nTerima kasih atas kesabarannya.\n\n— {perusahaan}",
        ],
        'login_code' => [
            'label' => 'Kode OTP login portal',
            'topic' => null,
            'type' => WhatsappMessageType::AccountSecurity,
            'icon' => '🔐',
            'when' => 'Saat pelanggan meminta kode masuk di portal atau bot. Dikunci karena menyangkut keamanan.',
            'editable' => false,
            'placeholders' => ['perusahaan' => 'Nama perusahaan', 'kode' => 'Kode OTP 6 digit', 'menit' => 'Masa berlaku (menit)'],
            'required' => ['kode'],
            'sample' => ['kode' => '123456', 'menit' => '10'],
            'default' => "Halo,\n\nKode masuk portal pelanggan {perusahaan}: *{kode}*\nBerlaku {menit} menit. Jangan bagikan kode ini kepada siapa pun, termasuk petugas.\n\nAbaikan pesan ini bila Anda tidak memintanya.",
        ],
    ];

    /** @var array<string, string>|null */
    private ?array $overrides = null;

    public function __construct(private readonly SettingService $settings) {}

    /**
     * @return array<string, array{label: string, topic: string|null, type: WhatsappMessageType, icon: string, when: string, editable: bool, placeholders: array<string, string>, required: list<string>, sample: array<string, string>, default: string}>
     */
    public function definitions(): array
    {
        return self::DEFINITIONS;
    }

    public function exists(string $key): bool
    {
        return isset(self::DEFINITIONS[$key]);
    }

    /**
     * @return array{label: string, topic: string|null, type: WhatsappMessageType, icon: string, when: string, editable: bool, placeholders: array<string, string>, required: list<string>, sample: array<string, string>, default: string}
     */
    public function definition(string $key): array
    {
        return self::DEFINITIONS[$key] ?? throw new \InvalidArgumentException("Template pesan tidak dikenal: {$key}");
    }

    /**
     * Teks yang berlaku: hasil suntingan Owner bila ada (dan template boleh disunting), selain itu teks bawaan.
     */
    public function body(string $key): string
    {
        $definition = $this->definition($key);

        if (! $definition['editable']) {
            return $definition['default'];
        }

        $this->overrides ??= MessageTemplate::query()->pluck('body', 'key')->all();

        return $this->overrides[$key] ?? $definition['default'];
    }

    public function isCustomised(string $key): bool
    {
        $this->overrides ??= MessageTemplate::query()->pluck('body', 'key')->all();

        return isset($this->overrides[$key]);
    }

    /**
     * @param  array<string, scalar|null>  $variables
     */
    public function render(string $key, array $variables): string
    {
        return $this->fill($this->body($key), $variables);
    }

    /**
     * @param  array<string, scalar|null>  $variables
     */
    public function fill(string $body, array $variables): string
    {
        return (string) preg_replace_callback('/\{([a-z_]+)\}/', fn (array $match): string => array_key_exists($match[1], $variables) ? (string) $variables[$match[1]] : $match[0], $body);
    }

    /**
     * Data contoh untuk pratinjau dan penyuntingan: nilai statis template ditambah nilai yang bergantung pada pengaturan.
     *
     * @return array<string, string>
     */
    public function sample(string $key): array
    {
        $definition = $this->definition($key);

        return [
            'nama' => 'Budi Santoso',
            'perusahaan' => (string) $this->settings->get('company', 'name'),
            'kode_pelanggan' => 'TT-2612-0001',
            'link_bayar' => route('isolir.show', 'TT-2612-0001'),
            'link_portal' => route('portal.login'),
            ...$definition['sample'],
        ];
    }

    /**
     * Galat pada teks yang diajukan; daftar kosong berarti boleh disimpan.
     *
     * @return list<string>
     */
    public function validate(string $key, string $body): array
    {
        $definition = $this->definition($key);
        $errors = [];

        if (trim($body) === '') {
            return ['Teks pesan tidak boleh kosong.'];
        }

        if (mb_strlen($body) > self::MAX_LENGTH) {
            $errors[] = 'Teks pesan maksimal '.self::MAX_LENGTH.' karakter.';
        }

        preg_match_all('/\{([a-z_]+)\}/', $body, $found);
        $used = array_unique($found[1]);

        if ($unknown = array_diff($used, array_keys($definition['placeholders']))) {
            $errors[] = 'Variabel tidak dikenal: {'.implode('}, {', $unknown).'}.';
        }

        if ($missing = array_diff($definition['required'], $used)) {
            $errors[] = 'Variabel penting harus tetap ada: {'.implode('}, {', $missing).'}.';
        }

        return $errors;
    }

    public function save(string $key, string $body, ?User $user): void
    {
        MessageTemplate::query()->updateOrCreate(['key' => $key], ['body' => $body, 'updated_by' => $user?->id]);
        $this->overrides = null;
    }

    public function reset(string $key): void
    {
        MessageTemplate::query()->where('key', $key)->delete();
        $this->overrides = null;
    }
}
