@extends('projects.layout')
@section('title'){{ trans('projects.issue') }} @parent @stop
@section('content')
@php $tab = request('tab') === 'allocations' ? 'allocations' : 'sessions'; @endphp
<section class="box box-default project-start-panel">
    <div class="box-header with-border"><h2 class="box-title">{{ trans('projects.start_session') }}</h2></div>
    <form class="box-body project-start-form" method="post" action="{{ route('projects.issue.start') }}">
        @csrf
        <p class="help-block">{{ trans('projects.session_help') }}</p>
        <div class="project-start-controls">
            <div class="form-group"><label for="issue-project">{{ trans('projects.title') }}</label><select id="issue-project" name="project_id" class="form-control" required><option value="">{{ trans('general.select') }}</option>@foreach ($projects as $project)<option value="{{ $project->id }}">{{ $project->name }} ({{ $project->code }})</option>@endforeach</select></div>
            <div class="project-actions"><button class="btn btn-primary" type="submit" @disabled($projects->isEmpty())>{{ trans('projects.start_session') }}</button><a href="{{ route('projects.index') }}" class="btn btn-link">{{ trans('projects.all') }}</a></div>
        </div>
    </form>
</section>
<section class="box box-default project-issue-history">
    <nav class="project-tabs" aria-label="{{ trans('projects.issue') }}">
        <a id="project-sessions-tab" href="{{ route('projects.issue.index', ['tab' => 'sessions']) }}" @if($tab === 'sessions') aria-current="page" @endif>{{ trans('projects.sessions') }}</a>
        <a id="project-allocations-tab" href="{{ route('projects.issue.index', ['tab' => 'allocations']) }}" @if($tab === 'allocations') aria-current="page" @endif>{{ trans('projects.allocations') }}</a>
    </nav>
    @if($tab === 'sessions')
<div class="box-body"><div class="table-responsive"><table class="table table-striped project-table" aria-labelledby="project-sessions-tab"><thead><tr><th scope="col">{{ trans('projects.title') }}</th><th scope="col">{{ trans('general.date') }}</th><th scope="col">{{ trans('general.status') }}</th><th scope="col">{{ trans('general.action') }}</th></tr></thead><tbody>@forelse ($sessions as $session)<tr><th scope="row">{{ $session->project_name }}</th><td><time datetime="{{ \Carbon\Carbon::parse($session->created_at)->toIso8601String() }}" data-project-time>{{ $session->created_at }}</time></td><td>{{ trans($session->closed_at ? 'projects.closed' : 'projects.open') }}</td><td><a class="btn btn-default btn-sm" href="{{ route('projects.issue.audit', $session->id) }}">{{ trans('projects.session') }}</a></td></tr>@empty<tr><td colspan="4" class="project-empty">{{ trans('projects.no_sessions') }}</td></tr>@endforelse</tbody></table></div>{{ $sessions->appends(['tab' => 'sessions'])->links() }}</div>
    @else
<div class="box-body"><div class="table-responsive"><table class="table table-striped project-table" aria-labelledby="project-allocations-tab"><thead><tr><th>{{ trans('projects.title') }}</th><th>{{ trans('general.asset_tag') }}</th><th>{{ trans('projects.recipient') }}</th><th>{{ trans('projects.return_by') }}</th><th>{{ trans('general.status') }}</th><th>{{ trans('general.action') }}</th></tr></thead><tbody>@forelse($allocations as $allocation)<tr><td>{{ $allocation->project_name }}</td><td>{{ $allocation->asset_tag }}</td><td>{{ $allocation->recipient_name }}</td><td>{{ $allocation->expected_checkin }}</td><td>{{ trans($allocation->declined_at ? 'projects.rejected_status' : ($allocation->accepted_at ? 'projects.accepted_status' : ($allocation->cancelled_at ? 'projects.cancelled' : 'projects.pending_acceptance'))) }}</td><td><a class="btn btn-default btn-sm" href="{{ route('projects.issue.audit', $allocation->session_id) }}">{{ trans('projects.investigate') }}</a></td></tr>@empty<tr><td colspan="6">{{ trans('projects.no_issues') }}</td></tr>@endforelse</tbody></table></div>{{ $allocations->appends(['tab' => 'allocations'])->links() }}</div>
    @endif
</section>
@stop
@push('js')<script src="{{ asset('js/projects.js') }}?v=1" nonce="{{ csrf_token() }}"></script>@endpush
