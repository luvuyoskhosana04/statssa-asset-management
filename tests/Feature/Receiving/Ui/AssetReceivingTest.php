<?php

namespace Tests\Feature\Receiving\Ui;

use App\Models\Asset;
use App\Models\Company;
use App\Models\Location;
use App\Models\Statuslabel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AssetReceivingTest extends TestCase
{
    private function pendingAsset(array $attributes = []): Asset
    {
        return Asset::factory()->create(array_merge(['assigned_to' => null, 'status_id' => Statuslabel::factory()->pending()->create()->id], $attributes));
    }

    private function tokenFor(Asset $asset): string
    {
        return $this->postJson(route('hardware.receive.lookup'), ['serial' => $asset->serial])
            ->assertOk()->assertJsonPath('status', 'success')->json('payload.receipt_token');
    }

    private function receiptBody(Asset $asset): array
    {
        return ['receipt_token' => $this->tokenFor($asset), 'asset_tag' => 'STATS-RECEIVE-001', 'status_id' => Statuslabel::factory()->readyToDeploy()->create()->id, 'condition' => 'good'];
    }

    public function test_page_renders(): void
    {
        $this->actingAs(User::factory()->viewAssets()->editAssets()->create())
            ->get(route('hardware.receive.index'))->assertOk()->assertSee('Scan to Receive')->assertSee('Receipt history');
    }

    public function test_requires_permission(): void
    {
        $this->actingAs(User::factory()->viewAssets()->create())->get(route('hardware.receive.index'))->assertForbidden();
        $this->postJson(route('hardware.receive.lookup'), ['serial' => 'ABC'])->assertForbidden();
        $this->postJson(route('hardware.receive.store', ['assetId' => 1]), [])->assertForbidden();
    }

    public function test_navigation_shows_receiving_to_asset_editors_and_creators(): void
    {
        $url = route('hardware.receive.index');
        $this->actingAs(User::factory()->viewAssets()->create())->get(route('hardware.index'))->assertOk()->assertDontSee($url, false);
        $this->actingAs(User::factory()->viewAssets()->editAssets()->create())->get(route('hardware.index'))->assertOk()->assertSee($url, false);
        $this->actingAs(User::factory()->viewAssets()->createAssets()->create())->get(route('hardware.index'))->assertOk()->assertSee($url, false);
    }

    public function test_receipt_updates_asset_and_records_history_and_condition(): void
    {
        $user = User::factory()->superuser()->create();
        $this->actingAs($user);
        $asset = $this->pendingAsset();
        $oldTag = $asset->asset_tag;
        $location = Location::factory()->create();
        $body = $this->receiptBody($asset) + ['location_id' => $location->id, 'note' => 'Power adapter included.'];
        $this->postJson(route('hardware.receive.store', ['assetId' => $asset->id]), $body)
            ->assertOk()->assertJsonPath('status', 'success')->assertJsonPath('payload.previous_tag', $oldTag);
        $asset->refresh();
        $this->assertSame($body['asset_tag'], $asset->asset_tag);
        $this->assertEquals($location->id, $asset->rtd_location_id);
        $this->assertNull($asset->assigned_to);
        $this->assertDatabaseHas('asset_receipts', ['asset_id' => $asset->id, 'received_by' => $user->id, 'condition' => 'good', 'previous_tag' => $oldTag]);
        $this->assertDatabaseHas('action_logs', ['item_id' => $asset->id, 'item_type' => Asset::class, 'action_type' => 'note added', 'note' => "Asset received. Condition: Good.\nPower adapter included."]);
    }

    public function test_duplicate_receipt_is_rejected_even_with_a_pending_outcome(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $asset = $this->pendingAsset();
        $body = $this->receiptBody($asset);
        $body['status_id'] = $asset->status_id;
        $url = route('hardware.receive.store', ['assetId' => $asset->id]);
        $this->postJson($url, $body)->assertOk();
        $this->postJson($url, $body)->assertOk()->assertJsonPath('status', 'error');
        $this->postJson(route('hardware.receive.lookup'), ['serial' => $asset->serial])->assertOk()->assertJsonPath('status', 'error');
        $this->assertSame(1, DB::table('asset_receipts')->where('asset_id', $asset->id)->count());
    }

    public function test_damaged_condition_requires_a_note_and_preserves_the_asset_on_failure(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $asset = $this->pendingAsset();
        $oldTag = $asset->asset_tag;
        $body = $this->receiptBody($asset);
        foreach (['damaged', 'faulty', 'missing_parts'] as $condition) {
            $body['condition'] = $condition;
            $this->postJson(route('hardware.receive.store', ['assetId' => $asset->id]), $body)->assertOk()->assertJsonPath('status', 'error')->assertJsonValidationErrors('note', 'messages');
        }
        $this->assertSame($oldTag, $asset->refresh()->asset_tag);
        $this->assertDatabaseMissing('asset_receipts', ['asset_id' => $asset->id]);
        $body['note'] = 'Adapter is missing.';
        $this->postJson(route('hardware.receive.store', ['assetId' => $asset->id]), $body)->assertOk();
    }

    public function test_duplicate_tag_and_archived_outcome_are_rejected(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $asset = $this->pendingAsset();
        $body = $this->receiptBody($asset);
        $body['asset_tag'] = Asset::factory()->create()->asset_tag;
        $body['status_id'] = Statuslabel::factory()->archived()->create()->id;
        $this->postJson(route('hardware.receive.store', ['assetId' => $asset->id]), $body)->assertOk()->assertJsonPath('status', 'error')->assertJsonValidationErrors(['asset_tag', 'status_id'], 'messages');
        $this->assertDatabaseMissing('asset_receipts', ['asset_id' => $asset->id]);
    }

    public function test_receipt_barcode_cannot_be_reused_after_the_first_asset_is_retagged(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $first = $this->pendingAsset();
        $body = $this->receiptBody($first);
        $this->postJson(route('hardware.receive.store', ['assetId' => $first->id]), $body)->assertJsonPath('status', 'success');
        $first->refresh()->update(['asset_tag' => 'RETAGGED-AFTER-RECEIPT']);
        $second = $this->pendingAsset();
        $originalTag = $second->asset_tag;
        $this->postJson(route('hardware.receive.store', ['assetId' => $second->id]), $this->receiptBody($second))
            ->assertOk()->assertJsonPath('status', 'error');
        $this->assertSame($originalTag, $second->refresh()->asset_tag);
        $this->assertDatabaseMissing('asset_receipts', ['asset_id' => $second->id]);
    }

    public function test_lookup_rejects_unknown_ambiguous_assigned_and_wrong_order_assets(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $url = route('hardware.receive.lookup');
        $this->postJson($url, ['serial' => 'UNKNOWN-RECEIPT'])->assertOk()->assertJsonPath('status', 'error');
        $asset = $this->pendingAsset(['order_number' => 'PO-1']);
        $this->postJson($url, ['serial' => $asset->serial, 'order_number' => 'PO-2'])->assertOk()->assertJsonPath('status', 'error');
        Asset::factory()->assignedToUser()->create(['serial' => $asset->serial]);
        $this->postJson($url, ['serial' => $asset->serial])->assertOk()->assertJsonPath('status', 'error');
        $assigned = Asset::factory()->assignedToUser()->create(['status_id' => $asset->status_id]);
        $this->postJson($url, ['serial' => $assigned->serial])->assertOk()->assertJsonPath('status', 'error');
    }

    public function test_token_cannot_be_tampered_with_transferred_or_used_after_record_changes(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $asset = $this->pendingAsset();
        $body = $this->receiptBody($asset);
        $url = route('hardware.receive.store', ['assetId' => $asset->id]);
        $validToken = $body['receipt_token'];
        $body['receipt_token'] = 'invalid';
        $this->postJson($url, $body)->assertOk()->assertJsonPath('status', 'error');
        $body['receipt_token'] = $validToken;
        $this->actingAs(User::factory()->superuser()->create());
        $this->postJson($url, $body)->assertOk()->assertJsonPath('status', 'error');
        $body['receipt_token'] = $this->tokenFor($asset);
        $asset->update(['name' => 'Changed after lookup']);
        $this->postJson($url, $body)->assertOk()->assertJsonPath('status', 'error');
        $this->assertDatabaseMissing('asset_receipts', ['asset_id' => $asset->id]);
    }

    public function test_expired_scan_requires_another_lookup(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $asset = $this->pendingAsset();
        $body = $this->receiptBody($asset);
        $this->travel(16)->minutes();
        $this->postJson(route('hardware.receive.store', ['assetId' => $asset->id]), $body)->assertOk()->assertJsonPath('status', 'error');
        $this->assertDatabaseMissing('asset_receipts', ['asset_id' => $asset->id]);
    }

    public function test_lookup_respects_company_scope(): void
    {
        $this->settings->set(['full_multiple_companies_support' => '1']);
        $company = Company::factory()->create();
        $asset = $this->pendingAsset(['company_id' => $company->id]);
        $this->actingAs(User::factory()->viewAssets()->editAssets()->forCompany(Company::factory()->create()->id)->create());
        $this->postJson(route('hardware.receive.lookup'), ['serial' => $asset->serial])->assertOk()->assertJsonPath('status', 'error');
    }
}
