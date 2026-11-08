@props([
    'type' => 'info',
    'icon' => null,
    'live' => 'polite',
    // 'alert' by default; callers can pass role="status" for polite info
    // callouts. Made a prop rather than passthrough so we don't emit two
    // role= attributes on the tag.
    'role' => 'alert',
])
@php
    $statusKey = match ($type) {
        'success' => 'banner_success',
        'warning' => 'banner_warning',
        'danger', 'error' => 'banner_error',
        default => 'banner_info',
    };
@endphp
<div {{ $attributes->merge(['class' => 'callout callout-'.$type.' system-banner']) }} role="{{ $role }}" aria-live="{{ $live }}" aria-atomic="true">
    <span class="banner-status">{{ trans('presentation.'.$statusKey) }}</span>
    <div class="banner-content">{{ $slot }}</div>
</div>
