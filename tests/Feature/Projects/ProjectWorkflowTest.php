<?php

namespace Tests\Feature\Projects;

use App\Actions\Projects\SendProjectIssueNoticeAction;
use App\Models\Asset;
use App\Models\CheckoutAcceptance;
use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use App\Notifications\ProjectIssueAcceptanceNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProjectWorkflowTest extends TestCase
{
    public function test_analytics_separates_currencies_and_reports_missing_costs(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $project = Project::factory()->create();
        foreach ([['ZAR', 123.45], ['USD', 20], ['ZAR', null]] as [$currency, $cost]) {
            $location = \App\Models\Location::factory()->create(['currency' => $currency]);
            $asset = Asset::factory()->create(['purchase_cost' => $cost, 'location_id' => $location->id]);
            $project->assets()->attach($asset, ['original_status_id' => $asset->status_id, 'reserved_by' => auth()->id()]);
        }
        $this->get(route('projects.index', ['tab' => 'analytics', 'currency' => 'ZAR']))->assertOk()
            ->assertSee(trans('projects.analytics_tab'))->assertViewHas('analytics', fn ($data) => $data['value'] === 12345 && $data['assets'] === 3 && $data['missing_costs'] === 1 && $data['currencies'] === ['USD', 'ZAR']);
        $this->get(route('projects.index', ['tab' => 'analytics', 'currency' => 'USD']))->assertOk()
            ->assertViewHas('analytics', fn ($data) => $data['value'] === 2000 && $data['currency'] === 'USD');
    }

    public function test_analytics_renders_empty_state_and_requires_permission(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('projects.index', ['tab' => 'analytics']))
            ->assertOk()->assertSee(trans('projects.analytics_empty'))->assertViewHas('analytics', fn ($data) => $data['assets'] === 0 && $data['value'] === 0);
        $this->actingAs(User::factory()->create())->get(route('projects.index', ['tab' => 'analytics']))->assertForbidden();
    }

    public function test_analytics_excludes_other_companies(): void
    {
        $this->settings->enableMultipleFullCompanySupport();
        $visible = Company::factory()->create();
        $hidden = Company::factory()->create();
        $user = User::factory()->viewAssets()->create();
        $user->companies()->attach($visible);
        $project = Project::factory()->create(['company_id' => $visible->id]);
        $other = Project::factory()->create(['company_id' => $hidden->id, 'name' => 'Private project analytics']);
        $asset = Asset::factory()->create(['company_id' => $hidden->id, 'purchase_cost' => 999]);
        $other->assets()->attach($asset, ['original_status_id' => $asset->status_id, 'reserved_by' => $user->id]);
        $this->actingAs($user)->get(route('projects.index', ['tab' => 'analytics']))->assertOk()
            ->assertDontSee('Private project analytics')->assertViewHas('analytics', fn ($data) => $data['projects'] === 1 && $data['assets'] === 0 && $data['value'] === 0);
    }

    public function test_analytics_distinguishes_pending_acceptance_from_overdue_issued_assets(): void
    {
        [, $project, $worker, $asset] = $this->context();
        $location = \App\Models\Location::factory()->create(['currency' => 'ZAR']);
        $asset->update(['purchase_cost' => 88, 'location_id' => $location->id]);
        $session = DB::table('project_issue_sessions')->where('project_id', $project->id)->first()->id;
        $this->post(route('projects.issue.store', $session), $this->data($worker, $asset, $project))->assertSessionHasNoErrors();
        $project->update(['start_date' => today()->subYear(), 'end_date' => today()->subDay()]);
        $url = route('projects.index', ['tab' => 'analytics', 'currency' => 'ZAR']);
        $this->get($url)->assertOk()->assertViewHas('analytics', fn ($data) => $data['stages']['awaiting'] === 1 && $data['pending_returns'] === 0 && $data['at_risk'] === 0);
        $issue = DB::table('project_issues')->where('session_id', $session)->first();
        CheckoutAcceptance::findOrFail($issue->acceptance_id)->forceFill(['accepted_at' => now()])->save();
        $this->get($url)->assertOk()->assertViewHas('analytics', fn ($data) => $data['stages']['issued'] === 1 && $data['overdue'] === 1 && $data['at_risk'] === 8800);
    }

    private function context(): array
    {
        $manager = User::factory()->admin()->create();
        $project = Project::factory()->create();
        $worker = User::factory()->create(['employee_num' => 'EMP-001', 'email' => 'employee@example.test']);
        $asset = Asset::factory()->create();
        $this->actingAs($manager)->post(route('projects.issue.start'), ['project_id' => $project->id])->assertSessionHasNoErrors();
        $session = DB::table('project_issue_sessions')->where('created_by', $manager->id)->first()->id;

        return [$manager, $project, $worker, $asset, $session];
    }

    private function data(User $worker, Asset $asset, Project $project): array
    {
        return ['worker' => 'STATSSA-EMP-'.$worker->id, 'asset' => $asset->asset_tag, 'expected_checkin' => $project->end_date->toDateString(), 'reviewed' => 1, 'handover_declared' => 1, 'note' => 'Charger included'];
    }

    public function test_asset_search_filters_inventory_and_never_allocates(): void
    {
        [$manager, $project, $worker, $asset, $session] = $this->context();
        $asset->update(['asset_tag' => 'SEARCH-0001', 'serial' => 'SERIAL-SEARCH', 'name' => 'Field tablet']);
        $url = route('projects.issue.search-assets', $session);
        foreach (['SEARCH-0001', 'SERIAL-SEARCH', 'Field tablet', $asset->model->name] as $term) {
            $this->getJson($url.'?'.http_build_query(['q' => $term]))->assertOk()->assertJsonFragment(['asset_tag' => 'SEARCH-0001']);
        }
        $this->getJson($url.'?q=NO-MATCH-XYZ')->assertOk()->assertJsonCount(0, 'results');
        $this->getJson($url.'?q='.str_repeat('x', 256))->assertJsonPath('status', 'error');
        $this->assertDatabaseCount('project_issues', 0);
        $this->assertNull($asset->fresh()->assigned_to);
        $this->post(route('projects.issue.store', $session), $this->data($worker, $asset, $project))->assertSessionHasNoErrors();
        $this->getJson($url.'?q=SEARCH-0001')->assertOk()->assertJsonCount(0, 'results');
        $this->actingAs(User::factory()->admin()->create());
        $this->getJson($url)->assertNotFound();
        $this->actingAs($manager);
        DB::table('project_issue_sessions')->where('id', $session)->update(['closed_at' => now()]);
        $this->getJson($url)->assertStatus(409);
    }

    public function test_directory_is_readonly_even_for_managers(): void
    {
        [$manager, $project] = $this->context();
        $this->get(route('projects.index'))->assertOk()->assertSee('projectsListingTable')->assertDontSee('href="'.route('projects.create').'"', false)->assertDontSee('project-directory');
        $this->getJson(route('projects.index', ['table' => 1]))->assertOk()->assertJsonFragment(['name' => $project->name]);
        $this->get(route('projects.show', $project))->assertOk();
        $this->get(route('projects.create'))->assertForbidden();
        $this->get(route('projects.edit', $project))->assertForbidden();
        $this->post(route('projects.store'), [])->assertForbidden();
        $this->put(route('projects.update', $project), [])->assertForbidden();
        $this->post(route('projects.members.store', $project), ['user_id' => $manager->id])->assertForbidden();
        $this->delete(route('projects.members.remove', [$project, $manager->id]))->assertForbidden();
        $this->assertDatabaseCount('projects', 1);
    }

    public function test_project_views_and_scan_endpoints_require_permissions(): void
    {
        [, $project, , , $session] = $this->context();
        $this->actingAs(User::factory()->create());
        foreach (['projects.index' => [], 'projects.show' => [$project], 'projects.issue.index' => [], 'projects.issue.show' => [$session], 'projects.issue.audit' => [$session]] as $route => $args) {
            $this->get(route($route, $args))->assertForbidden();
        }
        $this->postJson(route('projects.issue.lookup-employee', $session), ['barcode' => 'EMP-001'])->assertForbidden();
        $this->postJson(route('projects.issue.lookup-asset', $session), ['barcode' => 'ANY'])->assertForbidden();
        $this->getJson(route('projects.issue.search-assets', $session))->assertForbidden();
    }

    public function test_scans_identify_real_directory_employee_and_inventory_without_allocating(): void
    {
        [, , $worker, $asset, $session] = $this->context();
        $this->get(route('projects.issue.show', $session))->assertOk()->assertSee('role="combobox"', false)->assertSee('id="issue-worker-list"', false)->assertDontSee(trans('projects.preview_help'))->assertDontSee(trans('projects.session_records'));
        $this->postJson(route('projects.issue.lookup-employee', $session), ['barcode' => $worker->employee_num])->assertOk()->assertJsonPath('name', $worker->display_name);
        $this->postJson(route('projects.issue.lookup-employee', $session), ['barcode' => 'STATSSA-EMP-'.$worker->id])->assertOk();
        $this->postJson(route('projects.issue.lookup-asset', $session), ['barcode' => $asset->serial])->assertOk()->assertJsonPath('asset_tag', $asset->asset_tag)->assertJsonPath('model', $asset->model->name)->assertJsonPath('condition', 'not_recorded');
        $this->postJson(route('projects.issue.lookup-employee', $session), ['barcode' => 'NOT-KNOWN'])->assertJsonPath('status', 'error');
        $this->postJson(route('projects.issue.lookup-asset', $session), ['barcode' => 'NOT-KNOWN'])->assertJsonPath('status', 'error');
        $this->assertNull($asset->fresh()->assigned_to);
        $this->assertDatabaseCount('project_issues', 0);
        $this->assertDatabaseCount('project_asset', 0);
    }

    public function test_issue_records_recipient_return_and_snapshot_and_blocks_duplicate_issue(): void
    {
        [, $project, $worker, $asset, $session] = $this->context();
        $this->post(route('projects.issue.store', $session), $this->data($worker, $asset, $project))->assertSessionHasNoErrors()->assertRedirect(route('projects.issue.audit', $session));
        $issue = DB::table('project_issues')->first();
        $this->assertEquals($worker->id, $asset->fresh()->assigned_to);
        $this->assertNull($issue->handover_declared_at);
        $this->assertNull($issue->confirmed_by);
        $this->assertNotEmpty(json_decode($issue->asset_snapshot, true)['planned_handover_at']);
        $this->assertNull($issue->handed_over_at);
        $this->assertEquals($project->end_date->toDateString(), $issue->expected_checkin);
        $this->assertEquals($asset->serial, json_decode($issue->asset_snapshot, true)['serial']);
        $this->assertFalse($asset->fresh()->availableForCheckout());
        $this->get(route('projects.issue.index', ['tab' => 'allocations']))->assertOk()->assertSee($worker->display_name)->assertSee(trans('projects.pending_acceptance'));
        $this->postJson(route('projects.issue.lookup-asset', $session), ['barcode' => $asset->asset_tag])->assertJsonPath('status', 'error');
        $this->post(route('projects.issue.store', $session), $this->data($worker, $asset, $project))->assertSessionHasErrors('asset');
        $this->assertDatabaseCount('project_issues', 1);
    }

    public function test_employee_acceptance_keeps_asset_reserved_and_requires_separate_handover(): void
    {
        [$manager, $project, $worker, $asset, $session] = $this->context();
        $this->post(route('projects.issue.store', $session), $this->data($worker, $asset, $project))->assertSessionHasNoErrors();
        $issue = DB::table('project_issues')->first();
        $this->post(route('projects.issue.handover', [$session, $issue->id]))->assertSessionHasErrors('asset');
        $acceptance = CheckoutAcceptance::findOrFail($issue->acceptance_id);
        // The issuing official cannot supply the employee response.
        $this->get(route('account.accept.item', $acceptance->id))->assertRedirect();
        $this->actingAs($worker)->get(route('account.accept.item', $acceptance->id))->assertOk();
        $acceptance->accept(null);
        $this->assertNull(DB::table('project_issues')->where('id', $issue->id)->value('handed_over_at'));
        $this->actingAs($manager);
        $this->get(route('projects.issue.audit', $session))->assertOk()->assertSee(trans('projects.handover_planned'))->assertSee('action="'.route('projects.issue.handover', [$session, $issue->id]).'"', false);
        $this->post(route('projects.issue.handover', [$session, $issue->id]))->assertSessionHasNoErrors();
        $this->assertNotNull(DB::table('project_issues')->where('id', $issue->id)->value('handed_over_at'));
        $this->assertFalse($asset->fresh()->availableForCheckout());
        $this->assertDatabaseHas('project_events', ['project_id' => $project->id, 'action' => 'accepted']);
    }

    public function test_rejection_returns_asset_to_available_stock_and_new_issue_can_proceed(): void
    {
        [$manager, $project, $worker, $asset, $session] = $this->context();
        $this->post(route('projects.issue.store', $session), $this->data($worker, $asset, $project))->assertSessionHasNoErrors();
        $issue = DB::table('project_issues')->first();
        $acceptance = CheckoutAcceptance::findOrFail($issue->acceptance_id);
        $this->actingAs($worker)->post(route('account.store-acceptance', $acceptance), ['asset_acceptance' => 'declined', 'note' => 'Wrong device'])->assertSessionHas('success');
        $this->actingAs($manager);
        $this->assertNull($asset->fresh()->assigned_to);
        $this->assertTrue($asset->fresh()->availableForCheckout());
        $this->assertDatabaseMissing('project_asset', ['asset_id' => $asset->id]);
        $this->assertNotNull(DB::table('project_issues')->where('id', $issue->id)->value('cancelled_at'));
        $this->get(route('projects.issue.audit', $session))->assertOk()->assertSee(trans('projects.rejected_status'));
        $this->post(route('projects.issue.store', $session), $this->data($worker, $asset, $project))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('project_issues', 2);
    }

    public function test_other_sessions_and_other_projects_cannot_issue_a_pending_asset(): void
    {
        [, $project,$worker,$asset,$session] = $this->context();
        $this->post(route('projects.issue.store', $session), $this->data($worker, $asset, $project))->assertSessionHasNoErrors();
        $otherProject = Project::factory()->create();
        $this->post(route('projects.issue.start'), ['project_id' => $otherProject->id]);
        $other = DB::table('project_issue_sessions')->where('project_id', $otherProject->id)->first()->id;
        $this->post(route('projects.issue.store', $other), $this->data($worker, $asset, $otherProject))->assertSessionHasErrors('asset');
        $this->assertDatabaseCount('project_issues', 1);
    }

    public function test_required_return_date_review_handover_and_unknown_asset_fail_without_reserving(): void
    {
        [, $project,$worker,$asset,$session] = $this->context();
        $data = $this->data($worker, $asset, $project);
        foreach (['expected_checkin', 'reviewed', 'handover_declared'] as $field) {
            $bad = $data;
            unset($bad[$field]);
            $this->post(route('projects.issue.store', $session), $bad)->assertSessionHasErrors($field);
        }
        $data['expected_checkin'] = $project->end_date->addDay()->toDateString();
        $this->post(route('projects.issue.store', $session), $data)->assertSessionHasErrors('expected_checkin');
        $this->assertDatabaseCount('project_asset', 0);
        $this->assertDatabaseCount('project_issues', 0);
    }

    public function test_audit_snapshots_survive_inventory_edits_and_are_visible_to_other_issuers(): void
    {
        [, $project,$worker,$asset,$session] = $this->context();
        $original = $asset->serial;
        $this->post(route('projects.issue.store', $session), $this->data($worker, $asset, $project))->assertSessionHasNoErrors();
        $asset->serial = 'CHANGED-SERIAL';
        $asset->save();
        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('projects.issue.show', $session))->assertNotFound();
        $this->get(route('projects.issue.audit', $session))->assertOk()->assertSee($original)->assertSee($worker->display_name);
    }

    public function test_notice_targets_selected_employee_with_acceptance_link_and_status(): void
    {
        Notification::fake();
        [, $project,$worker,$asset,$session] = $this->context();
        $this->post(route('projects.issue.store', $session), $this->data($worker, $asset, $project))->assertSessionHasNoErrors();
        $issue = DB::table('project_issues')->first();
        SendProjectIssueNoticeAction::run($issue->id);
        Notification::assertSentTo($worker, ProjectIssueAcceptanceNotification::class, function ($notification) use ($issue) {
            return $notification->acceptanceId === $issue->acceptance_id;
        });
        $this->assertContains(DB::table('project_issues')->value('notification_status'), ['demo_logged', 'sent']);
    }

    public function test_project_column_filters_cover_all_rows_and_combine_conditions(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Project::factory()->count(21)->create(['name' => 'A directory project', 'status' => 'active']);
        $target = Project::factory()->create(['name' => 'Z final project', 'status' => 'ended']);
        $url = route('projects.index', ['table' => 1]);
        $this->getJson($url)->assertOk()->assertJsonPath('total', 22);
        $filters = ['name' => ['op' => 'in', 'values' => [$target->name]], 'status' => ['op' => 'in', 'values' => [trans('projects.ended')]]];
        $this->getJson($url.'&'.http_build_query(['limit' => 1, 'column_filters' => json_encode($filters)]))
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('rows.0.name', $target->name);
        $filters['assets_count'] = ['op' => 'gt', 'value' => '0'];
        $this->getJson($url.'&'.http_build_query(['column_filters' => json_encode($filters)]))->assertOk()->assertJsonPath('total', 0);
        $this->getJson($url.'&column_filter_field=status')->assertOk()->assertJsonFragment(['values' => [trans('projects.active'), trans('projects.ended')]]);
        $this->getJson($url.'&'.http_build_query(['column_filters' => json_encode(['private_field' => ['op' => 'blank']])]))->assertJsonPath('status', 'error');
        $this->actingAs(User::factory()->create());
        $this->getJson($url)->assertForbidden();
        $this->getJson($url.'&column_filter_meta=1')->assertForbidden();
    }

    public function test_audit_can_start_another_issue_for_the_same_project(): void
    {
        [$manager, $project, , , $original] = $this->context();
        $this->get(route('projects.issue.audit', $original))->assertOk()
            ->assertSee(trans('projects.issue_another'))
            ->assertSee('action="'.route('projects.issue.start').'"', false)
            ->assertSee('name="project_id" value="'.$project->id.'"', false);
        $this->post(route('projects.issue.start'), ['project_id' => $project->id])->assertSessionHasNoErrors();
        $newSession = DB::table('project_issue_sessions')->where('created_by', $manager->id)->where('id', '!=', $original)->sole();
        $this->assertSame($project->id, (int) $newSession->project_id);
        $this->assertDatabaseHas('project_issue_sessions', ['id' => $original, 'closed_at' => null]);
        $this->get(route('projects.issue.show', $newSession->id))->assertOk()->assertSee('id="issue-worker"', false);
    }

    public function test_company_scope_hides_other_projects_and_employees(): void
    {
        $own = Company::factory()->create();
        $other = Company::factory()->create();
        $visible = Project::factory()->create(['company_id' => $own->id]);
        $hidden = Project::factory()->create(['company_id' => $other->id]);
        $this->settings->enableMultipleFullCompanySupport();
        $this->actingAs(User::factory()->viewAssets()->forCompany($own->id)->create());
        $this->getJson(route('projects.index', ['table' => 1]))->assertOk()->assertJsonFragment(['name' => $visible->name])->assertJsonMissing(['name' => $hidden->name])->assertJsonMissingPath('rows.0.members_count');
        $this->getJson(route('projects.index', ['table' => 1, 'column_filter_meta' => 1]))->assertOk()->assertJsonMissingPath('columns.members_count');
        $this->get(route('projects.show', $hidden))->assertRedirect(route('projects.index'));
    }
}
