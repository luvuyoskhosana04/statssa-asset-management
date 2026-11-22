<div class="row dashboard-row-eq dashboard-row-compact">
    <div class="col-md-12"><x-dashboard.insights section="summary" /></div>
    <div class="col-md-12"><x-dashboard.insights section="charts" /></div>
    <div class="col-md-12"><x-dashboard.low-stock /></div>
    <div class="col-md-12"><x-dashboard.recent-activity /></div>
</div>
@if (Gate::denies('view', \App\Models\Asset::class) && Gate::denies('view', \App\Models\User::class))
    <p class="help-block">{{ trans('presentation.reporting_unavailable') }}</p>
@endif
