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
                <label for="role">{{ trans('auth/role-login.role') }}</label>
                <div class="sign-in-controls">
                    <div class="select-wrap">
                        <select id="role" name="role" required aria-describedby="role-hint{{ $errors->has('role') ? ' role-error' : '' }}" @if($errors->has('role')) aria-invalid="true" @endif>
                            <option value="" disabled @selected(! old('role'))>{{ trans('auth/role-login.placeholder') }}</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role }}" @selected(old('role') === $role)>{{ trans('auth/role-login.roles.'.$role) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="sign-in-button">
                        <span id="sign-in-label">{{ trans('auth/role-login.sign_in') }}</span>
                        <span class="button-arrow" aria-hidden="true">↗</span>
                    </button>
                </div>
                @error('role')
                    <p class="role-error" id="role-error" role="alert">{{ $message }}</p>
                @enderror
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
