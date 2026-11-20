@extends('projects.layout')
@section('title'){{ trans($project->exists ? 'projects.edit' : 'projects.create') }} @parent @stop
@section('content')
<form class="box box-default project-edit" method="post" action="{{ $project->exists ? route('projects.update', $project) : route('projects.store') }}">
    @csrf @if ($project->exists) @method('PUT') @endif
    <div class="box-body">
        <p class="help-block">{{ trans('projects.local_source') }}</p>
        <div class="form-group"><label for="project-name">{{ trans('general.name') }}</label><input class="form-control" id="project-name" name="name" value="{{ old('name', $project->name) }}" required maxlength="255"></div>
        <div class="form-group"><label for="project-code">{{ trans('projects.code') }}</label><input class="form-control" id="project-code" name="code" value="{{ old('code', $project->code) }}" required maxlength="100"></div>
        <div class="row"><div class="col-sm-6 form-group"><label for="project-start">{{ trans('projects.start_date') }}</label><input class="form-control" type="date" id="project-start" name="start_date" value="{{ old('start_date', $project->start_date?->toDateString()) }}" required></div><div class="col-sm-6 form-group"><label for="project-end">{{ trans('projects.end_date') }}</label><input class="form-control" type="date" id="project-end" name="end_date" value="{{ old('end_date', $project->end_date?->toDateString()) }}" required></div></div>
        <div class="form-group"><label for="project-state">{{ trans('general.status') }}</label><select class="form-control" id="project-state" name="status">@foreach (['planned', 'active', 'suspended', 'ended'] as $state)<option value="{{ $state }}" @selected(old('status', $project->status) === $state)>{{ trans('projects.'.$state) }}</option>@endforeach</select></div>
        <div class="form-group"><label for="project-company">{{ trans('general.company') }}</label><select class="form-control" id="project-company" name="company_id"><option value="">{{ trans('general.unassigned') }}</option>@foreach ($companies as $company)<option value="{{ $company->id }}" @selected((int) old('company_id', $project->company_id) === $company->id)>{{ $company->name }}</option>@endforeach</select></div>
        <div class="form-group"><label for="project-location">{{ trans('projects.office') }}</label><select class="form-control" id="project-location" name="location_id"><option value="">{{ trans('general.unassigned') }}</option>@foreach ($locations as $location)<option value="{{ $location->id }}" @selected((int) old('location_id', $project->location_id) === $location->id)>{{ $location->name }}</option>@endforeach</select></div>
        <div class="form-group"><label for="project-notes">{{ trans('general.notes') }}</label><textarea class="form-control" rows="4" id="project-notes" name="notes" maxlength="10000">{{ old('notes', $project->notes) }}</textarea></div>
    </div>
    <div class="box-footer project-toolbar"><a class="btn btn-default" href="{{ $project->exists ? route('projects.show', $project) : route('projects.index') }}">{{ trans('button.cancel') }}</a><button type="submit" class="btn btn-primary">{{ trans('general.save') }}</button></div>
</form>
@stop
