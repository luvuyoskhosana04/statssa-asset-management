<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectAssetRequest;
use App\Http\Requests\SaveProjectRequest;
use App\Models\Asset;
use App\Models\Company;
use App\Models\Location;
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
        $query = Project::query()->with('location')->withCount('assets');
        if (Gate::allows('view', User::class)) {
            $query->withCount('members');
        }
        if (in_array($request->input('status', 'active'), ['planned', 'active', 'suspended', 'ended'], true)) {
            $query->where('status', $request->input('status', 'active'));
        }
        if ($search = mb_substr(trim((string) $request->input('search')), 0, 255)) {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%'));
        }

        return view('projects.index', ['projects' => $query->orderBy('name')->paginate(20)->withQueryString()]);
    }

    public function create(): View
    {
        $this->authorize('create', Project::class);

        return $this->form(new Project(['status' => 'planned']));
    }

    public function edit(Project $project): View
    {
        $this->authorize('update', $project);

        return $this->form($project);
    }

    private function form(Project $project): View
    {
        return view('projects.edit', [
            'project' => $project,
            'companies' => Company::orderBy('name')->get(),
            'locations' => Location::orderBy('name')->get(),
        ]);
    }

    public function store(SaveProjectRequest $request): RedirectResponse
    {
        return $this->save($request, new Project);
    }

    public function update(SaveProjectRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        return $this->save($request, $project);
    }

    private function save(SaveProjectRequest $request, Project $project): RedirectResponse
    {
        $data = $request->validated();
        $company = ! empty($data['company_id']) ? Company::findOrFail($data['company_id']) : null;
        $location = ! empty($data['location_id']) ? Location::findOrFail($data['location_id']) : null;
        if (! $request->user()->hasAccess('superuser') && \App\Models\Setting::getSettings()->full_multiple_companies_support && ! $company) {
            throw ValidationException::withMessages(['company_id' => trans('projects.company_required')]);
        }
        if ($location && $company && $location->effectiveFmcsCompanyId() && $location->effectiveFmcsCompanyId() !== $company->id) {
            throw ValidationException::withMessages(['location_id' => trans('projects.company_mismatch')]);
        }
        DB::transaction(function () use ($project, $data, $request): void {
            if ($project->exists) {
                $project = Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
                if (in_array($data['status'], ['ended', 'planned']) && $project->assets()->whereNotNull('assigned_to')->exists()) {
                    throw ValidationException::withMessages(['status' => trans('projects.return_first')]);
                }
                if (($project->assets()->exists() || $project->members()->exists()) && (int) $project->company_id !== (int) ($data['company_id'] ?? 0)) {
                    throw ValidationException::withMessages(['company_id' => trans('projects.company_locked')]);
                }
            }
            $project->fill($data);
            $project->created_by ??= $request->user()->id;
            if (! $project->save()) {
                throw ValidationException::withMessages($project->getErrors()->toArray());
            }
            $this->event($project, 'saved', $project->code);
        });

        return redirect()->route('projects.show', $project)->with('success', trans('projects.saved'));
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
        $this->authorize('view', User::class);
        $user = User::findOrFail($request->integer('user_id'));
        abort_unless($user->activated && $project->canCheckoutTo($user), 422, trans('projects.company_mismatch'));
        DB::transaction(function () use ($project, $user): void {
            Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
            $project->members()->syncWithoutDetaching([$user->id]);
            $this->event($project, 'member_added', $user->display_name);
        });

        return redirect()->route('projects.show', [$project, 'tab' => 'people'])->with('success', trans('projects.saved'));
    }

    public function removeMember(ProjectAssetRequest $request, Project $project, int $user): RedirectResponse
    {
        $this->authorize('view', User::class);
        DB::transaction(function () use ($project, $user): void {
            Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
            $member = $project->members()->whereKey($user)->firstOrFail();
            if ($project->assets()->where('assigned_type', User::class)->where('assigned_to', $user)->exists()) {
                throw ValidationException::withMessages(['user_id' => trans('projects.return_first')]);
            }
            $project->members()->detach($user);
            $this->event($project, 'member_removed', $member->display_name);
        });

        return redirect()->route('projects.show', [$project, 'tab' => 'people']);
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
