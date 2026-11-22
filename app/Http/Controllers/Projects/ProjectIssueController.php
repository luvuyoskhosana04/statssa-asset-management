<?php

namespace App\Http\Controllers\Projects;

use App\Actions\Acceptances\CreateCheckoutAcceptanceAction;
use App\Actions\Projects\SendProjectIssueNoticeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\LookupProjectIssueRequest;
use App\Http\Requests\ProjectAssetRequest;
use App\Http\Requests\SearchProjectIssueAssetsRequest;
use App\Models\Asset;
use App\Models\CheckoutAcceptance;
use App\Models\Project;
use App\Models\Statuslabel;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
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
            'sessions' => DB::table('project_issue_sessions as s')->join('projects as p', 'p.id', '=', 's.project_id')
                ->whereIn('p.id', Project::select('id'))->select('s.*', 'p.name as project_name')->orderByDesc('s.created_at')->paginate(20),
            'allocations' => DB::table('project_issues as i')->join('project_issue_sessions as s', 's.id', '=', 'i.session_id')->join('projects as p', 'p.id', '=', 's.project_id')->leftJoin('checkout_acceptances as a', 'a.id', '=', 'i.acceptance_id')->whereIn('p.id', Project::select('id'))->select('i.*', 'p.name as project_name', 'a.accepted_at', 'a.declined_at')->orderByDesc('i.id')->paginate(20, ['*'], 'allocation_page'),
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

        return view('projects.issue-session', compact('project', 'session', 'issues', 'acceptances') + ['employees' => User::where('activated', 1)->orderBy('first_name')->get()->filter(fn ($user) => $project->canCheckoutTo($user))]);
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
            $worker = $this->worker($request->input('worker'), $project);
            $matches = Asset::where(fn ($q) => $q->where('asset_tag', $request->input('asset'))->orWhere('serial', $request->input('asset')))->pluck('id');
            if ($matches->count() !== 1) {
                throw ValidationException::withMessages(['asset' => trans('projects.no_unique_asset', ['value' => $request->input('asset')])]);
            }
            $asset = Asset::whereKey($matches->first())->lockForUpdate()->firstOrFail();
            $this->authorize('checkout', $asset);
            $reservation = DB::table('project_asset')->where('asset_id', $asset->id)->first();
            if ($reservation && (int) $reservation->project_id !== $project->id) {
                throw ValidationException::withMessages(['asset' => trans('projects.asset_unavailable', ['value' => $asset->asset_tag])]);
            }
            $wasReserved = (bool) $reservation;
            if (! $reservation) {
                if (! $asset->availableForCheckout()) {
                    throw ValidationException::withMessages(['asset' => trans('projects.asset_unavailable', ['value' => $asset->asset_tag])]);
                }
                $project->assets()->attach($asset->id, ['original_status_id' => $asset->status_id, 'reserved_by' => auth()->id()]);
                $reservation = (object) ['original_status_id' => $asset->status_id];
            }
            $snapshot = $this->assetDetails($asset) + ['recipient_employee_number' => $worker->employee_num, 'recipient_email' => $worker->email, 'issuer_name' => $request->user()->display_name, 'planned_handover_at' => now()->toIso8601String(), 'original_location_id' => $asset->location_id, 'reservation_existed' => $wasReserved];
            $project->members()->syncWithoutDetaching([$worker->id]);
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
            if (! $worker->email) {
                throw ValidationException::withMessages(['worker' => trans('projects.email_missing')]);
            }
            $asset->unsetRelation('status');
            if (! $asset->checkOut($worker, $request->user(), now()->toDateTimeString(), $return, trans('projects.reserved_note', ['project' => $project->code]), $asset->name, null, true)) {
                throw ValidationException::withMessages($asset->getErrors()->toArray());
            }
            $acceptance = CheckoutAcceptance::where('checkoutable_type', Asset::class)->where('checkoutable_id', $asset->id)
                ->where('assigned_to_id', $worker->id)->pending()->latest('id')->first()
                ?? CreateCheckoutAcceptanceAction::run($asset, $worker);
            $issueId = DB::table('project_issues')->insertGetId(['session_id' => $session->id, 'asset_id' => $asset->id, 'user_id' => $worker->id,
                'acceptance_id' => $acceptance->id, 'asset_tag' => $asset->asset_tag, 'recipient_name' => $worker->display_name,
                'expected_checkin' => $return, 'asset_snapshot' => json_encode($snapshot), 'issue_note' => $request->input('note'),
                'handover_declared_at' => null, 'confirmed_by' => null, 'notification_status' => 'pending',
                'created_at' => now(), 'updated_at' => now()]);
            DB::table('project_events')->insert(['project_id' => $project->id, 'actor_id' => auth()->id(), 'action' => 'issued',
                'description' => $asset->asset_tag.' / '.$worker->display_name, 'created_at' => now(), 'updated_at' => now()]);

            DB::afterCommit(fn () => SendProjectIssueNoticeAction::run($issueId));

            return $acceptance;
        }, 3);

        return redirect()->route('projects.issue.show', $session);
    }

    public function resume(string $session, int $issue): RedirectResponse
    {
        $this->session($session);
        $record = DB::table('project_issues')->where('session_id', $session)->where('id', $issue)->first() ?? abort(404);
        abort_if($record->cancelled_at || $record->handed_over_at, 409);

        return redirect()->route('projects.issue.audit', $session);
    }

    private function worker(string $barcode, Project $project): User
    {
        $query = User::where('activated', 1);
        if (preg_match('/^STATSSA-EMP-(\d+)$/i', $barcode, $match)) {
            $query->whereKey((int) $match[1]);
        } else {
            $query->where(fn ($q) => $q->where('employee_num', $barcode)->orWhere('username', $barcode));
        }
        $workers = $query->get();
        if ($workers->count() !== 1 || ! $project->canCheckoutTo($workers->first())) {
            throw ValidationException::withMessages(['worker' => trans('projects.worker_missing')]);
        }

        return $workers->first();
    }

    public function lookupEmployee(LookupProjectIssueRequest $request, string $session): JsonResponse
    {
        $row = $this->session($session);
        abort_if($row->closed_at, 409);
        $worker = $this->worker(trim($request->validated('barcode')), Project::findOrFail($row->project_id));
        if (! $worker->email) {
            throw ValidationException::withMessages(['worker' => trans('projects.email_missing')]);
        }

        return response()->json(['name' => $worker->display_name, 'barcode' => 'STATSSA-EMP-'.$worker->id, 'employee_number' => $worker->employee_num, 'email' => $worker->email]);
    }

    private function assetDetails(Asset $asset): array
    {
        return ['id' => $asset->id, 'asset_tag' => $asset->asset_tag, 'serial' => $asset->serial, 'name' => $asset->name,
            'model' => $asset->model?->name, 'category' => $asset->model?->category?->name,
            'status' => $asset->status?->name, 'location' => $asset->location?->name,
            'condition' => DB::table('asset_receipts')->where('asset_id', $asset->id)->orderByDesc('id')->value('condition') ?? 'not_recorded'];
    }

    public function searchAssets(SearchProjectIssueAssetsRequest $request, string $session): JsonResponse
    {
        $row = $this->session($session);
        abort_if($row->closed_at, 409);
        $term = trim($request->validated('q') ?? '');
        $reserved = DB::table('project_asset')->where('project_id', $row->project_id)->pluck('asset_id');
        $assets = Asset::with(['model', 'status'])->whereNull('assigned_to')
            ->where(fn ($q) => $q->whereHas('status', fn ($status) => $status->where('archived', 0)->where('deployable', 1))->orWhereIn('id', $reserved))
            ->whereNotIn('id', DB::table('project_issues')->select('asset_id')->whereNull('handed_over_at')->whereNull('cancelled_at'))
            ->whereNotIn('id', DB::table('project_asset')->select('asset_id')->where('project_id', '!=', $row->project_id))
            ->when($term !== '', fn ($query) => $query->where(fn ($q) => $q->where('asset_tag', 'like', '%'.$term.'%')->orWhere('serial', 'like', '%'.$term.'%')->orWhere('name', 'like', '%'.$term.'%')->orWhereHas('model', fn ($m) => $m->where('name', 'like', '%'.$term.'%'))))
            ->orderBy('asset_tag')->limit(100)->get();
        $results = $assets->filter(fn ($asset) => $request->user()->can('checkout', $asset))
            ->take(15)->map(fn ($asset) => ['asset_tag' => $asset->asset_tag, 'serial' => $asset->serial, 'name' => $asset->name ?: $asset->model?->name, 'model' => $asset->model?->name])->values();

        return response()->json(['results' => $results]);
    }

    public function lookupAsset(LookupProjectIssueRequest $request, string $session): JsonResponse
    {
        $row = $this->session($session);
        abort_if($row->closed_at, 409);
        $barcode = trim($request->validated('barcode'));
        $assets = Asset::where(fn ($q) => $q->where('asset_tag', $barcode)->orWhere('serial', $barcode))->get();
        if ($assets->count() !== 1) {
            throw ValidationException::withMessages(['asset' => trans('projects.no_unique_asset', ['value' => $barcode])]);
        }
        $asset = $assets->first();
        $this->authorize('checkout', $asset);
        $reservation = DB::table('project_asset')->where('asset_id', $asset->id)->first();
        $pending = DB::table('project_issues')->where('asset_id', $asset->id)->whereNull('handed_over_at')->whereNull('cancelled_at')->exists();
        if ($asset->assigned_to || $pending || ($reservation && (int) $reservation->project_id !== (int) $row->project_id) || (! $reservation && ! $asset->availableForCheckout())) {
            throw ValidationException::withMessages(['asset' => trans('projects.asset_unavailable', ['value' => $barcode])]);
        }

        return response()->json($this->assetDetails($asset));
    }

    public function audit(string $session): View
    {
        $this->authorize('issue', Project::class);
        $session = DB::table('project_issue_sessions')->where('id', $session)->whereIn('project_id', Project::select('id'))->first() ?? abort(404);
        $project = Project::findOrFail($session->project_id);
        $issues = DB::table('project_issues as i')->leftJoin('checkout_acceptances as a', 'a.id', '=', 'i.acceptance_id')->where('i.session_id', $session->id)->select('i.*', 'a.accepted_at', 'a.declined_at')->orderByDesc('i.id')->get();

        return view('projects.issue-audit', compact('session', 'project', 'issues'));
    }

    public function resend(ProjectAssetRequest $request, string $session, int $issue): RedirectResponse
    {
        $this->session($session);
        $record = DB::table('project_issues')->where('session_id', $session)->where('id', $issue)->first() ?? abort(404);
        abort_if($record->cancelled_at || $record->handed_over_at, 409);
        SendProjectIssueNoticeAction::run($record->id);

        return redirect()->route('projects.issue.audit', $session);
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

    public function cancel(ProjectAssetRequest $request, string $session): RedirectResponse
    {
        DB::transaction(function () use ($session): void {
            $sessionRow = $this->session($session, true);
            $issues = DB::table('project_issues')->where('session_id', $session)->whereNull('handed_over_at')->whereNull('cancelled_at')->lockForUpdate()->get();
            if ($sessionRow->closed_at && $issues->isEmpty()) {
                return;
            }
            foreach ($issues as $issue) {
                $acceptance = CheckoutAcceptance::whereKey($issue->acceptance_id)->lockForUpdate()->firstOrFail();
                $asset = Asset::whereKey($issue->asset_id)->lockForUpdate()->firstOrFail();
                $this->authorize('checkin', $asset);
                if ($asset->assigned_to && ($asset->assigned_type !== User::class || (int) $asset->assigned_to !== (int) $issue->user_id)) {
                    throw ValidationException::withMessages(['asset' => trans('projects.cancel_assignment_changed')]);
                }
                $snapshot = json_decode($issue->asset_snapshot ?? '{}', true);
                $reservation = DB::table('project_asset')->where('project_id', $sessionRow->project_id)->where('asset_id', $asset->id)->first();
                if ($asset->assigned_to) {
                    $asset->assignedTo()->disassociate();
                    $asset->accepted = null;
                    $asset->expected_checkin = null;
                    $asset->location_id = array_key_exists('original_location_id', $snapshot) ? $snapshot['original_location_id'] : $asset->rtd_location_id;
                    $asset->last_checkin = now();
                    $asset->logCheckin(User::find($issue->user_id), trans('projects.cancel_issue_note'), now());
                }
                if ($reservation) {
                    if ($snapshot['reservation_existed'] ?? true) {
                        $asset->status_id = Project::findOrFail($sessionRow->project_id)->reservation_status_id ?: $reservation->original_status_id;
                    } else {
                        $asset->status_id = $reservation->original_status_id;
                        DB::table('project_asset')->where('id', $reservation->id)->delete();
                    }
                }
                $asset->saveOrFail();
                // Retain the response history while preventing the old link from accepting again.
                $acceptance->declined_at = now();
                $acceptance->note = trans('projects.cancel_issue_note');
                $acceptance->saveOrFail();
                DB::table('project_issues')->where('id', $issue->id)->update(['cancelled_at' => now(), 'confirmed_by' => auth()->id(), 'updated_at' => now()]);
                DB::table('project_events')->insert(['project_id' => $sessionRow->project_id, 'actor_id' => auth()->id(), 'action' => 'cancelled', 'description' => $issue->asset_tag.' / '.$issue->recipient_name, 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('project_issue_sessions')->where('id', $session)->update(['closed_at' => now(), 'updated_at' => now()]);
        });

        return redirect()->route('projects.issue.index')->with('success', trans('projects.issue_cancelled'));
    }

    public function close(ProjectAssetRequest $request, string $session): RedirectResponse
    {
        DB::transaction(function () use ($session): void {
            $this->session($session, true);
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
