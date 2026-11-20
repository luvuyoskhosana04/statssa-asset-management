@extends('projects.layout')
@section('title'){{ trans('projects.session') }} @parent @stop
@section('content')
<section class="box box-default"><div class="box-header with-border project-toolbar"><div><h2 class="box-title">{{ $project->name }}</h2><p class="help-block">{{ $project->code }} · {{ $project->location?->name }}</p></div><div class="project-actions"><a href="{{ route('projects.show', $project) }}" class="btn btn-default">{{ trans('projects.view') }}</a>@if (!$session->closed_at)<form method="post" action="{{ route('projects.issue.close', $session->id) }}">@csrf<button type="submit" class="btn btn-default">{{ trans('projects.close') }}</button></form>@endif</div></div></section>
@php $pending = $issues->whereNull('handed_over_at')->whereNull('cancelled_at'); @endphp
@if (!$session->closed_at && $pending->isEmpty())
<section class="box box-default project-edit" id="project-issue-preview" data-copy="{{ json_encode(collect(trans('projects'))->only(['worker_required', 'asset_required', 'return_required', 'return_within_project', 'acceptance_required', 'handover_required', 'scan_ready', 'scan_added', 'type_laptop', 'type_tablet', 'type_monitor', 'type_phone', 'type_desktop'])) }}">
    <div class="box-body">
        <p class="project-status">{{ trans('projects.preview') }}</p><p class="help-block">{{ trans('projects.preview_help') }}</p>
        <noscript><p class="alert alert-info">{{ trans('projects.javascript_required') }}</p></noscript>
        <nav class="form-stepper" aria-label="{{ trans('projects.issue') }}"><ol class="stepper-list">
        @foreach (['employee_step', 'asset_step', 'acceptance_step', 'handover_step'] as $step)
            <li class="stepper-item {{ $loop->first ? 'is-current' : '' }}" @if($loop->first) aria-current="step" @endif><span class="stepper-button"><span class="stepper-marker">{{ $loop->iteration }}</span><span class="stepper-label">{{ trans('projects.'.$step) }}</span></span></li>
        @endforeach
        </ol></nav>
        <div id="issue-error-summary" class="alert alert-danger" role="alert" tabindex="-1" hidden><strong>{{ trans('projects.fix_fields') }}</strong><ul></ul></div>
        <form id="issue-preview-form" novalidate>
            <section data-issue-panel="0" aria-labelledby="issue-employee-heading">
                <h3 id="issue-employee-heading" tabindex="-1">{{ trans('projects.employee_step') }}</h3>
                <div class="form-group"><label for="issue-worker">{{ trans('projects.worker') }}</label><input class="form-control" id="issue-worker" value="{{ old('worker') }}" required autocomplete="off" maxlength="255" aria-describedby="worker-help issue-worker-error"><p class="help-block" id="worker-help">{{ trans('projects.worker_help') }}</p><p id="issue-worker-error" class="project-field-error" hidden></p></div>
                <div class="form-group"><label for="issue-return">{{ trans('projects.return_by') }}</label><input class="form-control" type="date" id="issue-return" required min="{{ today()->toDateString() }}" max="{{ $project->end_date->toDateString() }}" value="{{ old('expected_checkin', $project->end_date->toDateString()) }}" aria-describedby="issue-return-error"><p id="issue-return-error" class="project-field-error" hidden></p></div>
            </section>
            <section data-issue-panel="1" aria-labelledby="issue-asset-heading" hidden>
                <h3 id="issue-asset-heading" tabindex="-1">{{ trans('projects.asset_step') }}</h3><p class="help-block" id="issue-scan-help">{{ trans('projects.scan_help') }}</p>
                <div class="form-group"><label for="issue-asset">{{ trans('projects.asset') }}</label><input class="form-control" id="issue-asset" value="{{ old('asset') }}" autocomplete="off" maxlength="255" aria-describedby="issue-scan-help issue-asset-error"><p id="issue-asset-error" class="project-field-error" hidden></p></div>
                <p id="issue-scan-status" role="status">{{ trans('projects.scan_ready') }}</p>
                <dl class="project-overview project-scan-result" id="issue-scan-result" hidden><div><dt>{{ trans('projects.scanned_identifier') }}</dt><dd data-issue-value="asset"></dd></div><div><dt>{{ trans('projects.example_type') }}</dt><dd data-issue-value="type"></dd></div></dl>
            </section>
            <section data-issue-panel="2" aria-labelledby="issue-acceptance-heading" hidden>
                <h3 id="issue-acceptance-heading" tabindex="-1">{{ trans('projects.signature') }}</h3><p>{{ trans('projects.acceptance_help') }}</p>
                @include('projects.issue-summary')
                <div class="form-group"><label class="project-issue-check"><input type="checkbox" id="issue-accepted" aria-describedby="issue-accepted-error"> {{ trans('projects.acceptance_confirm') }}</label><p id="issue-accepted-error" class="project-field-error" hidden></p></div>
            </section>
            <section data-issue-panel="3" aria-labelledby="issue-handover-heading" hidden>
                <h3 id="issue-handover-heading" tabindex="-1">{{ trans('projects.handover') }}</h3><p>{{ trans('projects.handover_help') }}</p>
                @include('projects.issue-summary')
                <div class="form-group"><label class="project-issue-check"><input type="checkbox" id="issue-handed-over" aria-describedby="issue-handed-over-error"> {{ trans('projects.handover_confirm') }}</label><p id="issue-handed-over-error" class="project-field-error" hidden></p></div>
            </section>
            <div class="project-issue-footer"><a href="{{ route('projects.issue.index') }}" class="btn btn-default" id="issue-cancel">{{ trans('projects.cancel') }}</a><button type="button" class="btn btn-default" id="issue-back" hidden>{{ trans('projects.back') }}</button><button type="submit" class="btn btn-primary" id="issue-next">{{ trans('projects.next') }}</button></div>
        </form>
        <section id="issue-preview-success" hidden aria-labelledby="issue-success-heading" tabindex="-1">
            <span class="project-status project-status-active">{{ trans('projects.preview') }}</span><h3 id="issue-success-heading">{{ trans('projects.preview_complete') }}</h3><p>{{ trans('projects.preview_complete_help') }}</p>
            @include('projects.issue-summary')
            <div class="project-issue-footer"><a href="{{ route('projects.issue.index') }}" class="btn btn-default">{{ trans('projects.back') }}</a><button type="button" class="btn btn-primary" id="issue-restart">{{ trans('projects.issue_another') }}</button></div>
        </section>
    </div>
</section>
@elseif ($session->closed_at)
<p class="alert alert-info">{{ trans('projects.session_closed') }}</p><a href="{{ route('projects.issue.index') }}" class="btn btn-default">{{ trans('projects.back') }}</a>
@else
@foreach ($pending as $issue)
@php $acceptance = $acceptances->get($issue->acceptance_id); @endphp
<section class="box box-default project-edit"><div class="box-body"><h3>{{ $issue->asset_tag }}</h3><p>{{ $issue->recipient_name }}</p><p>{{ trans($acceptance?->declined_at ? 'projects.declined' : ($acceptance?->accepted_at ? 'projects.pending_handover' : 'projects.pending_acceptance')) }}</p><div class="project-actions"><a href="{{ route('projects.issue.index') }}" class="btn btn-default">{{ trans('projects.cancel') }}</a>
@if ($acceptance?->accepted_at)<form method="post" action="{{ route('projects.issue.handover', [$session->id, $issue->id]) }}">@csrf<button class="btn btn-primary" type="submit">{{ trans('projects.next') }}</button></form>
@elseif (!$acceptance?->declined_at)<a class="btn btn-primary" href="{{ route('projects.issue.sign', [$session->id, $issue->id]) }}">{{ trans('projects.next') }}</a>@endif
<form method="post" action="{{ route('projects.issue.resolve', [$session->id, $issue->id]) }}">@csrf<button type="submit" class="btn btn-default">{{ trans('projects.resolve') }}</button></form></div></div></section>
@endforeach
@endif
@stop
@push('js')<script src="{{ asset('js/projects.js') }}?v=2" nonce="{{ csrf_token() }}"></script><script src="{{ asset('js/project-issue-session.js') }}?v=1" nonce="{{ csrf_token() }}"></script>@endpush
