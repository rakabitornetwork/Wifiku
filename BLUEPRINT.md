Bertindaklah sebagai Senior Full-Stack Laravel Architect, UI/UX Engineer, & Network Automation Engineer. Saya ingin membangun aplikasi **Manajemen ISP / Kecamatan Net (Wifi Teslatech)** yang sangat ringan, responsif seperti aplikasi desktop (SPA), namun powerful untuk menangani operasional harian (billing otomatis, provisioning multi-router MikroTik, voucher Hotspot, monitoring OLT/TR-069, inventaris perangkat, dan manajemen teknisi lapangan).

---

### Panduan Model AI & Strategi Kuota (Wajib Diikuti Per Fase)
- **Fase 1 (Fondasi, Skema DB, Arsitektur Service & Enkripsi):** Gunakan **Opus 5.5 (Effort: High)**. Fokus pada ketepatan relasi database, keamanan enkripsi, dan pola desain (*design pattern*).
- **Fase 2 (UI/UX Layout & Modul CRUD Standar):** Turunkan ke **Sonnet 5.5 (Effort: Medium)**. Fokus pada kecepatan eksekusi komponen frontend dan CRUD rutin.
- **Fase 3 (Integrasi Hardware, Webhook Gateway & Queue Automation):** Naikkan kembali ke **Opus 5.5 (Effort: High)** saat menulis logika API MikroTik, signature Payment Gateway, dan GenieACS untuk mencegah bug/race condition.
- **Fase 4 (GIS Peta, Laporan Keuangan & Audit Log):** Turunkan ke **Sonnet 5.5 (Effort: Medium)** untuk penyusunan tampilan peta, tabel laporan, dan logging.

---

### 1. Tech Stack & Arsitektur Wajib
- **Backend:** Laravel terbaru (PHP 8.4+), siap dijalankan dengan Laravel Octane (FrankenPHP/Swoole).
- **Frontend:** Inertia.js + React + TypeScript + Tailwind CSS (gunakan komponen modern ala Shadcn UI / Lucide Icons).
- **Database:** SQLite (WAL mode) atau MariaDB dengan optimasi composite index pada tabel transaksi/tagihan.
- **Background Jobs:** Laravel Queue + Redis (untuk pengiriman notifikasi WhatsApp massal, generate invoice bulanan, generate voucher massal, dan eksekusi API router).

### 2. Desain Tampilan (UI/UX) & Navigasi Sidebar
- **Layout Utama (Sidebar Navigation):** Gunakan layout dashboard modern dengan **Collapsible Sidebar** di sisi kiri (bisa diperkecil menjadi ikon saja di desktop, dan menjadi *slide-over drawer* responsif di HP teknisi lapangan).
- **Struktur Menu Sidebar:** Kelompokkan menu secara rapi dengan kategori:
  - *Main:* Dashboard Overview, Peta Topologi & ODP.
  - *Pelanggan:* Data Pelanggan (PPPoE), Paket Bandwidth, Voucher Hotspot.
  - *Network:* Manajemen Router (Multi-NAS), Monitoring OLT & TR-069, Inventaris ONT & Aset.
  - *Keuangan & Billing:* Tagihan & Invoice, Penjualan Voucher, Pengeluaran (OPEX), Laporan Laba Rugi.
  - *Sistem & Tim:* Tiket & Broadcast Gangguan, Manajemen Teknisi & RBAC, Audit Log Aktivitas, Pengaturan (WA Gateway, Payment Gateway, Template Isolir).
- **Estetika Premium & High-Craft:**
  - Terapkan desain *clean, modern SaaS / enterprise dashboard* dengan palet warna elegan (mendukung **Dark Mode & Light Mode** toggle yang mulus).
  - Gunakan *subtle borders*, efek *glassmorphism* ringan pada header/modal, tipografi yang tajam (`Inter` / `Plus Jakarta Sans` dengan `tabular-nums` untuk angka tagihan, kode voucher, & trafik), *skeleton loading* saat memuat data, serta *badge* status yang kontras (Online/Offline/Isolir/Lunas/Terpakai).

### 3. Kredensial Default & Database Seeder
- Buat `DatabaseSeeder` yang otomatis membuat role `Owner` dan akun super-admin default saat migrasi dijalankan:
  - **Nama:** Amon (Owner)
  - **Email / Username:** `amon@teslatech.my.id`
  - **Password:** `SayaTamvan`
  - **Role:** `Owner` (akses penuh ke seluruh sistem)

### 4. Modul & Fitur Utama yang Harus Dibangun

#### [FASE 3 - Model: Opus 5.5] 1. Manajemen Multi-Router MikroTik (Multi-NAS)
- Tabel `routers` (Nama Router/POP/Desa, IP Address / VPN Tunnel IP, Port API, Username, Password terenkripsi dengan `Crypt::encryptString()`, Status Koneksi Online/Offline).
- Buat Service Class dinamis (`MikrotikService.php`) menggunakan `evilfreelancer/routeros-api-php` yang dapat menerima instance model `Router` secara dinamis sehingga setiap pelanggan PPPoE, paket, dan voucher terikat ke router spesifiknya masing-masing.

#### [FASE 2 - Model: Sonnet 5.5 | Logika Isolir: Opus 5.5] 2. Manajemen Pelanggan (PPPoE), Inventaris ONT, & Paket Internet
- CRUD Pelanggan (ID Pelanggan otomatis, Router ID, ODP ID, NIK, Nama, No. WhatsApp, Koordinat GPS, Alamat/Desa, Tipe Siklus Tagihan: Fixed Date / Anniversary Date, Tanggal Pasang, Status: Aktif/Isolir/Nonaktif).
- Manajemen Aset ONT (Serial Number/MAC ONT yang terpasang di rumah pelanggan serta status kepemilikan perangkat).
- **Auto-Isolir, Janji Bayar, & Auto-Open [Opus 5.5]:** Mengubah `profile` user PPPoE pada router terkait ke profil `ISOLIR` secara otomatis via Queue Job saat melewati jatuh tempo (kecuali sedang dalam masa *Janji Bayar / Grace Period*), dan langsung mengembalikan ke paket normal saat tagihan lunas.

#### [FASE 2 - Model: Sonnet 5.5] 3. Halaman Publik Isolir (Captive Portal Landing Page)
- Route publik khusus (`/isolir/{customer_code}`) tanpa autentikasi yang menampilkan informasi status isolir, rincian tagihan tertunggak, pilihan pembayaran instan QRIS / Virtual Account (menggunakan Payment Gateway yang sedang aktif), dan tombol hubungi CS via WhatsApp.

#### [FASE 2 - Model: Sonnet 5.5 | Webhook Aktivasi: Opus 5.5] 4. Manajemen Voucher Hotspot (Mikhmon-Style)
- **Paket/Profil Hotspot:** Router ID, Nama Profil, Harga Jual, Masa Aktif/Validity, dan nama *Hotspot User Profile* di MikroTik.
- **Generator & Cetak Voucher:** Generate voucher massal per `batch_code` / reseller melalui Queue Job, halaman cetak siap print (A4 Grid & Thermal 58mm/80mm dengan QR Code auto-login), serta endpoint webhook sinkronisasi aktivasi login pertama dari MikroTik.

#### [FASE 4 - Model: Sonnet 5.5] 5. Peta Topologi ODP, GIS & Broadcast Gangguan Massal (Leaflet.js)
- Pemetaan titik tiang/ODP (Nama ODP, Router/POP induk, Koordinat, Kapasitas Port total vs terpakai) dan pin lokasi rumah pelanggan beserta garis tarikan kabel dari ODP ke pelanggan.
- Fitur **Broadcast Gangguan ODP**: Memilih satu titik ODP yang mengalami gangguan (FO Cut) untuk mengirim pengumuman WhatsApp massal secara otomatis ke seluruh pelanggan yang terhubung di ODP tersebut.

#### [FASE 3 - Model: Opus 5.5 | UI Laporan & OPEX: Sonnet 5.5] 6. Sistem Billing, Multi-Payment Gateway (Duitku, Tripay, Midtrans) & WA Webhook
- **Konfigurasi Payment Gateway via Panel Admin (DILARANG di `.env`) [Opus 5.5]:**
  - Jangan menyimpan API Key, Merchant Code, Server Key, atau Private Key payment gateway di dalam file `.env`.
  - Buat tabel `payment_gateways` di database dan halaman pengaturan khusus di Panel Admin (hanya dapat diakses role `Owner`) untuk mengelola dan mengaktifkan salah satu atau beberapa gateway: **Duitku**, **Tripay**, dan **Midtrans**.
  - Seluruh kredensial sensitif (API Key, Merchant Key, Server Key) wajib dienkripsi di database menggunakan Laravel `Crypt::encryptString()` / cast `encrypted`, serta ditampilkan dalam kondisi ter-masking (`••••••••`) di form UI pengaturan.
  - Gunakan **Strategy / Factory Pattern** (`PaymentGatewayManager`) agar sistem bisa membuat transaksi QRIS/VA dan memverifikasi signature webhook callback secara dinamis sesuai driver gateway yang dipilih (`duitku`, `tripay`, atau `midtrans`).
- Scheduler generate invoice bulanan (mendukung perhitungan prorata bulan pertama) dan pembayaran tunai kasir.
- Modul pencatatan pengeluaran operasional (OPEX: bandwidth, peralatan, operasional lapangan) untuk menghitung laporan laba bersih **[Sonnet 5.5]**.
- Service webhook keluar (`WhatsappNotificationService.php`) ke REST API WhatsApp Gateway lokal (Node.js/Baileys) untuk invoice, pengingat H-3, bukti bayar, dan notifikasi gangguan.

#### [FASE 3 - Model: Opus 5.5] 7. Monitoring OLT / TR-069 (GenieACS API)
- Service untuk menarik data redaman optik (RX Power ONT) dan mengubah SSID/Password Wi-Fi pelanggan via API GenieACS.

#### [FASE 4 - Model: Sonnet 5.5] 8. RBAC & Audit Log Keamanan
- Menggunakan `spatie/laravel-permission` (`Owner`, `Admin/Kasir`, `Teknisi`) dan `spatie/laravel-activitylog` untuk merekam setiap perubahan transaksi tunai, perubahan pengaturan API key gateway, penghapusan data, dan perubahan paket.

### 5. Aturan Penulisan Kode
- Gunakan **Action / Service Pattern** agar Controller tetap tipis dan bersih.
- Semua komunikasi ke perangkat eksternal (MikroTik, GenieACS, WA Gateway, API Duitku/Tripay/Midtrans) wajib dibungkus `try-catch`, memiliki *timeout* singkat (maksimal 5–10 detik), dan dieksekusi melalui Queue Job untuk proses massal agar UI tidak pernah *freeze* atau *timeout*.
- Terapkan Eager Loading (`with()`) untuk mencegah N+1 Query Problem.

---

### Peta Jalan Eksekusi Bertahap (Checklist Pergantian Model)

- **[x] FASE 1 — Gunakan Model: `Opus 5.5` (Effort: `High`)**
  - Menyusun seluruh file Migration database (multi-router, `payment_gateways` terenkripsi, ONT, billing prorata, OPEX, voucher) + `DatabaseSeeder` (akun Amon).
  - Menyusun kerangka struktur folder `app/Services`, `app/Drivers/Payment`, dan `app/Jobs`.
  - *Stop setelah fase ini selesai disetujui, lalu ganti model ke Sonnet 5.5.*

- **[x] FASE 2 — Gunakan Model: `Sonnet 5.5` (Effort: `Medium`)**
  - Membangun Layout utama (Collapsible Sidebar, Dark/Light mode), halaman pengaturan Payment Gateway (UI masking), CRUD Router, Paket Bandwidth, Pelanggan PPPoE, Inventaris ONT, Generator/Cetak Voucher Hotspot, dan halaman publik `/isolir/{customer_code}`.
  - *Stop setelah modul CRUD & UI dasar selesai, lalu naikkan model ke Opus 5.5.*

- **[x] FASE 3 — Gunakan Model: `Opus 5.5` (Effort: `High`)**
  - Mengimplementasikan logika inti `MikrotikService.php`, `PaymentGatewayManager` (Duitku, Tripay, Midtrans + verifikasi signature webhook), `WhatsappNotificationService.php`, `GenieAcsService.php`, serta Queue Job untuk Auto-Isolir, Auto-Open, dan Scheduler Invoice Prorata.
  - *Stop setelah integrasi hardware & webhook berjalan stabil, lalu turunkan model ke Sonnet 5.5.*

- **[x] FASE 4 — Gunakan Model: `Sonnet 5.5` (Effort: `Medium`)**
  - Membangun Peta Topologi ODP (Leaflet.js) + fitur Broadcast Gangguan massal, modul Keuangan OPEX & Laporan Laba Rugi, RBAC (`spatie/laravel-permission`), serta Audit Log (`spatie/laravel-activitylog`).

---

### Tugas Pertama Anda (Mulai Fase 1 dengan Opus 5.5):
Jangan langsung menulis semua kode sekaligus. Mulai dengan menyusun:
1. **Struktur Skema Database (Migration)** lengkap dengan relasi multi-router, tabel `payment_gateways` terenkripsi, inventaris ONT, billing prorata, pengeluaran, voucher hotspot, dan indexing-nya, beserta kode `DatabaseSeeder` untuk akun admin default di atas.
2. **Struktur Folder Service, Payment Driver (Duitku/Tripay/Midtrans), & Job** yang direkomendasikan.
Setelah saya setujui, kita akan lanjut ke **Fase 2** (dan saya akan mengganti model ke Sonnet 5.5).