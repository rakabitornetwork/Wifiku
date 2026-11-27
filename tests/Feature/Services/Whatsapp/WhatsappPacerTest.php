<?php

namespace Tests\Feature\Services\Whatsapp;

use App\Enums\WhatsappMessageType;
use App\Jobs\Notification\SendWhatsappMessageJob;
use App\Models\WhatsappMessage;
use App\Services\Settings\SettingService;
use App\Services\Whatsapp\WhatsappNotificationService;
use App\Services\Whatsapp\WhatsappPacer;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WhatsappPacerTest extends TestCase
{
    use RefreshDatabase;

    private function setting(string $key, string $value): void
    {
        $this->app->make(SettingService::class)->set('whatsapp', $key, $value);
    }

    private function pacer(): WhatsappPacer
    {
        return $this->app->make(WhatsappPacer::class);
    }

    private function bulk(CarbonImmutable $at): WhatsappMessage
    {
        return WhatsappMessage::factory()->create(['type' => WhatsappMessageType::Invoice, 'scheduled_at' => $at]);
    }

    public function test_urgent_messages_are_never_delayed(): void
    {
        foreach ([WhatsappMessageType::AccountSecurity, WhatsappMessageType::PasswordReset, WhatsappMessageType::Chat, WhatsappMessageType::Test] as $type) {
            $this->assertNull($this->pacer()->reserve($type));
        }
    }

    public function test_pacing_can_be_turned_off(): void
    {
        $this->setting('pacing_enabled', '0');

        $this->assertNull($this->pacer()->reserve(WhatsappMessageType::Invoice));
    }

    public function test_each_bulk_message_gets_a_later_slot_with_a_random_gap_inside_the_bounds(): void
    {
        $this->setting('delay_min', '10');
        $this->setting('delay_max', '40');
        $this->setting('batch_size', '200');
        $now = CarbonImmutable::parse('2026-11-30 10:00:00');
        $previous = $now;

        for ($i = 0; $i < 12; $i++) {
            $slot = $this->pacer()->reserve(WhatsappMessageType::Invoice, $now);
            $this->bulk($slot);
            $gap = $previous->diffInSeconds($slot, false);

            $this->assertGreaterThanOrEqual(10, $gap, 'jeda terlalu pendek');
            $this->assertLessThan(10 + 31 + 8 * 60 + 1, $gap, 'jeda terlalu panjang');
            $previous = $slot;
        }

        $this->assertTrue($previous->greaterThan($now->addSeconds(100)));
    }

    public function test_gaps_are_not_all_identical(): void
    {
        $this->setting('delay_min', '5');
        $this->setting('delay_max', '60');
        $this->setting('batch_size', '200');
        $now = CarbonImmutable::parse('2026-11-30 10:00:00');
        $previous = $now;
        $gaps = [];

        for ($i = 0; $i < 15; $i++) {
            $slot = $this->pacer()->reserve(WhatsappMessageType::Invoice, $now);
            $this->bulk($slot);
            $gaps[] = $previous->diffInSeconds($slot, false);
            $previous = $slot;
        }

        $this->assertGreaterThan(3, count(array_unique($gaps)));
    }

    public function test_messages_outside_the_sending_window_move_to_the_next_opening(): void
    {
        $this->setting('window_start', '07:00');
        $this->setting('window_end', '20:30');

        $night = $this->pacer()->reserve(WhatsappMessageType::Invoice, CarbonImmutable::parse('2026-11-30 23:10:00'));
        $earlyMorning = $this->pacer()->reserve(WhatsappMessageType::Invoice, CarbonImmutable::parse('2026-12-01 03:00:00'));

        $this->assertTrue($night->between(CarbonImmutable::parse('2026-12-01 07:00:00'), CarbonImmutable::parse('2026-12-01 07:15:00')), $night->toDateTimeString());
        $this->assertTrue($earlyMorning->between(CarbonImmutable::parse('2026-12-01 07:00:00'), CarbonImmutable::parse('2026-12-01 07:15:00')), $earlyMorning->toDateTimeString());
    }

    public function test_the_daily_limit_pushes_extra_messages_to_the_next_day(): void
    {
        $this->setting('daily_limit', '3');
        $this->setting('delay_min', '1');
        $this->setting('delay_max', '2');
        $this->setting('batch_size', '200');
        $now = CarbonImmutable::parse('2026-11-30 09:00:00');

        $slots = [];

        for ($i = 0; $i < 5; $i++) {
            $slot = $this->pacer()->reserve(WhatsappMessageType::Invoice, $now);
            $this->bulk($slot);
            $slots[] = $slot;
        }

        $this->assertSame(['2026-11-30', '2026-11-30', '2026-11-30', '2026-12-01', '2026-12-01'], array_map(fn (CarbonImmutable $slot): string => $slot->toDateString(), $slots));
    }

    public function test_a_zero_daily_limit_means_unlimited(): void
    {
        $this->setting('daily_limit', '0');
        $this->setting('delay_min', '1');
        $this->setting('delay_max', '1');
        $this->setting('batch_size', '200');
        $now = CarbonImmutable::parse('2026-11-30 09:00:00');

        for ($i = 0; $i < 6; $i++) {
            $this->bulk($this->pacer()->reserve(WhatsappMessageType::Invoice, $now));
        }

        $this->assertSame('2026-11-30', $this->pacer()->reserve(WhatsappMessageType::Invoice, $now)->toDateString());
    }

    public function test_typing_delay_grows_with_the_text_and_is_capped(): void
    {
        $short = $this->pacer()->typingDelayMs('Halo');
        $long = $this->pacer()->typingDelayMs(str_repeat('a', 2000));

        $this->assertGreaterThanOrEqual(800, $short);
        $this->assertLessThanOrEqual(7000, $long);
        $this->assertGreaterThan($short, $long);

        $this->setting('typing_simulation', '0');
        $this->assertSame(0, $this->pacer()->typingDelayMs('Halo'));
    }

    public function test_queueing_schedules_bulk_messages_and_sends_urgent_ones_now(): void
    {
        Queue::fake([SendWhatsappMessageJob::class]);
        $this->travelTo('2026-11-30 10:00:00');
        $service = $this->app->make(WhatsappNotificationService::class);

        $bulk = $service->queue('628111', WhatsappMessageType::Invoice, 'Tagihan');
        $otp = $service->queue('628111', WhatsappMessageType::AccountSecurity, 'Kode', sensitive: true);

        $this->assertNotNull($bulk->fresh()->scheduled_at);
        $this->assertNull($otp->fresh()->scheduled_at);
        Queue::assertPushed(SendWhatsappMessageJob::class, fn (SendWhatsappMessageJob $job): bool => $job->whatsappMessage->is($bulk) && $job->delay !== null);
        Queue::assertPushed(SendWhatsappMessageJob::class, fn (SendWhatsappMessageJob $job): bool => $job->whatsappMessage->is($otp) && $job->delay === null);
    }
}
