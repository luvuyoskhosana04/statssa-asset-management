@extends('layouts/basic')
@section('title'){{ trans('branding.notices') }} @parent @stop
@section('content')
<article style="max-width:800px;margin:32px auto;padding:24px">
    <h1>{{ trans('branding.notices') }}</h1>
    <p>{{ trans('branding.upstream_credit') }}</p>
    <p>{{ trans('branding.no_warranty') }}</p>
    <p><a href="{{ asset('legal/LICENSE.txt') }}">{{ trans('branding.license') }}</a></p>
    <p><a href="https://github.com/luvuyoskhosana04/statssa-asset-management" rel="noopener">{{ trans('branding.source') }}</a></p>
</article>
@stop
