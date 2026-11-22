@extends('layouts/setup')
@section('title'){{ trans('general.create_admin_user') }} @parent @stop
@section('content')
<p>{{ trans('branding.setup_ready') }}</p>
<div class="project-actions">
    <a class="btn btn-primary" href="{{ route('home') }}">{{ trans('branding.dashboard') }}</a>
    <a class="btn btn-default" href="{{ route('statssa.guide') }}">{{ trans('branding.guide') }}</a>
</div>
@stop
