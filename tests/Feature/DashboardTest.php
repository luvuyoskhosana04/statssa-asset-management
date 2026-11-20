<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\User;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    public function test_users_with_no_visibility_are_redirected_to_own_assets(): void
    {
        // A regular user with no view grants on any HasCalendarEvents
        // adopter fails the canViewUsersAndCheckoutables gate and
        // still lands on their own /account/view-assets page, matching
        // the pre-widening behavior for people with no read access.
        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertRedirect(route('view-assets'));
    }

    public function test_admin_sees_reporting_dashboard_without_legacy_cards(): void
    {
        Asset::factory()->count(2)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('home'))
            ->assertOk()
            ->assertViewIs('dashboard')
            ->assertSee('id="dashboard-insights"', false)
            ->assertSee('data-insight-chart="statuses"', false)
            ->assertDontSee('dashboard small-box', false)
            ->assertDontSee('dashboard-today-calendar', false)
            ->assertDontSee('activityReport', false)
            ->assertDontSee('wire:lazy', false);
    }

    public function test_non_admin_with_view_asset_sees_dashboard_without_top_boxes(): void
    {
        // A user with `assets.view` passes canViewUsersAndCheckoutables
        // (Asset is a HasCalendarEvents source) and gets the widget
        // section of the dashboard. The top-boxes row and the empty-
        // inventory shortcut both live behind the admin-only wrapper
        // and must not render.
        $viewer = User::factory()->viewAssets()->create();

        $response = $this->actingAs($viewer)
            ->get(route('home'))
            ->assertOk()
            ->assertViewIs('dashboard');

        // The top-box row wraps each cell in a "dashboard small-box"
        // div. Absence of that class in the rendered HTML proves the
        // admin-only region did not render.
        $response->assertDontSee('dashboard small-box', false);

        // The empty-inventory shortcut headline (only appears for
        // admins on empty installs) must also be absent for
        // non-admins regardless of inventory state.
        $response->assertDontSee(trans('general.dashboard_empty'), false);
    }

    public function test_empty_inventory_still_renders_reporting_dashboard(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('home'))
            ->assertOk()
            ->assertSee('id="dashboard-insights"', false)
            ->assertDontSee(trans('general.dashboard_empty'), false);
    }

    public function test_accessory_only_viewer_sees_permission_context_instead_of_blank_dashboard(): void
    {
        $this->actingAs(User::factory()->viewAccessories()->create())
            ->get(route('home'))
            ->assertOk()
            ->assertSee(trans('presentation.reporting_unavailable'))
            ->assertDontSee('id="dashboard-insights"', false)
            ->assertDontSee('dashboard small-box', false);
    }
}
