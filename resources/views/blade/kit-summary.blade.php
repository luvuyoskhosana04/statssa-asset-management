@props(['kit'])
<section class="presentation-kit-summary">
    <h3>{{ trans('presentation.kit_summary') }}: {{ $kit->name }}</h3>
    <p class="help-block">{{ trans('presentation.kit_help') }}</p>
    <ul class="presentation-kit-items">
        @forelse ($kit->present()->bundleContents() as $content)
            <li>
                <span><strong>{{ $content['name'] }}</strong><small>{{ $content['type'] }}</small></span>
                <span>{{ trans('general.quantity') }}: {{ $content['quantity'] }}</span>
                <span class="presentation-state {{ $content['available'] < $content['quantity'] ? 'state-warning' : 'state-neutral' }}">{{ trans('presentation.available') }}: {{ $content['available'] }}</span>
            </li>
        @empty
            <li>{{ trans('presentation.no_selection') }}</li>
        @endforelse
    </ul>
</section>
