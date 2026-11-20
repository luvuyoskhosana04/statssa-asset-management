<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Company;
use App\Models\Location;
use App\Models\Statuslabel;
use App\Models\User;
use Tests\TestCase;

class DashboardInsightsTest extends TestCase
{
    public function test_requires_asset_or_user_view_permission(): void
    {
        $this->actingAsForApi(User::factory()->viewLicenses()->create())->getJson(route('api.dashboard.insights'))->assertForbidden();
    }

    public function test_return_counts_use_fourteen_calendar_days_and_separate_overdue_records(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 20)->setTime(14, 0));
        $status = Statuslabel::factory()->readyToDeploy()->create();
        foreach ([today(), today()->addDays(13)->endOfDay(), today()->addDays(14), today()->subDay()] as $date) {
            Asset::factory()->assignedToUser()->create(['status_id' => $status->id, 'expected_checkin' => $date, 'warranty_months' => 0]);
        }
        Asset::factory()->create(['status_id' => $status->id, 'expected_checkin' => today(), 'warranty_months' => 0]);
        Asset::factory()->assignedToUser()->create(['status_id' => Statuslabel::factory()->archived()->create()->id, 'expected_checkin' => today(), 'warranty_months' => 0]);
        $this->actingAsForApi(User::factory()->viewAssets()->create())->getJson(route('api.dashboard.insights'))
            ->assertOk()->assertJsonPath('payload.start', '2026-11-20')->assertJsonPath('payload.end', '2026-12-03')
            ->assertJsonPath('payload.assets.due', 2)->assertJsonPath('payload.assets.overdue', 1)
            ->assertJsonPath('payload.assets.total', 5)->assertJsonPath('payload.assets.deployed', 4);
    }

    public function test_category_counts_and_deployable_counts_exclude_archived_deleted_and_assigned_assets(): void
    {
        $category = Category::factory()->create();
        $model = AssetModel::factory()->create(['category_id' => $category->id]);
        $ready = Statuslabel::factory()->readyToDeploy()->create();
        $attributes = ['model_id' => $model->id, 'status_id' => $ready->id, 'warranty_months' => 0];
        Asset::factory()->create($attributes);
        Asset::factory()->assignedToUser()->create($attributes);
        Asset::factory()->create(array_merge($attributes, ['status_id' => Statuslabel::factory()->pending()->create()->id]));
        Asset::factory()->create(array_merge($attributes, ['status_id' => Statuslabel::factory()->archived()->create()->id]));
        Asset::factory()->create($attributes)->delete();
        $response = $this->actingAsForApi(User::factory()->viewAssets()->create())->getJson(route('api.dashboard.insights'))->assertOk();
        $response->assertJsonPath('payload.assets.categories.0.count', 3)->assertJsonPath('payload.assets.categories.0.available', 1);
        $this->assertSame(3, array_sum(array_column($response->json('payload.assets.statuses'), 'count')));
        $this->assertArrayNotHasKey('department', $response->json('payload.assets.costs'));
        $response->assertJsonPath('payload.offboarding', null);
    }

    public function test_costs_keep_currencies_separate_without_double_counting_order_history(): void
    {
        $ready = Statuslabel::factory()->readyToDeploy()->create();
        foreach (['ZAR' => 1500, 'EUR' => 100] as $currency => $amount) {
            $location = Location::factory()->create(['currency' => $currency]);
            $asset = Asset::factory()->create(['status_id' => $ready->id, 'location_id' => $location->id, 'purchase_cost' => $amount, 'warranty_months' => 0]);
            $asset->orderItems()->firstOrFail()->replicate()->save();
        }
        Asset::factory()->create(['status_id' => $ready->id, 'purchase_cost' => null, 'warranty_months' => 0]);
        $response = $this->actingAsForApi(User::factory()->viewAssets()->create())->getJson(route('api.dashboard.insights'))->assertOk()->assertJsonPath('payload.assets.total', 3);
        $totals = collect($response->json('payload.assets.costs.totals'))->keyBy('currency');
        $this->assertEquals(1500, $totals['ZAR']['amount']);
        $this->assertEquals(100, $totals['EUR']['amount']);
        $this->assertSame(1, $totals['ZAR']['priced']);
        $this->assertSame(2, $totals->sum('priced'));
    }

    public function test_purchase_timeline_uses_latest_acquisition_dates_without_double_counting(): void
    {
        $asset = Asset::factory()->create(['purchase_date' => '2026-01-05', 'purchase_cost' => 100]);
        $line = $asset->orderItems()->firstOrFail();
        $line->order->update(['purchase_date' => '2026-03-08', 'currency' => 'ZAR']);
        $line->update(['price' => 125]);
        $line->replicate()->save();
        $response = $this->actingAsForApi(User::factory()->viewAssets()->create())->getJson(route('api.dashboard.insights'))->assertOk();
        $rows = collect($response->json('payload.assets.costs.timeline'))->where('currency', 'ZAR')->values();
        $this->assertCount(1, $rows);
        $this->assertSame('2026-03-01', $rows[0]['period']);
        $this->assertEquals(125, $rows[0]['amount']);
        $this->assertSame(1, $rows[0]['priced']);
        $this->assertEquals(125, collect($response->json('payload.assets.costs.totals'))->firstWhere('currency', 'ZAR')['amount']);
    }

    public function test_purchase_timeline_keeps_undated_costs_and_uses_asset_date_as_fallback(): void
    {
        foreach ([['date' => '2026-01-31', 'cost' => 10], ['date' => null, 'cost' => 20]] as $row) {
            $asset = Asset::factory()->create(['purchase_date' => $row['date'], 'purchase_cost' => $row['cost']]);
            $asset->orderItems()->firstOrFail()->order->update(['purchase_date' => null, 'currency' => 'ZAR']);
        }
        $archived = Asset::factory()->create(['status_id' => Statuslabel::factory()->archived()->create()->id, 'purchase_cost' => 999]);
        $archived->orderItems()->firstOrFail()->order->update(['currency' => 'ZAR']);
        $response = $this->actingAsForApi(User::factory()->viewAssets()->create())->getJson(route('api.dashboard.insights'))->assertOk();
        $rows = collect($response->json('payload.assets.costs.timeline'))->where('currency', 'ZAR');
        $this->assertEquals(10, $rows->firstWhere('period', '2026-01-01')['amount']);
        $this->assertEquals(20, $rows->firstWhere('period', null)['amount']);
        $this->assertEquals(30, $rows->sum('amount'));
        $this->assertSame(2, $rows->sum('priced'));
    }

    public function test_warranty_expiry_matches_the_existing_asset_accessor(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 20)->setTime(14, 0));
        $ready = Statuslabel::factory()->readyToDeploy()->create();
        foreach (['2025-11-20', '2025-12-03', '2025-12-04', '2025-11-19'] as $date) {
            Asset::factory()->create(['status_id' => $ready->id, 'purchase_date' => $date, 'warranty_months' => 12]);
        }
        $this->actingAsForApi(User::factory()->viewAssets()->create())->getJson(route('api.dashboard.insights'))
            ->assertOk()->assertJsonPath('payload.assets.warranties', 2);
    }

    public function test_user_viewer_only_receives_offboarding_insights(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 20)->setTime(14, 0));
        User::factory()->create(['end_date' => today()->addDays(3)->toDateString()]);
        $this->actingAsForApi(User::factory()->viewUsers()->create())->getJson(route('api.dashboard.insights'))
            ->assertOk()->assertJsonPath('payload.assets', null)->assertJsonPath('payload.offboarding', 1);
    }

    public function test_insights_respect_company_scope(): void
    {
        $this->settings->enableMultipleFullCompanySupport();
        [$own, $other] = Company::factory()->count(2)->create();
        $ready = Statuslabel::factory()->readyToDeploy()->create();
        foreach ([$own, $other] as $company) {
            Asset::factory()->create(['company_id' => $company->id, 'status_id' => $ready->id, 'warranty_months' => 0]);
        }
        $this->actingAsForApi(User::factory()->viewAssets()->forCompany($own->id)->create())->getJson(route('api.dashboard.insights'))
            ->assertOk()->assertJsonPath('payload.assets.total', 1)
            ->assertJsonCount(1, 'payload.assets.costs.timeline');
    }

    public function test_empty_inventory_uses_zero_counts_and_empty_comparisons(): void
    {
        $this->actingAsForApi(User::factory()->viewAssets()->create())->getJson(route('api.dashboard.insights'))
            ->assertOk()->assertJsonPath('payload.assets.total', 0)->assertJsonPath('payload.assets.due', 0)
            ->assertJsonPath('payload.assets.categories', [])->assertJsonPath('payload.assets.costs.totals', [])->assertJsonPath('payload.assets.costs.timeline', []);
    }
}
