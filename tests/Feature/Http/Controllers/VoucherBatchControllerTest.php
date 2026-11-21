<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\VoucherBatchStatus;
use App\Enums\VoucherStatus;
use App\Jobs\Voucher\GenerateVoucherBatchJob;
use App\Models\HotspotProfile;
use App\Models\Reseller;
use App\Models\Setting;
use App\Models\Voucher;
use App\Models\VoucherBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class VoucherBatchControllerTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function validPayload(HotspotProfile $profile, array $overrides = []): array
    {
        return [
            'hotspot_profile_id' => $profile->id,
            'quantity' => 50,
            'prefix' => 'wf',
            'code_length' => 6,
            'charset' => 'alphanumeric',
            ...$overrides,
        ];
    }

    public function test_lists_batches_with_used_voucher_count(): void
    {
        $batch = VoucherBatch::factory()->create();
        Voucher::factory()->for($batch)->create(['status' => VoucherStatus::Unused]);
        Voucher::factory()->for($batch)->create(['status' => VoucherStatus::Active]);

        $this->actingAs($this->admin())->get('/hotspot/batches')->assertInertia(
            fn (Assert $page) => $page->component('Hotspot/Batches/Index')->where('batches.data.0.vouchers_count', 2)->where('batches.data.0.used_count', 1),
        );
    }

    public function test_create_form_offers_only_active_profiles_and_resellers(): void
    {
        HotspotProfile::factory()->create(['is_active' => true]);
        HotspotProfile::factory()->create(['is_active' => false]);
        Reseller::factory()->create(['is_active' => false]);

        $this->actingAs($this->admin())->get('/hotspot/batches/create')->assertInertia(
            fn (Assert $page) => $page->has('profiles', 1)->has('resellers', 0)->has('charsets', 3),
        );
    }

    public function test_store_queues_generation_and_redirects_to_the_batch(): void
    {
        Queue::fake();
        $profile = HotspotProfile::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->post('/hotspot/batches', $this->validPayload($profile))->assertRedirect();

        $batch = VoucherBatch::sole();
        $this->assertSame(VoucherBatchStatus::Queued, $batch->status);
        $this->assertSame($profile->router_id, $batch->router_id);
        $this->assertSame($admin->id, $batch->created_by);
        $this->assertMatchesRegularExpression('/^VC-\d{6}-[A-Z0-9]{4}$/', $batch->batch_code);
        Queue::assertPushed(GenerateVoucherBatchJob::class, fn (GenerateVoucherBatchJob $job): bool => $job->voucherBatch->is($batch));
    }

    public function test_store_rejects_inactive_profile_and_out_of_range_quantity(): void
    {
        Queue::fake();
        $inactive = HotspotProfile::factory()->create(['is_active' => false]);

        $this->actingAs($this->admin())->post('/hotspot/batches', $this->validPayload($inactive, ['quantity' => 5000, 'charset' => 'emoji']))
            ->assertSessionHasErrors(['hotspot_profile_id', 'quantity', 'charset']);

        Queue::assertNothingPushed();
    }

    public function test_show_reports_progress_and_status_counts(): void
    {
        $batch = VoucherBatch::factory()->create(['quantity' => 3, 'generated_count' => 3]);
        Voucher::factory()->count(2)->for($batch)->create(['status' => VoucherStatus::Unused]);
        Voucher::factory()->for($batch)->create(['status' => VoucherStatus::Active]);

        $this->actingAs($this->admin())->get("/hotspot/batches/{$batch->id}?status=unused")->assertInertia(
            fn (Assert $page) => $page
                ->component('Hotspot/Batches/Show')
                ->where('stats.unused', 2)
                ->where('stats.active', 1)
                ->has('vouchers.data', 2),
        );
    }

    public function test_print_page_embeds_login_url_from_settings(): void
    {
        $batch = VoucherBatch::factory()->create();
        Voucher::factory()->count(2)->for($batch)->create();
        Setting::create(['group' => 'hotspot', 'key' => 'login_url', 'value' => 'http://wifi.local/login']);

        $this->actingAs($this->admin())->get("/hotspot/batches/{$batch->id}/print?layout=thermal58")->assertInertia(
            fn (Assert $page) => $page->component('Print/Vouchers')->where('loginUrl', 'http://wifi.local/login')->has('vouchers', 2)->has('vouchers.0.code'),
        );
    }

    public function test_print_page_falls_back_to_router_host_for_login_url(): void
    {
        $batch = VoucherBatch::factory()->create();
        $batch->router->update(['host' => '10.9.9.1']);

        $this->actingAs($this->admin())->get("/hotspot/batches/{$batch->id}/print")->assertInertia(
            fn (Assert $page) => $page->where('loginUrl', 'http://10.9.9.1/login'),
        );
    }
}
