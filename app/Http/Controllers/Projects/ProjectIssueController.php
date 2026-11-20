<?php

namespace App\Http\Controllers\Projects;

use App\Actions\Acceptances\CreateCheckoutAcceptanceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectAssetRequest;
use App\Models\Asset;
use App\Models\CheckoutAcceptance;
use App\Models\Project;
use App\Models\Statuslabel;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProjectIssueController extends Controller
{
    public function index(): View
    {
        $this->authorize('issue', Project::class);

        return view('projects.issue-index', [
            'projects' => Project::where('status', 'active')->whereDate('start_date', '<=', today())->whereDate('end_date', '>=', today())->orderBy('name')->get(),
            'sessions' => DB::table('project_issue_sessions')->where('created_by', auth()->id())
                ->whereIn('project_id', Project::select('id'))->orderByDesc('created_at')->paginate(20),
        ]);
    }

    public function start(ProjectAssetRequest $request): RedirectResponse
    {
        $this->authorize('issue', Project::class);
        $project = Project::findOrFail($request->integer('project_id'));
        $this->active($project);
        $id = (string) Str::uuid();
        DB::table('project_issue_sessions')->insert(['id' => $id, 'project_id' => $project->id, 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);

        return redirect()->route('projects.issue.show', $id);
    }

    private function session(string $session, bool $lock = false): object
    {
        $this->authorize('issue', Project::class);
        $query = DB::table('project_issue_sessions')->where('id', $session)->where('created_by', auth()->id())
            ->whereIn('project_id', Project::select('id'));

        return ($lock ? $query->lockForUpdate() : $query)->first() ?? abort(404);
    }

    private function active(Project $project): void
    {
        if ($project->status !== 'active' || $project->start_date->isFuture() || $project->end_date->lt(today())) {
            throw ValidationException::withMessages(['project_id' => trans('projects.project_unavailable')]);
        }
    }

    public function show(string $session): View
    {
        $session = $this->session($session);
        $project = Project::findOrFail($session->project_id);
        $issues = DB::table('project_issues')->where('session_id', $session->id)->orderByDesc('id')->get();
        $acceptances = CheckoutAcceptance::whereIn('id', $issues->pluck('acceptance_id'))->get()->keyBy('id');

        return view('projects.issue-session', compact('project', 'session', 'issues', 'acceptances'));
    }

    public function store(ProjectAssetRequest $request, string $session): RedirectResponse
    {
        $acceptance = DB::transaction(function () use ($request, $session): CheckoutAcceptance {
            $visibleSession = $this->session($session);
            $project = Project::whereKey($visibleSession->project_id)->lockForUpdate()->firstOrFail();
            $session = $this->session($session, true);
            abort_if($session->closed_at, 409, trans('projects.session_closed'));
            $this->active($project);
            if (DB::table('project_issues')->where('session_id', $session->id)->whereNull('handed_over_at')->whereNull('cancelled_at')->exists()) {
                throw ValidationException::withMessages(['asset' => trans('projects.finish_handover')]);
            }
            $workers = $project->members()->where('activated', 1)
                ->where(fn ($q) => $q->where('employee_num', $request->input('worker'))->orWhere('username', $request->input('worker')))->get();
            if ($workers->count() !== 1) {
                throw ValidationException::withMessages(['worker' => trans('projects.worker_missing')]);
            }
            $worker = $workers->first();
            $matches = $project->assets()->where(fn ($q) => $q->where('asset_tag', $request->input('asset'))->orWhere('serial', $request->input('asset')))->pluck('assets.id');
            if ($matches->count() !== 1) {
                throw ValidationException::withMessages(['asset' => trans('projects.reservation_missing')]);
            }
            $asset = Asset::whereKey($matches->first())->lockForUpdate()->firstOrFail();
            $this->authorize('checkout', $asset);
            $reservation = DB::table('project_asset')->where('project_id', $project->id)->where('asset_id', $asset->id)->first();
            if (DB::table('project_issues')->where('asset_id', $asset->id)->whereNull('handed_over_at')->whereNull('cancelled_at')->exists()) {
                throw ValidationException::withMessages(['asset' => trans('projects.finish_handover')]);
            }
            if ($asset->assigned_to || ! in_array((int) $asset->status_id, [(int) $project->reservation_status_id, (int) $reservation->original_status_id], true) || ! $asset->canCheckoutTo($worker)) {
                throw ValidationException::withMessages(['asset' => trans('projects.asset_unavailable', ['value' => $asset->asset_tag])]);
            }
            $status = Statuslabel::find($reservation->original_status_id);
            if (! $status || ! $status->deployable || $status->archived) {
                throw ValidationException::withMessages(['asset' => trans('projects.restore_status')]);
            }
            $return = $request->input('expected_checkin', $project->end_date->toDateString());
            if ($return > $project->end_date->toDateString()) {
                throw ValidationException::withMessages(['expected_checkin' => trans('projects.return_within_project')]);
            }
            $asset->status_id = $status->id;
            $asset->unsetRelation('status');
            if (! $asset->checkOut($worker, $request->user(), now()->toDateTimeString(), $return, trans('projects.reserved_note', ['project' => $project->code]), $asset->name, null, true)) {
                throw ValidationException::withMessages($asset->getErrors()->toArray());
            }
            $acceptance = CheckoutAcceptance::where('checkoutable_type', Asset::class)->where('checkoutable_id', $asset->id)
                ->where('assigned_to_id', $worker->id)->pending()->latest('id')->first()
                ?? CreateCheckoutAcceptanceAction::run($asset, $worker);
            DB::table('project_issues')->insert(['session_id' => $session->id, 'asset_id' => $asset->id, 'user_id' => $worker->id,
                'acceptance_id' => $acceptance->id, 'asset_tag' => $asset->asset_tag, 'recipient_name' => $worker->display_name,
                'created_at' => now(), 'updated_at' => now()]);
            DB::table('project_events')->insert(['project_id' => $project->id, 'actor_id' => auth()->id(), 'action' => 'issued',
                'description' => $asset->asset_tag.' / '.$worker->display_name, 'created_at' => now(), 'updated_at' => now()]);

            return $acceptance;
        }, 3);

        return $this->sign($session, $acceptance);
    }

    private function sign(string $session, CheckoutAcceptance $acceptance): RedirectResponse
    {
        session(['sign_in_place' => true, 'sign_in_place_acceptance_id' => $acceptance->id,
            'sign_in_place_item_id' => $acceptance->checkoutable_id, 'sign_in_place_resource_type' => 'Assets',
            'project_issue_return' => $session]);

        return redirect()->route('account.accept.item', $acceptance->id);
    }

    public function resume(string $session, int $issue): RedirectResponse
    {
        $sessionRow = $this->session($session);
        $issue = DB::table('project_issues')->where('session_id', $session)->where('id', $issue)->first() ?? abort(404);
        abort_if($sessionRow->closed_at || $issue->handed_over_at || $issue->cancelled_at, 409);
        $asset = Asset::findOrFail($issue->asset_id);
        $this->authorize('checkout', $asset);
        abort_unless($asset->assigned_type === User::class && (int) $asset->assigned_to === (int) $issue->user_id, 409);
        $acceptance = CheckoutAcceptance::whereKey($issue->acceptance_id)->pending()->firstOrFail();

        return $this->sign($session, $acceptance);
    }

    public function handover(ProjectAssetRequest $request, string $session, int $issue): RedirectResponse
    {
        DB::transaction(function () use ($session, $issue): void {
            $sessionRow = $this->session($session, true);
            abort_if($sessionRow->closed_at, 409);
            $issue = DB::table('project_issues')->where('session_id', $session)->where('id', $issue)->lockForUpdate()->first() ?? abort(404);
            abort_if($issue->cancelled_at, 409);
            if ($issue->handed_over_at) {
                return;
            }
            $acceptance = CheckoutAcceptance::findOrFail($issue->acceptance_id);
            $asset = Asset::whereKey($issue->asset_id)->lockForUpdate()->firstOrFail();
            if (! $acceptance->accepted_at || $acceptance->declined_at || $asset->assigned_type !== User::class || (int) $asset->assigned_to !== (int) $issue->user_id) {
                throw ValidationException::withMessages(['asset' => trans('projects.accept_first')]);
            }
            DB::table('project_issues')->where('id', $issue->id)->update(['handed_over_at' => now(), 'confirmed_by' => auth()->id(), 'updated_at' => now()]);
            DB::table('project_events')->insert(['project_id' => $sessionRow->project_id, 'actor_id' => auth()->id(), 'action' => 'handed_over', 'description' => $issue->asset_tag, 'created_at' => now(), 'updated_at' => now()]);
        });

        return redirect()->route('projects.issue.show', $session)->with('success', trans('projects.handover_saved'));
    }

    public function close(ProjectAssetRequest $request, string $session): RedirectResponse
    {
        DB::transaction(function () use ($session): void {
            $this->session($session, true);
            if (DB::table('project_issues')->where('session_id', $session)->whereNull('handed_over_at')->whereNull('cancelled_at')->exists()) {
                throw ValidationException::withMessages(['asset' => trans('projects.finish_handover')]);
            }
            DB::table('project_issue_sessions')->where('id', $session)->update(['closed_at' => now(), 'updated_at' => now()]);
        });

        return redirect()->route('projects.issue.index');
    }

    public function resolve(ProjectAssetRequest $request, string $session, int $issue): RedirectResponse
    {
        DB::transaction(function () use ($session, $issue): void {
            $this->session($session, true);
            $issue = DB::table('project_issues')->where('session_id', $session)->where('id', $issue)->lockForUpdate()->first() ?? abort(404);
            abort_if($issue->handed_over_at, 409);
            $asset = Asset::whereKey($issue->asset_id)->lockForUpdate()->firstOrFail();
            $this->authorize('checkin', $asset);
            if ($asset->assigned_to) {
                throw ValidationException::withMessages(['asset' => trans('projects.return_first')]);
            }
            CheckoutAcceptance::whereKey($issue->acceptance_id)->pending()->update(['declined_at' => now(), 'note' => trans('projects.cancellation_note'), 'updated_at' => now()]);
            DB::table('project_issues')->where('id', $issue->id)->update(['cancelled_at' => now(), 'confirmed_by' => auth()->id(), 'updated_at' => now()]);
        });

        return redirect()->route('projects.issue.show', $session);
    }
}
