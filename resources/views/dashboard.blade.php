@extends('layouts/default')

{{-- Page title --}}
@section('title')
{{ trans('general.dashboard') }}
@parent
@stop


{{-- Page content --}}
@section('content')

<x-container>

    @if ($snipeSettings->dashboard_message != '')
        <div class="row">
            <div class="col-md-12">
                <div class="box box-default">
                    <div class="box-body">
                        {!! Helper::parseEscapedMarkedown($snipeSettings->dashboard_message) !!}
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Top-boxes summary + empty-inventory shortcut are admin-only.
         Non-admins with view access to at least one HasCalendarEvents
         adopter reach this dashboard (see DashboardController::index),
         but the count boxes read as global inventory and the create-
         first-thing shortcuts assume admin-shaped permissions. The
         widget section below stays visible for everyone since each
         widget component already gates itself with @can and Companyable-
         scopes its queries. --}}
    @if (auth()->user()->hasAccess('admin'))
        <x-dashboard.top-boxes :counts="$counts" />
    @endif

    {{-- Empty-inventory shortcut is also admin-only. Non-admins take
         the @else branch below and see the widget section, whose
         queries Companyable-scope to what the caller can see. --}}
    @if (auth()->user()->hasAccess('admin') && $counts['grand_total'] == 0)
        <x-dashboard.empty-inventory-shortcut />
    @else
        <x-dashboard.index />
    @endif

</x-container>

@stop

@section('moar_scripts')
@include ('partials.bootstrap-table', ['simple_view' => true, 'nopages' => true])

        @can('canViewUsersAndCheckoutables')
            {{-- Today widget for the dashboard. Reuses the main calendar
                 bundle. Initializes into listUpcoming view (rolling 7
                 days from today). No URL sync (widgets don't own the
                 page URL), no filter buttons (too tight for the
                 dashboard column), no toolbar (title suffices). Users
                 who want to filter head over to the full /calendar
                 page. Gate matches the widget component's own gate so
                 the JS bundle loads for every viewer the component
                 renders for, otherwise the widget div stays empty.
                 See resources/views/blade/dashboard/today-calendar.blade.php. --}}
            <script src="{{ url(mix('js/dist/snipeit-calendar.js')) }}" nonce="{{ csrf_token() }}"></script>
            <script nonce="{{ csrf_token() }}">
                document.addEventListener('DOMContentLoaded', function () {
                    // Format list-view day-group labels. Compares the
                    // group's date to today's local Y-M-D so the
                    // "Today" / "Tomorrow" swap survives a browser
                    // timezone that's east/west of UTC. Anything beyond
                    // tomorrow falls back to FC's own locale-aware
                    // default via arg.text.
                    var upcomingDayFormat = function (arg) {
                        var d = arg.date;
                        var localYmd = d.year + '-' + String(d.month + 1).padStart(2, '0') + '-' + String(d.day).padStart(2, '0');
                        var today = new Date();
                        var todayYmd = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');
                        var tomorrow = new Date(today.getTime() + 86400000);
                        var tomorrowYmd = tomorrow.getFullYear() + '-' + String(tomorrow.getMonth() + 1).padStart(2, '0') + '-' + String(tomorrow.getDate()).padStart(2, '0');
                        if (localYmd === todayYmd) return '{{ trans('general.calendar_today') }}';
                        if (localYmd === tomorrowYmd) return '{{ trans('general.calendar_tomorrow') }}';
                        return arg.text;
                    };

                    window.snipeitCalendar.init('dashboard-today-calendar', {
                        events: '{{ route('api.calendar.events') }}',
                        filter: null,
                        filterButtons: [
                            {name: '', text: @json(trans('general.all'))},
                            {name: 'asset.expected_checkin', text: @json(trans('general.calendar_returns'))},
                            {name: 'maintenance.start', text: @json(trans('general.calendar_event_maintenance'))},
                        ],
                        noEventsContent: 'No deadlines in the next 7 days.',
                        // Rolling 7-day list starting today (not listWeek,
                        // which starts on the week's Sunday/Monday and
                        // would show yesterday on a Tuesday). Keeps the
                        // widget useful when today itself is empty by
                        // pulling in tomorrow through 6 days out.
                        initialView: 'listUpcoming',
                        views: {
                            listUpcoming: {
                                type: 'list',
                                duration: {days: 7},
                            },
                        },
                        // Left side gets the "Today" / "Tomorrow" /
                        // weekday-default swap. Right side stays a full
                        // human-readable date ("August 14, 2026") so
                        // the viewer can see the actual calendar day
                        // even when the left label is relative.
                        listDayFormat: upcomingDayFormat,
                        listDaySideFormat: {
                            month: 'long',
                            day: 'numeric',
                            year: 'numeric',
                        },
                        headerToolbar: false,
                        direction: '{{ \App\Helpers\Helper::determineLanguageDirection() }}',
                        locale: '{{ str_replace('_', '-', app()->getLocale()) }}',
                        urlState: false,
                        limit: 10,
                        onFetchMeta: function (meta) {
                            var more = document.getElementById('dashboard-today-more');
                            var link = document.getElementById('dashboard-today-more-link');
                            if (!more || !link) {
                                return;
                            }
                            if (meta.truncated) {
                                var remaining = Math.max(0, meta.total - meta.returned);
                                link.textContent = '{{ trans('general.calendar_upcoming_more') }}'.replace(':count', String(remaining));
                                more.style.display = '';
                            }
                            else {
                                more.style.display = 'none';
                            }
                        },
                    });
                    var allDeadlines = document.querySelector('.agenda-panel [data-filter-name=""]');
                    if (allDeadlines) {
                        allDeadlines.classList.add('active');
                        allDeadlines.setAttribute('aria-pressed', 'true');
                    }
                });
            </script>
        @endcan
@stop

@push('js')
    <script src="{{ url(mix('js/dist/Chart.min.js')) }}" nonce="{{ csrf_token() }}"></script>
    <script src="{{ asset('js/status-overview.js') }}?v=6" nonce="{{ csrf_token() }}"></script>
@endpush
