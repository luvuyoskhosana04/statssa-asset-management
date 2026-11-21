<?php

namespace App\Actions\Projects;

use App\Models\Asset;
use App\Models\CheckoutAcceptance;
use Illuminate\Support\Facades\DB;
class ResolveProjectIssueAcceptanceAction
{
    public static function run(CheckoutAcceptance $acceptance): void
    {
        DB::transaction(function () use ($acceptance) {
            $issue = DB::table('project_issues')->where('acceptance_id', $acceptance->id)->lockForUpdate()->first();
            if (! $issue || $issue->cancelled_at) {
                return;
            }
            $asset = Asset::whereKey($issue->asset_id)->lockForUpdate()->firstOrFail();
            $session = DB::table('project_issue_sessions')->where('id', $issue->session_id)->first();
            if ($acceptance->declined_at) {
                $reservation = DB::table('project_asset')->where('asset_id', $asset->id)->where('project_id', $session->project_id)->first();
                if ($reservation && ! $asset->assigned_to) {
                    $asset->status_id = $reservation->original_status_id;
                    $asset->expected_checkin = null;
                    $asset->saveOrFail();
                    DB::table('project_asset')->where('id', $reservation->id)->delete();
                }
                DB::table('project_issues')->where('id', $issue->id)->update(['cancelled_at' => now(), 'updated_at' => now()]);
                $action = 'declined';
            } elseif ($acceptance->accepted_at) {
                if ($issue->handed_over_at) {
                    return;
                }
                if ($issue->handover_declared_at) {
                    DB::table('project_issues')->where('id', $issue->id)->update(['handed_over_at' => $issue->handover_declared_at, 'updated_at' => now()]);
                }
                $action = 'accepted';
            } else {
                return;
            }
            DB::table('project_events')->insert(['project_id' => $session->project_id, 'actor_id' => $issue->user_id, 'action' => $action, 'description' => $issue->asset_tag.' / '.$issue->recipient_name, 'created_at' => now(), 'updated_at' => now()]);
        });
    }
}
