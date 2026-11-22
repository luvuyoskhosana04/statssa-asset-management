@extends('layouts/default')
@section('title'){{ trans('branding.guide') }} @parent @stop
@section('content')
<section class="box box-default">
    <div class="box-header with-border"><h2 class="box-title">{{ trans('branding.guide') }}</h2></div>
    <div class="box-body">
        <p>{{ trans('branding.guide_intro') }}</p>
        @foreach(['assets', 'projects', 'audits', 'imports', 'access', 'api', 'support'] as $topic)
            <section id="{{ $topic }}" style="margin-top:28px;max-width:800px"><h3>{{ trans('branding.'.$topic) }}</h3><p>{{ trans('branding.'.$topic.'_help') }}</p></section>
        @endforeach
        <p><a href="mailto:statssa@assetverification.co.za">{{ trans('branding.support') }}</a></p>
        <a href="{{ route('statssa.notices') }}">{{ trans('branding.notices') }}</a>
    </div>
</section>
@stop
