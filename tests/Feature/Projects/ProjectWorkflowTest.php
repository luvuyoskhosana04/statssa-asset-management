<?php

namespace Tests\Feature\Projects;

use App\Events\CheckoutAccepted;
use App\Models\Asset;
use App\Models\CheckoutAcceptance;
use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ProjectWorkflowTest extends TestCase
{
    public function test_project_pages_render_for_manager(): void
    {
        $project = Project::factory()->create();
        $this->actingAs(User::factory()->admin()->create());
        foreach (['projects.index' => [], 'projects.create' => [], 'projects.edit' => [$project], 'projects.issue.index' => []] as $route => $parameters) {
            $this->get(route($route, $parameters))->assertOk();
        }
        foreach (['assets', 'people', 'locations', 'history'] as $tab) {
            $this->get(route('projects.show', [$project, 'tab' => $tab]))->assertOk();
        }
    }

    public function test_project_pages_require_permission(): void
    {
        $project = Project::factory()->create();
        $this->actingAs(User::factory()->create());
        foreach (['projects.index' => [], 'projects.create' => [], 'projects.edit' => [$project], 'projects.show' => [$project], 'projects.issue.index' => []] as $route => $parameters) {
            $this->get(route($route, $parameters))->assertForbidden();
        }
    }

    public function test_asset_viewer_cannot_mutate_projects_or_view_employee_tabs(): void
    {
        $project = Project::factory()->create();
        $this->actingAs(User::factory()->viewAssets()->create());
        $this->get(route('projects.show', $project))->assertOk();
        $this->get(route('projects.show', [$project, 'tab' => 'people']))->assertForbidden();
        $this->get(route('projects.create'))->assertForbidden();
        $this->get(route('projects.edit', $project))->assertForbidden();
        $this->post(route('projects.store'), [])->assertForbidden();
        $this->put(route('projects.update', $project), [])->assertForbidden();
        $this->post(route('projects.reserve', $project), ['identifiers' => 'anything'])->assertForbidden();
        $this->get(route('projects.issue.index'))->assertForbidden();
    }

    public function test_project_creation_validates_dates_and_unique_identifiers(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $data = ['name' => 'Field survey', 'code' => 'LOCAL-01', 'status' => 'active', 'start_date' => today()->toDateString(), 'end_date' => today()->addMonth()->toDateString()];
        $this->post(route('projects.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('projects', ['code' => 'LOCAL-01']);
        $this->post(route('projects.store'), $data)->assertSessionHasErrors('code');
        $this->post(route('projects.store'), array_merge($data, ['code' => 'LOCAL-02', 'end_date' => today()->subDay()->toDateString()]))->assertSessionHasErrors('end_date');
        $project = Project::where('code', 'LOCAL-01')->firstOrFail();
        $this->put(route('projects.update', $project), array_merge($data, ['name' => 'Revised survey']))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'Revised survey']);
    }

    public function test_reservation_blocks_standard_checkout_and_can_be_released(): void
    {
        $project = Project::factory()->create();
        $asset = Asset::factory()->create();
        $original = $asset->status_id;
        $this->actingAs(User::factory()->admin()->create());
        $this->post(route('projects.reserve', $project), ['identifiers' => $asset->asset_tag])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('project_asset', ['project_id' => $project->id, 'asset_id' => $asset->id, 'original_status_id' => $original]);
        $this->assertFalse($asset->fresh()->availableForCheckout());
        $this->assertNull($asset->fresh()->assigned_to);
        $this->delete(route('projects.release', [$project, $asset]))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('project_asset', ['asset_id' => $asset->id]);
        $this->assertEquals($original, $asset->fresh()->status_id);
        $this->assertTrue($asset->fresh()->availableForCheckout());
    }

    public function test_reservation_batch_is_atomic_and_cannot_reserve_for_two_projects(): void
    {
        $project = Project::factory()->create();
        $other = Project::factory()->create();
        $asset = Asset::factory()->create();
        $this->actingAs(User::factory()->admin()->create());
        $this->post(route('projects.reserve', $project), ['identifiers' => $asset->asset_tag."\nNOT-REGISTERED"])->assertSessionHasErrors('identifiers');
        $this->assertDatabaseCount('project_asset', 0);
        $this->assertTrue($asset->fresh()->availableForCheckout());
        $this->post(route('projects.reserve', $project), ['identifiers' => $asset->serial])->assertSessionHasNoErrors();
        $this->post(route('projects.reserve', $other), ['identifiers' => $asset->asset_tag])->assertSessionHasErrors('identifiers');
        $this->assertDatabaseCount('project_asset', 1);
    }

    public function test_returned_project_stock_is_not_counted_as_deployable(): void
    {
        $project = Project::factory()->create();
        $asset = Asset::factory()->create();
        $status = $asset->status_id;
        $manager = User::factory()->admin()->create();
        $this->actingAs($manager)->post(route('projects.reserve', $project), ['identifiers' => $asset->asset_tag])->assertSessionHasNoErrors();
        $asset->refresh();
        $asset->status_id = $status;
        $asset->save();
        $response = $this->actingAsForApi($manager)->getJson(route('api.dashboard.insights'))->assertOk();
        $this->assertEquals(0, collect($response->json('payload.assets.categories'))->sum('available'));
        $this->assertContains(trans('projects.reserved_stock'), array_column($response->json('payload.assets.statuses'), 'label'));
    }

    public function test_employee_membership_does_not_assign_equipment(): void
    {
        $project = Project::factory()->create();
        $worker = User::factory()->create();
        $this->actingAs(User::factory()->admin()->create());
        $this->post(route('projects.members.store', $project), ['user_id' => $worker->id])->assertSessionHasNoErrors();
        $this->assertTrue($project->members()->whereKey($worker->id)->exists());
        $this->delete(route('projects.members.remove', [$project, $worker]))->assertSessionHasNoErrors();
        $this->assertFalse($project->members()->whereKey($worker->id)->exists());
    }

    private function issuingContext(): array
    {
        $manager = User::factory()->admin()->create();
        $project = Project::factory()->create();
        $asset = Asset::factory()->create();
        $worker = User::factory()->create(['employee_num' => 'WORKER-001']);
        $project->members()->attach($worker->id);
        $this->actingAs($manager);
        $this->post(route('projects.reserve', $project), ['identifiers' => $asset->asset_tag])->assertSessionHasNoErrors();
        $this->post(route('projects.issue.start'), ['project_id' => $project->id])->assertSessionHasNoErrors();
        $session = DB::table('project_issue_sessions')->where('created_by', $manager->id)->first()->id;

        return [$project, $asset, $worker, $session, $manager];
    }

    public function test_issue_session_renders_and_is_private_to_issuing_official(): void
    {
        [$project, $asset, $worker, $session] = $this->issuingContext();
        $this->get(route('projects.issue.show', $session))->assertOk()->assertSee($project->name);
        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('projects.issue.show', $session))->assertNotFound();
        $this->actingAs(User::factory()->create());
        $this->get(route('projects.issue.show', $session))->assertForbidden();
    }

    public function test_issue_preview_has_clear_navigation_without_session_records(): void
    {
        [, , , $session] = $this->issuingContext();
        $this->get(route('projects.issue.show', $session))->assertOk()
            ->assertSee(trans('projects.preview_help'))
            ->assertSee('id="issue-next"', false)
            ->assertSee('id="issue-back"', false)
            ->assertSee('id="issue-cancel"', false)
            ->assertSee('id="issue-preview-success"', false)
            ->assertDontSee(trans('projects.session_records'))
            ->assertDontSee('Continue to employee acceptance');
        $this->assertDatabaseCount('project_issues', 0);
    }

    public function test_issue_validation_displays_the_actual_problem_and_retains_input(): void
    {
        [, $asset, , $session] = $this->issuingContext();
        $url = route('projects.issue.show', $session);
        $this->from($url)->post(route('projects.issue.store', $session), [
            'worker' => 'NOT-A-PROJECT-MEMBER', 'asset' => $asset->asset_tag,
        ])->assertRedirect($url)->assertSessionHasErrors('worker');
        $this->get($url)->assertOk()->assertSee(trans('projects.worker_missing'))
            ->assertSee('NOT-A-PROJECT-MEMBER')->assertDontSee(trans('general.notification_error_hint'));
        $this->assertDatabaseCount('project_issues', 0);
        $this->assertNull($asset->fresh()->assigned_to);
    }

    public function test_issue_uses_existing_checkout_acceptance_and_requires_handover(): void
    {
        [$project, $asset, $worker, $session] = $this->issuingContext();
        $this->post(route('projects.issue.store', $session), ['worker' => $worker->employee_num, 'asset' => $asset->asset_tag])->assertSessionHasNoErrors()->assertRedirect();
        $issue = DB::table('project_issues')->where('session_id', $session)->first();
        $this->assertEquals($worker->id, $asset->fresh()->assigned_to);
        $this->assertEquals($project->end_date->toDateString(), substr($asset->fresh()->expected_checkin, 0, 10));
        $acceptance = CheckoutAcceptance::findOrFail($issue->acceptance_id);
        $this->get(route('account.accept.item', $acceptance->id))->assertOk();
        $this->get(route('projects.issue.show', $session))->assertOk()->assertSee(trans('projects.pending_acceptance'));
        $this->post(route('projects.issue.handover', [$session, $issue->id]))->assertSessionHasErrors('asset');
        $this->post(route('projects.issue.close', $session))->assertSessionHasErrors('asset');
        Event::fake([CheckoutAccepted::class]);
        $this->post(route('account.store-acceptance', $acceptance), ['asset_acceptance' => 'accepted'])
            ->assertSessionHas('success')->assertRedirect(route('projects.issue.show', $session));
        $this->assertNotNull($acceptance->fresh()->accepted_at);
        $this->post(route('projects.issue.handover', [$session, $issue->id]))->assertSessionHasNoErrors();
        $this->assertNotNull(DB::table('project_issues')->where('id', $issue->id)->value('handed_over_at'));
        $this->post(route('projects.issue.close', $session))->assertSessionHasNoErrors();
        $this->assertNotNull(DB::table('project_issue_sessions')->where('id', $session)->value('closed_at'));
    }

    public function test_issue_rejects_non_members_missing_reservations_and_out_of_range_returns(): void
    {
        [$project, $asset, $worker, $session] = $this->issuingContext();
        $this->post(route('projects.issue.store', $session), ['worker' => 'NOT-MEMBER', 'asset' => $asset->asset_tag])->assertSessionHasErrors('worker');
        $this->post(route('projects.issue.store', $session), ['worker' => $worker->username, 'asset' => 'NOT-RESERVED'])->assertSessionHasErrors('asset');
        $this->post(route('projects.issue.store', $session), ['worker' => $worker->username, 'asset' => $asset->asset_tag, 'expected_checkin' => $project->end_date->addDay()->toDateString()])->assertSessionHasErrors('expected_checkin');
        $this->assertDatabaseCount('project_issues', 0);
        $this->assertNull($asset->fresh()->assigned_to);
    }

    public function test_resuming_signature_requires_session_ownership(): void
    {
        [$project, $asset, $worker, $session] = $this->issuingContext();
        $this->post(route('projects.issue.store', $session), ['worker' => $worker->username, 'asset' => $asset->asset_tag])->assertSessionHasNoErrors();
        $issue = DB::table('project_issues')->first();
        $this->get(route('projects.issue.sign', [$session, $issue->id]))->assertRedirect(route('account.accept.item', $issue->acceptance_id));
        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('projects.issue.sign', [$session, $issue->id]))->assertNotFound();
        $this->actingAs(User::factory()->create());
        $this->get(route('projects.issue.sign', [$session, $issue->id]))->assertForbidden();
    }

    public function test_failed_handover_can_be_resolved_only_after_asset_checkin(): void
    {
        [$project, $asset, $worker, $session] = $this->issuingContext();
        $this->post(route('projects.issue.store', $session), ['worker' => $worker->username, 'asset' => $asset->asset_tag])->assertSessionHasNoErrors();
        $issue = DB::table('project_issues')->first();
        $this->post(route('projects.issue.resolve', [$session, $issue->id]))->assertSessionHasErrors('asset');
        $asset->refresh();
        $asset->assigned_to = null;
        $asset->assigned_type = null;
        $asset->save();
        $this->assertFalse($asset->fresh()->availableForCheckout());
        $this->post(route('projects.issue.resolve', [$session, $issue->id]))->assertSessionHasNoErrors();
        $this->assertNotNull(DB::table('project_issues')->where('id', $issue->id)->value('cancelled_at'));
        $this->post(route('projects.issue.close', $session))->assertSessionHasNoErrors();
    }

    public function test_company_scope_hides_other_projects(): void
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
