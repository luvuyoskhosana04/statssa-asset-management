@props([
'class' => 'col-md-12',
])

<!-- Start column component -->
<div class="ui-page-column {{ $class }}" {{ $attributes->merge(['style' => '']) }}>
    {{ $slot }}
</div>
