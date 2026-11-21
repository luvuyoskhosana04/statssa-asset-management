<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectAssetRequest;
use App\Http\Requests\SaveProjectRequest;
use App\Models\Asset;
use App\Models\Project;
use App\Models\Statuslabel;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ProjectsController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('view', Project::class);
        $options = Project::orderBy('name')->get(['id', 'name', 'code']);
        $query = Project::query()->with('location')->withCount('assets');
        if ($request->filled('project_id')) {
            $query->whereKey($request->integer('project_id'));
        }
        if (Gate::allows('view', User::class)) {
            $query->withCount('members');
        }
        if (in_array($request->input('status', 'active'), ['planned', 'active', 'suspended', 'ended'], true)) {
            $query->where('status', $request->input('status', 'active'));
        }
        if ($search = mb_substr(trim((string) $request->input('search')), 0, 255)) {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%'));
        }

        return view('projects.index', ['projects' => $query->orderBy('name')->paginate(20)->withQueryString(), 'projectOptions' => $options]);
    }

    public function create(): View
    {
        abort(403, trans('projects.directory_readonly'));
    }

    public function edit(Project $project): View
    {
        abort(403, trans('projects.directory_readonly'));
    }

    public function store(SaveProjectRequest $request): RedirectResponse
    {
        abort(403, trans('projects.directory_readonly'));
    }

    public function update(SaveProjectRequest $request, Project $project): RedirectResponse
    {
        abort(403, trans('projects.directory_readonly'));
    }

    public function show(Request $request, Project $project): View
    {
        $this->authorize('view', $project);
        $tab = in_array($request->input('tab'), ['assets', 'people', 'locations', 'history'], true) ? $request->input('tab') : 'assets';
        if ($tab === 'people') {
            $this->authorize('view', User::class);
        }
        $assets = $project->assets()->with(['model', 'status', 'location', 'assignedTo'])->orderBy('asset_tag')->get();
        $members = $tab === 'people' ? $project->members()->orderBy('first_name')->paginate(20) : null;

        return view('projects.show', compact('project', 'tab', 'assets', 'members') + [
            'events' => $tab === 'history' ? DB::table('project_events')->where('project_id', $project->id)->orderByDesc('id')->paginate(30) : null,
        ]);
    }

    public function member(ProjectAssetRequest $request, Project $project): RedirectResponse
    {
        abort(403, trans('projects.directory_readonly'));
    }

    public function removeMember(ProjectAssetRequest $request, Project $project, int $user): RedirectResponse
    {
        abort(403, trans('projects.directory_readonly'));
    }

    public function reserve(ProjectAssetRequest $request, Project $project): RedirectResponse
    {
        $identifiers = array_values(array_unique(array_filter(array_map('trim', preg_split('/[\r\n]+/', $request->input('identifiers'))))));
        if (! count($identifiers) || count($identifiers) > 100) {
            throw ValidationException::withMessages(['identifiers' => trans('projects.reserve_limit')]);
        }
        DB::transaction(function () use ($project, $identifiers): void {
            $project = Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
            if (! in_array($project->status, ['active', 'planned'])) {
                throw ValidationException::withMessages(['identifiers' => trans('projects.project_unavailable')]);
            }
            $matches = [];
            foreach ($identifiers as $identifier) {
                $found = Asset::where(fn ($q) => $q->where('asset_tag', $identifier)->orWhere('serial', $identifier))->get();
                if ($found->count() !== 1) {
                    throw ValidationException::withMessages(['identifiers' => trans('projects.no_unique_asset', ['value' => $identifier])]);
                }
                $matches[] = $found->first()->id;
            }
            $assets = Asset::whereIn('id', $matches)->orderBy('id')->lockForUpdate()->get();
            $status = $project->reservation_status_id ? Statuslabel::find($project->reservation_status_id) : null;
            if (! $status) {
                $status = new Statuslabel(['name' => 'Reserved for project '.$project->code, 'deployable' => 0, 'pending' => 0, 'archived' => 0, 'created_by' => auth()->id()]);
                if (! $status->save()) {
                    throw ValidationException::withMessages($status->getErrors()->toArray());
                }
                $project->reservation_status_id = $status->id;
                $project->saveOrFail();
            }
            foreach ($assets as $asset) {
                $this->authorize('update', $asset);
                if (! $asset->availableForCheckout() || DB::table('project_asset')->where('asset_id', $asset->id)->exists() || ! $asset->canCheckoutTo($project)) {
                    throw ValidationException::withMessages(['identifiers' => trans('projects.asset_unavailable', ['value' => $asset->asset_tag])]);
                }
                $project->assets()->attach($asset->id, ['original_status_id' => $asset->status_id, 'reserved_by' => auth()->id()]);
                $asset->status_id = $status->id;
                if (! $asset->save()) {
                    throw ValidationException::withMessages($asset->getErrors()->toArray());
                }
                $this->event($project, 'reserved', $asset->asset_tag);
            }
        }, 3);

        return redirect()->route('projects.show', $project)->with('success', trans('projects.reserved'));
    }

    public function release(ProjectAssetRequest $request, Project $project, int $asset): RedirectResponse
    {
        DB::transaction(function () use ($project, $asset): void {
            $project = Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
            $asset = $project->assets()->where('assets.id', $asset)->lockForUpdate()->firstOrFail();
            $this->authorize('update', $asset);
            if ($asset->assigned_to || DB::table('project_issues')->where('asset_id', $asset->id)->whereNull('handed_over_at')->whereNull('cancelled_at')->whereIn('session_id', DB::table('project_issue_sessions')->where('project_id', $project->id)->select('id'))->exists()) {
                throw ValidationException::withMessages(['asset_id' => trans('projects.return_first')]);
            }
            if ((int) $asset->status_id === (int) $project->reservation_status_id) {
                $status = Statuslabel::find($asset->pivot->original_status_id);
                if (! $status || ! $status->deployable || $status->archived) {
                    throw ValidationException::withMessages(['asset_id' => trans('projects.restore_status')]);
                }
                $asset->status_id = $status->id;
                if (! $asset->save()) {
                    throw ValidationException::withMessages($asset->getErrors()->toArray());
                }
            }
            $project->assets()->detach($asset->id);
            $this->event($project, 'released', $asset->asset_tag);
        });

        return redirect()->route('projects.show', $project)->with('success', trans('projects.released'));
    }

    private function event(Project $project, string $action, string $description): void
    {
        DB::table('project_events')->insert(['project_id' => $project->id, 'actor_id' => auth()->id(), 'action' => $action, 'description' => $description, 'created_at' => now(), 'updated_at' => now()]);
    }
}
