# Wifi Teslatech — Manajemen ISP / RT RW Net

Aplikasi manajemen ISP skala kecamatan: billing otomatis, multi-router MikroTik, voucher Hotspot, monitoring ONT (GenieACS), peta topologi ODP, tiket gangguan, dan layanan VPN Tunnel.

**Stack:** Laravel 13 (PHP 8.4), Inertia.js + React + TypeScript + Tailwind CSS, SQLite/MariaDB, Laravel Queue.

## Fitur Utama

- **Multi-router MikroTik** — PPPoE, paket, dan voucher terikat ke router masing-masing; kredensial terenkripsi.
- **Pelanggan & billing** — invoice bulanan (prorata), janji bayar, auto-isolir dan auto-open, bayar tunai kasir.
- **Payment gateway** — Duitku, Tripay, Midtrans (QRIS/VA), kredensial dikelola dari panel admin (terenkripsi, bukan `.env`).
- **Hotspot voucher** — generator massal, cetak A4/thermal dengan QR, webhook login pertama.
- **WhatsApp & Telegram** — notifikasi invoice, pengingat jatuh tempo, bot pelanggan (tagihan, status ONU, ganti WiFi).
- **Monitoring ONT** — redaman optik dan ganti SSID/password via GenieACS.
- **Peta ODP (Leaflet)** — topologi, kabel, broadcast gangguan massal.
- **Keuangan** — OPEX dan laporan laba rugi.
- **RBAC & audit log** — Owner, Admin/Kasir, Teknisi.
- **VPN Tunnel**, portal pelanggan `/portal`, halaman isolir publik `/isolir/{kode}`, landing page.

## Kebutuhan Server

| Komponen | Versi |
|---|---|
| PHP | 8.4 (minimal 8.3) dengan ekstensi `mbstring`, `xml`, `curl`, `zip`, `bcmath`, `intl`, `sqlite3` / `mysql` |
| Composer | 2.x |
| Web server | Nginx (atau Apache) + PHP-FPM |
| Database | SQLite (bawaan) atau MariaDB |
| Git | untuk deploy dan fitur pembaruan dari admin |
| Supervisor | menjalankan queue worker |

> **Node.js/npm TIDAK diperlukan di server.** Aset frontend sudah di-build dan di-commit di `public/build`.

## Instalasi di VPS (Ubuntu/Debian)

### 1. Paket sistem

```bash
sudo apt update
sudo apt install -y nginx git unzip supervisor \
  php8.4-fpm php8.4-cli php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip \
  php8.4-bcmath php8.4-intl php8.4-sqlite3 php8.4-mysql
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer
```

### 2. Ambil kode

```bash
sudo mkdir -p /var/www/wifi && sudo chown $USER:www-data /var/www/wifi
git clone <URL-REPO-ANDA> /var/www/wifi
cd /var/www/wifi
```

### 3. Konfigurasi dan deploy pertama

```bash
./deploy.sh --first-run      # membuat .env lalu berhenti
nano .env                    # isi nilai di bawah
./deploy.sh --first-run      # composer, APP_KEY, migrasi, seeder, cache
```

Nilai penting di `.env`:

```ini
APP_NAME="Wifi Teslatech"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-anda.com
APP_TIMEZONE=Asia/Jakarta

DB_CONNECTION=sqlite          # atau mysql + DB_HOST/DB_DATABASE/DB_USERNAME/DB_PASSWORD

QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_STORE=database

# Isi bila di belakang proxy/Cloudflare (IP proxy dipisah koma, atau * )
TRUSTED_PROXIES=
```

Kredensial payment gateway, WhatsApp (Evolution API), Telegram, dan Gemini **tidak** diisi di `.env`; atur lewat menu **Pengaturan** di panel admin.

### 4. Nginx

`/etc/nginx/sites-available/wifi`:

```nginx
server {
    listen 80;
    server_name domain-anda.com;
    root /var/www/wifi/public;
    index index.php;

    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }

    location ~ /\.(?!well-known) {
        deny all;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/wifi /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo apt install -y certbot python3-certbot-nginx && sudo certbot --nginx -d domain-anda.com
```

### 5. Izin folder

```bash
sudo chown -R $USER:www-data storage bootstrap/cache database
sudo chmod -R ug+rwX storage bootstrap/cache database
```

Untuk SQLite, folder `database/` dan file `database.sqlite` harus bisa ditulis oleh `www-data`.

### 6. Queue worker (Supervisor)

`/etc/supervisor/conf.d/wifi-worker.conf`:

```ini
[program:wifi-worker]
command=php /var/www/wifi/artisan queue:work --sleep=3 --tries=3 --max-time=3600
directory=/var/www/wifi
user=www-data
autostart=true
autorestart=true
numprocs=1
stopwaitsecs=3600
redirect_stderr=true
stdout_logfile=/var/www/wifi/storage/logs/worker.log
```

```bash
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start wifi-worker:*
```

### 7. Scheduler (cron)

Wajib untuk invoice bulanan, auto-isolir, dan pengingat. Tambahkan lewat `sudo crontab -u www-data -e`:

```cron
* * * * * cd /var/www/wifi && php artisan schedule:run >> /dev/null 2>&1
```

### 8. Login pertama

Akun Owner dibuat oleh seeder `OwnerAccountSeeder` (email `amon@teslatech.my.id`). Kata sandi awal ada di `database/seeders/OwnerAccountSeeder.php`. **Segera ganti kata sandi setelah login pertama**, lalu ubah/hapus akun default sesuai kebutuhan.

## Pembaruan

```bash
cd /var/www/wifi
./deploy.sh
```

Skrip: mode maintenance, `git merge --ff-only`, `composer install --no-dev`, `migrate --force`, cache config/route/view, `queue:restart`, lalu kembali online. Pembaruan juga bisa dijalankan dari panel admin (`app:update`).

### Alur rilis tanpa npm di server

Build dilakukan di komputer lokal, hasilnya di-commit:

```bash
# lokal
npm install
npm run build
git add public/build && git commit -m "Build aset frontend"
git push
# VPS
./deploy.sh
```

`deploy.sh` berhenti dengan pesan jelas bila `public/build/manifest.json` tidak ada.

## Pemecahan Masalah

### `SQLSTATE[HY000]: General error: 5 database is locked` (SQLite)

Terjadi bila ada lebih dari satu penulis yang berebut file SQLite. Cache, session, dan queue yang semuanya memakai driver `database` memperbesar peluangnya. WAL dan `busy_timeout` sudah aktif di `config/database.php`.

1. **Samakan pemilik file.** User yang menjalankan `deploy.sh`/`artisan`/queue worker harus sama dengan user PHP-FPM, atau file `database.sqlite-wal` dan `-shm` bisa dimiliki user lain.
   ```bash
   ls -la database/
   sudo chown -R <user-php-fpm>:<user-php-fpm> database
   sudo chmod -R ug+rw database
   ```
2. **Pastikan tidak ada proses menggantung**, lalu jalankan hanya satu queue worker:
   ```bash
   ps aux | grep -E "artisan|php-fpm" | grep -v grep
   ```
3. **Kurangi beban tulis ke SQLite** di `.env`, lalu `php artisan config:cache` dan `php artisan queue:restart`:
   ```ini
   CACHE_STORE=file
   SESSION_DRIVER=file
   DB_BUSY_TIMEOUT=15000
   ```
4. **Jangka panjang:** pindah ke MariaDB (`DB_CONNECTION=mysql`) bila traffic, webhook, dan antrean pesan bertambah.

### `Failed opening required '.../vendor/autoload.php'`

Dependensi belum terpasang. Jalankan `composer install --no-dev --optimize-autoloader` (atau `./deploy.sh`) sebelum perintah `artisan` apa pun.

### Halaman error 500 setelah deploy

Periksa `php artisan migrate:status` (pastikan tidak ada migrasi `Pending`), `tail -n 40 storage/logs/laravel.log`, serta izin tulis `storage/` dan `bootstrap/cache/`.

## Webhook

Daftarkan URL berikut di layanan terkait (ganti domain):

| Layanan | URL |
|---|---|
| Payment gateway | `https://domain/webhooks/payment/{duitku\|tripay\|midtrans}` |
| Login pertama Hotspot | `https://domain/webhooks/hotspot/{router}/login` |
| Telegram | `https://domain/webhooks/chat/telegram` (dipasang dari menu Pengaturan) |
| WhatsApp (Evolution) | `https://domain/webhooks/chat/whatsapp/{secret}` (dari menu Pengaturan) |

## Pengembangan Lokal

```bash
composer setup        # install, .env, key, migrate, npm install, build
composer dev          # server + queue + vite
php artisan test --compact
vendor/bin/pint --dirty
```

## Perintah Artisan Khusus

| Perintah | Fungsi |
|---|---|
| `app:update` | Tarik kode, composer, migrasi, bersihkan cache, restart queue |
| `legacy:import` | Impor data dari aplikasi lama |
| `legacy:sync-billing-contacts` | Sinkron tanggal tagihan dan WA dari aplikasi lama |

## Keamanan

- Jangan commit `.env` atau database berisi data pelanggan.
- Pastikan `APP_DEBUG=false` di produksi.
- Ganti kata sandi akun default dan batasi akses SSH.
- Batasi API MikroTik (port 8728/8729) hanya dari IP server ini atau lewat VPN.

## Lisensi

Lihat [LICENSE](LICENSE).
