@extends('layouts/welcome')

@section('welcome-intro')
    @include('auth.partials.welcome-intro')
@endsection

@section('content')
    <div class="welcome-grid">
        <section class="welcome-copy" aria-labelledby="welcome-heading">
            <h1 id="welcome-heading">
                <span class="welcome-intro">{{ trans('auth/role-login.welcome') }} <span class="platform-name">{{ trans('auth/role-login.brand') }}</span></span>
                <span class="solution-name">{{ trans('auth/role-login.platform') }} <span class="solution-word">{{ trans('auth/role-login.solution_word') }}</span></span>
            </h1>
            <p class="welcome-description">{{ trans('auth/role-login.description') }}</p>
            <form class="role-form" action="{{ route('login') }}" method="POST">
                @csrf
                <div class="credential-field">
                    <label for="username">{{ trans('general.username') }}</label>
                    <input id="username" name="username" type="text" value="{{ old('username') }}" placeholder="{{ trans('auth/role-login.username_example') }}" autocomplete="username" required autocapitalize="none" spellcheck="false" @if($errors->has('username')) aria-invalid="true" aria-describedby="username-error" @endif>
                    @error('username')
                        <p class="role-error" id="username-error" role="alert">{{ $message }}</p>
                    @enderror
                </div>
                <div class="credential-field">
                    <label for="password">{{ trans('general.password') }}</label>
                    <input id="password" name="password" type="password" placeholder="{{ trans('auth/role-login.password_placeholder') }}" autocomplete="current-password" required @if($errors->has('password')) aria-invalid="true" aria-describedby="password-error" @endif>
                    @error('password')
                        <p class="role-error" id="password-error" role="alert">{{ $message }}</p>
                    @enderror
                </div>
                <div class="credential-actions">
                    <a href="{{ $snipeSettings->custom_forgot_pass_url ?: route('password.request') }}">{{ trans('auth/general.forgot_password') }}</a>
                    <button type="submit" class="sign-in-button">
                        <span id="sign-in-label">{{ trans('auth/role-login.sign_in') }}</span>
                        <span class="button-arrow" aria-hidden="true">↗</span>
                    </button>
                </div>
                @if (session('error'))
                    <p class="role-error" role="alert">{{ session('error') }}</p>
                @endif
                <p class="role-hint" id="role-hint">{{ trans('auth/role-login.hint') }}</p>
            </form>
        </section>
        <figure class="welcome-artwork">
            <img src="{{ asset('img/welcome-assets.png') }}?v=4" width="1254" height="1254" alt="{{ trans('auth/role-login.artwork_alt') }}" fetchpriority="high">
        </figure>
    </div>
@endsection

@section('scripts')
    @include('auth.partials.welcome-intro-script')
    <script nonce="{{ csrf_token() }}">
        document.querySelector('.role-form').addEventListener('submit', function () {
            var button = this.querySelector('button');
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            document.getElementById('sign-in-label').textContent = @json(trans('auth/role-login.signing_in'));
        });
        window.addEventListener('pageshow', function () {
            var button = document.querySelector('.sign-in-button');
            button.disabled = false;
            button.removeAttribute('aria-busy');
            document.getElementById('sign-in-label').textContent = @json(trans('auth/role-login.sign_in'));
        });
    </script>
@endsection
