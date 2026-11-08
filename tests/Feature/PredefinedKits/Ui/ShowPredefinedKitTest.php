<?php

namespace Tests\Feature\PredefinedKits\Ui;

use App\Models\PredefinedKit;
use App\Models\User;
use Tests\TestCase;

class ShowPredefinedKitTest extends TestCase
{
    public function test_page_renders()
    {
        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('kits.show', PredefinedKit::factory()->create()->id))
            ->assertOk();
    }

    public function test_bundle_summary_shows_required_quantity_and_available_stock()
    {
        $kit = PredefinedKit::factory()->create();
        $model = \App\Models\AssetModel::factory()->create(['name' => 'Office laptop']);
        $kit->models()->attach($model, ['quantity' => 2]);

        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('kits.show', $kit))
            ->assertOk()
            ->assertSee('Bundle contents')
            ->assertSee('Office laptop')
            ->assertSee('Quantity: 2')
            ->assertSee('Available: 0');
    }

    public function test_checkout_has_guided_steps_and_a_bundle_summary()
    {
        $kit = PredefinedKit::factory()->create();

        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('kits.checkout.show', $kit))
            ->assertOk()
            ->assertSee('data-stepper="kit"', false)
            ->assertSee('Bundle contents');
    }
}
