<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class AuditLogControllerTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    public function test_lists_newest_activity_first_with_actor_name_and_changes(): void
    {
        $owner = $this->owner();
        $package = Package::factory()->create(['price' => 100000]);
        $this->actingAs($owner);
        $package->update(['price' => 120000]);

        $this->get('/audit-log')->assertInertia(
            fn (Assert $page) => $page
                ->component('AuditLog/Index')
                ->where('activities.data.0.description', 'Paket diubah')
                ->where('activities.data.0.causer', $owner->name)
                ->where('activities.data.0.subject', "Package #{$package->id}")
                ->where('activities.data.0.changes.attributes.price', 120000)
                ->where('activities.data.0.changes.old.price', 100000),
        );
    }

    public function test_changes_without_a_user_are_attributed_to_the_system(): void
    {
        Package::factory()->create();

        $this->actingAs($this->owner())->get('/audit-log')->assertInertia(fn (Assert $page) => $page->where('activities.data.0.causer', 'Sistem'));
    }

    public function test_filters_by_log_name_and_description(): void
    {
        Package::factory()->create();
        activity('Pengaturan')->log('Pengaturan diubah: company.name');
        $owner = $this->owner();

        $this->actingAs($owner)->get('/audit-log?log=Pengaturan')->assertInertia(fn (Assert $page) => $page->has('activities.data', 1)->where('activities.data.0.log_name', 'Pengaturan'));
        $this->actingAs($owner)->get('/audit-log?search=company')->assertInertia(fn (Assert $page) => $page->has('activities.data', 1));
        $this->actingAs($owner)->get('/audit-log')->assertInertia(fn (Assert $page) => $page->has('logNames'));
    }

    public function test_paginates_at_25_rows(): void
    {
        $owner = $this->owner();
        Activity::query()->delete();

        foreach (range(1, 30) as $i) {
            activity('Uji')->log("Aktivitas {$i}");
        }

        $this->actingAs($owner)->get('/audit-log')->assertInertia(fn (Assert $page) => $page->has('activities.data', 25)->where('activities.total', 30));
        $this->assertSame(30, Activity::count());
    }
}
