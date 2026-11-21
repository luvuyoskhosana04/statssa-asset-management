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

    public function test_directory_is_readonly_even_for_managers(): void
    {
        [$manager, $project] = $this->context();
        $this->get(route('projects.index'))->assertOk()->assertSee($project->name)->assertDontSee('href="'.route('projects.create').'"', false)->assertSee('project-directory');
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
        $this->assertNotNull($issue->handover_declared_at);
        $this->assertNull($issue->handed_over_at);
        $this->assertEquals($project->end_date->toDateString(), $issue->expected_checkin);
        $this->assertEquals($asset->serial, json_decode($issue->asset_snapshot, true)['serial']);
        $this->assertFalse($asset->fresh()->availableForCheckout());
        $this->get(route('projects.issue.index', ['tab' => 'allocations']))->assertOk()->assertSee($worker->display_name)->assertSee(trans('projects.pending_acceptance'));
        $this->postJson(route('projects.issue.lookup-asset', $session), ['barcode' => $asset->asset_tag])->assertJsonPath('status', 'error');
        $this->post(route('projects.issue.store', $session), $this->data($worker, $asset, $project))->assertSessionHasErrors('asset');
        $this->assertDatabaseCount('project_issues', 1);
    }

    public function test_employee_acceptance_keeps_asset_reserved_and_links_handover(): void
    {
        [, $project, $worker, $asset, $session] = $this->context();
        $this->post(route('projects.issue.store', $session), $this->data($worker, $asset, $project))->assertSessionHasNoErrors();
        $issue = DB::table('project_issues')->first();
        $acceptance = CheckoutAcceptance::findOrFail($issue->acceptance_id);
        // The issuing official cannot supply the employee response.
        $this->get(route('account.accept.item', $acceptance->id))->assertRedirect();
        $this->actingAs($worker)->get(route('account.accept.item', $acceptance->id))->assertOk();
        $acceptance->accept(null);
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

    public function test_company_scope_hides_other_projects_and_employees(): void
    {
        $own = Company::factory()->create();
        $other = Company::factory()->create();
        $visible = Project::factory()->create(['company_id' => $own->id]);
        $hidden = Project::factory()->create(['company_id' => $other->id]);
        $this->settings->enableMultipleFullCompanySupport();
        $this->actingAs(User::factory()->viewAssets()->forCompany($own->id)->create());
        $this->get(route('projects.index'))->assertOk()->assertSee($visible->name)->assertDontSee($hidden->name);
        $this->get(route('projects.show', $hidden))->assertRedirect(route('projects.index'));
    }
}
