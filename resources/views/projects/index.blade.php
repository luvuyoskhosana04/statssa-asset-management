@extends('projects.layout')
@section('title'){{ trans('projects.title') }} @parent @stop
@section('content')
<section class="box box-default">
    <div class="box-header with-border project-toolbar">
        <h2 class="box-title">{{ trans('projects.all') }}</h2>
        <div class="project-actions">
            @can('issue', \App\Models\Project::class)<a class="btn btn-default" href="{{ route('projects.issue.index') }}">{{ trans('projects.issue') }}</a>@endcan
        </div>
    </div>
    <div class="box-body">
        <p class="help-block">{{ trans('projects.local_source') }}</p>
        <form method="get" action="{{ route('projects.index') }}" class="project-filters">
            <div class="project-search"><label for="project-directory">{{ trans('projects.choose_project') }}</label><select id="project-directory" name="project_id" class="form-control"><option value="">{{ trans('projects.all') }}</option>@foreach($projectOptions as $option)<option value="{{ $option->id }}" @selected((int)request('project_id') === $option->id)>{{ $option->name }} ({{ $option->code }})</option>@endforeach</select></div><div><label for="project-status">{{ trans('general.status') }}</label><select id="project-status" name="status" class="form-control">
                @foreach (['active', 'planned', 'suspended', 'ended', 'all'] as $status)<option value="{{ $status }}" @selected(request('status', 'active') === $status)>{{ trans('projects.'.$status) }}</option>@endforeach
            </select></div>
            <div class="project-search"><label for="project-search">{{ trans('general.search') }}</label><input class="form-control" id="project-search" type="search" name="search" maxlength="255" placeholder="{{ trans('projects.search') }}" value="{{ request('search') }}"></div>
            <button class="btn btn-default" type="submit">{{ trans('general.search') }}</button>
        </form>
        <div class="table-responsive"><table class="table table-striped project-table"><thead><tr><th scope="col">{{ trans('projects.title') }}</th><th scope="col">{{ trans('projects.dates') }}</th><th scope="col">{{ trans('general.status') }}</th><th scope="col">{{ trans('projects.office') }}</th><th scope="col">{{ trans('projects.assets') }}</th>@can('view', \App\Models\User::class)<th scope="col">{{ trans('projects.people') }}</th>@endcan<th scope="col">{{ trans('general.action') }}</th></tr></thead>
            <tbody>@forelse ($projects as $project)<tr><th scope="row"><a href="{{ route('projects.show', $project) }}">{{ $project->name }}</a><small class="text-muted">{{ $project->code }}</small></th><td>{{ $project->present()->dates() }}</td><td><span class="project-status project-status-{{ $project->status }}">{{ $project->present()->status() }}</span></td><td>{{ $project->location?->name ?? trans('general.unassigned') }}</td><td>{{ number_format($project->assets_count) }}</td>@can('view', \App\Models\User::class)<td>{{ number_format($project->members_count) }}</td>@endcan<td><a class="btn btn-default btn-sm" href="{{ route('projects.show', $project) }}">{{ trans('projects.view') }}</a></td></tr>
            @empty<tr><td colspan="7" class="project-empty"><p>{{ trans('projects.empty') }}</p>@can('create', \App\Models\Project::class)<p class="help-block">{{ trans('projects.empty_help') }}</p>@endcan</td></tr>@endforelse</tbody>
        </table></div>
    </div>
    <div class="box-footer">{{ $projects->links() }}</div>
</section>
@stop
