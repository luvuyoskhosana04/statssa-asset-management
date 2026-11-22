@extends('layouts/default')

{{-- Page title --}}
@section('title')
{{ trans('general.dashboard') }}
@parent
@stop


{{-- Page content --}}
@section('content')

<x-container>
<div class="dashboard-modern dashboard-studio">
    <div class="dashboard-intro"><div class="dashboard-welcome"><p class="dashboard-greeting">{{ trans('presentation.dashboard_welcome', ['name' => auth()->user()->first_name ?: auth()->user()->display_name]) }}</p><p>{{ trans('presentation.dashboard_analytics_intro') }}</p></div>@can('view', \App\Models\Asset::class)
    <nav class="dashboard-jump-nav" aria-label="{{ trans('general.dashboard') }}"><a href="#dashboard-overview">{{ trans('presentation.dashboard_nav_overview') }}</a><a href="#dashboard-distribution">{{ trans('presentation.dashboard_nav_distribution') }}</a><a href="#dashboard-purchases">{{ trans('presentation.dashboard_nav_purchases') }}</a></nav>
    @endcan</div>

    @if ($snipeSettings->dashboard_message != '')
        <div class="row">
            <div class="col-md-12">
                <div class="box box-default">
                    <div class="box-body">
                        {!! Helper::parseEscapedMarkedown($snipeSettings->dashboard_message) !!}
                    </div>
                </div>
            </div>
        </div>
    @endif

    <x-dashboard.index />

</div>
</x-container>

@stop

@include('partials.bootstrap-table')

@push('css')
    <link rel="stylesheet" href="{{ asset('css/dashboard-modern.css') }}?v=29">
@endpush

@push('js')
    <script src="{{ asset('js/dashboard-activity.js') }}?v=1" nonce="{{ csrf_token() }}"></script>
    <script nonce="{{ csrf_token() }}">
        $(document).on('load-success.bs.table', '#dashLowStock', function (event, data) {
            $('#dashboard-low-stock-count').text(@json(trans('presentation.chart_stock_count')).replace(':count', new Intl.NumberFormat(document.documentElement.lang || 'en').format(data.total)));
        }).on('load-error.bs.table', '#dashLowStock', function () {
            $('#dashboard-low-stock-count').text(@json(trans('presentation.chart_error')));
        });
    </script>
    <script src="{{ asset('js/dashboard-numbers.js') }}?v=5" nonce="{{ csrf_token() }}"></script>
    <script src="{{ asset('js/dashboard-purchase-value.js') }}?v=4" nonce="{{ csrf_token() }}"></script>
    <script src="{{ asset('js/dashboard-chart-variants.js') }}?v=12" nonce="{{ csrf_token() }}"></script>
    <script src="{{ asset('js/dashboard-insights.js') }}?v=12" nonce="{{ csrf_token() }}"></script>
    <script src="{{ asset('js/dashboard-motion.js') }}?v=3" nonce="{{ csrf_token() }}"></script>
@endpush
