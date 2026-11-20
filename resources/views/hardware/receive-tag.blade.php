@extends('layouts/default')
@section('title'){{ trans('presentation.receive_title') }} @parent @stop
@section('content')
<p class="help-block">{{ trans('presentation.receive_audit_intro') }}</p>
<section class="box box-default receipt-history-panel">
    <div class="box-header with-border receipt-history-toolbar">
        <h2 class="box-title">{{ trans('presentation.receive_audit') }}</h2>
        <div class="receipt-history-tools">
            <form action="{{ route('hardware.receive.index') }}" method="get" class="receipt-history-search" role="search">
                <label for="receipt-history-search" class="sr-only">{{ trans('presentation.receive_search_history') }}</label>
                <input id="receipt-history-search" name="q" value="{{ $search }}" class="form-control" placeholder="{{ trans('presentation.receive_search_placeholder') }}" maxlength="200">
                <button class="btn btn-default" type="submit" aria-label="{{ trans('general.search') }}"><x-icon type="search" /></button>
                @if ($search !== '')<a href="{{ route('hardware.receive.index') }}" class="btn btn-default">{{ trans('presentation.receive_clear_search') }}</a>@endif
            </form>
            <a href="{{ route('hardware.receive.index', ['q' => $search]) }}" class="btn btn-default" aria-label="{{ trans('presentation.receive_refresh') }}"><x-icon type="refresh" /></a>
            @can('create', \App\Models\Asset::class)<a href="{{ route('hardware.receive.create') }}" class="btn btn-primary"><x-icon type="plus" /> {{ trans('presentation.receive_new') }}</a>@endcan
        </div>
    </div>
    <div class="box-body">
        <div class="table-responsive">
            <table class="table table-striped receipt-history-table">
                <thead><tr><th scope="col">{{ trans('presentation.receive_batch_reference') }}</th><th scope="col" aria-sort="descending">{{ trans('presentation.receive_datetime') }} <span aria-hidden="true">↓</span></th><th scope="col">{{ trans('presentation.receive_receiver') }}</th><th scope="col">{{ trans('presentation.receive_items') }}</th><th scope="col">{{ trans('general.location') }}</th><th scope="col">{{ trans('button.actions') }}</th></tr></thead>
                <tbody>
                @forelse ($batches as $batch)
                    <tr id="receipt-batch-{{ $batch->id }}">
                        <th scope="row"><a href="{{ route('hardware.receive.show', ['batchId' => $batch->id]) }}">RCV-{{ str_pad((string) $batch->id, 6, '0', STR_PAD_LEFT) }}</a><small class="receipt-subtext">{{ $batch->status_name ?? trans('presentation.receive_not_recorded') }}</small></th>
                        <td><time class="receipt-local-time" datetime="{{ \Carbon\Carbon::parse($batch->created_at)->toIso8601String() }}">{{ $batch->created_at }} {{ config('app.timezone') }}</time></td>
                        <td>{{ $batch->receiver_name }}</td><td><strong>{{ number_format($batch->item_count) }}</strong></td>
                        <td>{{ $batch->location_name ?? trans('presentation.receive_existing_locations') }}</td>
                        <td><a class="btn btn-default btn-sm" href="{{ route('hardware.receive.show', ['batchId' => $batch->id]) }}">{{ trans('presentation.receive_view_batch') }}<span class="sr-only"> RCV-{{ str_pad((string) $batch->id, 6, '0', STR_PAD_LEFT) }}</span></a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="receipt-history-empty">{{ trans('presentation.'.($search === '' ? 'receive_history_empty' : 'receive_search_empty')) }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if ($batches->total())
    <div class="box-footer receipt-history-pagination"><span>{{ trans('presentation.receive_history_count', ['first' => $batches->firstItem(), 'last' => $batches->lastItem(), 'total' => $batches->total()]) }}</span>{{ $batches->links() }}</div>
    @endif
</section>
@stop
@section('moar_scripts')<script src="{{ asset('js/receipt-history.js') }}?v=1"></script>@stop
