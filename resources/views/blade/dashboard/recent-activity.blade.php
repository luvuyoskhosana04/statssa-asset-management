{{-- Read-only feed uses the existing permission-filtered activity endpoint. --}}
@can('canViewUsersAndCheckoutables')
<section class="box box-default dashboard-activity" id="dashboard-activity"
    data-url="{{ route('api.dashboard.activity') }}" data-base-url="{{ url('/') }}"
    data-copy="{{ json_encode(collect(['activity_loading', 'activity_error', 'activity_empty', 'activity_search_empty', 'activity_count', 'activity_unknown_actor', 'activity_unknown_item', 'activity_target'])->mapWithKeys(fn ($key) => [$key => trans('presentation.'.$key)])->all()) }}">
    <div class="box-header with-border dashboard-activity-header">
        <div><h2 class="box-title">{{ trans('general.recent_activity') }}</h2><p>{{ trans('presentation.activity_intro') }}</p></div>
        <div class="dashboard-activity-controls">
            <label class="sr-only" for="dashboard-activity-search">{{ trans('presentation.activity_search') }}</label>
            <input type="search" id="dashboard-activity-search" placeholder="{{ trans('presentation.activity_search') }}" autocomplete="off">
            <button type="button" class="btn btn-default" id="dashboard-activity-refresh">{{ trans('presentation.activity_refresh') }}</button>
        </div>
    </div>
    <div class="box-body">
        <ol id="dashActivityReport" class="dashboard-activity-list" aria-label="{{ trans('general.recent_activity') }}"></ol>
        <div class="dashboard-activity-status"><p id="dashboard-activity-message" role="status" aria-live="polite">{{ trans('presentation.activity_loading') }}</p><button type="button" class="btn btn-default" id="dashboard-activity-retry" hidden>{{ trans('presentation.chart_retry') }}</button><button type="button" class="btn btn-default" id="dashboard-activity-more" hidden>{{ trans('presentation.activity_more') }}</button></div>
    </div>
    <div class="box-footer"><a href="{{ route('reports.activity') }}">{{ trans('presentation.activity_view_all') }}</a></div>
</section>
@endcan
