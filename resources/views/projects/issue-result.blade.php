@php
    $waitingIssue = $pending->first();
    $response = $waitingIssue ? $acceptances->get($waitingIssue->acceptance_id) : null;
    $snapshot = $waitingIssue ? json_decode($waitingIssue->asset_snapshot ?? '{}', true) : [];
    $accepted = (bool) $response?->accepted_at;
    $notification = $waitingIssue?->notification_status ?? 'pending';
    $notificationNeedsAttention = !in_array($notification, ['sent', 'demo_logged']);
@endphp
<section class="box box-default project-edit project-issue-flow issue-result" aria-labelledby="issue-result-title">
    <div class="box-body">
    @if(!$session->closed_at && $waitingIssue)
        <header class="issue-result-hero">
            <span class="issue-result-check" aria-hidden="true">✓</span>
            <div>
                <p class="issue-result-eyebrow">{{ trans('projects.result_saved') }}</p>
                <h2 id="issue-result-title">{{ trans('projects.result_title') }}</h2>
                <p>{{ trans('projects.result_intro', ['asset' => $waitingIssue->asset_tag, 'employee' => $waitingIssue->recipient_name]) }}</p>
            </div>
            <span class="issue-result-status">{{ trans($accepted ? 'projects.pending_handover' : 'projects.pending_acceptance') }}</span>
        </header>
        <div class="issue-result-layout">
            <section class="issue-result-receipt" aria-labelledby="issue-result-details">
                <h3 id="issue-result-details">{{ trans('projects.result_details') }}</h3>
                <dl class="issue-result-facts">
                    <div><dt>{{ trans('projects.employee_step') }}</dt><dd>{{ $waitingIssue->recipient_name }}</dd>@if(!empty($snapshot['recipient_employee_number']))<small>{{ trans('projects.employee_barcode') }}: {{ $snapshot['recipient_employee_number'] }}</small>@endif</div>
                    <div><dt>{{ trans('projects.asset') }}</dt><dd>{{ $waitingIssue->asset_tag }}</dd>@if(!empty($snapshot['model']))<small>{{ $snapshot['model'] }}</small>@endif</div>
                    <div><dt>{{ trans('projects.result_project') }}</dt><dd>{{ $project->name }}</dd></div>
                    <div><dt>{{ trans('projects.return_by') }}</dt><dd>{{ $waitingIssue->expected_checkin ? \Carbon\Carbon::parse($waitingIssue->expected_checkin)->format('d M Y') : trans('projects.not_recorded') }}</dd></div>
                    <div><dt>{{ trans('projects.actor') }}</dt><dd>{{ $snapshot['issuer_name'] ?? trans('projects.not_recorded') }}</dd></div>
                    <div><dt>{{ trans('projects.date_issued') }}</dt><dd><time datetime="{{ \Carbon\Carbon::parse($waitingIssue->created_at)->toIso8601String() }}">{{ \Carbon\Carbon::parse($waitingIssue->created_at)->format('d M Y, H:i T') }}</time></dd></div>
                </dl>
                @if($waitingIssue->issue_note)<div class="issue-result-note"><h4>{{ trans('projects.issue_note') }}</h4><p>{{ $waitingIssue->issue_note }}</p></div>@endif
            </section>
            <section class="issue-result-next" aria-labelledby="issue-result-next-title">
                <h3 id="issue-result-next-title">{{ trans('projects.result_next') }}</h3>
                <ol class="issue-result-timeline">
                    <li class="is-recorded"><span class="issue-result-dot" aria-hidden="true"></span><div><h4>{{ trans('projects.result_recorded') }}</h4><p>{{ trans('projects.result_recorded_help') }}</p></div></li>
                    <li class="{{ $accepted ? 'is-recorded' : '' }}"><span class="issue-result-dot" aria-hidden="true"></span><div><h4>{{ trans($accepted ? 'projects.accepted_status' : 'projects.pending_acceptance') }}</h4><p>{{ trans($accepted ? 'projects.acceptance_ready' : 'projects.result_employee_help') }}</p></div></li>
                    <li><span class="issue-result-dot" aria-hidden="true"></span><div><h4>{{ trans('projects.result_handover') }}</h4><p>{{ trans($accepted ? 'projects.result_handover_ready' : 'projects.acceptance_resume') }}</p></div></li>
                </ol>
            </section>
        </div>
        <aside class="issue-result-notification {{ $notificationNeedsAttention ? 'needs-attention' : '' }}" aria-label="{{ trans('projects.notification') }}">
            <div><strong>{{ trans('projects.notification') }}</strong>@if(!empty($snapshot['recipient_email']))<span>{{ $snapshot['recipient_email'] }}</span>@endif</div>
            <p role="status">@if($notification === 'sent'){{ trans('projects.acceptance_email_sent') }}
                @elseif($notification === 'demo_logged'){{ trans('projects.acceptance_email_demo') }}
                @else{{ trans('projects.acceptance_email_waiting') }} {{ trans('projects.notification_'.$notification) }}@endif</p>
        </aside>
        <footer class="issue-result-actions">
            <div class="project-actions">
                @if($accepted)<a class="btn btn-primary" href="{{ route('projects.issue.audit', $session->id) }}">{{ trans('projects.result_continue') }}</a>@endif
                @can('issue', \App\Models\Project::class)<form method="post" action="{{ route('projects.issue.start') }}">@csrf<input type="hidden" name="project_id" value="{{ $project->id }}"><button class="btn {{ $accepted ? 'btn-default' : 'btn-primary' }}" type="submit">{{ trans('projects.issue_another') }}</button></form>@endcan
                @if(!$accepted)<a class="btn btn-default" href="{{ route('projects.issue.audit', $session->id) }}">{{ trans('projects.result_view_record') }}</a>@endif
                <a class="btn btn-default" href="{{ route('projects.show', $project) }}">{{ trans('projects.view') }}</a>
            </div>
            <button type="button" class="btn btn-default issue-result-cancel" data-toggle="collapse" data-target="#issue-cancel-confirm" aria-expanded="false" aria-controls="issue-cancel-confirm">{{ trans('projects.cancel_issue') }}</button>
        </footer>
        <p class="issue-result-reference">{{ trans('projects.result_reference') }} #{{ $waitingIssue->id }}</p>
        @include('projects.issue-cancel')
    @else
        <h2 id="issue-result-title">{{ trans('projects.session_closed') }}</h2>
        <div class="project-actions"><a class="btn btn-primary" href="{{ route('projects.issue.audit', $session->id) }}">{{ trans('projects.result_view_record') }}</a><a class="btn btn-default" href="{{ route('projects.show', $project) }}">{{ trans('projects.view') }}</a></div>
    @endif
    </div>
</section>
