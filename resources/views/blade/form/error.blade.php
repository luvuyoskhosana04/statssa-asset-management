@props(['name'])
@error($name)
    <span data-server-error class="alert-msg" role="alert" aria-live="assertive">{{ $message }}</span>
@enderror
