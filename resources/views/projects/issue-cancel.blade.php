<div class="collapse project-cancel-confirm" id="issue-cancel-confirm">
<p>{{ trans('projects.cancel_issue_help') }}</p>
<form method="post" action="{{ route('projects.issue.cancel', $session->id) }}">@csrf
<button type="button" class="btn btn-default" data-toggle="collapse" data-target="#issue-cancel-confirm">{{ trans('projects.keep_issuing') }}</button>
<button type="submit" class="btn btn-danger">{{ trans('projects.confirm_cancel_issue') }}</button>
</form></div>
