<?php

namespace Tests\Feature;

use App\Helpers\TableColumnFilters;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Location;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TableColumnFiltersTest extends TestCase
{
    private function request(array $filters = [], array $extras = []): Request
    {
        return Request::create('/', 'GET', array_merge(['column_filters' => json_encode($filters)], $extras));
    }

    public function test_multiple_values_are_combined_with_or_and_columns_with_and(): void
    {
        $first = AssetModel::factory()->create(['name' => 'Laptop']);
        $second = AssetModel::factory()->create(['name' => 'Monitor']);
        $location = Location::factory()->create(['name' => 'Pretoria']);
        $other = Location::factory()->create(['name' => 'Cape Town']);
        $a = Asset::factory()->create(['model_id' => $first->id, 'location_id' => $location->id]);
        $b = Asset::factory()->create(['model_id' => $second->id, 'location_id' => $location->id]);
        Asset::factory()->create(['model_id' => $first->id, 'location_id' => $other->id]);
        $query = Asset::query();
        TableColumnFilters::handle($query, $this->request([
            'model' => ['op' => 'in', 'values' => ['Laptop', 'Monitor']],
            'location' => ['op' => 'in', 'values' => ['Pretoria']],
        ]));
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $query->pluck('assets.id')->all());
    }

    public function test_facets_keep_query_scope_and_other_filters_but_omit_their_own_filter(): void
    {
        $place = Location::factory()->create(['name' => 'Pretoria']);
        $visible = Asset::factory()->create(['name' => 'Laptop', 'location_id' => $place->id]);
        $alsoVisible = Asset::factory()->create(['name' => 'Monitor', 'location_id' => $place->id]);
        Asset::factory()->create(['name' => 'Secret', 'location_id' => $place->id]);
        $query = Asset::whereIn('assets.id', [$visible->id, $alsoVisible->id]);
        $result = TableColumnFilters::handle($query, $this->request([
            'name' => ['op' => 'in', 'values' => ['Laptop']],
            'location' => ['op' => 'in', 'values' => ['Pretoria']],
        ], ['column_filter_field' => 'name', 'column_filter_search' => null, 'limit' => 1]));
        $this->assertSame(['Laptop', 'Monitor'], $result['values']);
    }

    public function test_filters_are_applied_before_api_pagination(): void
    {
        Asset::factory()->count(22)->create(['name' => 'Unrelated']);
        $wanted = Asset::factory()->create(['name' => 'Wanted']);
        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(route('api.assets.index', ['limit' => 1, 'column_filters' => json_encode(['name' => ['op' => 'in', 'values' => ['Wanted']]])]))
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('rows.0.id', $wanted->id);
    }

    public function test_empty_values_include_both_null_and_empty_strings(): void
    {
        $a = Asset::factory()->create(['serial' => null]);
        $b = Asset::factory()->create(['serial' => '']);
        $c = Asset::factory()->create(['serial' => '0']);
        $query = Asset::whereIn('assets.id', [$a->id, $b->id, $c->id]);
        TableColumnFilters::handle($query, $this->request(['serial' => ['op' => 'in', 'values' => ['']]]));
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $query->pluck('assets.id')->all());
    }

    public function test_numeric_comparison_is_numeric_and_not_lexicographic(): void
    {
        $wanted = Asset::factory()->create(['purchase_cost' => 100]);
        $other = Asset::factory()->create(['purchase_cost' => 20]);
        $query = Asset::whereIn('assets.id', [$wanted->id, $other->id]);
        TableColumnFilters::handle($query, $this->request(['purchase_cost' => ['op' => 'gt', 'value' => '90']]));
        $this->assertSame([$wanted->id], $query->pluck('assets.id')->all());
    }

    public function test_contains_treats_percent_and_underscore_as_literal_characters(): void
    {
        $wanted = Asset::factory()->create(['serial' => '50%_off']);
        Asset::factory()->create(['serial' => '50xxoff']);
        $query = Asset::query();
        TableColumnFilters::handle($query, $this->request(['serial' => ['op' => 'contains', 'value' => '%_']]));
        $this->assertSame([$wanted->id], $query->pluck('assets.id')->all());
    }

    public function test_column_and_operator_names_are_whitelisted(): void
    {
        $this->expectException(ValidationException::class);
        TableColumnFilters::handle(Asset::query(), $this->request(['password OR 1=1' => ['op' => 'in', 'values' => ['x']]]));
    }

    public function test_license_keys_are_not_exposed_as_facets(): void
    {
        $columns = TableColumnFilters::columns(\App\Models\License::query());
        $this->assertArrayNotHasKey('serial', $columns);
        $this->assertArrayNotHasKey('password', TableColumnFilters::columns(User::query()));
    }

    public function test_facet_endpoint_requires_listing_permission(): void
    {
        $this->actingAsForApi(User::factory()->create())
            ->getJson(route('api.assets.index', ['column_filter_field' => 'model']))->assertForbidden();
    }

    public function test_deployed_status_and_display_name_match_the_table(): void
    {
        $model = AssetModel::factory()->create(['name' => 'Workstation']);
        $asset = Asset::factory()->create(['name' => '', 'model_id' => $model->id, 'assigned_to' => User::factory()->create()->id, 'assigned_type' => User::class]);
        $query = Asset::query();
        TableColumnFilters::handle($query, $this->request(['name' => ['op' => 'in', 'values' => ['Workstation']], 'status' => ['op' => 'in', 'values' => ['Deployed']]]));
        $this->assertSame([$asset->id], $query->pluck('assets.id')->all());
    }
    public function test_all_exposed_columns_have_valid_database_expressions(): void
    {
        foreach (['Asset', 'License', 'Accessory', 'Consumable', 'Component', 'User', 'Maintenance', 'AssetModel', 'Location', 'Company', 'Department', 'Category', 'Manufacturer', 'Supplier', 'Statuslabel', 'PredefinedKit', 'Depreciation', 'Group'] as $name) {
            $class = 'App\\Models\\'.$name;
            foreach (TableColumnFilters::columns($class::query()) as $field => $column) {
                $response = TableColumnFilters::handle($class::query(), $this->request([], ['column_filter_field' => $field]));
                $this->assertIsArray($response['values'], $name.'.'.$field);
            }
        }
    }

    public function test_collection_queues_filter_before_paginating_and_preserve_scope(): void
    {
        $rows = [
            ['user' => ['name' => 'Lethabo Maboe'], 'quantity' => 2],
            ['user' => ['name' => 'Dan Baloyi'], 'quantity' => 5],
            ['user' => ['name' => 'Dan Baloyi'], 'quantity' => 1],
        ];
        $result = TableColumnFilters::handleRows($rows, ['user' => 'text', 'quantity' => 'number'], $this->request([
            'user' => ['op' => 'in', 'values' => ['Dan Baloyi']],
            'quantity' => ['op' => 'gt', 'value' => '2'],
        ], ['limit' => 1]));
        $this->assertSame(1, $result['total']);
        $this->assertSame(5, $result['rows'][0]['quantity']);
        $facet = TableColumnFilters::handleRows($rows, ['user' => 'text', 'quantity' => 'number'], $this->request([
            'user' => ['op' => 'in', 'values' => ['Dan Baloyi']],
        ], ['column_filter_field' => 'quantity']));
        $this->assertSame(['1', '5'], $facet['values']);
    }

    public function test_facets_are_bounded_and_can_be_searched_beyond_the_first_batch(): void
    {
        $assets = Asset::factory()->count(105)->sequence(fn ($sequence) => ['serial' => 'Filter-test-'.str_pad((string) $sequence->index, 3, '0', STR_PAD_LEFT)])->create();
        $query = Asset::whereIn('assets.id', $assets->modelKeys());
        $first = TableColumnFilters::handle(clone $query, $this->request([], ['column_filter_field' => 'serial']));
        $this->assertCount(100, $first['values']);
        $this->assertTrue($first['more']);
        $next = TableColumnFilters::handle(clone $query, $this->request([], ['column_filter_field' => 'serial', 'column_filter_offset' => 100]));
        $this->assertCount(5, $next['values']);
        $search = TableColumnFilters::handle(clone $query, $this->request([], ['column_filter_field' => 'serial', 'column_filter_search' => '104']));
        $this->assertSame(['Filter-test-104'], $search['values']);
    }

    public function test_available_seat_filters_match_the_displayed_value_for_non_reassignable_licenses(): void
    {
        $license = \App\Models\License::factory()->create(['seats' => 5, 'reassignable' => false]);
        \App\Models\LicenseSeat::factory()->count(3)->create(['license_id' => $license->id, 'assigned_to' => null, 'asset_id' => null]);
        \App\Models\LicenseSeat::factory()->create(['license_id' => $license->id, 'assigned_to' => null, 'asset_id' => null, 'unreassignable_seat' => true]);
        $license->loadCount(['freeSeats as free_seats_count', 'licenseseats as unreassignable_seats_count' => fn ($q) => $q->where('unreassignable_seat', true)]);
        $displayed = (new \App\Http\Transformers\LicensesTransformer)->transformLicense($license)['free_seats_count'];
        $query = \App\Models\License::whereKey($license->id);
        TableColumnFilters::handle($query, $this->request(['free_seats_count' => ['op' => 'eq', 'value' => (string) $displayed]]));
        $this->assertSame(1, $query->count());
    }

}
