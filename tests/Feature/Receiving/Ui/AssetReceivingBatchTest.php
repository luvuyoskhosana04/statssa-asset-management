<?php

namespace Tests\Feature\Receiving\Ui;

use App\Models\Asset;
use App\Models\Company;
use App\Models\Statuslabel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AssetReceivingBatchTest extends TestCase
{
    private function pendingAsset(array $attributes = []): Asset
    {
        return Asset::factory()->create(array_merge(['assigned_to' => null, 'status_id' => Statuslabel::factory()->pending()->create()->id], $attributes));
    }

    private function bodyFor(array $assets): array
    {
        $items = [];
        foreach ($assets as $index => $asset) {
            $token = $this->postJson(route('hardware.receive.lookup'), ['serial' => $asset->serial, 'for_batch' => true])->assertOk()->assertJsonPath('status', 'success')->json('payload.receipt_token');
            $items[] = ['asset_id' => $asset->id, 'asset_tag' => 'BATCH-TAG-'.$asset->id, 'receipt_token' => $token, 'condition' => 'good', 'note' => 'Inspected and received.'];
        }

        return ['request_key' => (string) Str::uuid(), 'status_id' => Statuslabel::factory()->readyToDeploy()->create()->id, 'items' => $items];
    }

    public function test_create_page_renders_and_requires_permission(): void
    {
        $this->actingAs(User::factory()->viewAssets()->create())->get(route('hardware.receive.create'))->assertForbidden();
        $this->postJson(route('hardware.receive.batches.store'), [])->assertForbidden();
        $this->get(route('hardware.receive.show', ['batchId' => 999]))->assertForbidden();
        $this->actingAs(User::factory()->viewAssets()->createAssets()->create())->get(route('hardware.receive.create'))->assertOk()->assertSee('Draft batch')->assertSee('Save batch');
        $this->assertDatabaseCount('asset_receipt_batches', 0);
    }

    public function test_lookups_do_not_receive_assets_and_save_commits_one_batch(): void
    {
        $actor = User::factory()->superuser()->create();
        $this->actingAs($actor);
        $first = $this->pendingAsset();
        $second = $this->pendingAsset();
        $oldTag = $first->asset_tag;
        $body = $this->bodyFor([$first, $second]);
        $this->assertSame($oldTag, $first->refresh()->asset_tag);
        $this->assertDatabaseCount('asset_receipts', 0);
        $this->assertDatabaseCount('asset_receipt_batches', 0);
        $response = $this->postJson(route('hardware.receive.batches.store'), $body)->assertOk()->assertJsonPath('status', 'success')->assertJsonPath('payload.item_count', 2);
        $id = $response->json('payload.batch_id');
        $this->assertDatabaseCount('asset_receipt_batches', 1);
        $this->assertDatabaseHas('asset_receipts', ['batch_id' => $id, 'asset_id' => $first->id, 'previous_tag' => $oldTag]);
        $this->assertSame($body['items'][0]['asset_tag'], $first->refresh()->asset_tag);
        $this->assertNull($first->assigned_to);
        $this->assertSame($body['items'][1]['asset_tag'], $second->refresh()->asset_tag);
        $this->get(route('hardware.receive.show', ['batchId' => $id]))->assertOk()->assertSee($first->serial)->assertSee($second->serial)->assertSee('View asset');
    }

    public function test_invalid_second_barcode_leaves_the_entire_batch_unsaved(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $first = $this->pendingAsset();
        $second = $this->pendingAsset();
        $oldTag = $first->asset_tag;
        $body = $this->bodyFor([$first, $second]);
        $body['items'][1]['asset_tag'] = Asset::factory()->create()->asset_tag;
        $this->postJson(route('hardware.receive.batches.store'), $body)->assertJsonPath('status', 'error');
        $this->assertSame($oldTag, $first->refresh()->asset_tag);
        $this->assertDatabaseCount('asset_receipts', 0);
        $this->assertDatabaseCount('asset_receipt_batches', 0);
    }

    public function test_a_record_changed_after_scanning_blocks_every_item(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $first = $this->pendingAsset();
        $second = $this->pendingAsset();
        $oldTag = $first->asset_tag;
        $body = $this->bodyFor([$first, $second]);
        $second->update(['name' => 'Changed after scanning']);
        $this->postJson(route('hardware.receive.batches.store'), $body)->assertJsonPath('status', 'error');
        $this->assertSame($oldTag, $first->refresh()->asset_tag);
        $this->assertDatabaseCount('asset_receipts', 0);
        $this->assertDatabaseCount('asset_receipt_batches', 0);
    }

    public function test_failure_saving_a_later_asset_rolls_back_earlier_changes_and_history(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $first = $this->pendingAsset();
        $second = $this->pendingAsset();
        DB::table('assets')->where('id', $second->id)->update(['model_id' => 999999999]);
        $oldTag = $first->asset_tag;
        $body = $this->bodyFor([$first, $second]);
        $this->postJson(route('hardware.receive.batches.store'), $body)->assertJsonPath('status', 'error');
        $this->assertSame($oldTag, $first->refresh()->asset_tag);
        $this->assertDatabaseCount('asset_receipt_batches', 0);
        $this->assertDatabaseCount('asset_receipts', 0);
        $this->assertDatabaseMissing('action_logs', ['item_id' => $first->id, 'action_type' => 'note added']);
    }

    public function test_retrying_a_successful_save_does_not_duplicate_the_batch(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $asset = $this->pendingAsset();
        $body = $this->bodyFor([$asset]);
        $url = route('hardware.receive.batches.store');
        $id = $this->postJson($url, $body)->assertJsonPath('status', 'success')->json('payload.batch_id');
        $this->postJson($url, $body)->assertJsonPath('status', 'success')->assertJsonPath('payload.batch_id', $id);
        $this->assertDatabaseCount('asset_receipt_batches', 1);
        $this->assertDatabaseCount('asset_receipts', 1);
        $this->assertSame(1, DB::table('action_logs')->where('item_id', $asset->id)->where('action_type', 'note added')->count());
        $body['items'][0]['asset_tag'] = 'CHANGED-AFTER-SAVE';
        $this->postJson($url, $body)->assertJsonPath('status', 'error');
        $this->assertDatabaseCount('asset_receipt_batches', 1);
    }

    public function test_a_different_operator_cannot_replay_another_operators_batch_key(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $asset = $this->pendingAsset();
        $body = $this->bodyFor([$asset]);
        $this->postJson(route('hardware.receive.batches.store'), $body)->assertJsonPath('status', 'success');
        $this->actingAs(User::factory()->superuser()->create());
        $this->postJson(route('hardware.receive.batches.store'), $body)->assertForbidden();
        $this->assertDatabaseCount('asset_receipts', 1);
    }

    public function test_newest_receipts_appear_first_and_search_keeps_the_full_batch_count(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $first = $this->pendingAsset();
        $second = $this->pendingAsset();
        $body = $this->bodyFor([$first, $second]);
        $older = $this->postJson(route('hardware.receive.batches.store'), $body)->assertJsonPath('status', 'success')->json('payload.batch_id');
        $this->travel(1)->seconds();
        $newer = $this->postJson(route('hardware.receive.batches.store'), $this->bodyFor([$this->pendingAsset()]))->assertJsonPath('status', 'success')->json('payload.batch_id');
        $this->get(route('hardware.receive.index'))->assertOk()->assertSeeInOrder(['RCV-'.str_pad((string) $newer, 6, '0', STR_PAD_LEFT), 'RCV-'.str_pad((string) $older, 6, '0', STR_PAD_LEFT)]);
        $search = $this->get(route('hardware.receive.index', ['q' => $body['items'][0]['asset_tag']]))->assertOk();
        $this->assertSame(1, $search->viewData('batches')->total());
        $this->assertEquals(2, $search->viewData('batches')->first()->item_count);
    }

    public function test_audit_details_keep_receipt_snapshots_when_current_records_change(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $asset = $this->pendingAsset();
        $modelName = $asset->model->name;
        $body = $this->bodyFor([$asset]);
        $id = $this->postJson(route('hardware.receive.batches.store'), $body)->assertJsonPath('status', 'success')->json('payload.batch_id');
        $asset->refresh()->update(['asset_tag' => 'CURRENT-TAG-CHANGED']);
        $asset->model->update(['name' => 'Current model name changed']);
        $response = $this->get(route('hardware.receive.show', ['batchId' => $id]))->assertOk();
        $this->assertSame($body['items'][0]['asset_tag'], $response->viewData('items')->first()->asset_tag);
        $this->assertSame($modelName, $response->viewData('items')->first()->model_name);
    }

    public function test_history_and_details_only_expose_accessible_company_records(): void
    {
        $this->settings->enableMultipleFullCompanySupport();
        [$own, $other] = Company::factory()->count(2)->create();
        $this->actingAs(User::factory()->superuser()->create());
        $first = $this->pendingAsset(['company_id' => $own->id]);
        $second = $this->pendingAsset(['company_id' => $other->id]);
        $mixed = $this->postJson(route('hardware.receive.batches.store'), $this->bodyFor([$first, $second]))->assertJsonPath('status', 'success')->json('payload.batch_id');
        $private = $this->postJson(route('hardware.receive.batches.store'), $this->bodyFor([$this->pendingAsset(['company_id' => $other->id])]))->assertJsonPath('status', 'success')->json('payload.batch_id');
        $this->actingAs(User::factory()->viewAssets()->editAssets()->forCompany($own->id)->create());
        $index = $this->get(route('hardware.receive.index'))->assertOk();
        $this->assertSame(1, $index->viewData('batches')->total());
        $this->assertEquals(1, $index->viewData('batches')->first()->item_count);
        $detail = $this->get(route('hardware.receive.show', ['batchId' => $mixed]))->assertOk()->assertSee($first->serial)->assertDontSee($second->serial);
        $this->assertCount(1, $detail->viewData('items'));
        $hiddenSearch = $this->get(route('hardware.receive.index', ['q' => $second->serial]))->assertOk();
        $this->assertSame(0, $hiddenSearch->viewData('batches')->total());
        $this->get(route('hardware.receive.show', ['batchId' => $private]))->assertNotFound();
    }

    public function test_empty_duplicate_and_missing_condition_notes_cannot_be_saved(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $asset = $this->pendingAsset();
        $body = $this->bodyFor([$asset]);
        $url = route('hardware.receive.batches.store');
        $empty = $body;
        $empty['items'] = [];
        $this->postJson($url, $empty)->assertJsonPath('status', 'error');
        $duplicate = $body;
        $duplicate['items'][] = $duplicate['items'][0];
        $this->postJson($url, $duplicate)->assertJsonPath('status', 'error');
        $body['items'][0]['condition'] = 'damaged';
        $body['items'][0]['note'] = '';
        $this->postJson($url, $body)->assertJsonPath('status', 'error')->assertJsonValidationErrors('items.0.note', 'messages');
        $this->assertDatabaseCount('asset_receipts', 0);
        $this->assertDatabaseCount('asset_receipt_batches', 0);
    }

    public function test_batch_tokens_allow_time_to_scan_multiple_items(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $asset = $this->pendingAsset();
        $body = $this->bodyFor([$asset]);
        $this->travel(16)->minutes();
        $this->postJson(route('hardware.receive.batches.store'), $body)->assertJsonPath('status', 'success');
        $this->assertDatabaseCount('asset_receipts', 1);
    }
}
