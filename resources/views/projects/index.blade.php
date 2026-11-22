@extends('projects.layout')
@section('title'){{ trans('projects.title') }} @parent @stop
@section('content')
<section class="box box-default">
    <div class="box-header with-border project-toolbar">
        <nav class="project-tabs project-index-tabs" aria-label="{{ trans('projects.title') }}">
            <a href="{{ route('projects.index') }}" @if($tab === 'projects') aria-current="page" @endif>{{ trans('projects.all') }}</a>
            <a href="{{ route('projects.index', ['tab' => 'analytics']) }}" @if($tab === 'analytics') aria-current="page" @endif>{{ trans('projects.analytics_tab') }}</a>
        </nav>
        <div class="project-actions">
            @can('issue', \App\Models\Project::class)<a class="btn btn-primary" href="{{ route('projects.issue.index') }}">{{ trans('projects.issue') }}</a>@endcan
        </div>
    </div>
    <div class="box-body">
        @if($tab === 'analytics')
            @include('projects.partials.analytics')
        @else
        <p class="help-block">{{ trans('projects.local_source') }}</p>
        <table id="projectsListingTable" data-cookie-id-table="projectsListingTable" class="table table-striped snipe-table project-table"
               data-url="{{ route('projects.index', ['table' => 1]) }}" data-side-pagination="server"
               data-pagination="true" data-search="true" data-show-refresh="true" data-sort-name="name" data-sort-order="asc">
            <thead><tr>
                <th data-field="name" data-sortable="true" data-formatter="projectNameFormatter">{{ trans('projects.title') }}</th>
                <th data-field="start_date" data-sortable="true">{{ trans('projects.start_date') }}</th>
                <th data-field="end_date" data-sortable="true">{{ trans('projects.end_date') }}</th>
                <th data-field="status" data-sortable="true" data-formatter="projectStatusFormatter">{{ trans('general.status') }}</th>
                <th data-field="office" data-sortable="true" data-formatter="projectTextFormatter">{{ trans('projects.office') }}</th>
                <th data-field="assets_count" data-sortable="true">{{ trans('projects.assets') }}</th>
                @can('view', \App\Models\User::class)<th data-field="members_count" data-sortable="true">{{ trans('projects.people') }}</th>@endcan
                <th data-field="actions" data-searchable="false" data-formatter="projectViewFormatter">{{ trans('general.action') }}</th>
            </tr></thead>
        </table>
        @endif
    </div>
</section>
@stop
@section('moar_scripts')
<script src="{{ asset('js/projects.js') }}?v=4" nonce="{{ csrf_token() }}"></script>
@if($tab === 'projects')
@include('partials.bootstrap-table', ['exportFile' => 'projects-export', 'search' => true])
@endif
@stop
