<?php

namespace App\Actions\Projects;

use App\Models\User;
use App\Notifications\ProjectIssueAcceptanceNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
class SendProjectIssueNoticeAction
{
    public static function run(int $issueId): void
    {
        $issue = DB::table('project_issues')->where('id', $issueId)->first();
        if (! $issue || $issue->cancelled_at) {
            return;
        }
        $worker = User::find($issue->user_id);
        $project = DB::table('project_issue_sessions')->join('projects', 'projects.id', '=', 'project_issue_sessions.project_id')->where('project_issue_sessions.id', $issue->session_id)->value('projects.name');
        $status = 'missing_email';
        if ($worker?->email) {
            try {
                $worker->notify(new ProjectIssueAcceptanceNotification($project, $issue->asset_tag, $issue->expected_checkin, $issue->acceptance_id));
                $status = in_array(config('mail.default'), ['log', 'array']) ? 'demo_logged' : 'sent';
            } catch (\Throwable $e) {
                $status = 'failed';
                Log::warning('Project issue acceptance email failed', ['issue_id' => $issueId]);
            }
        }
        DB::table('project_issues')->where('id', $issueId)->update(['notification_status' => $status, 'updated_at' => now()]);
    }
}
