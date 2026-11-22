<?php

namespace Tests\Feature\Inventory;

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\License;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ManualInventoryCreationDisabledTest extends TestCase
{
    public function test_manual_create_and_clone_are_denied_even_to_superusers(): void
    {
        foreach ([User::factory()->superuser()->create(), User::factory()->admin()->create(), User::factory()->createAssets()->createAccessories()->createLicenses()->create()] as $user) {
            $this->actingAs($user);
            foreach ([Asset::class, Accessory::class, License::class] as $model) {
                $this->assertFalse(Gate::allows('create', $model));
                $this->assertFalse(Gate::allows('clone', $model));
            }
        }
    }

    public function test_manual_create_pages_and_posts_are_forbidden_without_creating_inventory(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        foreach (['hardware', 'accessories', 'licenses'] as $resource) {
            $this->get(route($resource.'.create'))->assertForbidden();
            $this->post(route($resource.'.store'), [])->assertForbidden();
        }
        foreach (['assets', 'accessories', 'licenses'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_direct_api_creation_is_forbidden_without_creating_inventory(): void
    {
        $this->actingAsForApi(User::factory()->superuser()->create());
        foreach (['api.assets.store', 'api.accessories.store', 'api.licenses.store'] as $route) {
            $this->postJson(route($route), [])->assertForbidden();
        }
        foreach (['assets', 'accessories', 'licenses'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_clone_urls_cannot_open_creation_forms(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $asset = Asset::factory()->create();
        $accessory = Accessory::factory()->create();
        $license = License::factory()->create();
        $this->get(route('clone/hardware', $asset))->assertForbidden();
        $this->get(route('clone/accessories', $accessory))->assertForbidden();
        $this->post('/accessories/'.$accessory->id.'/clone')->assertForbidden();
        $this->get(route('clone/license', $license))->assertForbidden();
    }

    public function test_receiving_retains_existing_permission_requirements_and_import_is_available(): void
    {
        $this->actingAs(User::factory()->viewAssets()->createAssets()->create());
        $this->assertTrue(Gate::allows('receive', Asset::class));
        $this->get(route('hardware.receive.create'))->assertOk();
        $this->actingAs(User::factory()->viewAssets()->create());
        $this->assertFalse(Gate::allows('receive', Asset::class));
        $this->get(route('hardware.receive.create'))->assertForbidden();
        $this->actingAs(User::factory()->superuser()->create());
        $this->assertTrue(Gate::allows('import'));
        $this->assertTrue(Gate::allows('update', Asset::class));
        $this->assertTrue(Gate::allows('checkout', Asset::class));
        $this->get(route('imports.index'))->assertOk();
    }

    public function test_creation_links_are_removed_from_inventory_pages_and_quick_actions(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        foreach (['hardware', 'accessories', 'licenses'] as $resource) {
            $this->get(route($resource.'.index'))->assertOk()
                ->assertDontSee('href="'.route($resource.'.create').'"', false);
        }
    }
}
