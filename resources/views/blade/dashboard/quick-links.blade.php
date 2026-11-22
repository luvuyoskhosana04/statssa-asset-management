@php
    $links = [
        ['index', \App\Models\Asset::class, 'hardware.index', 'assets', 'assets', 1],
        ['view', \App\Models\License::class, 'licenses.index', 'licenses', 'licenses', 2],
        ['index', \App\Models\Accessory::class, 'accessories.index', 'accessories', 'accessories', 3],
        ['index', \App\Models\Consumable::class, 'consumables.index', 'consumables', 'consumables', 4],
        ['view', \App\Models\Component::class, 'components.index', 'components', 'components', 5],
        ['index', \App\Models\User::class, 'users.index', 'users', 'users', 6],
    ];
@endphp
<nav class="dashboard-quick-links" aria-label="{{ trans('presentation.dashboard_quick_links') }}">
    @foreach ($links as [$ability, $model, $route, $icon, $label, $shortcut])
        @can($ability, $model)
        <a href="{{ route($route) }}" @if ($snipeSettings->shortcuts_enabled == 1) accesskey="{{ $shortcut }}" @endif>
            <x-icon :type="$icon" class="fa-fw" aria-hidden="true" />
            <span>{{ trans('general.'.$label) }}</span>
        </a>
        @endcan
    @endforeach
</nav>
