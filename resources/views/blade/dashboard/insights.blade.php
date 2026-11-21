@props(['section' => 'charts'])
@if (Gate::allows('view', \App\Models\Asset::class) || Gate::allows('view', \App\Models\User::class))
@if ($section === 'summary')
<div id="dashboard-insights" data-url="{{ route('api.dashboard.insights') }}" data-loading="{{ trans('general.loading') }}" data-asset-url="{{ route('hardware.index') }}" data-ready="{{ trans('general.ready_to_deploy') }}" data-deployed="{{ trans('general.deployed') }}" data-pending="{{ trans('general.pending') }}"
     data-copy="{{ json_encode(collect(['chart_cost_all_years', 'chart_cost_coverage_count', 'chart_active_assets', 'chart_cost_composition', 'chart_status_total', 'chart_cost_calendar_context', 'chart_empty', 'chart_error', 'chart_show_all', 'chart_show_less', 'chart_overdue', 'chart_period', 'chart_checked_out', 'chart_priced', 'chart_unknown_costs', 'chart_cost_timeline', 'chart_cost_monthly', 'chart_cost_quarterly', 'chart_cost_yearly', 'chart_cost_multi_year', 'chart_cost_quarter', 'chart_cost_undated', 'chart_cost_no_dates', 'chart_cost_period_detail', 'chart_cost_undated_label'])->mapWithKeys(fn ($key) => [$key => trans('presentation.'.$key)])->all()) }}">
    <p id="insights-message" role="status" aria-live="polite">{{ trans('general.loading') }}</p>
    <button type="button" id="insights-retry" class="btn btn-default" hidden>{{ trans('presentation.chart_retry') }}</button>
    <div class="row insight-summary-row">
        @can('view', \App\Models\Asset::class)
        <div class="{{ Gate::allows('view', \App\Models\User::class) ? 'col-sm-4' : 'col-sm-6' }}">
            <section class="box box-default insight-summary"><div class="box-header with-border"><h2 class="box-title">{{ trans('presentation.chart_due_returns') }}</h2></div><div class="box-body"><strong data-insight-value="due" class="insight-metric">...</strong><p>{{ trans('presentation.chart_next_days') }}</p><p class="insight-period"></p><a id="insights-overdue" href="{{ route('hardware.index', ['status_type' => 'Deployed', 'column_filters' => json_encode(['expected_checkin' => ['op' => 'lt', 'value' => today()->toDateString()]])]) }}"></a></div><div class="box-footer"><a href="{{ route('calendar.index', ['filter' => 'asset.expected_checkin', 'date' => today()->toDateString()]) }}">{{ trans('presentation.chart_view_returns') }}</a></div></section>
        </div>
        <div class="{{ Gate::allows('view', \App\Models\User::class) ? 'col-sm-4' : 'col-sm-6' }}">
            <section class="box box-default insight-summary"><div class="box-header with-border"><h2 class="box-title">{{ trans('presentation.chart_warranties') }}</h2></div><div class="box-body"><strong data-insight-value="warranties" class="insight-metric">...</strong><p>{{ trans('presentation.chart_next_days') }}</p><p class="insight-period"></p></div><div class="box-footer"><a href="{{ route('calendar.index', ['filter' => 'asset.warranty_expiration', 'date' => today()->toDateString()]) }}">{{ trans('presentation.chart_view_warranties') }}</a></div></section>
        </div>
        @endcan
        @can('view', \App\Models\User::class)
        <div class="{{ Gate::allows('view', \App\Models\Asset::class) ? 'col-sm-4' : 'col-sm-12' }}">
            <section class="box box-default insight-summary"><div class="box-header with-border"><h2 class="box-title">{{ trans('presentation.chart_offboarding') }}</h2></div><div class="box-body"><strong data-insight-value="offboarding" class="insight-metric">...</strong><p>{{ trans('presentation.chart_next_days') }}</p><p class="insight-period"></p></div><div class="box-footer"><a href="{{ route('calendar.index', ['filter' => 'user.end_date', 'date' => today()->toDateString()]) }}">{{ trans('presentation.chart_view_offboarding') }}</a></div></section>
        </div>
        @endcan
    </div>
</div>
@else
@can('view', \App\Models\Asset::class)
<div class="row dashboard-row-eq insight-chart-grid">
    <div class="col-md-12"><x-dashboard.assets-by-status /></div>
    <div class="col-md-6"><x-dashboard.comparison-chart chart="categories" :title="trans('presentation.chart_categories')" /></div>
    <div class="col-md-6"><x-dashboard.comparison-chart chart="locations" :title="trans('presentation.chart_locations')" /></div>
    <div class="col-md-6">
        <section class="box box-default"><div class="box-header with-border"><h2 class="box-title">{{ trans('presentation.chart_deployable') }}</h2></div><div class="box-body"><p class="help-block">{{ trans('presentation.chart_scope') }}</p><div class="table-responsive"><table class="table insight-deployable"><thead><tr><th>{{ trans('general.category') }}</th><th>{{ trans('presentation.chart_available') }}</th><th>{{ trans('presentation.chart_total') }}</th><th>{{ trans('presentation.chart_share') }}</th></tr></thead><tbody id="insight-deployable-rows"></tbody></table></div></div></section>
    </div>
    <div class="col-md-6">
        <section class="box box-default insight-utilisation"><div class="box-header with-border"><h2 class="box-title">{{ trans('presentation.chart_utilisation') }}</h2></div><div class="box-body"><strong class="insight-metric" id="insight-utilisation-value">...</strong><p id="insight-utilisation-context"></p><progress id="insight-utilisation-meter" max="100" value="0" aria-label="{{ trans('presentation.chart_utilisation') }}"></progress><p class="help-block">{{ trans('presentation.chart_scope') }}</p></div></section>
    </div>
    <div class="col-md-12">
        <section class="box box-default insight-cost-summary"><div class="box-header with-border cost-overview-header"><h2 class="box-title">{{ trans('presentation.chart_purchase') }}</h2><div class="cost-currency"><label for="insight-currency">{{ trans('presentation.chart_currency') }}</label><select id="insight-currency" class="form-control"></select></div></div>
            <div class="box-body">
                <div class="purchase-value-hero">
                    <div class="purchase-value-lead"><span class="purchase-value-eyebrow">{{ trans('presentation.chart_cost_recorded_value') }}</span><strong id="insight-cost-total" class="purchase-value-total">...</strong><p id="insight-priced-count"></p></div>
                    <div class="purchase-value-facts"><div><span>{{ trans('presentation.chart_cost_average') }}</span><strong id="purchase-average-value">...</strong></div><div><span>{{ trans('presentation.chart_cost_latest_month') }}</span><strong id="purchase-latest-value">...</strong></div></div>
                </div>
                <div class="purchase-cost-coverage"><div class="purchase-coverage-score"><strong id="purchase-coverage-value">...</strong><span>{{ trans('presentation.chart_cost_coverage') }}</span></div><div><p id="purchase-coverage-count"></p><p id="insight-unknown-costs"></p></div><span class="purchase-coverage-scope">{{ trans('presentation.chart_cost_all_currencies') }}</span></div>
                <div class="purchase-ledger-header"><div><h3>{{ trans('presentation.chart_cost_ledger') }}</h3><p>{{ trans('presentation.chart_cost_ledger_help') }}</p></div><div><label for="purchase-ledger-year" class="sr-only">{{ trans('presentation.chart_cost_year') }}</label><select id="purchase-ledger-year" class="form-control"></select></div></div>
                <ul id="purchase-ledger-list" class="purchase-ledger-list"></ul><p id="purchase-ledger-empty" class="help-block" hidden>{{ trans('presentation.chart_empty') }}</p><button type="button" class="btn btn-default btn-sm" id="purchase-ledger-expand" hidden>{{ trans('presentation.chart_show_all', ['count' => '']) }}</button>
                <p class="cost-overview-note">{{ trans('presentation.chart_cost_scope') }}</p>
            </div></section>
    </div>
    <div class="col-md-6"><x-dashboard.comparison-chart chart="cost_location" :title="trans('presentation.chart_cost_location')" :description="trans('presentation.chart_cost_scope')" /></div>
    <div class="col-md-6"><x-dashboard.comparison-chart chart="cost_manufacturer" :title="trans('presentation.chart_cost_manufacturer')" :description="trans('presentation.chart_cost_scope')" /></div>
    @can('view', \App\Models\User::class)
    <div class="col-md-12"><x-dashboard.comparison-chart chart="cost_department" :title="trans('presentation.chart_cost_department')" :description="trans('presentation.chart_cost_scope')" /></div>
    @endcan
</div>
@endcan
@endif
@endif
