<?php

namespace Tests\Feature\Services\Billing;

use App\Enums\BillingCycle;
use App\Enums\CustomerStatus;
use App\Enums\InvoiceItemType;
use App\Enums\WhatsappMessageType;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\WhatsappMessage;
use App\Services\Billing\InvoiceGeneratorService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InvoiceGeneratorServiceTest extends TestCase
{
    use RefreshDatabase;

    private function generator(): InvoiceGeneratorService
    {
        return $this->app->make(InvoiceGeneratorService::class);
    }

    private function customer(array $attributes = []): Customer
    {
        return Customer::factory()->for(Package::factory()->create(['name' => 'Home 20', 'price' => 300000]))->create([
            'billing_cycle' => BillingCycle::Fixed,
            'billing_day' => 1,
            'due_days' => 7,
            'installed_at' => '2026-01-10',
            ...$attributes,
        ]);
    }

    /**
     * @return array<string, array{int, string, string, string}>
     */
    public static function cycles(): array
    {
        return [
            'on the billing day' => [5, '2026-11-05', '2026-11-05', '2026-12-04'],
            'after the billing day' => [5, '2026-11-20', '2026-11-05', '2026-12-04'],
            'before the billing day' => [5, '2026-11-03', '2026-10-05', '2026-11-04'],
            'january before billing day' => [10, '2026-01-02', '2025-12-10', '2026-01-09'],
            'billing day 28 in february' => [28, '2026-03-10', '2026-02-28', '2026-03-27'],
        ];
    }

    #[DataProvider('cycles')]
    public function test_cycle_starts_on_billing_day_and_ends_the_day_before_the_next(int $billingDay, string $date, string $start, string $end): void
    {
        [$cycleStart, $cycleEnd] = $this->generator()->cycleContaining($billingDay, CarbonImmutable::parse($date));

        $this->assertSame($start, $cycleStart->toDateString());
        $this->assertSame($end, $cycleEnd->toDateString());
    }

    public function test_full_cycle_invoice_for_established_customer(): void
    {
        $invoice = $this->generator()->generateFor($this->customer(), CarbonImmutable::parse('2026-11-01'));

        $this->assertSame('2026-11-01', $invoice->period_start->toDateString());
        $this->assertSame('2026-11-30', $invoice->period_end->toDateString());
        $this->assertSame('2026-11-08', $invoice->due_date->toDateString());
        $this->assertSame(300000, $invoice->total);
        $this->assertFalse($invoice->is_prorated);
        $this->assertSame(InvoiceItemType::Package, $invoice->items()->sole()->type);
        $this->assertSame('INV/2611/00001', $invoice->invoice_number);
    }

    public function test_first_invoice_is_prorated_from_install_date(): void
    {
        $customer = $this->customer(['installed_at' => '2026-11-16']);

        $invoice = $this->generator()->generateFor($customer, CarbonImmutable::parse('2026-11-16'));

        $this->assertTrue($invoice->is_prorated);
        $this->assertSame('2026-11-16', $invoice->period_start->toDateString());
        $this->assertSame('2026-11-30', $invoice->period_end->toDateString());
        $this->assertSame(15, $invoice->prorata_days);
        $this->assertSame(30, $invoice->period_days);
        $this->assertSame(150000, $invoice->total);
        $this->assertSame(InvoiceItemType::Prorata, $invoice->items()->sole()->type);
    }

    public function test_next_cycle_after_prorata_is_billed_in_full(): void
    {
        $customer = $this->customer(['installed_at' => '2026-11-16']);
        $this->generator()->generateFor($customer, CarbonImmutable::parse('2026-11-16'));

        $december = $this->generator()->generateFor($customer, CarbonImmutable::parse('2026-12-01'));

        $this->assertFalse($december->is_prorated);
        $this->assertSame(300000, $december->total);
        $this->assertSame(2, $customer->invoices()->count());
    }

    public function test_running_again_in_the_same_cycle_returns_the_existing_invoice(): void
    {
        $customer = $this->customer(['installed_at' => '2026-11-16']);

        $first = $this->generator()->generateFor($customer, CarbonImmutable::parse('2026-11-16'));
        $second = $this->generator()->generateFor($customer, CarbonImmutable::parse('2026-11-25'));

        $this->assertTrue($first->is($second));
        $this->assertSame(1, Invoice::count());
    }

    public function test_skips_customer_installed_in_the_future(): void
    {
        $this->assertNull($this->generator()->generateFor($this->customer(['installed_at' => '2026-12-05']), CarbonImmutable::parse('2026-11-20')));
    }

    public function test_skips_inactive_and_archived_customers(): void
    {
        $archived = $this->customer();
        $archived->delete();

        $this->assertNull($this->generator()->generateFor($this->customer(['status' => CustomerStatus::Inactive]), CarbonImmutable::parse('2026-11-01')));
        $this->assertNull($this->generator()->generateFor($archived, CarbonImmutable::parse('2026-11-01')));
        $this->assertSame(0, Invoice::count());
    }

    public function test_isolated_customers_keep_being_billed(): void
    {
        $this->assertNotNull($this->generator()->generateFor($this->customer(['status' => CustomerStatus::Isolated]), CarbonImmutable::parse('2026-11-01')));
    }

    public function test_invoice_numbers_increment_within_a_month(): void
    {
        $this->generator()->generateFor($this->customer(), CarbonImmutable::parse('2026-11-01'));
        $second = $this->generator()->generateFor($this->customer(), CarbonImmutable::parse('2026-11-01'));

        $this->assertSame('INV/2611/00002', $second->invoice_number);
    }

    public function test_queues_whatsapp_invoice_notification_with_portal_link(): void
    {
        $customer = $this->customer();

        $this->generator()->generateFor($customer, CarbonImmutable::parse('2026-11-01'));

        $message = $customer->whatsappMessages()->sole();
        $this->assertSame(WhatsappMessageType::Invoice, $message->type);
        $this->assertSame($customer->whatsapp, $message->phone);
        $this->assertStringContainsString('INV/2611/00001', $message->body);
        $this->assertStringContainsString('Rp 300.000', $message->body);
        $this->assertStringContainsString(route('isolir.show', $customer->customer_code), $message->body);
    }

    public function test_existing_invoice_does_not_trigger_another_notification(): void
    {
        $customer = $this->customer();

        $this->generator()->generateFor($customer, CarbonImmutable::parse('2026-11-01'));
        $this->generator()->generateFor($customer, CarbonImmutable::parse('2026-11-02'));

        $this->assertSame(1, $customer->whatsappMessages()->count());
    }

    public function test_the_invoice_message_explains_the_prorata_first_bill_and_the_next_bill(): void
    {
        $customer = $this->customer(['installed_at' => '2026-11-11', 'whatsapp' => '6281234567890']);

        $this->generator()->generateFor($customer, CarbonImmutable::parse('2026-11-11'));

        $first = WhatsappMessage::sole()->body;
        $this->assertStringContainsString('Paket: Home 20 (Rp 300.000/bulan)', $first);
        $this->assertStringContainsString('ditagih 20 dari 30 hari (prorata bulan pertama)', $first);
        $this->assertStringContainsString('Rp 300.000 × 20/30 = Rp 200.000', $first);
        $this->assertStringContainsString('Tagihan berikutnya terbit 01/12/2026', $first);
        $this->assertStringContainsString('biaya penuh Rp 300.000 per bulan', $first);
        $this->assertStringNotContainsString('Tunggakan', $first);

        WhatsappMessage::query()->delete();
        $this->generator()->generateFor($customer->fresh(), CarbonImmutable::parse('2026-12-01'));

        $next = WhatsappMessage::sole()->body;
        $this->assertStringContainsString('Periode: 01/12/2026 s.d. 31/12/2026', $next);
        $this->assertStringContainsString('Biaya bulan ini: Rp 300.000', $next);
        $this->assertStringContainsString('Tunggakan sebelumnya: Rp 200.000 (1 tagihan)', $next);
        $this->assertStringContainsString('Tagihan berikutnya terbit 01/01/2027', $next);
        $this->assertStringNotContainsString('prorata', $next);
    }
}
