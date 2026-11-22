@can('view', \App\Models\User::class)
    <section class="box box-default dashboard-offboarding-panel" id="dashboard-pending-offboarding">
        <div class="box-header with-border"><h2 class="box-title">{{ trans('presentation.chart_pending_offboarding') }}</h2></div>
        <div class="box-body">
            <div class="offboarding-overview"><strong id="insight-pending-offboarding" class="insight-metric">...</strong><div><p>{{ trans('presentation.chart_pending_offboarding_help') }}</p><p id="insight-pending-items"></p><div data-alert-value="pending_asset_value"></div></div></div>
            <details class="dashboard-offboarding-details"><summary>{{ trans('presentation.chart_pending_people') }}</summary>
                <div class="table-responsive"><table class="table"><thead><tr><th>{{ trans('general.name') }}</th><th>{{ trans('general.end_date') }}</th>@can('view', \App\Models\Asset::class)<th>{{ trans('general.assets') }}</th>@endcan @can('view', \App\Models\Accessory::class)<th>{{ trans('general.accessories') }}</th>@endcan @can('view', \App\Models\License::class)<th>{{ trans('general.licenses') }}</th>@endcan</tr></thead><tbody id="insight-pending-people"></tbody></table></div>
                <p class="help-block">{{ trans('presentation.chart_pending_scope') }}</p>
            </details>
        </div>
    </section>
    @endcan
