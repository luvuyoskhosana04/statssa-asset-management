@props([
    'name' => null,
    'label' => null,
    'count' => 0,
    'icon' => null,
    'icon_style' => null,
    'tooltip' => null,
    'icon_type' => null,
])

<!-- start tab nav item -->
<li {{ $attributes->merge(['class' => 'snipetab']) }} role="presentation">


    <a href="#{{ $name ?? 'details' }}" data-toggle="tab" data-tooltip="true" title="{{ $tooltip ?? $label }}">

        @if ($icon)
            <i class="{{ $icon }} fa-fw" aria-hidden="true"></i>
        @elseif ($icon_type)
            <x-icon type="{{ $icon_type }}" class="fa-fw" />
        @endif
        <span class="tab-text">{{ $label ?? $tooltip }}</span>

        @if ($count > 0)
            <span class="badge">{{ number_format($count) }}</span>
        @endif

    </a>
</li>
<!-- end tab nav item -->