@extends('projects.layout')
@section('title'){{ $project->name }} @parent @stop
@section('content')
@php $pending = $issues->whereNull('handed_over_at')->whereNull('cancelled_at'); @endphp
@if (!$session->closed_at && $pending->isEmpty())
<section class="box box-default project-edit" id="project-issue-preview" data-worker-url="{{ route('projects.issue.lookup-employee', $session->id) }}" data-asset-url="{{ route('projects.issue.lookup-asset', $session->id) }}" data-copy="{{ json_encode(collect(trans('projects'))->only(['worker_required','asset_required','return_required','return_within_project','acceptance_required','handover_required','lookup_busy','lookup_failed','employee_identified','asset_identified','not_recorded','employee_picker_empty','employee_picker_count','step_completed'])) }}">
<div class="box-body">
<p class="help-block">{{ trans('projects.directory_help') }}</p>
<nav class="form-stepper" aria-label="{{ trans('projects.issue') }}"><ol class="stepper-list">@foreach (['employee_step','asset_step','acceptance_step','handover_step'] as $step)<li class="stepper-item {{ $loop->first ? 'is-current' : '' }}" @if($loop->first) aria-current="step" @endif><span class="stepper-button"><span class="stepper-marker">{{ $loop->iteration }}</span><span class="stepper-label">{{ trans('projects.'.$step) }}</span></span></li>@endforeach</ol></nav>
<div id="issue-error-summary" class="alert alert-danger" role="alert" tabindex="-1" hidden><strong>{{ trans('projects.fix_fields') }}</strong><ul></ul></div><p id="issue-live-status" role="status" aria-live="polite"></p>
<form id="issue-preview-form" method="post" action="{{ route('projects.issue.store', $session->id) }}" novalidate>@csrf
<section data-issue-panel="0"><h3 tabindex="-1">{{ trans('projects.employee_step') }}</h3>
<div class="form-group">
    <label for="issue-worker" id="issue-worker-label">{{ trans('projects.employee_picker_label') }}</label>
    <div id="issue-employee-picker" class="employee-picker" data-employees="{{ json_encode($employees->map(fn ($employee) => ['barcode' => 'STATSSA-EMP-'.$employee->id, 'name' => $employee->display_name, 'number' => (string) $employee->employee_num, 'username' => $employee->username])->values()) }}">
        <input id="issue-worker" class="form-control" role="combobox" aria-autocomplete="list" aria-haspopup="listbox" aria-expanded="false" aria-controls="issue-worker-list" placeholder="{{ trans('projects.employee_picker_placeholder') }}" autocomplete="off" autocapitalize="off" spellcheck="false" maxlength="255" value="{{ old('worker') }}" aria-describedby="worker-help issue-worker-error">
        <input id="issue-worker-value" name="worker" type="hidden" value="{{ old('worker') }}">
        <button type="button" class="employee-picker-toggle" id="issue-worker-toggle" aria-label="{{ trans('projects.employee_picker_toggle') }}" aria-controls="issue-worker-list" aria-expanded="false" tabindex="-1"><span class="employee-picker-chevron" aria-hidden="true"></span></button>
        <div class="employee-picker-menu" id="issue-worker-menu" hidden><ul id="issue-worker-list" role="listbox" aria-labelledby="issue-worker-label"></ul><p id="issue-worker-results" class="employee-picker-results" role="status" aria-live="polite"></p></div>
    </div>
    <p class="help-block" id="worker-help">{{ trans('projects.worker_help') }}</p><p class="project-field-error" id="issue-worker-error" hidden></p>
</div>
</section>
<section data-issue-panel="1" hidden><h3 tabindex="-1">{{ trans('projects.asset_step') }}</h3><p><strong data-issue-value="worker"></strong></p>
<div class="form-group"><label for="issue-asset">{{ trans('projects.asset') }}</label><input id="issue-asset" name="asset" class="form-control" value="{{ old('asset') }}" autocomplete="off" maxlength="255" aria-describedby="issue-asset-error"><p class="help-block">{{ trans('projects.scan_help') }}</p><p class="project-field-error" id="issue-asset-error" hidden></p></div>
<dl class="project-issue-summary" id="issue-scan-result" hidden>@foreach(['asset' => 'asset','serial' => 'scanned_identifier','model' => 'assets','category' => 'example_type','status' => 'active','condition' => 'condition','location' => 'office'] as $field => $label)<div><dt>{{ in_array($field,['serial','model','category','status']) ? trans('general.'.['serial'=>'serial','model'=>'asset_model','category'=>'category','status'=>'status'][$field]) : trans('projects.'.$label) }}</dt><dd data-issue-value="{{ $field }}"></dd></div>@endforeach</dl>
<div class="form-group"><label for="issue-return">{{ trans('projects.return_by') }}</label><input id="issue-return" name="expected_checkin" type="date" class="form-control" min="{{ today()->toDateString() }}" max="{{ $project->end_date->toDateString() }}" value="{{ old('expected_checkin') }}" aria-describedby="issue-return-error"><p class="project-field-error" id="issue-return-error" hidden></p></div></section>
<section data-issue-panel="2" hidden><h3 tabindex="-1">{{ trans('projects.signature') }}</h3><p>{{ trans('projects.request_help') }}</p>@include('projects.issue-summary')
<div class="form-group"><label for="issue-note">{{ trans('projects.issue_note') }}</label><textarea id="issue-note" class="form-control" name="note" maxlength="5000" rows="3">{{ old('note') }}</textarea></div>
<label class="project-issue-check"><input type="checkbox" id="issue-accepted" name="reviewed" value="1"> {{ trans('projects.review_ack') }}</label><p class="project-field-error" id="issue-accepted-error" hidden></p><p class="help-block">{{ trans('projects.review_edit') }}</p></section>
<section data-issue-panel="3" hidden><h3 tabindex="-1">{{ trans('projects.handover') }}</h3><p>{{ trans('projects.declare_help') }}</p>@include('projects.issue-summary')<label class="project-issue-check"><input type="checkbox" id="issue-handed-over" name="handover_declared" value="1"> {{ trans('projects.declare_handover') }}</label><p class="project-field-error" id="issue-handed-over-error" hidden></p></section>
<div class="project-issue-footer"><a id="issue-cancel" href="{{ route('projects.issue.index') }}" class="btn btn-default">{{ trans('projects.cancel') }}</a><button type="button" id="issue-back" class="btn btn-default" hidden>{{ trans('projects.back') }}</button><button type="submit" id="issue-next" class="btn btn-primary" data-next="{{ trans('projects.next') }}" data-finish="{{ trans('projects.send_request') }}">{{ trans('projects.next') }}</button></div>
</form></div></section>
@else
<section class="box box-default"><div class="box-body"><p>{{ trans($session->closed_at ? 'projects.session_closed' : 'projects.pending_acceptance') }}</p><a class="btn btn-primary" href="{{ route('projects.issue.audit', $session->id) }}">{{ trans('projects.investigate') }}</a></div></section>
@endif
@stop
@push('js')<script src="{{ asset('js/project-issue-session.js') }}?v=5" nonce="{{ csrf_token() }}"></script>@endpush
