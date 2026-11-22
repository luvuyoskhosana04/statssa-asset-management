@php
    $quickActionItems = collect([
        ['ability' => 'index', 'model' => \App\Models\Asset::class, 'route' => 'hardware', 'icon' => 'assets', 'plural' => 'assets', 'singular' => 'asset'],
        ['ability' => 'view', 'model' => \App\Models\License::class, 'route' => 'licenses', 'icon' => 'licenses', 'plural' => 'licenses', 'singular' => 'license'],
        ['ability' => 'index', 'model' => \App\Models\Accessory::class, 'route' => 'accessories', 'icon' => 'accessories', 'plural' => 'accessories', 'singular' => 'accessory'],
        ['ability' => 'index', 'model' => \App\Models\Consumable::class, 'route' => 'consumables', 'icon' => 'consumables', 'plural' => 'consumables', 'singular' => 'consumable'],
        ['ability' => 'view', 'model' => \App\Models\Component::class, 'route' => 'components', 'icon' => 'components', 'plural' => 'components', 'singular' => 'component'],
        ['ability' => 'index', 'model' => \App\Models\User::class, 'route' => 'users', 'icon' => 'users', 'plural' => 'users', 'singular' => 'user'],
    ]);
    $quickViewItems = $quickActionItems->filter(fn ($item) => Gate::allows($item['ability'], $item['model']));
    $quickCreateItems = Gate::allows('admin') ? $quickActionItems->filter(fn ($item) => Gate::allows('create', $item['model'])) : collect();
@endphp
@if ($quickViewItems->isNotEmpty() || $quickCreateItems->isNotEmpty())
<li class="header-quick-actions" id="header-quick-actions">
    <button type="button" class="header-quick-actions-trigger" id="quick-actions-trigger" aria-expanded="false" aria-controls="quick-actions-panel">
        {{ trans('presentation.header_quick_actions') }}<span class="caret" aria-hidden="true"></span>
    </button>
    <nav class="header-quick-actions-panel" id="quick-actions-panel" aria-label="{{ trans('presentation.header_quick_actions') }}" aria-hidden="true" inert>
        @foreach (['view' => $quickViewItems, 'create' => $quickCreateItems] as $group => $items)
            @if ($items->isNotEmpty())
            <div class="quick-actions-group" data-quick-action-group="{{ $group }}">
                <h2>{{ trans('presentation.header_'.$group) }}</h2>
                <ul>
                    @foreach ($items as $item)
                    <li><a href="{{ route($item['route'].($group === 'view' ? '.index' : '.create')) }}">
                        <x-icon :type="$item['icon']" class="fa-fw" aria-hidden="true" />
                        <span>{{ trans('general.'.$item[$group === 'view' ? 'plural' : 'singular']) }}</span>
                    </a></li>
                    @endforeach
                </ul>
            </div>
            @endif
        @endforeach
    </nav>
</li>
@endif
