<div class="project-analytics" id="all-project-analytics">
    <div class="analytics-intro">
        <div><p class="analytics-eyebrow">{{ trans('projects.analytics_eyebrow') }}</p><h2>{{ trans('projects.analytics_heading') }}</h2><p class="help-block">{{ trans('projects.analytics_scope') }}</p></div>
        <form method="get" action="{{ route('projects.index') }}" class="analytics-currency">
            <input type="hidden" name="tab" value="analytics">
            <label for="analytics-currency">{{ trans('projects.analytics_currency') }}</label>
            <select id="analytics-currency" name="currency" class="form-control">@foreach($analytics['currencies'] as $currency)<option value="{{ $currency }}" @selected($currency === $analytics['currency'])>{{ $currency }}</option>@endforeach</select>
            <button class="btn btn-default" type="submit">{{ trans('projects.analytics_apply') }}</button>
        </form>
    </div>
    <section class="analytics-value" aria-label="{{ trans('projects.analytics_total_value') }}">
        <div><span class="analytics-eyebrow">{{ trans('projects.analytics_total_value') }}</span><p class="analytics-total"><span>{{ $analytics['currency'] }}</span> {{ number_format($analytics['value'] / 100, 2) }}</p><p>{{ trans('projects.analytics_cost_basis') }}</p></div>
        <dl><div><dt>{{ trans('projects.title') }}</dt><dd>{{ number_format($analytics['projects']) }}</dd></div><div><dt>{{ trans('projects.assets') }}</dt><dd>{{ number_format($analytics['assets']) }}</dd></div>@if($analytics['accessories'] !== null)<div><dt>{{ trans('general.accessories') }}</dt><dd>{{ number_format($analytics['accessories']) }}</dd></div>@endif<div><dt>{{ trans('projects.analytics_missing_cost') }}</dt><dd>{{ number_format($analytics['missing_costs']) }}</dd></div></dl>
    </section>
    <div class="analytics-signals">
        <section><h3>{{ trans('projects.analytics_returns') }}</h3><p class="analytics-signal-value">{{ number_format($analytics['pending_returns']) }} <small>{{ trans('projects.analytics_units') }}</small></p><p>{{ trans('projects.analytics_overdue', ['count' => number_format($analytics['overdue'])]) }}</p></section>
        @foreach(['lost', 'damaged', 'transit'] as $signal)<section><h3>{{ trans('projects.analytics_'.$signal) }}</h3><p class="analytics-not-tracked">{{ trans('projects.analytics_not_tracked') }}</p></section>@endforeach
    </div>
    <div class="analytics-charts">
        <section class="analytics-chart"><h3>{{ trans('projects.analytics_by_project') }}</h3><p class="help-block">{{ $analytics['currency'] }} · {{ trans('projects.analytics_no_conversion') }}</p>
            @php($maxValue = max(1, collect($analytics['rows'])->max('value') ?? 0))
            <ol class="analytics-project-values">@forelse($analytics['rows'] as $row)<li><div><a href="{{ $row['url'] }}">{{ $row['name'] }}</a><strong>{{ number_format($row['value'] / 100, 2) }}</strong></div><span class="analytics-value-track" aria-hidden="true"><span style="width:{{ round($row['value'] / $maxValue * 100, 2) }}%"></span></span></li>@empty<li class="help-block">{{ trans('projects.analytics_empty') }}</li>@endforelse</ol>
        </section>
        <section class="analytics-chart"><h3>{{ trans('projects.analytics_where') }}</h3><p class="help-block">{{ trans('projects.analytics_stage_help') }}</p>
            <div class="analytics-stage-strip" aria-hidden="true">@foreach($analytics['stages'] as $stage => $count)@if($count)<span class="analytics-stage-{{ $stage }}" style="flex:{{ $count }}"></span>@endif@endforeach</div>
            <dl class="analytics-stage-list">@foreach($analytics['stages'] as $stage => $count)<div><dt><span class="analytics-stage-dot analytics-stage-{{ $stage }}" aria-hidden="true"></span>{{ trans('projects.analytics_stage_'.$stage) }}</dt><dd>{{ number_format($count) }} <small>{{ $analytics['assets'] ? number_format($count / $analytics['assets'] * 100, 1) : 0 }}%</small></dd></div>@endforeach</dl>
        </section>
    </div>
    <section class="analytics-risk"><div><h3>{{ trans('projects.analytics_risk') }}</h3><p class="help-block">{{ trans('projects.analytics_risk_help') }}</p></div><strong>{{ $analytics['currency'] }} {{ number_format($analytics['at_risk'] / 100, 2) }}</strong>
        @if($analytics['overdue'])<ul>@foreach($analytics['rows'] as $row)@if($row['at_risk'])<li><a href="{{ $row['url'] }}">{{ $row['name'] }}</a><span>{{ number_format($row['at_risk'] / 100, 2) }}</span></li>@endif@endforeach</ul>@else<p>{{ trans('projects.analytics_no_overdue') }}</p>@endif
    </section>
    <p class="analytics-coverage">{{ trans('projects.analytics_coverage') }}</p>
</div>
