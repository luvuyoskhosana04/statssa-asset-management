@extends('layouts/default')
@section('title'){{ trans('presentation.receive_new') }} @parent @stop
@push('css')<link rel="stylesheet" href="{{ asset('css/asset-intake.css') }}?v=10">@endpush
@section('content')
@php
    $copy = collect(['tag_scan_placeholder', 'barcode', 'added', 'duplicate', 'removed', 'invalid_barcode', 'saving', 'retry', 'remove_for', 'barcode_for', 'manual_added', 'draft_restored', 'draft_storage_error', 'draft_corrupt', 'scan_ready', 'scan_resume', 'missing_status', 'save', 'empty_barcode', 'draft_saved', 'exists', 'checking', 'check_failed', 'awaiting_title', 'received_title', 'original_prompt', 'tag_prompt', 'tag_label', 'pending_empty', 'pending_count', 'received_count', 'tagged', 'same_barcode', 'unfinished', 'review', 'select_tag', 'update_received', 'legacy_restored', 'tagged_status', 'replace_tag'])->mapWithKeys(fn ($key) => [$key => trans('intake.'.$key)])->all();
    $scannerCopy = ['input_ready' => trans('intake.scanner_input_ready'), 'input_paused' => trans('intake.scanner_input_paused'), 'generic_name' => trans('intake.scanner_generic_name'), 'ready' => trans('intake.scanner_ready_named'), 'paused' => trans('intake.scanner_paused_named'), 'disconnected' => trans('intake.scanner_disconnected'), 'checking' => trans('intake.scanner_checking'), 'unidentified' => trans('intake.scanner_unidentified'), 'unsupported' => trans('intake.scanner_unsupported'), 'blocked' => trans('intake.scanner_blocked'), 'keyboard_help' => trans('intake.scanner_keyboard_help'), 'pause_help' => trans('intake.scanner_pause_help'), 'disconnect_help' => trans('intake.scanner_disconnect_help'), 'blocked_help' => trans('intake.scanner_blocked_help')];
    $copy += collect(['receive_batch_limit', 'receive_draft_count', 'receive_error', 'receive_remove_item'])->mapWithKeys(fn ($key) => [$key => trans('presentation.'.$key)])->all();
@endphp
<div id="receiving-workspace" class="barcode-intake" data-scanner-copy="{{ json_encode($scannerCopy) }}" data-saved-draft="{{ json_encode($savedDraft) }}" data-draft-store="{{ route('hardware.receive.drafts.store') }}" data-draft-delete="{{ url('hardware/receive-tag/drafts') }}" data-check="{{ route('hardware.receive.check-barcode') }}" data-store="{{ route('hardware.receive.new-batches.store') }}" data-return="{{ route('hardware.receive.index') }}" data-draft-key="statssa-receiving-{{ auth()->id() }}-v1" data-copy="{{ json_encode($copy) }}" data-model-ids="{{ json_encode($models->modelKeys()) }}">
    <p class="help-block">{{ trans('intake.draft_hint') }}</p>
    <section class="box box-default intake-scan-card">
        <div class="box-header with-border intake-scan-heading"><h2 class="box-title">{{ trans('intake.scan_title') }}</h2><div class="intake-scanner-controls"><span id="receive-scanner-status" class="intake-scanner-status" data-state="checking" role="status" aria-live="polite">{{ trans('intake.scanner_checking') }}</span><button type="button" id="receive-identify" class="btn btn-default" hidden>{{ trans('intake.scanner_identify') }}</button><button type="button" id="receive-resume" class="btn btn-default" hidden>{{ trans('intake.scan_resume') }}</button></div></div>
        <div class="box-body intake-guide">
            <div class="intake-scan-animation" aria-hidden="true"><div class="intake-scan-frame"><div class="intake-barcode-art"></div><span class="intake-scan-beam"></span></div></div>
            <div class="intake-guide-copy"><h3>{{ trans('intake.scan_guide_title') }}</h3><ul><li>{{ trans('intake.scan_light') }}</li><li>{{ trans('intake.scan_integrity') }}</li><li>{{ trans('intake.scan_clean') }}</li></ul><p>{{ trans('intake.scan_automatic') }}</p></div>

        </div>
        <p class="help-block intake-scanner-help" id="receive-scanner-status-help"></p>
    </section>
    <div id="receive-message" class="alert alert-info" role="status" aria-live="polite" hidden></div>
    <section class="box box-default intake-tagging-panel">
        <div class="box-header with-border intake-draft-heading"><h2 class="box-title">{{ trans('intake.awaiting_title') }}</h2><div class="intake-draft-tools"><span id="receive-pending-count" class="text-muted"></span><button type="button" class="btn btn-default" id="receive-manual-open">{{ trans('intake.add_manual') }}</button></div></div>
        <div class="box-body">
            <p class="help-block">{{ trans('intake.pending_help') }}</p>
            <span id="receive-scanner-dock" class="intake-scanner-dock"><label for="receive-barcode" id="receive-scan-label" class="sr-only">{{ trans('intake.original_prompt') }}</label><input id="receive-barcode" class="sr-only" autocomplete="off" autocapitalize="off" spellcheck="false" maxlength="255" aria-describedby="receive-scanner-hint"><span class="sr-only" id="receive-scanner-hint">{{ trans('intake.scanner_input_help') }}</span></span>
            <p id="receive-pending-empty" class="intake-empty">{{ trans('intake.pending_empty') }}</p>
            <div class="table-responsive" id="receive-pending-results" hidden><table class="table intake-pending-table"><thead><tr><th>{{ trans('intake.barcode') }}</th><th>{{ trans('intake.tag_label') }}</th><th>{{ trans('button.actions') }}</th></tr></thead><tbody id="receive-pending-log"></tbody></table></div>
        </div>
    </section>
    <form id="receive-intake-form" novalidate>
        <input type="hidden" id="receive-status" value="{{ $defaultStatus }}">
        <section class="box box-default receipt-draft-panel">
            <div class="box-header with-border intake-draft-heading"><h2 class="box-title">{{ trans('intake.received_title') }}</h2><div class="intake-draft-tools"><span id="receive-count" class="text-muted"></span></div></div>
            <div class="box-body">
                @if ($snipeSettings->full_multiple_companies_support && $companies->count() > 1)
                <div class="form-group intake-company"><label for="receive-company">{{ trans('general.company') }}</label><select id="receive-company" class="form-control" @required(!auth()->user()->hasAccess('superuser'))><option value="">{{ trans('general.unassigned') }}</option>@foreach ($companies as $company)<option value="{{ $company->id }}" @selected($company->id === auth()->user()->company_id)>{{ $company->name }}</option>@endforeach</select></div>
                @else
                <input type="hidden" id="receive-company" value="{{ $snipeSettings->full_multiple_companies_support ? ($companies->first()?->id ?? '') : '' }}">
                @endif
                <p class="help-block">{{ trans('intake.tagged_help') }}</p><p id="receive-empty" class="intake-empty">{{ trans('intake.empty') }}</p><div id="receive-results" class="table-responsive" hidden><table class="table table-striped intake-draft-table"><thead><tr><th scope="col">{{ trans('intake.barcode') }}</th><th scope="col">{{ trans('intake.tag_label') }}</th><th scope="col">{{ trans('button.actions') }}</th></tr></thead><tbody id="receive-log"></tbody></table></div>
            </div>
            <div class="box-footer intake-draft-footer"><a href="{{ route('hardware.receive.index') }}" class="btn btn-default" id="receive-cancel">{{ trans('button.cancel') }}</a><div class="intake-save-actions"><button class="btn btn-default receive-save-later" type="button" disabled>{{ trans('intake.save_later') }}</button><button class="btn btn-primary receive-save-batch" type="submit" disabled>{{ trans('intake.save') }}</button></div><p class="help-block intake-draft-note">{{ trans('intake.save_later_help') }}</p></div>
        </section>
    </form>
</div>
<div class="modal fade" id="receive-manual-modal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="receive-manual-heading"><div class="modal-dialog"><div class="modal-content"><form id="receive-manual-form" novalidate><div class="modal-header"><h2 class="modal-title" id="receive-manual-heading">{{ trans('intake.add_manual') }}</h2></div><div class="modal-body"><p id="receive-manual-error" class="alert alert-danger" role="alert" hidden></p><div class="form-group"><label for="receive-manual-barcode">{{ trans('intake.barcode') }}</label><input type="text" class="form-control" id="receive-manual-barcode" maxlength="255" autocomplete="off" aria-describedby="receive-manual-error" required></div></div><div class="modal-footer"><button class="btn btn-default" type="button" data-dismiss="modal">{{ trans('button.cancel') }}</button><button class="btn btn-primary" type="submit">{{ trans('intake.add_draft') }}</button></div></form></div></div></div>
<div class="modal fade" id="receive-discard-modal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="receive-discard-heading"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h2 class="modal-title" id="receive-discard-heading">{{ trans('intake.cancel_title') }}</h2></div><div class="modal-body"><p>{{ trans($savedDraft ? 'intake.cancel_saved_help' : 'intake.cancel_help') }}</p><p id="receive-draft-save-error" class="alert alert-danger" role="alert" hidden></p></div><div class="modal-footer"><button class="btn btn-default" type="button" data-dismiss="modal">{{ trans('presentation.receive_keep_editing') }}</button><button class="btn btn-default" type="button" id="receive-discard">{{ trans('intake.cancel_without_saving') }}</button><button class="btn btn-primary receive-save-later" type="button">{{ trans('intake.save_later') }}</button></div></div></div></div>
@stop
@section('moar_scripts')<script src="{{ asset('js/asset-intake.js') }}?v=14" nonce="{{ csrf_token() }}"></script><script src="{{ asset('js/receiving-scanner-status.js') }}?v=2" nonce="{{ csrf_token() }}"></script>@stop
