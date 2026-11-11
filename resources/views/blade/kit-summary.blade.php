@props(['kit'])
@php
    $contents = collect($kit->present()->bundleContents());
    $readyGroups = $contents->filter(fn ($content) => $content['available'] >= $content['quantity'])->count();
    $hasShortage = $readyGroups < $contents->count();
@endphp
<section class="presentation-kit-summary kit-stock-panel" aria-label="{{ trans('presentation.kit_summary') }}">
    <div class="kit-summary-heading">
        <div>
            <p class="kit-eyebrow">{{ trans('admin/kits/general.stock_snapshot') }}</p>
            <h3>{{ trans('presentation.kit_summary') }}</h3>
            <p class="kit-summary-name">{{ $kit->name }}</p>
        </div>
        <span class="kit-stock-badge {{ $hasShortage ? 'kit-stock-warning' : '' }}">{{ trans('admin/kits/general.'.($contents->isEmpty() ? 'stock_empty' : ($hasShortage ? 'stock_short' : 'stock_ready'))) }}</span>
    </div>
    @if ($contents->isNotEmpty())
        <div class="kit-counts">
            <span>{{ trans('admin/kits/general.item_groups', ['count' => $contents->count()]) }}</span>
            <span>{{ trans('admin/kits/general.units_requested', ['count' => $contents->sum('quantity')]) }}</span>
        </div>
        <p class="kit-stock-progress">{{ trans('admin/kits/general.groups_available', ['ready' => $readyGroups, 'total' => $contents->count()]) }}</p>
    @endif
    <ul class="presentation-kit-items kit-checklist">
        @forelse ($contents as $content)
            <li>
                <div class="kit-item-identity"><strong>{{ $content['name'] }}</strong><small>{{ $content['type'] }}</small></div>
                <div class="kit-item-quantities">
                    <span>{{ trans('general.quantity') }}: {{ $content['quantity'] }}</span>
                    <span>{{ trans('presentation.available') }}: {{ $content['available'] }}</span>
                </div>
                <span class="kit-stock-badge {{ $content['available'] < $content['quantity'] ? 'kit-stock-warning' : '' }}">{{ $content['available'] < $content['quantity'] ? trans('admin/kits/general.short_by', ['count' => $content['quantity'] - $content['available']]) : trans('admin/kits/general.stock_available') }}</span>
            </li>
        @empty
            <li class="kit-empty-state"><strong>{{ trans('admin/kits/general.stock_empty') }}</strong><p>{{ trans('admin/kits/general.empty_help') }}</p></li>
        @endforelse
    </ul>
    <p class="kit-stock-footnote">{{ trans('admin/kits/general.stock_scope') }}</p>
</section>
