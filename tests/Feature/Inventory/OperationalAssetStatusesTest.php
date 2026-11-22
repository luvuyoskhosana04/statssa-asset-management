<?php

namespace Tests\Feature\Inventory;

use App\Helpers\TableColumnFilters;
use App\Models\Asset;
use App\Models\Location;
use App\Models\Statuslabel;
use App\Models\User;
use App\Support\OperationalAssetStatuses;
use Database\Seeders\OperationalAssetStatusSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationalAssetStatusesTest extends TestCase
{
    public function test_catalogue_restoration_preserves_assets_defaults_and_existing_labels_and_can_be_repeated(): void
    {
        $existing = Statuslabel::factory()->create(['name' => 'Existing Default Status', 'default_label' => 1]);
        $asset = Asset::factory()->create(['status_id' => $existing->id]);
        $this->seed(OperationalAssetStatusSeeder::class);
        $count = Statuslabel::count();
        $this->seed(OperationalAssetStatusSeeder::class);
        $this->assertSame($count, Statuslabel::count());
        $this->assertSame($existing->id, $asset->fresh()->status_id);
        $this->assertSame(1, (int) $existing->fresh()->default_label);
        $labels = Statuslabel::whereIn('name', OperationalAssetStatuses::names())->get();
        $this->assertCount(10, $labels);
        $this->assertSame(4, $labels->where('deployable', 1)->count());
        $this->assertSame(5, $labels->where('pending', 1)->count());
        $this->assertSame(10, $labels->where('show_in_nav', 1)->count());
    }

    public function test_status_filter_includes_unused_workflow_labels_and_respects_search(): void
    {
        $this->seed(OperationalAssetStatusSeeder::class);
        $result = TableColumnFilters::handle(Asset::query(), Request::create('/', 'GET', ['column_filter_field' => 'status']));
        $this->assertSame([], array_values(array_diff(OperationalAssetStatuses::names(), $result['values'])));
        $filtered = TableColumnFilters::handle(Asset::query(), Request::create('/', 'GET', ['column_filter_field' => 'status', 'column_filter_search' => 'Inspection']));
        $this->assertSame(['Awaiting Inspection'], $filtered['values']);
    }

    public function test_assigned_operational_assets_filter_by_their_actual_status(): void
    {
        $this->seed(OperationalAssetStatusSeeder::class);
        $transit = Statuslabel::where('name', 'In Transit')->firstOrFail();
        $asset = Asset::factory()->create(['status_id' => $transit->id, 'assigned_to' => Location::factory()->create()->id, 'assigned_type' => Location::class]);
        $query = Asset::query();
        TableColumnFilters::handle($query, Request::create('/', 'GET', ['column_filters' => json_encode(['status' => ['op' => 'in', 'values' => ['In Transit']]])]));
        $this->assertSame([$asset->id], $query->pluck('assets.id')->all());
        $query = Asset::query();
        TableColumnFilters::handle($query, Request::create('/', 'GET', ['column_filters' => json_encode(['status' => ['op' => 'in', 'values' => ['Deployed']]])]));
        $this->assertSame([], $query->pluck('assets.id')->all());
    }

    public function test_dispatch_reuses_the_restored_in_transit_label_without_moving_the_asset(): void
    {
        $this->seed(OperationalAssetStatusSeeder::class);
        $source = Location::factory()->create();
        $destination = Location::factory()->create();
        $asset = Asset::factory()->create(['location_id' => $source->id, 'rtd_location_id' => $source->id]);
        $this->actingAs(User::factory()->superuser()->create())->postJson(route('hardware.bulkcheckout.session'), [
            'mode' => 'location', 'target_id' => $destination->id, 'source_location_id' => $source->id,
            'source_type' => 'any', 'items' => [['kind' => 'asset', 'id' => $asset->id, 'quantity' => 1]],
            'note' => 'Dispatch test', 'idempotency_key' => (string) Str::uuid(), 'reviewed' => true,
        ])->assertOk()->assertJsonPath('status', 'success');
        $this->assertSame('In Transit', $asset->fresh()->status->name);
        $this->assertSame($source->id, $asset->fresh()->location_id);
        $this->assertSame($destination->id, $asset->fresh()->assigned_to);
        $this->assertSame(1, Statuslabel::where('name', 'In Transit')->count());
        $this->assertFalse($asset->fresh()->availableForCheckout());
    }
}
