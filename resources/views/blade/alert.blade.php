@props([
    'type' => 'info',
    'icon' => null,
    'title' => null,
    // 'alert' fits error/warning contexts; 'status' is more appropriate for
    // success/info notifications that shouldn't interrupt screen readers.
    'role' => 'alert',
])
@php
    $statusKey = match ($type) {
        'success' => 'banner_success',
        'warning' => 'banner_warning',
        'danger', 'error' => 'banner_error',
        default => 'banner_info',
    };
    $genericTitles = [trans('general.notification_error'), trans('general.notification_success'), trans('general.error'), trans('general.warning'), trans('general.info'), trans('general.notification_warning'), trans('general.notification_info')];
@endphp
<div {{ $attributes->merge(['class' => 'alert alert-'.$type.' system-banner fade in']) }} role="{{ $role }}">
    <span class="banner-status">{{ trans('presentation.'.$statusKey) }}</span>
    <div class="banner-content">
        @if ($title && ! in_array($title, $genericTitles, true))
            <strong class="banner-title">{{ $title }}</strong>
        @endif
        {{ $slot }}
    </div>
</div>
