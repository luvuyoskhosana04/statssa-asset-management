@props(['chart', 'title', 'description' => null])
<section class="box box-default dashboard-comparison" data-insight-chart="{{ $chart }}" data-description="{{ $description ?? trans('presentation.chart_scope') }}">
    <div class="box-header with-border"><h2 class="box-title">{{ $title }}</h2></div>
    <div class="box-body">
        <p class="help-block">{{ $description ?? trans('presentation.chart_scope') }}</p>
        <div class="insight-bars" role="region" aria-label="{{ $title }}"></div>
        <button type="button" class="btn btn-default btn-sm insight-expand" hidden>{{ trans('presentation.chart_show_all', ['count' => '']) }}</button>
    </div>
    <div class="box-footer text-center"><a href="{{ route('hardware.index') }}">{{ trans('presentation.chart_details') }}</a></div>
</section>
