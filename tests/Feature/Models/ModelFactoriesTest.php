<?php

namespace Tests\Feature\Models;

use App\Models\Asset;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\HotspotProfile;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Odp;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentCallback;
use App\Models\PaymentGateway;
use App\Models\Reseller;
use App\Models\Router;
use App\Models\Setting;
use App\Models\Voucher;
use App\Models\VoucherBatch;
use App\Models\WhatsappMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ModelFactoriesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{class-string<Model>}>
     */
    public static function models(): array
    {
        return collect([
            Setting::class, Router::class, Odp::class, Package::class, Customer::class, Asset::class,
            Invoice::class, InvoiceItem::class, PaymentGateway::class, Payment::class, PaymentCallback::class,
            Reseller::class, HotspotProfile::class, VoucherBatch::class, Voucher::class,
            ExpenseCategory::class, Expense::class, WhatsappMessage::class,
        ])->mapWithKeys(fn (string $model): array => [class_basename($model) => [$model]])->all();
    }

    /**
     * @param  class-string<Model>  $model
     */
    #[DataProvider('models')]
    public function test_factory_creates_a_persisted_record(string $model): void
    {
        $record = $model::factory()->create();

        $this->assertModelExists($record);
    }

    public function test_voucher_belongs_to_the_router_and_profile_of_its_batch(): void
    {
        $voucher = Voucher::factory()->create();

        $this->assertSame($voucher->voucherBatch->router_id, $voucher->router_id);
        $this->assertSame($voucher->voucherBatch->hotspot_profile_id, $voucher->hotspot_profile_id);
    }
}
