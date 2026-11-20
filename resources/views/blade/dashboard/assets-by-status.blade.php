@can('view', \App\Models\Asset::class)
<x-dashboard.comparison-chart chart="statuses" :title="trans($snipeSettings->dash_chart_type === 'name' ? 'general.assets_by_status' : 'general.assets_by_status_type')" />
@endcan
