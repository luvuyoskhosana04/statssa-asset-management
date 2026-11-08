@can('view', \App\Models\Asset::class)
    <div class="box box-default status-overview">
        <div class="box-header with-border">
            <h2 class="box-title">
                {{ (\App\Models\Setting::getSettings()->dash_chart_type == 'name') ? trans('general.assets_by_status') : trans('general.assets_by_status_type') }}
            </h2>
            <div class="box-tools pull-right">
                <button type="button" class="btn btn-box-tool" data-widget="collapse" aria-hidden="true">
                    <x-icon type="minus"/>
                    <span class="sr-only">{{ trans('general.collapse') }}</span>
                </button>
            </div>
        </div>
        <div class="box-body dashboard-chart-body"
             data-status-url="{{ (\App\Models\Setting::getSettings()->dash_chart_type == 'name') ? route('api.statuslabels.assets.byname') : route('api.statuslabels.assets.bytype') }}"
             data-status-ready="{{ trans('general.ready_to_deploy') }}"
             data-status-pending="{{ trans('general.pending') }}"
             data-status-deployed="{{ trans('general.deployed') }}"
             data-status-archived="{{ trans('general.archived') }}"
             data-status-undeployable="{{ trans('general.undeployable') }}">
            <p class="status-chart-message" role="status" data-empty="{{ trans('general.no_results') }}" data-error="{{ trans('general.error') }}">{{ trans('general.loading') }}</p>
            <div class="status-ring" hidden>
                <canvas id="statusPieChart" aria-hidden="true"></canvas>
                <div class="status-ring-total">
                    <strong class="status-total-value"></strong>
                    <span>{{ trans('general.total_assets') }}</span>
                </div>
            </div>
            <ul class="status-breakdown" aria-label="{{ trans('general.assets_by_status') }}"></ul>
        </div>
        <div class="box-footer text-center">
            <a class="btn btn-theme btn-sm btn-block" href="{{ route('hardware.index') }}">{{ trans('general.viewall') }}</a>
        </div>
    </div>
@endcan
