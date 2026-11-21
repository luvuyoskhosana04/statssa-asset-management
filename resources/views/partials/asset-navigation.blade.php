<ul class="treeview-menu asset-nav-groups">
<li class="asset-nav-group" data-asset-nav-group>
    <a href="{{ url('hardware') }}" class="asset-nav-trigger" aria-expanded="false" aria-controls="asset-nav-status" @if(request()->is('hardware') && !request()->query('status_type')) aria-current="page" @endif><span>{{ trans('general.all_assets') }}</span><span class="badge">{{ $total_assets ?? '' }}</span><span class="asset-nav-chevron" aria-hidden="true">›</span></a>
    <div id="asset-nav-status" class="asset-nav-panel" role="region" aria-labelledby="asset-nav-title-status">
        <h3 id="asset-nav-title-status" class="asset-nav-title">{{ trans('general.all_assets') }}</h3>
        <ul class="asset-nav-links"><li @if(request()->is('hardware') && !request()->query('status_type')) class="active" @endif><a href="{{ url('hardware') }}">{{ trans('presentation.chart_details') }}</a></li><?php $status_navs = \App\Models\Statuslabel::where('show_in_nav', '=', 1)->withCount('assets as asset_count')->get(); ?>
                                    @if (count($status_navs) > 0)
                                        @foreach ($status_navs as $status_nav)
                                            <li{!! (request()->is('statuslabels/'.$status_nav->id) ? ' class="active" aria-current="page"' : '') !!}>
                                                <a href="{{ route('statuslabels.show', ['statuslabel' => $status_nav->id]) }}">
                                                    {{ $status_nav->name }}
                                                    <span class="badge badge-secondary">{{ $status_nav->asset_count }}</span></a></li>
                                        @endforeach
                                    @endif



<li id="deployed-sidenav-option" {!! (request()->query('status_type') == 'Deployed' ? ' class="active" aria-current="page"' : '') !!}>
                                        <a href="{{ url('hardware?status_type=Deployed') }}">
                                            {{ trans('general.deployed') }}
                                            <span class="badge">{{ (isset($total_deployed_sidebar)) ? $total_deployed_sidebar : '' }}</span>
                                        </a>
                                    </li>
<li id="rtd-sidenav-option"{!! (request()->query('status_type') == 'RTD' ? ' class="active" aria-current="page"' : '') !!}>
                                        <a href="{{ url('hardware?status_type=RTD') }}">
                                            {{ trans('general.ready_to_deploy') }}
                                            <span class="badge">{{ (isset($total_rtd_sidebar)) ? $total_rtd_sidebar : '' }}</span>
                                        </a>
                                    </li>
<li id="pending-sidenav-option"{!! (request()->query('status_type') == 'Pending' ? ' class="active" aria-current="page"' : '') !!}>
                                        <a href="{{ url('hardware?status_type=Pending') }}">
                                            {{ trans('general.pending') }}
                                            <span class="badge">{{ (isset($total_pending_sidebar)) ? $total_pending_sidebar : '' }}</span>
                                        </a>
                                    </li>
<li id="undeployable-sidenav-option"{!! (request()->query('status_type') == 'Undeployable' ? ' class="active" aria-current="page"' : '') !!}>
                                        <a href="{{ url('hardware?status_type=Undeployable') }}">

                                            {{ trans('general.undeployable') }}
                                            <span class="badge">{{ (isset($total_undeployable_sidebar)) ? $total_undeployable_sidebar : '' }}</span>
                                        </a>
                                    </li>
<li id="requestable-sidenav-option"{!! (request()->query('status_type') == 'Requestable' ? ' class="active" aria-current="page"' : '') !!}>
                                        <a
                                            href="{{ url('hardware?status_type=Requestable') }}">

                                            {{ trans('admin/hardware/general.requestable') }}
                                        </a>
                                    </li></ul>
    </div>
</li>
@if (Gate::allows('create', \App\Models\Asset::class) || Gate::allows('update', \App\Models\Asset::class) || Gate::allows('checkin', \App\Models\Asset::class) || Gate::allows('checkout', \App\Models\Asset::class) || Gate::allows('audit', \App\Models\Asset::class))
<li class="asset-nav-group" data-asset-nav-group>
    <button type="button" class="asset-nav-trigger" aria-expanded="false" aria-controls="asset-nav-tools"><span>{{ trans('presentation.asset_nav_tools') }}</span><span class="asset-nav-chevron" aria-hidden="true">›</span></button>
    <div id="asset-nav-tools" class="asset-nav-panel" role="region" aria-labelledby="asset-nav-title-tools">
        <h3 id="asset-nav-title-tools" class="asset-nav-title">{{ trans('presentation.asset_nav_tools') }}</h3>
        <ul class="asset-nav-links">


@can('checkin', \App\Models\Asset::class)
<li{!! (request()->is('hardware/quickscancheckin') ? ' class="active" aria-current="page"' : '') !!}>
                                            <a href="{{ route('hardware/quickscancheckin') }}">
                                                {{ trans('general.quickscan_checkin') }}
                                            </a>
                                        </li>
@endcan
@canany(['create', 'update'], \App\Models\Asset::class)
<li{!! (request()->is('hardware/receive-tag*') ? ' class="active" aria-current="page"' : '') !!}>
    <a href="{{ route('hardware.receive.index') }}">{{ trans('presentation.receive_title') }}</a>
</li>
@endcanany
@can('checkout', \App\Models\Asset::class)
<li{!! (request()->is('hardware/bulkcheckout') ? ' class="active" aria-current="page"' : '') !!}>
                                            <a href="{{ route('hardware.bulkcheckout.show') }}">
                                                {{ trans('general.bulk_checkout') }}
                                            </a>
                                        </li>
@endcan
@can('audit', \App\Models\Asset::class)
<li id="bulk-audit-sidenav-option" {!! (request()->is('hardware/bulkaudit') ? ' class="active" aria-current="page"' : '') !!}>
                                            <a href="{{ route('assets.bulkaudit') }}">
                                                {{ trans('general.bulkaudit') }}
                                            </a>
                                        </li>
@endcan
        </ul>
    </div>
</li>
@endif
@if (Gate::allows('audit', \App\Models\Asset::class) || Gate::allows('checkin', \App\Models\Asset::class))
<li class="asset-nav-group" data-asset-nav-group>
    <button type="button" class="asset-nav-trigger" aria-expanded="false" aria-controls="asset-nav-audit"><span>{{ trans('presentation.asset_nav_audit') }}</span><span class="asset-nav-chevron" aria-hidden="true">›</span></button>
    <div id="asset-nav-audit" class="asset-nav-panel" role="region" aria-labelledby="asset-nav-title-audit">
        <h3 id="asset-nav-title-audit" class="asset-nav-title">{{ trans('presentation.asset_nav_audit') }}</h3>
        <ul class="asset-nav-links">@can('audit', \App\Models\Asset::class)
<li id="audit-due-sidenav-option"{!! (request()->is('hardware/audit/due') ? ' class="active" aria-current="page"' : '') !!}>
                                            <a href="{{ route('assets.audit.due') }}">

                                                {{ trans('general.audit_due') }}
                                                <span class="badge">{{ (isset($total_due_and_overdue_for_audit)) ? $total_due_and_overdue_for_audit : '' }}</span>
                                            </a>
                                        </li>
@endcan
@can('checkin', \App\Models\Asset::class)
<li id="checkin-due-sidenav-option"{!! (request()->is('hardware/checkins/due') ? ' class="active" aria-current="page"' : '') !!}>
                                        <a href="{{ route('assets.checkins.due') }}">

                                            {{ trans('general.checkin_due') }}
                                            <span class="badge">{{ (isset($total_due_and_overdue_for_checkin)) ? $total_due_and_overdue_for_checkin : '' }}</span>
                                        </a>
                                    </li>
@endcan

</ul>
    </div>
</li>
@endif
<li class="asset-nav-group" data-asset-nav-group>
    <button type="button" class="asset-nav-trigger" aria-expanded="false" aria-controls="asset-nav-checkout"><span>{{ trans('presentation.asset_nav_checkout') }}</span><span class="asset-nav-chevron" aria-hidden="true">›</span></button>
    <div id="asset-nav-checkout" class="asset-nav-panel" role="region" aria-labelledby="asset-nav-title-checkout">
        <h3 id="asset-nav-title-checkout" class="asset-nav-title">{{ trans('presentation.asset_nav_checkout') }}</h3>
        <ul class="asset-nav-links"><li id="byod-sidenav-option"{!! (request()->query('status_type') == 'byod' ? ' class="active" aria-current="page"' : '') !!}>
                                        <a
                                            href="{{ url('hardware?status_type=byod') }}">

                                            {{ trans('general.byod') }}
                                            <span class="badge">{{ (isset($total_byod_sidebar)) ? $total_byod_sidebar : '' }}</span>
                                        </a>
                                    </li>
</ul>
    </div>
</li>
<li class="asset-nav-group" data-asset-nav-group>
    <button type="button" class="asset-nav-trigger" aria-expanded="false" aria-controls="asset-nav-archive"><span>{{ trans('presentation.asset_nav_archive') }}</span><span class="asset-nav-chevron" aria-hidden="true">›</span></button>
    <div id="asset-nav-archive" class="asset-nav-panel" role="region" aria-labelledby="asset-nav-title-archive">
        <h3 id="asset-nav-title-archive" class="asset-nav-title">{{ trans('presentation.asset_nav_archive') }}</h3>
        <ul class="asset-nav-links"><li id="archived-sidenav-option"{!! (request()->query('status_type') == 'Archived' ? ' class="active" aria-current="page"' : '') !!}>
                                        <a
                                            href="{{ url('hardware?status_type=Archived') }}">

                                            {{ trans('admin/hardware/general.archived') }}
                                            <span class="badge">{{ (isset($total_archived_sidebar)) ? $total_archived_sidebar : '' }}</span>
                                        </a>
                                    </li>
@can('create', \App\Models\Asset::class)
<li{!! (request()->query('status_type') == 'Deleted' ? ' class="active" aria-current="page"' : '') !!}>
                                            <a href="{{ url('hardware?status_type=Deleted') }}">
                                                {{ trans('general.deleted') }}
                                            </a>
                                        </li>
@endcan</ul>
    </div>
</li>
</ul>
