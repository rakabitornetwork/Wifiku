<?php

namespace App\Services\Whatsapp;

use App\Enums\WhatsappMessageType;
use App\Models\WhatsappMessage;
use App\Services\Settings\SettingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Mengatur ritme kirim WhatsApp supaya nomor tidak terlihat seperti robot yang menyiarkan pesan massal.
 *
 * Tiap pesan massal mendapat jadwal kirim sendiri: jeda acak antar pesan, istirahat panjang di waktu acak (kira-kira tiap
 * N pesan), hanya di dalam jam kirim, dan dibatasi per hari. Pesan penting yang ditunggu pengguna (OTP, reset password,
 * balasan bot, pesan uji) tidak pernah ditunda. Jadwal disimpan di kolom scheduled_at sehingga tahan restart dan cache:clear.
 */
class WhatsappPacer
{
    /**
     * Jenis pesan yang harus terkirim segera.
     *
     * @var list<WhatsappMessageType>
     */
    public const URGENT = [
        WhatsappMessageType::AccountSecurity,
        WhatsappMessageType::PasswordReset,
        WhatsappMessageType::Chat,
        WhatsappMessageType::Test,
    ];

    public function __construct(private readonly SettingService $settings) {}

    public function isEnabled(): bool
    {
        return $this->settings->get('whatsapp', 'pacing_enabled') !== '0';
    }

    public function isUrgent(WhatsappMessageType $type): bool
    {
        return in_array($type, self::URGENT, true);
    }

    /**
     * Reservasi jadwal kirim berikutnya untuk satu pesan massal; null berarti kirim sekarang (pesan penting / ritme dimatikan).
     */
    public function reserve(WhatsappMessageType $type, ?CarbonImmutable $now = null): ?CarbonImmutable
    {
        if (! $this->isEnabled() || $this->isUrgent($type)) {
            return null;
        }

        $now ??= CarbonImmutable::now();

        // Kunci menjaga dua worker tidak membagikan slot yang sama.
        return Cache::lock('whatsapp-pacer', 10)->block(5, fn (): CarbonImmutable => $this->nextSlot($now));
    }

    /**
     * Lama (ms) indikator "sedang mengetik" sebelum pesan muncul; mengikuti panjang teks dengan variasi acak.
     */
    public function typingDelayMs(string $text): int
    {
        if ($this->settings->get('whatsapp', 'typing_simulation') === '0') {
            return 0;
        }

        $base = 800 + (int) (mb_strlen($text) * random_int(18, 40));

        return min(7000, $base + random_int(0, 900));
    }

    private function nextSlot(CarbonImmutable $now): CarbonImmutable
    {
        $min = max(1, (int) $this->settings->get('whatsapp', 'delay_min'));
        $max = max($min, (int) $this->settings->get('whatsapp', 'delay_max'));
        $batch = max(2, (int) $this->settings->get('whatsapp', 'batch_size'));
        $restMin = max(1, (int) $this->settings->get('whatsapp', 'rest_min'));
        $restMax = max($restMin, (int) $this->settings->get('whatsapp', 'rest_max'));

        $last = WhatsappMessage::query()->whereNotNull('scheduled_at')->where('scheduled_at', '>=', $now->subDay())->max('scheduled_at');
        $start = $last === null ? $now : CarbonImmutable::parse($last)->max($now);

        // Jeda acak sejak pesan terakhir; sesekali (peluang 1/N, jadi ukuran "batch" ikut acak) istirahat panjang.
        $gap = random_int($min, $max);

        if (random_int(1, $batch) === 1) {
            $gap += random_int($restMin * 60, $restMax * 60);
        }

        return $this->withinWindow($start->addSeconds($gap), $now);
    }

    /**
     * Geser ke jam kirim berikutnya bila di luar jendela atau batas harian sudah penuh.
     */
    private function withinWindow(CarbonImmutable $candidate, CarbonImmutable $now): CarbonImmutable
    {
        $limit = (int) $this->settings->get('whatsapp', 'daily_limit');

        for ($guard = 0; $guard < 14; $guard++) {
            [$open, $close] = $this->windowOn($candidate);

            if ($candidate->lessThan($open)) {
                $candidate = $open->addSeconds(random_int(0, 900));
            } elseif ($candidate->greaterThanOrEqualTo($close)) {
                $candidate = $this->windowOn($candidate->addDay())[0]->addSeconds(random_int(0, 900));
            } elseif ($limit > 0 && $this->scheduledOn($candidate) >= $limit) {
                $candidate = $this->windowOn($candidate->addDay())[0]->addSeconds(random_int(0, 900));
            } else {
                return $candidate;
            }
        }

        return $candidate;
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function windowOn(CarbonImmutable $day): array
    {
        [$startHour, $startMinute] = array_map('intval', explode(':', (string) $this->settings->get('whatsapp', 'window_start')));
        [$endHour, $endMinute] = array_map('intval', explode(':', (string) $this->settings->get('whatsapp', 'window_end')));

        $open = $day->setTime($startHour, $startMinute);
        $close = $day->setTime($endHour, $endMinute);

        // Jendela terbalik atau kosong dianggap seharian penuh agar pesan tidak tertahan selamanya.
        if ($close->lessThanOrEqualTo($open)) {
            return [$day->startOfDay(), $day->endOfDay()];
        }

        return [$open, $close];
    }

    private function scheduledOn(CarbonImmutable $day): int
    {
        return WhatsappMessage::query()
            ->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [$day->startOfDay(), $day->endOfDay()])
            ->whereNotIn('type', array_map(fn (WhatsappMessageType $type): string => $type->value, self::URGENT))
            ->count();
    }
}
