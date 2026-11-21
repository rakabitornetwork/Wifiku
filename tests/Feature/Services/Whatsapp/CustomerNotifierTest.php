<?php

namespace Tests\Feature\Services\Whatsapp;

use App\Jobs\Notification\SendWhatsappMessageJob;
use App\Models\Customer;
use App\Models\Outage;
use App\Models\WhatsappMessage;
use App\Services\Whatsapp\CustomerNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CustomerNotifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_skips_customers_without_whatsapp_number(): void
    {
        Queue::fake();
        $notifier = $this->app->make(CustomerNotifier::class);
        $outage = Outage::factory()->create();

        $notifier->outageResolved(Customer::factory()->create(['whatsapp' => '']), $outage);
        $notifier->outageResolved(Customer::factory()->create(['whatsapp' => '6281234567890']), $outage);

        $this->assertSame(1, WhatsappMessage::count());
        Queue::assertPushed(SendWhatsappMessageJob::class, 1);
    }
}
