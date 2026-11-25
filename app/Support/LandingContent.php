<?php

namespace App\Support;

/**
 * Teks bawaan landing page publik. Satu tempat untuk menyunting copywriting; `{company}` diganti nama perusahaan
 * dari Pengaturan. Angka harga, kontak, dan nama perusahaan tidak ditulis di sini: harga diambil dari paket
 * yang ditandai tampil di landing, kontak dari Pengaturan.
 */
class LandingContent
{
    /**
     * @return array<string, mixed>
     */
    public static function sections(string $company): array
    {
        return self::fill([
            'hero' => [
                'badge' => 'Instalasi WiFi Rumahan',
                'title' => 'Internet Rumah Tanpa Drama',
                'body' => 'Pemasangan WiFi rumahan yang rapi, cepat, dan dikonfigurasi agar sinyal merata di seluruh ruangan.',
                'cta' => 'Konsultasi Gratis',
                'secondary_cta' => 'Lihat Paket',
                'highlights' => ['Survey sinyal gratis', 'Kabel rapi', 'Support via WhatsApp'],
            ],
            'services' => [
                'eyebrow' => 'Layanan',
                'title' => 'Layanan yang siap dipasang',
                'body' => 'Dari rumah kecil hingga hunian bertingkat, kami sesuaikan perangkat dan layout sinyal dengan kebutuhan Anda.',
                'items' => [
                    ['icon' => 'wifi', 'title' => 'Instalasi WiFi Rumahan', 'description' => 'Pemasangan router dan akses poin dengan routing kabel rapi serta konfigurasi keamanan dasar.'],
                    ['icon' => 'signal', 'title' => 'Optimasi Sinyal', 'description' => 'Survey dead-zone, penempatan perangkat strategis, dan tuning agar coverage merata.'],
                    ['icon' => 'router', 'title' => 'Upgrade & Mesh Network', 'description' => 'Perluasan jaringan mesh untuk rumah bertingkat tanpa putus saat berpindah ruangan.'],
                    ['icon' => 'shield', 'title' => 'Maintenance Berkala', 'description' => 'Pengecekan performa, update firmware, dan perbaikan gangguan koneksi rumah Anda.'],
                ],
            ],
            'about' => [
                'eyebrow' => 'Tentang {company}',
                'title' => 'Solusi konektivitas rumah yang mengutamakan kualitas kerja',
                'body' => '{company} fokus pada jasa pemasangan dan optimasi WiFi rumahan. Kami menggabungkan standar teknis yang rapi dengan komunikasi yang jelas, sehingga pelanggan paham apa yang dipasang dan mengapa.',
                // Angka di bawah dipindahkan apa adanya dari situs lama; ganti dengan data Anda yang sebenarnya atau kosongkan array-nya untuk menyembunyikan.
                'stats' => [
                    ['value' => '500+', 'label' => 'Rumah terpasang'],
                    ['value' => '98%', 'label' => 'Kepuasan klien'],
                    ['value' => '24 jam', 'label' => 'Respon support'],
                ],
            ],
            'benefits' => [
                'eyebrow' => 'Keunggulan',
                'title' => 'Mengapa rumah Anda lebih baik bersama kami',
                'body' => 'Setiap pemasangan dirancang agar stabil jangka panjang — bukan sekadar “nyala dulu”.',
                'items' => [
                    ['icon' => 'zap', 'title' => 'Instalasi cepat & rapi', 'description' => 'Jadwal jelas, kerja bersih, dan dokumentasi singkat setelah selesai.'],
                    ['icon' => 'radar', 'title' => 'Survey sinyal menyeluruh', 'description' => 'Kami ukur titik lemah sebelum menentukan posisi perangkat.'],
                    ['icon' => 'lock', 'title' => 'Keamanan jaringan', 'description' => 'Password kuat, guest network opsional, dan konfigurasi aman untuk keluarga.'],
                    ['icon' => 'headphones', 'title' => 'After-sales responsif', 'description' => 'Bantuan remote atau kunjungan ulang bila terjadi gangguan.'],
                ],
            ],
            'process' => [
                'eyebrow' => 'Cara Kerja',
                'title' => 'Empat langkah menuju WiFi rumah yang stabil',
                'body' => 'Proses transparan dari konsultasi hingga serah terima.',
                'steps' => [
                    ['title' => 'Konsultasi & survey', 'description' => 'Kami dengarkan kebutuhan, denah rumah, dan kendala sinyal yang ada.'],
                    ['title' => 'Rekomendasi perangkat', 'description' => 'Paket router/mesh disesuaikan dengan luas area dan jumlah perangkat.'],
                    ['title' => 'Instalasi profesional', 'description' => 'Pemasangan, kabel rapi, dan konfigurasi SSID serta keamanan.'],
                    ['title' => 'Uji & serah terima', 'description' => 'Speed test tiap zona, panduan singkat, dan kontak support.'],
                ],
            ],
            'pricing' => [
                'eyebrow' => 'Harga & Langganan',
                'title' => 'Paket WiFi rumahan',
                'body' => 'Pilih yang pas buat rumah Anda. Harga jelas, pasang dibantu teknisi, dan kalau bingung bisa tanya dulu.',
                'note' => 'Harga bisa berbeda tergantung area. Mau tanya dulu juga boleh, gratis.',
                'perks' => ['Pasang dibantu teknisi', 'Bantuan via WhatsApp bila ada gangguan'],
                'empty' => 'Hubungi kami untuk mengetahui paket dan harga yang tersedia di area Anda.',
            ],
            // Contoh testimoni dari situs lama. Ganti dengan testimoni pelanggan Anda yang sebenarnya, atau kosongkan array-nya untuk menyembunyikan bagian ini.
            'testimonials' => [
                'eyebrow' => 'Testimoni',
                'title' => 'Yang dikatakan pelanggan kami',
                'body' => 'Pengalaman dari pemilik rumah yang sudah menggunakan jasa kami.',
                'items' => [
                    ['name' => 'Rina Wulandari', 'role' => 'Pemilik rumah', 'quote' => 'Sinyal di lantai dua akhirnya stabil. Teknisi menjelaskan tiap langkah dengan sabar.'],
                    ['name' => 'Andi Pratama', 'role' => 'Work from home', 'quote' => 'Meeting video tidak putus-putus lagi. Instalasi rapi dan tepat waktu.'],
                    ['name' => 'Sinta Maharani', 'role' => 'Ibu rumah tangga', 'quote' => 'Anak-anak bisa belajar online di kamar masing-masing tanpa berebut sinyal.'],
                ],
            ],
            'cta' => [
                'title' => 'Siap pasang WiFi rumah yang lebih stabil?',
                'body' => 'Ceritakan denah rumah Anda. Kami bantu rekomendasikan perangkat dan jadwal survey.',
                'button' => 'Chat WhatsApp',
            ],
            'contact' => [
                'eyebrow' => 'Kontak',
                'title' => 'Hubungi tim kami',
                'body' => 'Tim kami siap membantu konsultasi pemasangan, perbaikan, maupun upgrade jaringan rumah Anda.',
                'form_note' => 'Atau kirim pesan singkat — pesan Anda dibuka di WhatsApp dan kami balas secepatnya di jam operasional.',
            ],
            'vpn' => [
                'eyebrow' => 'Layanan VPN Tunnel',
                'title' => 'VPN Tunnel: remote RouterOS dan Speedtest',
                'body' => 'VPN Tunnel dipakai untuk me-remote perangkat RouterOS, me-remote perangkat di belakangnya, dan mengalihkan trafik Speedtest dari ISP utama ke tunnel kami. Internet utama tetap jalan.',
                'features' => [
                    'Alihkan trafik Speedtest dari ISP utama ke VPN Tunnel',
                    'Tunnel langsung ke router MikroTik Anda (L2TP dan SSTP)',
                    'Port forward Winbox/API otomatis dan script RouterOS siap tempel',
                    'Masuk portal dengan email dan password',
                ],
                'steps' => [
                    ['title' => 'Daftar', 'description' => 'Isi nama, email, password, dan WhatsApp.'],
                    ['title' => 'Buat akun VPN', 'description' => 'Pilih protokol dan port yang ingin dibuka.'],
                    ['title' => 'Bayar', 'description' => 'QRIS atau Virtual Account; akun aktif otomatis.'],
                ],
            ],
            'footer' => [
                'body' => 'Mitra pemasangan WiFi rumahan untuk koneksi yang rapi, aman, dan andal.',
            ],
        ], $company);
    }

    /**
     * @return array{updated_at: string, intro: string, paragraphs: list<array{heading: string, body: string}>}
     */
    public static function terms(string $company): array
    {
        return self::fill([
            'updated_at' => '25 November 2026',
            'intro' => 'Dengan menggunakan layanan {company}, Anda menyetujui ketentuan berikut. Harap baca dengan saksama sebelum memesan jasa pemasangan atau layanan terkait.',
            'paragraphs' => [
                ['heading' => '1. Layanan', 'body' => "{company} menyediakan jasa konsultasi, pemasangan, optimasi, dan perawatan jaringan WiFi rumahan serta layanan VPN Tunnel.\nRuang lingkup pekerjaan mengikuti kesepakatan yang dikomunikasikan sebelum instalasi dimulai."],
                ['heading' => '2. Pemesanan & Jadwal', 'body' => "Pemesanan dapat dilakukan melalui WhatsApp atau kontak yang tertera di situs.\nJadwal survey dan instalasi bersifat kesepakatan bersama dan dapat berubah jika ada kondisi teknis atau force majeure."],
                ['heading' => '3. Pembayaran', 'body' => 'Biaya layanan diinformasikan sebelum pekerjaan dimulai. Pembayaran mengikuti skema yang disepakati (sebagian di muka, pelunasan setelah serah terima, atau sesuai penawaran tertulis). Layanan langganan ditagih per periode dan dapat dinonaktifkan bila tagihan melewati jatuh tempo.'],
                ['heading' => '4. Tanggung Jawab Pelanggan', 'body' => 'Pelanggan wajib memberikan akses lokasi yang aman, informasi denah/kebutuhan yang akurat, serta memastikan perangkat milik pelanggan (jika ada) dalam kondisi layak digunakan. Pelanggan bertanggung jawab menjaga kerahasiaan kredensial akunnya, termasuk akun VPN Tunnel.'],
                ['heading' => '5. Garansi & After-Sales', 'body' => 'Garansi pekerjaan mengikuti ketentuan yang disampaikan saat serah terima. Gangguan di luar lingkup instalasi (misalnya gangguan ISP, listrik, atau kerusakan perangkat pihak ketiga) dapat dikenakan biaya tambahan.'],
                ['heading' => '6. Penggunaan yang Wajar', 'body' => 'Layanan tidak boleh dipakai untuk kegiatan yang melanggar hukum atau merugikan pihak lain, termasuk penyalahgunaan VPN Tunnel. Kami berhak menonaktifkan layanan yang disalahgunakan.'],
                ['heading' => '7. Privasi Data', 'body' => 'Data kontak dan informasi teknis yang Anda berikan digunakan hanya untuk keperluan layanan, komunikasi, dan peningkatan kualitas kerja. Kami tidak menjual data pelanggan kepada pihak ketiga yang tidak relevan.'],
                ['heading' => '8. Perubahan Ketentuan', 'body' => 'Kami dapat memperbarui Terms of Service sewaktu-waktu. Versi terbaru selalu tersedia di halaman ini. Penggunaan layanan setelah pembaruan dianggap sebagai penerimaan atas ketentuan baru.'],
                ['heading' => '9. Kontak', 'body' => 'Untuk pertanyaan terkait ketentuan ini, hubungi kami melalui kontak yang tertera di situs resmi {company}.'],
            ],
        ], $company);
    }

    /**
     * Ganti `{company}` di seluruh nilai string secara rekursif.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private static function fill(array $content, string $company): array
    {
        array_walk_recursive($content, function (mixed &$value) use ($company): void {
            if (is_string($value)) {
                $value = str_replace('{company}', $company, $value);
            }
        });

        return $content;
    }
}
