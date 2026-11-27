<?php

namespace App\Services\Chat;

/**
 * Satu balasan bot. WhatsApp hanya memakai teks (menu bernomor); Telegram juga menampilkan tombol.
 */
final readonly class ChatReply
{
    /**
     * @param  list<list<array{label: string, data?: string, url?: string}>>  $buttons  Baris tombol inline Telegram; data = kata kunci yang dikirim balik, url = tombol tautan.
     * @param  bool  $requestContact  Telegram: tampilkan tombol Bagikan nomor saya.
     * @param  bool  $removeKeyboard  Telegram: sembunyikan tombol kontak setelah verifikasi.
     * @param  bool  $deleteIncoming  Telegram: hapus pesan pengguna (mis. berisi password WiFi) dari chat.
     * @param  bool  $sensitive  Balasan memuat password; isinya disamarkan di riwayat pesan.
     */
    public function __construct(
        public string $text,
        public array $buttons = [],
        public bool $requestContact = false,
        public bool $removeKeyboard = false,
        public bool $deleteIncoming = false,
        public bool $sensitive = false,
    ) {}
}
