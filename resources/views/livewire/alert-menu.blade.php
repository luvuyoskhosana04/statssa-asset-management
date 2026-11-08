<li class="dropdown tasks-menu" wire:ignore.self wire:poll.60s.visible>
    <a href="#" class="dropdown-toggle" data-toggle="dropdown" wire:click.prevent="$refresh" wire:ignore.self aria-haspopup="true" aria-expanded="false" aria-controls="stock-alert-panel">
        <x-icon type="alerts" />
        <span class="sr-only">{{ trans('general.alerts') }}</span>
        <span class="label label-danger" @if (($alert_count ?? 0) === 0) aria-hidden="true" style="visibility:hidden" @endif>{{ ($alert_count ?? 0) ?: '0' }}</span>
    </a>
    <ul class="dropdown-menu stock-alert-panel" id="stock-alert-panel" aria-labelledby="stock-alert-title" wire:ignore.self>
        <li class="stock-alert-header">
            <div>
                <h2 id="stock-alert-title">{{ trans('presentation.stock_alerts_title') }}</h2>
                <p>{{ trans('presentation.stock_alerts_description') }}</p>
            </div>
            <button type="button" class="btn btn-default stock-alert-refresh" wire:click.stop="$refresh" wire:loading.attr="disabled" aria-label="{{ trans('presentation.stock_alerts_refresh') }}">
                <x-icon type="sync" />
            </button>
        </li>
        <li wire:offline class="stock-alert-offline" role="status">{{ trans('presentation.stock_alerts_offline') }}</li>
        <li class="stock-alert-scroll">
            <ul class="stock-alert-list">
                @forelse (array_slice($alert_items, 0, 50) as $alert_item)
                    @php
                        $available = (int) $alert_item['remaining'];
                        $minimum = (int) ($alert_item['min_amt'] ?? 0);
                        $threshold = $minimum + (int) $snipeSettings->alert_threshold;
                        $percent = max(0, min(100, round($available / max(1, $threshold) * 100)));
                        $state = $available <= 0 ? 'empty' : ($available < $minimum ? 'low' : 'soon');
                        $url = route($alert_item['type'].'.show', $alert_item['id']);
                    @endphp
                    <li wire:key="stock-alert-{{ $alert_item['type'] }}-{{ $alert_item['id'] }}">
                        <article class="stock-alert-card stock-alert-{{ $state }}">
                            <div class="stock-alert-identity">
                                <span class="stock-alert-type">{{ $alert_item['type'] === 'models' ? trans('presentation.stock_alerts_model_type') : trans('general.'.$alert_item['type']) }}</span>
                                <a class="stock-alert-name" href="{{ $url }}">{{ $alert_item['name'] }}</a>
                                <span class="stock-alert-state">{{ trans('presentation.stock_alerts_'.$state) }}</span>
                            </div>
                            <div class="stock-alert-quantity"><strong>{{ number_format($available) }}</strong><span>{{ trans('presentation.stock_alerts_available') }}</span></div>
                            <div class="progress stock-alert-progress">
                                <div class="progress-bar" style="width:{{ $percent }}%" role="progressbar" aria-label="{{ trans('presentation.stock_alerts_meter') }}" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <p class="stock-alert-minimum">{{ trans('presentation.stock_alerts_minimum', ['minimum' => $minimum, 'threshold' => $threshold]) }}</p>
                            <a class="btn btn-default stock-alert-investigate" href="{{ $url }}" aria-label="{{ trans('presentation.stock_alerts_investigate_item', ['name' => $alert_item['name']]) }}">{{ trans('presentation.stock_alerts_investigate') }}</a>
                        </article>
                    </li>
                @empty
                    <li class="stock-alert-empty"><strong>{{ trans('presentation.stock_alerts_clear') }}</strong><p>{{ trans('presentation.stock_alerts_clear_help') }}</p></li>
                @endforelse
            </ul>
            @can('superadmin')
                @if ($deprecations)
                    <div class="stock-alert-system">
                        <h3>{{ trans('presentation.stock_alerts_system') }}</h3>
                        @foreach ($deprecations as $key => $deprecation)
                            @if ($deprecation['check'])
                                <div class="alert alert-warning" wire:key="stock-notice-{{ $key }}">{!! $deprecation['message'] !!}</div>
                            @endif
                        @endforeach
                    </div>
                @endif
            @endcan
        </li>
        <li class="stock-alert-footer">
            <span>@if (count($alert_items) > 50){{ trans('presentation.stock_alerts_shown', ['shown' => 50, 'total' => count($alert_items)]) }}@else{{ trans_choice('presentation.stock_alerts_count', count($alert_items), ['count' => count($alert_items)]) }}@endif</span>
            @if (count($alert_items) > 3)<small class="stock-alert-scroll-hint">{{ trans('presentation.stock_alerts_scroll') }}</small>@endif
            <span wire:loading.delay role="status">{{ trans('presentation.stock_alerts_updating') }}</span>
            <p>{{ trans('presentation.stock_alerts_freshness') }}</p>
        </li>
    </ul>
</li>
