<?php

namespace App\Services\Chat;

use App\Enums\CustomerStatus;
use App\Enums\InvoiceStatus;
use App\Models\ChatSession;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\GenieAcs\GenieAcsException;
use App\Services\GenieAcs\GenieAcsService;
use App\Services\Portal\CustomerLoginService;
use App\Services\Portal\CustomerOnuService;
use App\Services\Settings\SettingService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Bot pelanggan di WhatsApp dan Telegram: tagihan, status ONU, perangkat terhubung, dan ganti WiFi, dengan aturan yang sama
 * seperti portal web. Identitas pelanggan = nomor WhatsApp terdaftar (WhatsApp: nomor pengirim; Telegram: kontak yang dibagikan
 * dan terbukti milik pengirim). Mesin ini tidak tahu cara mengirim; ia hanya mengembalikan daftar balasan.
 */
class ChatBot
{
    private const MESSAGES_PER_MINUTE = 20;

    private const WIFI_CHANGES_PER_10_MIN = 5;

    private const FLOW_MINUTES = 10;

    private const CONFIRM_MINUTES = 5;

    public function __construct(
        private readonly CustomerLoginService $login,
        private readonly CustomerOnuService $onu,
        private readonly GenieAcsService $genieAcs,
        private readonly SettingService $settings,
    ) {}

    /**
     * @return list<ChatReply>
     */
    public function handle(ChatMessage $message): array
    {
        $key = "chat:{$message->channel}:{$message->senderId}";

        if (RateLimiter::tooManyAttempts($key, self::MESSAGES_PER_MINUTE)) {
            return [];
        }

        RateLimiter::hit($key, 60);

        $session = ChatSession::firstOrNew(['channel' => $message->channel, 'sender_id' => $message->senderId]);
        $session->last_message_at = now();

        if ($session->expires_at?->isPast()) {
            $session->resetFlow();
        }

        $replies = $this->route($session, $message);
        $session->save();

        return $replies;
    }

    /**
     * @return list<ChatReply>
     */
    private function route(ChatSession $session, ChatMessage $message): array
    {
        $raw = trim((string) $message->text);
        $text = $this->normalize($raw);

        if ($message->channel === ChatMessage::CHANNEL_WHATSAPP) {
            $session->phone = $message->senderId;
        } else {
            if ($message->text === ChatMessage::FOREIGN_CONTACT) {
                return [new ChatReply('⚠️ Itu kontak orang lain. Tekan tombol Bagikan nomor saya di bawah untuk membagikan kontak Anda sendiri.', requestContact: true)];
            }

            if ($message->contactPhone !== null) {
                return $this->linkTelegram($session, $message->contactPhone);
            }

            if ($session->phone === null) {
                return [$this->contactPrompt()];
            }

            if ($text === 'keluar') {
                $session->phone = null;
                $session->customer_id = null;
                $session->resetFlow();

                return [new ChatReply('🚪 Telegram ini sudah diputus dari akun pelanggan. Kirim pesan apa saja untuk menautkan lagi.', removeKeyboard: true)];
            }
        }

        $customers = $this->login->customersFor((string) $session->phone);

        if ($customers->isEmpty()) {
            return $this->notRegistered($session);
        }

        $customer = $customers->firstWhere('id', $session->customer_id);

        if ($customer === null && $customers->count() === 1) {
            $customer = $customers->first();
            $session->customer_id = $customer->id;
        }

        if ($customer === null) {
            return $this->chooseCustomer($session, $customers, $text);
        }

        if (in_array($text, ['batal', 'cancel'], true) && $session->state !== ChatSession::IDLE) {
            $session->resetFlow();

            return [new ChatReply('❌ Dibatalkan.'), $this->menu($customer, $customers->count())];
        }

        // Saat mengetik nama atau password WiFi, hanya kata persis "menu" yang menyela; "hai" bisa saja nama WiFi.
        $greeting = in_array($text, ['menu', 'halo', 'hai', 'start', 'mulai', 'hi', '0'], true);

        if ($greeting && (! in_array($session->state, ['wifi_ssid', 'wifi_password'], true) || $text === 'menu')) {
            $session->resetFlow();

            return [$this->menu($customer, $customers->count())];
        }

        return match ($session->state) {
            'wifi_menu' => $this->wifiMenu($session, $customer, $text),
            'wifi_ssid' => $this->wifiSsid($session, $raw),
            'wifi_password' => $this->wifiPassword($session, $raw),
            'wifi_confirm' => $this->wifiConfirm($session, $customer, $message->channel, $text),
            default => $this->command($session, $customer, $customers->count(), $text),
        };
    }

    private function normalize(string $text): string
    {
        return (string) Str::of($text)->lower()->trim()->replaceMatches('/^[\/!.]+/', '')->replaceMatches('/[.!?]+$/', '')->trim();
    }

    /**
     * @return list<ChatReply>
     */
    private function command(ChatSession $session, Customer $customer, int $accounts, string $text): array
    {
        $intent = match (true) {
            in_array($text, ['1', 'tagihan', 'invoice', 'bayar', 'pembayaran'], true) => 'billing',
            in_array($text, ['2', 'onu', 'status', 'sinyal', 'redaman'], true) => 'onu',
            in_array($text, ['3', 'perangkat', 'device', 'devices'], true) => 'devices',
            in_array($text, ['4', 'wifi', 'ssid', 'password', 'sandi'], true) => 'wifi',
            in_array($text, ['5', 'cs', 'bantuan', 'help', 'admin'], true) => 'support',
            $accounts > 1 && in_array($text, ['6', 'ganti', 'sambungan'], true) => 'switch',
            default => null,
        };

        return match ($intent) {
            'billing' => [$this->billing($customer)],
            'onu' => [$this->onuStatus($customer)],
            'devices' => [$this->devices($customer)],
            'wifi' => $this->wifiEntry($session, $customer),
            'support' => [$this->support()],
            'switch' => $this->switchCustomer($session),
            default => [new ChatReply('🤔 Maaf, saya belum paham pesan itu.'), $this->menu($customer, $accounts)],
        };
    }

    // ───────────────────────────── Menu dan identitas ─────────────────────────────

    private function menu(Customer $customer, int $accounts): ChatReply
    {
        $first = Str::of($customer->name)->before(' ')->title();
        $lines = [
            "👋 Halo {$first}! Ada yang bisa dibantu?",
            $accounts > 1 ? "Sambungan: {$customer->customer_code}" : null,
            '',
            '1️⃣ 🧾 Tagihan dan cara bayar',
            '2️⃣ 📶 Status ONU dan sinyal',
            '3️⃣ 💻 Perangkat terhubung',
            '4️⃣ 🔑 Ganti nama dan password WiFi',
            '5️⃣ 🎧 Hubungi CS',
            $accounts > 1 ? '6️⃣ 🔁 Ganti sambungan' : null,
            '',
            'Balas dengan angka atau kata kunci (tagihan, onu, wifi). Ketik menu kapan saja untuk kembali.',
        ];

        $buttons = [
            [['label' => '🧾 Tagihan', 'data' => 'tagihan'], ['label' => '📶 Status ONU', 'data' => 'onu']],
            [['label' => '💻 Perangkat', 'data' => 'perangkat'], ['label' => '🔑 Ganti WiFi', 'data' => 'wifi']],
            [['label' => '🎧 Hubungi CS', 'data' => 'cs']],
        ];

        if ($accounts > 1) {
            $buttons[] = [['label' => '🔁 Ganti sambungan', 'data' => 'ganti']];
        }

        return new ChatReply(implode("\n", array_filter($lines, fn (?string $line): bool => $line !== null)), $buttons);
    }

    private function contactPrompt(): ChatReply
    {
        return new ChatReply("👋 Selamat datang di layanan pelanggan {$this->company()}.\n\n📱 Untuk keamanan, tekan tombol Bagikan nomor saya di bawah. Nomor harus sama dengan nomor WhatsApp yang terdaftar di data pelanggan.", requestContact: true);
    }

    /**
     * @return list<ChatReply>
     */
    private function linkTelegram(ChatSession $session, string $contactPhone): array
    {
        $phone = CustomerLoginService::normalizePhone($contactPhone);
        $customers = $phone === null ? new Collection : $this->login->customersFor($phone);

        if ($customers->isEmpty()) {
            return [new ChatReply("❌ Nomor tersebut belum terdaftar sebagai pelanggan {$this->company()}.\nPastikan memakai nomor yang sama dengan data pelanggan, atau hubungi CS.", buttons: $this->supportButtons(), requestContact: true)];
        }

        $session->phone = $phone;
        $session->customer_id = null;
        $session->resetFlow();

        if ($customers->count() === 1) {
            $session->customer_id = $customers->first()->id;

            return [new ChatReply('✅ Telegram Anda sudah tertaut ke akun pelanggan.', removeKeyboard: true), $this->menu($customers->first(), 1)];
        }

        return [new ChatReply('✅ Nomor terverifikasi.', removeKeyboard: true), ...$this->chooseCustomer($session, $customers, '')];
    }

    /**
     * @return list<ChatReply>
     */
    private function notRegistered(ChatSession $session): array
    {
        if ($session->channel === ChatMessage::CHANNEL_TELEGRAM) {
            $session->phone = null;

            return [new ChatReply('❌ Nomor Anda sudah tidak terdaftar sebagai pelanggan aktif. Tautkan ulang dengan nomor yang benar.', requestContact: true)];
        }

        // Nomor asing hanya dijawab sekali per 12 jam agar bot tidak menjadi sumber pesan beruntun.
        if (! Cache::add("chat:unknown:{$session->phone}", 1, now()->addHours(12))) {
            return [];
        }

        return [new ChatReply("🙏 Nomor ini belum terdaftar sebagai pelanggan {$this->company()}.\nHubungi CS untuk mendaftarkan nomor Anda.", buttons: $this->supportButtons())];
    }

    /**
     * @param  Collection<int, Customer>  $customers
     * @return list<ChatReply>
     */
    private function chooseCustomer(ChatSession $session, Collection $customers, string $text): array
    {
        if ($session->state === 'choose_customer' && ctype_digit($text) && ($picked = $customers->values()->get((int) $text - 1)) !== null) {
            $session->customer_id = $picked->id;
            $session->resetFlow();

            return [$this->menu($picked, $customers->count())];
        }

        $session->state = 'choose_customer';
        $session->expires_at = now()->addMinutes(self::FLOW_MINUTES);
        $numbers = ['1️⃣', '2️⃣', '3️⃣', '4️⃣', '5️⃣', '6️⃣', '7️⃣', '8️⃣', '9️⃣'];
        $lines = ["🔁 Nomor ini terdaftar pada {$customers->count()} sambungan. Pilih salah satu:", ''];
        $buttons = [];

        foreach ($customers->values()->take(9) as $index => $customer) {
            $lines[] = ($numbers[$index] ?? ($index + 1).'.')." {$customer->name} · {$customer->customer_code}".($customer->package ? " · {$customer->package->name}" : '');
            $buttons[] = [['label' => '🏠 '.Str::limit($customer->name, 24)." · {$customer->customer_code}", 'data' => (string) ($index + 1)]];
        }

        $lines[] = '';
        $lines[] = 'Balas dengan nomor pilihan.';

        return [new ChatReply(implode("\n", $lines), $buttons)];
    }

    /**
     * @return list<ChatReply>
     */
    private function switchCustomer(ChatSession $session): array
    {
        $session->customer_id = null;
        $session->resetFlow();

        return $this->chooseCustomer($session, $this->login->customersFor((string) $session->phone), '');
    }

    // ───────────────────────────── Tagihan, ONU, perangkat, CS ─────────────────────────────

    private function billing(Customer $customer): ChatReply
    {
        $unpaid = Invoice::query()
            ->where('customer_id', $customer->id)
            ->whereNull('vpn_account_id')
            ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial])
            ->orderBy('due_date')
            ->get();
        $paid = Invoice::query()->where('customer_id', $customer->id)->whereNull('vpn_account_id')->where('status', InvoiceStatus::Paid)->latest('paid_at')->first();
        $outstanding = $unpaid->sum(fn (Invoice $invoice): int => max(0, $invoice->total - $invoice->amount_paid));
        $payUrl = route('isolir.show', $customer->customer_code);

        $lines = ["🧾 Tagihan {$customer->name} ({$customer->customer_code})", 'Layanan: '.$this->statusLabel($customer), ''];

        if ($unpaid->isEmpty()) {
            $lines[] = '✅ Tidak ada tunggakan. Terima kasih!';
        } else {
            $lines[] = '💰 Tunggakan: '.$this->rupiah($outstanding).' ('.$unpaid->count().' invoice)';

            foreach ($unpaid->take(5) as $invoice) {
                $late = $invoice->due_date->startOfDay()->lessThan(now()->startOfDay());
                $lines[] = ($late ? '⚠️' : '⏰')." {$invoice->invoice_number} · ".$this->rupiah(max(0, $invoice->total - $invoice->amount_paid)).' · jatuh tempo '.$invoice->due_date->format('d/m/Y').($late ? ' (lewat)' : '');
            }

            $lines[] = '';
            $lines[] = "💳 Bayar online (QRIS / Virtual Account):\n{$payUrl}";
        }

        if ($paid !== null) {
            $lines[] = '';
            $lines[] = "🧾 Pembayaran terakhir: {$paid->invoice_number}, lunas ".($paid->paid_at?->format('d/m/Y') ?? '-');
        }

        $buttons = $unpaid->isEmpty() ? [] : [[['label' => '💳 Bayar sekarang', 'url' => $payUrl]]];

        return new ChatReply(implode("\n", $lines), [...$buttons, [['label' => '📋 Menu', 'data' => 'menu']]]);
    }

    private function onuStatus(Customer $customer): ChatReply
    {
        $live = $this->onu->live($customer);

        if (! $live['available']) {
            return new ChatReply('📶 '.($live['message'] ?? 'Status ONU belum tersedia.'), [[['label' => '🎧 Hubungi CS', 'data' => 'cs'], ['label' => '📋 Menu', 'data' => 'menu']]]);
        }

        $quality = ['good' => '✅ Baik', 'warning' => '⚠️ Perlu dicek', 'bad' => '🔻 Lemah'][$live['rx_quality'] ?? ''] ?? '';
        $lines = [
            "📶 Status ONU {$customer->name}",
            '',
            'Koneksi: '.($live['online'] ? '🟢 Online' : '🔴 Offline'),
            'Redaman: '.($live['rx_power'] !== null ? number_format($live['rx_power'], 2).' dBm '.$quality : '-'),
            'Terakhir terlihat: '.($live['last_inform'] ? now()->parse($live['last_inform'])->timezone(config('app.timezone'))->format('d/m/Y H:i') : '-'),
            'Nama WiFi: '.($live['ssid'] ?? '-'),
            'Perangkat terhubung: '.count($live['devices']),
        ];

        if ($live['online'] === false || in_array($live['rx_quality'], ['warning', 'bad'], true)) {
            $lines[] = '';
            $lines[] = '🛠️ Jika internet bermasalah, coba cabut dan pasang ulang adaptor ONU 1 menit, atau hubungi CS.';
        }

        return new ChatReply(implode("\n", $lines), [[['label' => '💻 Perangkat', 'data' => 'perangkat'], ['label' => '🔑 WiFi', 'data' => 'wifi']], [['label' => '🎧 Hubungi CS', 'data' => 'cs'], ['label' => '📋 Menu', 'data' => 'menu']]]);
    }

    private function devices(Customer $customer): ChatReply
    {
        $live = $this->onu->live($customer);

        if (! $live['available']) {
            return new ChatReply('💻 '.($live['message'] ?? 'Daftar perangkat belum tersedia.'), [[['label' => '📋 Menu', 'data' => 'menu']]]);
        }

        if ($live['devices'] === []) {
            return new ChatReply('💻 Tidak ada perangkat yang terbaca terhubung saat ini.', [[['label' => '📋 Menu', 'data' => 'menu']]]);
        }

        $lines = ['💻 Perangkat terhubung ('.count($live['devices']).'):', ''];

        foreach (array_slice($live['devices'], 0, 15) as $device) {
            $lines[] = '• '.($device['hostname'] ?? 'Tanpa nama').' · '.implode(' · ', array_filter([$device['ip_address'], $device['mac_address']]));
        }

        return new ChatReply(implode("\n", $lines), [[['label' => '📋 Menu', 'data' => 'menu']]]);
    }

    private function support(): ChatReply
    {
        $number = preg_replace('/\D+/', '', (string) $this->settings->get('company', 'cs_whatsapp'));
        $hours = $this->settings->get('company', 'operating_hours');
        $lines = ['🎧 Hubungi CS '.$this->company(), '', $number ? '💬 WhatsApp: 0'.substr((string) preg_replace('/^62/', '', $number), 0) : 'Kontak CS belum diatur.', $hours ? "🕘 Jam operasional: {$hours}" : null];

        return new ChatReply(implode("\n", array_filter($lines, fn (?string $line): bool => $line !== null)), [...$this->supportButtons(), [['label' => '📋 Menu', 'data' => 'menu']]]);
    }

    /**
     * @return list<list<array{label: string, url: string}>>
     */
    private function supportButtons(): array
    {
        $number = preg_replace('/\D+/', '', (string) $this->settings->get('company', 'cs_whatsapp'));

        return $number ? [[['label' => '💬 Chat CS di WhatsApp', 'url' => "https://wa.me/{$number}"]]] : [];
    }

    // ───────────────────────────── Ganti WiFi ─────────────────────────────

    /**
     * @return list<ChatReply>
     */
    private function wifiEntry(ChatSession $session, Customer $customer): array
    {
        if ($this->onu->assetFor($customer) === null || ! $this->genieAcs->isConfigured()) {
            return [new ChatReply('🔑 ONU Anda belum bisa diatur dari chat. Hubungi CS untuk bantuan.', $this->supportButtons())];
        }

        $live = $this->onu->live($customer);
        $canChange = $customer->status === CustomerStatus::Active;
        $session->state = 'wifi_menu';
        $session->expires_at = now()->addMinutes(self::FLOW_MINUTES);

        $lines = ['🔑 WiFi '.$customer->name, '', 'Nama WiFi saat ini: '.($live['ssid'] ?? '-'), ''];
        $buttons = [];

        if ($canChange) {
            $lines[] = '1️⃣ ✏️ Ganti nama dan password WiFi';
            $buttons[] = [['label' => '✏️ Ganti nama & password', 'data' => '1']];
        } else {
            $lines[] = '⚠️ Ganti WiFi hanya tersedia saat layanan aktif.';
        }

        $lines[] = '2️⃣ 👁️ Lihat password saat ini';
        $lines[] = '';
        $lines[] = 'Balas angka pilihan, atau ketik batal.';
        $buttons[] = [['label' => '👁️ Lihat password', 'data' => '2'], ['label' => '❌ Batal', 'data' => 'batal']];

        return [new ChatReply(implode("\n", $lines), $buttons)];
    }

    /**
     * @return list<ChatReply>
     */
    private function wifiMenu(ChatSession $session, Customer $customer, string $text): array
    {
        if ($text === '1' && $customer->status === CustomerStatus::Active) {
            $session->state = 'wifi_ssid';
            $session->expires_at = now()->addMinutes(self::FLOW_MINUTES);

            return [new ChatReply("✏️ Ketik nama WiFi baru (maksimal 32 karakter).\n\nKetik batal untuk membatalkan.", [[['label' => '❌ Batal', 'data' => 'batal']]])];
        }

        if ($text === '2') {
            $live = $this->onu->live($customer);
            $session->resetFlow();

            return [new ChatReply($live['available'] && $live['wifi_password']
                ? "🔐 Password WiFi saat ini:\n{$live['wifi_password']}\n\n⚠️ Pesan ini tersimpan di riwayat chat. Hapus bila perangkat Anda dipakai bersama."
                : '🔐 Password WiFi belum bisa dibaca saat ini. Coba lagi sebentar lagi atau hubungi CS.', [[['label' => '📋 Menu', 'data' => 'menu']]])];
        }

        return [new ChatReply('Balas 1 (ganti) atau 2 (lihat password). Ketik batal untuk kembali.', [[['label' => '❌ Batal', 'data' => 'batal']]])];
    }

    /**
     * @return list<ChatReply>
     */
    private function wifiSsid(ChatSession $session, string $raw): array
    {
        if (preg_match('/^[\x20-\x7E]{1,32}$/', $raw) !== 1) {
            return [new ChatReply('⚠️ Nama WiFi harus 1–32 karakter (huruf, angka, spasi, dan simbol biasa). Coba lagi atau ketik batal.')];
        }

        $session->payload = ['ssid' => $raw];
        $session->state = 'wifi_password';
        $session->expires_at = now()->addMinutes(self::FLOW_MINUTES);

        return [new ChatReply("🔐 Sekarang ketik password WiFi baru (8–63 karakter).\n\nDi Telegram, pesan password otomatis dihapus dari chat. Ketik batal untuk membatalkan.", [[['label' => '❌ Batal', 'data' => 'batal']]])];
    }

    /**
     * @return list<ChatReply>
     */
    private function wifiPassword(ChatSession $session, string $raw): array
    {
        if (preg_match('/^[\x20-\x7E]{8,63}$/', $raw) !== 1) {
            return [new ChatReply('⚠️ Password harus 8–63 karakter (huruf, angka, dan simbol biasa). Coba lagi atau ketik batal.', deleteIncoming: true)];
        }

        $ssid = (string) ($session->payload['ssid'] ?? '');

        if ($ssid === '') {
            $session->resetFlow();

            return [new ChatReply('⚠️ Sesi habis. Ketik wifi untuk mengulang.', deleteIncoming: true)];
        }

        $session->payload = ['ssid' => $ssid, 'password' => $raw];
        $session->state = 'wifi_confirm';
        $session->expires_at = now()->addMinutes(self::CONFIRM_MINUTES);

        return [new ChatReply(
            "📝 Konfirmasi perubahan WiFi\n\nNama WiFi: {$ssid}\nPassword: ".str_repeat('•', min(12, strlen($raw))).' ('.strlen($raw)." karakter)\n\n⚠️ Semua perangkat akan terputus sebentar dan harus menyambung ulang dengan data baru.\n\nBalas YA untuk menerapkan, atau BATAL.",
            [[['label' => '✅ Terapkan', 'data' => 'ya'], ['label' => '❌ Batal', 'data' => 'batal']]],
            deleteIncoming: true,
        )];
    }

    /**
     * @return list<ChatReply>
     */
    private function wifiConfirm(ChatSession $session, Customer $customer, string $channel, string $text): array
    {
        if (! in_array($text, ['ya', 'iya', 'y', 'ok', 'terapkan', 'konfirmasi', 'setuju'], true)) {
            return [new ChatReply('Balas YA untuk menerapkan perubahan WiFi, atau BATAL untuk membatalkan.', [[['label' => '✅ Terapkan', 'data' => 'ya'], ['label' => '❌ Batal', 'data' => 'batal']]])];
        }

        $payload = (array) $session->payload;
        $session->resetFlow();

        if (! isset($payload['ssid'], $payload['password'])) {
            return [new ChatReply('⚠️ Sesi habis. Ketik wifi untuk mengulang.')];
        }

        $asset = $this->onu->assetFor($customer);

        if ($customer->status !== CustomerStatus::Active || $asset === null || ! $this->genieAcs->isConfigured()) {
            return [new ChatReply('⚠️ WiFi tidak bisa diubah saat ini. Pastikan layanan aktif atau hubungi CS.', $this->supportButtons())];
        }

        if (! RateLimiter::attempt("chat-wifi:{$customer->id}", self::WIFI_CHANGES_PER_10_MIN, fn (): bool => true, 600)) {
            return [new ChatReply('⏳ Terlalu sering mengubah WiFi. Coba lagi dalam beberapa menit.')];
        }

        try {
            $applied = $this->genieAcs->updateWifi($asset, $payload['ssid'], $payload['password']);
        } catch (GenieAcsException $exception) {
            Log::warning('Pelanggan gagal mengganti WiFi lewat chat.', ['customer_id' => $customer->id, 'error' => $exception->getMessage()]);

            return [new ChatReply('⚠️ Pengaturan WiFi belum bisa dikirim ke ONU. Coba lagi beberapa saat lagi atau hubungi CS.', $this->supportButtons())];
        }

        activity('Portal Pelanggan')->performedOn($customer)->withProperties(['ssid' => $payload['ssid'], 'channel' => $channel])->log("Pelanggan mengganti SSID dan password WiFi lewat {$channel}");

        return [new ChatReply($applied
            ? "✅ WiFi diperbarui!\nSambungkan lagi perangkat Anda ke \"{$payload['ssid']}\" dengan password baru."
            : '⏳ ONU sedang tidak terhubung. Pengaturan baru diterapkan otomatis saat ONU online kembali.', [[['label' => '📋 Menu', 'data' => 'menu']]])];
    }

    // ───────────────────────────── Format ─────────────────────────────

    private function statusLabel(Customer $customer): string
    {
        return match ($customer->status) {
            CustomerStatus::Active => '🟢 Aktif',
            CustomerStatus::Isolated => '🔴 Diisolir (menunggu pembayaran)',
            default => '⚪ Nonaktif',
        };
    }

    private function rupiah(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }

    private function company(): string
    {
        return (string) $this->settings->get('company', 'name', config('app.name'));
    }
}
