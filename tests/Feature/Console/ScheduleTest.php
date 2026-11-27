<?php

namespace Tests\Feature\Console;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    public function test_registers_billing_network_and_tr069_tasks(): void
    {
        $events = collect($this->app->make(Schedule::class)->events())->mapWithKeys(fn (Event $event) => [$event->description => $event->expression]);

        $this->assertSame([
            'generate-invoices' => '0 8 * * *',
            'auto-isolir' => '30 0 * * *',
            'invoice-reminders' => '0 9 * * *',
            'promise-reminders' => '5 9 * * *',
            'due-date-reminders' => '10 9 * * *',
            'check-routers' => '*/5 * * * *',
            'sync-isolation-status' => '*/5 * * * *',
            'expire-vouchers' => '*/5 * * * *',
            'fetch-ont-rx-power' => '0 * * * *',
            'generate-vpn-invoices' => '5 8 * * *',
            'expire-pending-vpn-accounts' => '25 0 * * *',
            'expire-vpn-accounts' => '20 0 * * *',
        ], $events->all());
    }
}
