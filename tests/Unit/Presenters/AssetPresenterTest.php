<?php

namespace Tests\Unit\Presenters;

use App\Models\Asset;
use App\Models\AssetModel;
use Tests\TestCase;

class AssetPresenterTest extends TestCase
{
    public function test_dynamic_url()
    {
        $this->settings->set(['locale' => 'en-US']);

        $assetModel = AssetModel::factory()->create([
            'model_number' => 'MN-123',
            'name' => 'Macbook',
        ]);

        $asset = Asset::factory()
            ->for($assetModel, 'model')
            ->create(['serial' => 'SN-123']);

        $this->assertEquals(
            'https://example.com/en-US/SN-123/MN-123/Macbook',
            $asset->present()->dynamicUrl('https://example.com/{LOCALE}/{SERIAL}/{MODEL_NUMBER}/{MODEL_NAME}')
        );
    }

    public function test_working_view_prioritises_identity_and_keeps_financial_fields_optional()
    {
        $layout = collect(json_decode(\App\Presenters\AssetPresenter::dataTableLayout(), true));
        $visible = $layout->filter(fn ($column) => $column['visible'] ?? false)->pluck('field')->all();

        $this->assertSame(['checkbox', 'name', 'asset_tag', 'model', 'status', 'assigned_to', 'location', 'checkincheckout', 'actions'], $visible);
        $this->assertFalse($layout->firstWhere('field', 'purchase_cost')['visible']);
        $this->assertFalse($layout->firstWhere('field', 'serial')['visible']);
    }

    public function test_table_presentation_does_not_restore_excluded_columns()
    {
        $layout = json_decode(\App\Presenters\AssetPresenter::dataTableLayout(['deleted_at']), true);

        $this->assertNotContains('deleted_at', array_column($layout, 'field'));
    }
}
