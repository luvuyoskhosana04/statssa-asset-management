<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ Helper::determineLanguageDirection() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ trans('auth/role-login.title') }}</title>
    <meta name="theme-color" content="#e2d7bf">
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('css/welcome.css') }}?v=10">
    <link rel="stylesheet" href="{{ asset('css/stats-sa-theme.css') }}?v=28">
</head>
<body class="stats-sa-welcome">
    <a class="skip-link" href="#username">{{ trans('auth/role-login.skip') }}</a>
    <div class="page-shell">
        <header class="welcome-header">
            <div class="wordmark">
                <img class="official-logo" src="{{ asset('img/stats-sa-logo.png') }}" alt="{{ trans('auth/role-login.organisation_logo') }}" width="632" height="242">
            </div>
            <span class="header-note">{{ trans('auth/role-login.header_note') }}</span>
        </header>
        <main id="main">@yield('content')</main>
        <footer class="welcome-footer">
            <a class="support-link" href="mailto:statssa@assetverification.co.za">{{ trans('auth/role-login.contact_support') }}</a>
            <span>{{ trans('auth/role-login.footer') }}</span>
        </footer>
    </div>
    @yield('welcome-intro')
    @yield('scripts')
</body>
</html>
