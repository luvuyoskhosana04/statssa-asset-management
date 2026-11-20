@extends('layouts/default')
@section('title')RCV-{{ str_pad((string) $batch->id, 6, '0', STR_PAD_LEFT) }} @parent @stop
@section('content')
<div class="receipt-page-actions"><a href="{{ route('hardware.receive.index') }}" class="btn btn-default">{{ trans('presentation.receive_audit') }}</a>@can('create', \App\Models\Asset::class)<a href="{{ route('hardware.receive.create') }}" class="btn btn-primary"><x-icon type="plus" /> {{ trans('presentation.receive_new') }}</a>@endcan</div>
<section class="box box-default">
    <div class="box-header with-border"><h2 class="box-title">{{ trans('presentation.receive_batch_reference') }} RCV-{{ str_pad((string) $batch->id, 6, '0', STR_PAD_LEFT) }}</h2></div>
    <div class="box-body">
        <dl class="receipt-metadata">
            <div><dt>{{ trans('presentation.receive_datetime') }}</dt><dd><time class="receipt-local-time" datetime="{{ \Carbon\Carbon::parse($batch->created_at)->toIso8601String() }}">{{ $batch->created_at }} {{ config('app.timezone') }}</time></dd></div>
            <div><dt>{{ trans('presentation.receive_receiver') }}</dt><dd>{{ $batch->receiver_name }}</dd></div>
            <div><dt>{{ trans('presentation.receive_items') }}</dt><dd>{{ $items->count() }}</dd></div>
            <div><dt>{{ trans('presentation.receive_saved_status') }}</dt><dd>{{ $batch->status_name ?? trans('presentation.receive_not_recorded') }}</dd></div>
        </dl>
        <p class="help-block">{{ trans('presentation.receive_snapshot') }}</p>
        <div class="table-responsive"><table class="table table-striped receipt-detail-table"><thead><tr><th scope="col">{{ trans('general.asset') }}</th><th scope="col">{{ trans('presentation.receive_previous_tag') }}</th><th scope="col">{{ trans('presentation.receive_new_tag') }}</th><th scope="col">{{ trans('presentation.receive_condition') }}</th><th scope="col">{{ trans('general.notes') }}</th><th scope="col">{{ trans('button.actions') }}</th></tr></thead><tbody>
        @foreach ($items as $item)
            <tr><th scope="row">{{ $item->model_name ?? trans('presentation.receive_not_recorded') }}<small class="receipt-subtext">{{ trans('general.serial_number') }}: {{ $item->serial ?: trans('presentation.receive_not_recorded') }}</small><small class="receipt-subtext">{{ trans('general.location') }}: {{ $item->location_name ?? trans('presentation.receive_not_recorded') }}</small><small class="receipt-subtext">{{ trans('presentation.receive_order') }}: {{ $item->order_number ?? trans('presentation.receive_not_recorded') }}</small></th>
                <td>{{ $item->previous_tag ?: trans('presentation.receive_not_recorded') }}</td><td>{{ $item->asset_tag }}</td><td>{{ trans('presentation.receive_condition_'.$item->condition) }}</td><td class="receipt-saved-note">{{ $item->note ?: trans('presentation.receive_not_recorded') }}</td>
                <td>@if (in_array($item->asset_id, $assetLinks))<a href="{{ route('hardware.show', $item->asset_id) }}" class="btn btn-default btn-sm">{{ trans('presentation.receive_go_record') }}</a>@else<span class="text-muted">{{ trans('presentation.receive_record_unavailable') }}</span>@endif</td>
            </tr>
        @endforeach
        </tbody></table></div>
    </div>
</section>
@stop
@section('moar_scripts')<script src="{{ asset('js/receipt-history.js') }}?v=1"></script>@stop
