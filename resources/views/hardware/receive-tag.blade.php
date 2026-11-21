@extends('layouts/default')
@section('title'){{ trans('presentation.receive_title') }} @parent @stop
@push('css')<link rel="stylesheet" href="{{ asset('css/asset-intake.css') }}?v=10">@endpush
@section('content')
<div id="receiving-drafts-hub" data-local-key="statssa-receiving-{{ auth()->id() }}-v1" data-draft-store="{{ route('hardware.receive.drafts.store') }}">

@can('create', \App\Models\Asset::class)<div id="receiving-recovery" class="alert alert-info" hidden><p id="receiving-recovery-message">{{ trans('intake.draft_recovery') }}</p><button type="button" id="receiving-recover" class="btn btn-default">{{ trans('intake.recover_draft') }}</button></div>@endcan
<p class="help-block">{{ trans($tab === 'drafts' ? 'intake.drafts_intro' : 'presentation.receive_audit_intro') }}</p>
<section class="box box-default receipt-history-panel">
    <div class="box-header with-border receipt-history-toolbar">
        <nav class="receiving-tabs" aria-label="{{ trans('presentation.receive_title') }}"><a href="{{ route('hardware.receive.index') }}" @if($tab === 'history') aria-current="page" @endif>{{ trans('presentation.receive_audit') }}</a><a href="{{ route('hardware.receive.index', ['tab' => 'drafts']) }}" @if($tab === 'drafts') aria-current="page" @endif>{{ trans('intake.drafts') }} <span>{{ $drafts->total() }}</span></a></nav>
        <div class="receipt-history-tools">
            @if ($tab === 'history')<form action="{{ route('hardware.receive.index') }}" method="get" class="receipt-history-search" role="search">
                <label for="receipt-history-search" class="sr-only">{{ trans('presentation.receive_search_history') }}</label>
                <input id="receipt-history-search" name="q" value="{{ $search }}" class="form-control" placeholder="{{ trans('presentation.receive_search_placeholder') }}" maxlength="200">
                <button class="btn btn-default" type="submit" aria-label="{{ trans('general.search') }}"><x-icon type="search" /></button>
                @if ($search !== '')<a href="{{ route('hardware.receive.index') }}" class="btn btn-default">{{ trans('presentation.receive_clear_search') }}</a>@endif
            </form>@endif
            <a href="{{ route('hardware.receive.index', ['q' => $search, 'tab' => $tab]) }}" class="btn btn-default" aria-label="{{ trans('presentation.receive_refresh') }}"><x-icon type="refresh" /> {{ trans('intake.refresh') }}</a>
            @can('create', \App\Models\Asset::class)<a href="{{ route('hardware.receive.create') }}" class="btn btn-primary"><x-icon type="plus" /> {{ trans('presentation.receive_new') }}</a>@endcan
        </div>
    </div>
    <div class="box-body">
        @if ($tab === 'drafts')
        <div class="table-responsive"><table class="table table-striped receipt-history-table"><thead><tr><th scope="col">{{ trans('intake.draft_reference') }}</th><th scope="col">{{ trans('presentation.receive_datetime') }}</th><th scope="col">{{ trans('presentation.receive_items') }}</th><th scope="col">{{ trans('button.actions') }}</th></tr></thead><tbody>
        @forelse ($drafts as $draft)<tr><th scope="row">DRF-{{ strtoupper(substr($draft->id, 0, 8)) }}</th><td><time class="receipt-local-time" datetime="{{ \Carbon\Carbon::parse($draft->updated_at)->toIso8601String() }}">{{ $draft->updated_at }}</time></td><td>{{ $draft->item_count }}</td><td><a class="btn btn-default" href="{{ route('hardware.receive.drafts.resume', $draft->id) }}">{{ trans('intake.resume_draft') }}</a> <button type="button" class="btn btn-danger receiving-delete-draft" data-delete-url="{{ route('hardware.receive.drafts.discard', $draft->id) }}" data-reference="DRF-{{ strtoupper(substr($draft->id, 0, 8)) }}">{{ trans('intake.delete_draft') }}</button></td></tr>
        @empty<tr><td colspan="4" class="receipt-history-empty">{{ trans('intake.drafts_empty') }}</td></tr>@endforelse
        </tbody></table></div>{{ $drafts->links() }}
        @else
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
        @endif
    </div>
    @if ($tab === 'history' && $batches->total())
    <div class="box-footer receipt-history-pagination"><span>{{ trans('presentation.receive_history_count', ['first' => $batches->firstItem(), 'last' => $batches->lastItem(), 'total' => $batches->total()]) }}</span>{{ $batches->links() }}</div>
    @endif
</section>
</div>
<div class="modal fade" id="receiving-delete-modal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="receiving-delete-heading"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h2 class="modal-title" id="receiving-delete-heading">{{ trans('intake.delete_draft_title') }}</h2></div><div class="modal-body"><p>{{ trans('intake.delete_draft_help') }}</p><strong id="receiving-delete-reference"></strong><p id="receiving-delete-error" class="alert alert-danger" role="alert" hidden>{{ trans('intake.delete_draft_error') }}</p></div><div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">{{ trans('button.cancel') }}</button><button type="button" class="btn btn-danger" id="receiving-delete-confirm">{{ trans('intake.delete_draft') }}</button></div></div></div></div>
@stop
@section('moar_scripts')<script src="{{ asset('js/receipt-history.js') }}?v=3"></script>@stop
