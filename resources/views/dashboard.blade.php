@extends('layouts/default')

{{-- Page title --}}
@section('title')
{{ trans('general.dashboard') }}
@parent
@stop


{{-- Page content --}}
@section('content')

<x-container>

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

</x-container>

@stop

@push('js')
    <script src="{{ asset('js/dashboard-purchase-value.js') }}?v=1" nonce="{{ csrf_token() }}"></script>
    <script src="{{ asset('js/dashboard-chart-variants.js') }}?v=3" nonce="{{ csrf_token() }}"></script>
    <script src="{{ asset('js/dashboard-insights.js') }}?v=7" nonce="{{ csrf_token() }}"></script>
@endpush
