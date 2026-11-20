@extends('layouts/default')
@section('title'){{ trans('presentation.receive_new') }} @parent @stop
@push('css')<link rel="stylesheet" href="{{ asset('css/asset-intake.css') }}?v=4">@endpush
@section('content')
@php
    $copy = collect(['added', 'duplicate', 'removed', 'invalid_barcode', 'model_required', 'saving', 'retry', 'remove_for', 'model_for', 'choose_model', 'barcode_for', 'manual_added', 'draft_restored', 'draft_storage_error', 'draft_corrupt', 'scan_ready', 'scan_resume', 'missing_status', 'save', 'empty_barcode', 'draft_saved'])->mapWithKeys(fn ($key) => [$key => trans('intake.'.$key)])->all();
    $copy += collect(['receive_batch_limit', 'receive_draft_count', 'receive_error', 'receive_remove_item'])->mapWithKeys(fn ($key) => [$key => trans('presentation.'.$key)])->all();
@endphp
<div id="receiving-workspace" class="barcode-intake" data-store="{{ route('hardware.receive.new-batches.store') }}" data-return="{{ route('hardware.receive.index') }}" data-draft-key="statssa-receiving-{{ auth()->id() }}-v1" data-copy="{{ json_encode($copy) }}" data-models="{{ json_encode($models->map(fn ($model) => ['id' => $model->id, 'name' => $model->name.($model->model_number ? ' ('.$model->model_number.')' : '')])->values()) }}">
    <p class="help-block">{{ trans('intake.draft_hint') }}</p>
    <section class="box box-default intake-scan-card">
        <div class="box-header with-border intake-scan-heading"><h2 class="box-title">{{ trans('intake.scan_title') }}</h2><button type="button" id="receive-resume" class="btn btn-default intake-ready">{{ trans('intake.scan_ready') }}</button></div>
        <div class="box-body intake-guide">
            <div class="intake-scan-animation" aria-hidden="true"><div class="intake-scan-frame"><div class="intake-barcode-art"></div><span class="intake-scan-beam"></span></div></div>
            <div class="intake-guide-copy"><h3>{{ trans('intake.scan_guide_title') }}</h3><ul><li>{{ trans('intake.scan_light') }}</li><li>{{ trans('intake.scan_integrity') }}</li><li>{{ trans('intake.scan_clean') }}</li></ul><p>{{ trans('intake.scan_automatic') }}</p></div>
            <label for="receive-barcode" class="sr-only">{{ trans('intake.scanner_input') }}</label><input id="receive-barcode" class="sr-only" autocomplete="off" autocapitalize="off" spellcheck="false" maxlength="8192" aria-describedby="receive-scanner-hint"><span class="sr-only" id="receive-scanner-hint">{{ trans('intake.scanner_input_help') }}</span>
        </div>
    </section>
    <div id="receive-message" class="alert alert-info" role="status" aria-live="polite" hidden></div>
    <form id="receive-intake-form" novalidate>
        <input type="hidden" id="receive-status" value="{{ $defaultStatus }}">
        <section class="box box-default receipt-draft-panel">
            <div class="box-header with-border intake-draft-heading"><h2 class="box-title">{{ trans('presentation.receive_draft') }}</h2><div class="intake-draft-tools"><span id="receive-count" class="text-muted"></span><button type="button" class="btn btn-default" id="receive-manual-open">{{ trans('intake.add_manual') }}</button></div></div>
            <div class="box-body">
                @if ($snipeSettings->full_multiple_companies_support && $companies->count() > 1)
                <div class="form-group intake-company"><label for="receive-company">{{ trans('general.company') }}</label><select id="receive-company" class="form-control" @required(!auth()->user()->hasAccess('superuser'))><option value="">{{ trans('general.unassigned') }}</option>@foreach ($companies as $company)<option value="{{ $company->id }}" @selected($company->id === auth()->user()->company_id)>{{ $company->name }}</option>@endforeach</select></div>
                @else
                <input type="hidden" id="receive-company" value="{{ $snipeSettings->full_multiple_companies_support ? ($companies->first()?->id ?? '') : '' }}">
                @endif
                <p id="receive-empty" class="intake-empty">{{ trans('intake.empty') }}</p><div id="receive-results" class="table-responsive" hidden><table class="table table-striped intake-draft-table"><thead><tr><th scope="col">{{ trans('intake.barcode') }}</th><th scope="col">{{ trans('intake.model') }}</th><th scope="col">{{ trans('button.actions') }}</th></tr></thead><tbody id="receive-log"></tbody></table></div>
            </div>
            <div class="box-footer intake-draft-footer"><a href="{{ route('hardware.receive.index') }}" class="btn btn-default" id="receive-cancel">{{ trans('button.cancel') }}</a><div class="intake-save-actions"><button class="btn btn-default receive-save-later" type="button" disabled>{{ trans('intake.save_later') }}</button><button class="btn btn-primary receive-save-batch" type="submit" disabled>{{ trans('intake.save') }}</button></div><p class="help-block intake-draft-note">{{ trans('intake.save_later_help') }}</p></div>
        </section>
    </form>
</div>
<div class="modal fade" id="receive-manual-modal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="receive-manual-heading"><div class="modal-dialog"><div class="modal-content"><form id="receive-manual-form" novalidate><div class="modal-header"><h2 class="modal-title" id="receive-manual-heading">{{ trans('intake.add_manual') }}</h2></div><div class="modal-body"><p id="receive-manual-error" class="alert alert-danger" role="alert" hidden></p><div class="form-group"><label for="receive-manual-barcode">{{ trans('intake.barcode') }}</label><input type="text" class="form-control" id="receive-manual-barcode" maxlength="255" autocomplete="off" aria-describedby="receive-manual-error" required></div><div class="form-group"><label for="receive-manual-model">{{ trans('intake.model') }}</label><select id="receive-manual-model" class="form-control"><option value="">{{ trans('intake.choose_model') }}</option>@foreach ($models as $model)<option value="{{ $model->id }}">{{ $model->name }}{{ $model->model_number ? ' ('.$model->model_number.')' : '' }}</option>@endforeach</select></div></div><div class="modal-footer"><button class="btn btn-default" type="button" data-dismiss="modal">{{ trans('button.cancel') }}</button><button class="btn btn-primary" type="submit">{{ trans('intake.add_draft') }}</button></div></form></div></div></div>
<div class="modal fade" id="receive-discard-modal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="receive-discard-heading"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h2 class="modal-title" id="receive-discard-heading">{{ trans('intake.cancel_title') }}</h2></div><div class="modal-body"><p>{{ trans('intake.cancel_help') }}</p><p id="receive-draft-save-error" class="alert alert-danger" role="alert" hidden></p></div><div class="modal-footer"><button class="btn btn-default" type="button" data-dismiss="modal">{{ trans('presentation.receive_keep_editing') }}</button><button class="btn btn-default" type="button" id="receive-discard">{{ trans('intake.cancel_without_saving') }}</button><button class="btn btn-primary receive-save-later" type="button">{{ trans('intake.save_later') }}</button></div></div></div></div>
@stop
@section('moar_scripts')<script src="{{ asset('js/asset-intake.js') }}?v=4" nonce="{{ csrf_token() }}"></script>@stop
