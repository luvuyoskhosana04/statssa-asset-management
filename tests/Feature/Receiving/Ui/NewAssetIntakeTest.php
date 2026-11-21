<?php

namespace Tests\Feature\Receiving\Ui;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Company;
use App\Models\Location;
use App\Models\Statuslabel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NewAssetIntakeTest extends TestCase
{
    public function test_two_scan_receipt_keeps_original_serial_and_statssa_tag_and_replays_safely(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $body = $this->body();
        $body['intake_mode'] = 'two_scan';
        $body['items'] = [['serial' => 'MANUFACTURER-001', 'asset_tag' => 'STATSSA-001']];
        $this->postJson(route('hardware.receive.new-batches.store'), $body)->assertJsonPath('status', 'success');
        $this->postJson(route('hardware.receive.new-batches.store'), $body)->assertJsonPath('status', 'success');
        $this->assertDatabaseHas('assets', ['serial' => 'MANUFACTURER-001', 'asset_tag' => 'STATSSA-001']);
        $this->assertDatabaseHas('asset_receipts', ['serial' => 'MANUFACTURER-001', 'asset_tag' => 'STATSSA-001']);
        $this->assertDatabaseCount('assets', 1);
        $this->assertDatabaseCount('asset_receipt_batches', 1);
    }

    public function test_two_scan_requires_both_codes_and_rejects_cross_item_collisions_atomically(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $body = $this->body();
        $body['intake_mode'] = 'two_scan';
        foreach ([
            [['asset_tag' => 'TAG']],
            [['serial' => 'SAME', 'asset_tag' => 'same']],
            [['serial' => 'SERIAL-1', 'asset_tag' => 'TAG-1'], ['serial' => 'TAG-1', 'asset_tag' => 'TAG-2']],
        ] as $items) {
            $body['items'] = $items;
            $this->postJson(route('hardware.receive.new-batches.store'), $body)->assertJsonPath('status', 'error');
            $this->assertDatabaseCount('assets', 0);
            $this->assertDatabaseCount('asset_receipt_batches', 0);
        }
    }

    public function test_final_two_scan_save_rechecks_original_barcode_against_existing_assets(): void
    {
        Asset::factory()->create(['asset_tag' => 'EXISTING-ORIGINAL']);
        $this->actingAs(User::factory()->superuser()->create());
        $body = $this->body();
        $body['intake_mode'] = 'two_scan';
        $body['items'] = [['serial' => 'EXISTING-ORIGINAL', 'asset_tag' => 'NEW-TAG']];
        $this->postJson(route('hardware.receive.new-batches.store'), $body)->assertJsonPath('status', 'error');
        $this->assertDatabaseCount('assets', 1);
        $this->assertDatabaseCount('asset_receipt_batches', 0);
    }

    public function test_two_scan_draft_preserves_pending_and_received_stages_without_creating_inventory(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $data = ['version' => 2, 'request_key' => (string) Str::uuid(), 'items' => [
            ['id' => (string) Str::uuid(), 'serial' => 'WAITING-001', 'asset_tag' => '', 'model_id' => null, 'stage' => 'pending'],
            ['id' => (string) Str::uuid(), 'serial' => 'ORIGINAL-002', 'asset_tag' => 'STATSSA-002', 'model_id' => null, 'stage' => 'received'],
        ]];
        $this->postJson(route('hardware.receive.drafts.store'), $data)->assertJsonPath('status', 'success');
        $payload = json_decode(DB::table('asset_receiving_drafts')->where('id', $data['request_key'])->value('payload'), true);
        $this->assertSame(2, $payload['version']);
        $this->assertEquals($data['items'], $payload['items']);
        $this->get(route('hardware.receive.drafts.resume', $data['request_key']))->assertOk()->assertSee('WAITING-001')->assertSee('STATSSA-002');
        $this->assertDatabaseCount('assets', 0);
    }

    public function test_two_barcode_receiving_needs_no_model_selection_and_reuses_unclassified_metadata(): void
    {
        $this->actingAs(User::factory()->viewAssets()->createAssets()->create());
        $data = ['request_key' => (string) Str::uuid(), 'intake_mode' => 'two_scan',
            'status_id' => Statuslabel::factory()->readyToDeploy()->create()->id,
            'items' => [['serial' => 'ORIGINAL-NO-MODEL-1', 'asset_tag' => 'STATSSA-NO-MODEL-1']]];
        $this->get(route('hardware.receive.create'))->assertOk()
            ->assertDontSee('id="receive-manual-model"', false)
            ->assertDontSee('Choose an asset model')
            ->assertSee(trans('intake.barcode'))->assertSee(trans('intake.tag_label'));
        $this->postJson(route('hardware.receive.new-batches.store'), $data)->assertJsonPath('status', 'success');
        $this->postJson(route('hardware.receive.new-batches.store'), $data)->assertJsonPath('status', 'success');
        $asset = Asset::where('asset_tag', 'STATSSA-NO-MODEL-1')->firstOrFail();
        $this->assertSame('ORIGINAL-NO-MODEL-1', $asset->serial);
        $this->assertSame('Model not specified', $asset->model->name);
        $this->assertSame('Unclassified received assets', $asset->model->category->name);
        $data['request_key'] = (string) Str::uuid();
        $data['items'] = [['serial' => 'ORIGINAL-NO-MODEL-2', 'asset_tag' => 'STATSSA-NO-MODEL-2']];
        $this->postJson(route('hardware.receive.new-batches.store'), $data)->assertJsonPath('status', 'success');
        $this->assertDatabaseCount('assets', 2);
        $this->assertDatabaseCount('models', 1);
        $this->assertSame(1, DB::table('categories')->where('name', 'Unclassified received assets')->count());
        $this->assertDatabaseCount('asset_receipt_batches', 2);
    }

    public function test_failed_model_free_receipt_rolls_back_unclassified_metadata(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $data = ['request_key' => (string) Str::uuid(), 'intake_mode' => 'two_scan',
            'status_id' => Statuslabel::factory()->readyToDeploy()->create()->id,
            'items' => [['serial' => 'SAME-CODE', 'asset_tag' => 'same-code']]];
        $this->postJson(route('hardware.receive.new-batches.store'), $data)->assertJsonPath('status', 'error');
        $this->assertDatabaseCount('assets', 0);
        $this->assertDatabaseCount('models', 0);
        $this->assertDatabaseMissing('categories', ['name' => 'Unclassified received assets']);
    }

    private function body(): array
    {
        return ['request_key' => (string) Str::uuid(), 'model_id' => AssetModel::factory()->create()->id,
            'status_id' => Statuslabel::factory()->readyToDeploy()->create()->id,
            'items' => [['asset_tag' => '000123456789'], ['asset_tag' => '000123456790']]];
    }

    public function test_scanning_page_renders_without_preparing_or_authenticating_asset_records(): void
    {
        $this->actingAs(User::factory()->viewAssets()->createAssets()->create());
        $this->get(route('hardware.receive.create'))->assertOk()->assertSee('Scan asset barcodes')->assertSee('receive-barcode', false)
            ->assertSee(trans('intake.scan_automatic'))
            ->assertSee(trans('intake.save_later'))
            ->assertSee('id="receive-manual-open"', false)
            ->assertDontSee(trans('intake.details'))
            ->assertDontSee('id="receive-model"', false)
            ->assertDontSee('id="receive-start"', false)->assertDontSee('id="receive-serial-form"', false)->assertDontSee('data-lookup=', false);
        $this->assertDatabaseCount('assets', 0);
        $this->assertDatabaseCount('asset_receipt_batches', 0);
    }

    public function test_barcode_check_rejects_existing_tags_and_serials_without_exposing_records(): void
    {
        $asset = Asset::factory()->create(['asset_tag' => 'KNOWN-TAG', 'serial' => 'KNOWN-SERIAL']);
        $this->actingAs(User::factory()->viewAssets()->createAssets()->create());
        foreach (['KNOWN-TAG', 'KNOWN-SERIAL'] as $barcode) {
            $response = $this->postJson(route('hardware.receive.check-barcode'), ['barcode' => $barcode])
                ->assertOk()->assertJsonPath('payload.available', false);
            $this->assertSame(['available' => false], $response->json('payload'));
        }
        $asset->delete();
        $this->postJson(route('hardware.receive.check-barcode'), ['barcode' => 'KNOWN-TAG'])
            ->assertOk()->assertJsonPath('payload.available', false);
        $this->postJson(route('hardware.receive.check-barcode'), ['barcode' => 'NEW-BARCODE'])
            ->assertOk()->assertJsonPath('payload.available', true);
        $this->assertDatabaseCount('assets', 1);
        $this->assertDatabaseCount('asset_receipt_batches', 0);
    }

    public function test_barcode_check_requires_permission_and_valid_input(): void
    {
        User::factory()->superuser()->create();
        $this->postJson(route('hardware.receive.check-barcode'), ['barcode' => 'NEW'])->assertUnauthorized();
        $this->actingAs(User::factory()->viewAssets()->create());
        $this->postJson(route('hardware.receive.check-barcode'), ['barcode' => 'NEW'])->assertForbidden();
        $this->actingAs(User::factory()->superuser()->create());
        foreach (['', str_repeat('x', 256), "BAD\nCODE"] as $barcode) {
            $this->postJson(route('hardware.receive.check-barcode'), ['barcode' => $barcode])->assertJsonPath('status', 'error');
        }
    }

    public function test_final_save_also_rejects_a_barcode_that_matches_an_existing_serial(): void
    {
        Asset::factory()->create(['serial' => 'KNOWN-SERIAL']);
        $this->actingAs(User::factory()->superuser()->create());
        $body = $this->body();
        $body['items'] = [['asset_tag' => 'KNOWN-SERIAL']];
        $this->postJson(route('hardware.receive.new-batches.store'), $body)->assertJsonPath('status', 'error');
        $this->assertDatabaseCount('assets', 1);
        $this->assertDatabaseCount('asset_receipt_batches', 0);
    }

    public function test_drafts_are_saved_listed_and_resumed_for_their_owner_only(): void
    {
        $owner = User::factory()->superuser()->create();
        $this->actingAs($owner);
        $data = ['request_key' => (string) Str::uuid(), 'items' => [['asset_tag' => 'DRAFT-ONLY', 'model_id' => null]]];
        $this->postJson(route('hardware.receive.drafts.store'), $data)->assertOk()->assertJsonPath('status', 'success');
        $this->get(route('hardware.receive.index', ['tab' => 'drafts']))->assertOk()->assertSee('Resume draft');
        $this->get(route('hardware.receive.drafts.resume', $data['request_key']))->assertOk()->assertSee('DRAFT-ONLY');
        $this->assertDatabaseCount('assets', 0);
        $this->assertDatabaseCount('asset_receipt_batches', 0);
        $this->actingAs(User::factory()->superuser()->create());
        $this->get(route('hardware.receive.index', ['tab' => 'drafts']))->assertOk()->assertDontSee(strtoupper(substr($data['request_key'], 0, 8)));
        $this->get(route('hardware.receive.drafts.resume', $data['request_key']))->assertNotFound();
        $this->postJson(route('hardware.receive.drafts.store'), $data)->assertForbidden();
        $this->deleteJson(route('hardware.receive.drafts.discard', $data['request_key']))->assertOk();
        $this->assertDatabaseHas('asset_receiving_drafts', ['id' => $data['request_key'], 'user_id' => $owner->id]);
    }

    public function test_saving_a_draft_again_updates_it_without_creating_another_session(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $data = ['request_key' => (string) Str::uuid(), 'items' => [['asset_tag' => 'DRAFT-ONLY']]];
        $this->postJson(route('hardware.receive.drafts.store'), $data)->assertOk();
        $data['items'][] = ['asset_tag' => 'SECOND'];
        $this->postJson(route('hardware.receive.drafts.store'), $data)->assertOk();
        $this->assertDatabaseCount('asset_receiving_drafts', 1);
        $this->assertDatabaseHas('asset_receiving_drafts', ['id' => $data['request_key'], 'item_count' => 2]);
        $this->deleteJson(route('hardware.receive.drafts.discard', $data['request_key']))->assertOk();
        $this->assertDatabaseCount('asset_receiving_drafts', 0);
    }

    public function test_draft_permissions_and_final_receipt_remove_the_saved_draft(): void
    {
        $this->actingAs(User::factory()->viewAssets()->create());
        $this->postJson(route('hardware.receive.drafts.store'), [])->assertForbidden();
        $this->get(route('hardware.receive.drafts.resume', (string) Str::uuid()))->assertForbidden();
        $this->actingAs(User::factory()->superuser()->create());
        $body = $this->body();
        $this->postJson(route('hardware.receive.drafts.store'), $body)->assertOk();
        $this->postJson(route('hardware.receive.new-batches.store'), $body)->assertJsonPath('status', 'success');
        $this->assertDatabaseCount('asset_receiving_drafts', 0);
        $this->postJson(route('hardware.receive.drafts.store'), $body)->assertStatus(409);
    }

    public function test_operator_signin_and_asset_creation_permission_are_still_required(): void
    {
        User::factory()->superuser()->create();
        $this->get(route('hardware.receive.create'))->assertRedirect(route('login'));
        $this->postJson(route('hardware.receive.new-batches.store'), [])->assertUnauthorized();
        foreach ([User::factory()->viewAssets()->create(), User::factory()->viewAssets()->editAssets()->create()] as $user) {
            $this->actingAs($user)->get(route('hardware.receive.create'))->assertForbidden();
            $this->postJson(route('hardware.receive.new-batches.store'), [])->assertForbidden();
        }
    }

    public function test_save_creates_unregistered_assets_and_receipt_history_together(): void
    {
        $this->actingAs(User::factory()->viewAssets()->createAssets()->create());
        $body = $this->body();
        $response = $this->postJson(route('hardware.receive.new-batches.store'), $body)->assertOk()->assertJsonPath('status', 'success')->assertJsonPath('payload.item_count', 2);
        $id = $response->json('payload.batch_id');
        $this->assertDatabaseCount('assets', 2);
        $this->assertDatabaseCount('asset_receipts', 2);
        $this->assertDatabaseCount('asset_receipt_batches', 1);
        foreach ($body['items'] as $item) {
            $asset = Asset::where('asset_tag', $item['asset_tag'])->firstOrFail();
            $this->assertNull($asset->serial);
            $this->assertNull($asset->assigned_to);
            $this->assertEquals($body['model_id'], $asset->model_id);
            $this->assertDatabaseHas('asset_receipts', ['batch_id' => $id, 'asset_id' => $asset->id, 'condition' => 'not_recorded']);
            $this->assertDatabaseHas('action_logs', ['item_id' => $asset->id, 'item_type' => Asset::class, 'action_type' => 'create']);
        }
        $this->get(route('hardware.receive.index'))->assertOk()->assertSee('RCV-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT));
        $this->get(route('hardware.receive.show', ['batchId' => $id]))->assertOk()->assertSee('000123456789')->assertSee('Not assessed');
    }

    public function test_individual_models_can_override_the_shared_batch_model(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $body = $this->body();
        $other = AssetModel::factory()->create();
        $body['items'][1]['model_id'] = $other->id;
        $this->postJson(route('hardware.receive.new-batches.store'), $body)->assertJsonPath('status', 'success');
        $this->assertDatabaseHas('assets', ['asset_tag' => $body['items'][0]['asset_tag'], 'model_id' => $body['model_id']]);
        $this->assertDatabaseHas('assets', ['asset_tag' => $body['items'][1]['asset_tag'], 'model_id' => $other->id]);
    }

    public function test_draft_rows_can_save_with_individual_models_without_shared_details(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $body = $this->body();
        foreach ($body['items'] as &$item) {
            $item['model_id'] = $body['model_id'];
        }
        unset($item, $body['model_id']);
        $this->postJson(route('hardware.receive.new-batches.store'), $body)
            ->assertOk()->assertJsonPath('status', 'success')->assertJsonPath('payload.item_count', 2);
        $this->assertDatabaseCount('assets', 2);
        $this->assertDatabaseCount('asset_receipt_batches', 1);
    }

    public function test_models_are_required_before_save_but_no_token_or_existing_asset_id_is_required(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $body = $this->body();
        unset($body['model_id']);
        $this->postJson(route('hardware.receive.new-batches.store'), $body)->assertJsonPath('status', 'error');
        $this->assertDatabaseCount('assets', 0);
        $this->assertDatabaseCount('asset_receipt_batches', 0);
    }

    public function test_existing_barcode_rejects_the_whole_batch_without_updating_it(): void
    {
        $existing = Asset::factory()->create(['asset_tag' => '000123456790']);
        $this->actingAs(User::factory()->superuser()->create());
        $body = $this->body();
        $this->postJson(route('hardware.receive.new-batches.store'), $body)->assertJsonPath('status', 'error');
        $this->assertDatabaseCount('assets', 1);
        $this->assertDatabaseMissing('assets', ['asset_tag' => '000123456789']);
        $this->assertEquals($existing->model_id, $existing->fresh()->model_id);
        $this->assertDatabaseCount('asset_receipt_batches', 0);
    }

    public function test_empty_duplicate_control_characters_and_oversized_batches_are_rejected(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $body = $this->body();
        foreach ([[], [['asset_tag' => 'ABC'], ['asset_tag' => 'abc']], [['asset_tag' => "BAD\nTAG"]], array_fill(0, 201, ['asset_tag' => 'BIG'])] as $items) {
            $body['items'] = $items;
            $this->postJson(route('hardware.receive.new-batches.store'), $body)->assertJsonPath('status', 'error');
        }
        $this->assertDatabaseCount('assets', 0);
    }

    public function test_replaying_the_same_save_does_not_create_duplicate_assets_or_history(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $body = $this->body();
        $id = $this->postJson(route('hardware.receive.new-batches.store'), $body)->assertJsonPath('status', 'success')->json('payload.batch_id');
        $this->postJson(route('hardware.receive.new-batches.store'), $body)->assertJsonPath('status', 'success')->assertJsonPath('payload.batch_id', $id);
        $this->assertDatabaseCount('assets', 2);
        $this->assertDatabaseCount('asset_receipt_batches', 1);
        $this->assertDatabaseCount('asset_receipts', 2);
        $body['items'][0]['asset_tag'] = 'CHANGED';
        $this->postJson(route('hardware.receive.new-batches.store'), $body)->assertJsonPath('status', 'error');
        $this->assertDatabaseMissing('assets', ['asset_tag' => 'CHANGED']);
    }

    public function test_other_operator_cannot_replay_the_saved_batch(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $body = $this->body();
        $this->postJson(route('hardware.receive.new-batches.store'), $body)->assertJsonPath('status', 'success');
        $this->actingAs(User::factory()->superuser()->create())->postJson(route('hardware.receive.new-batches.store'), $body)->assertForbidden();
    }

    public function test_later_model_validation_failure_rolls_back_the_whole_creation(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $body = $this->body();
        $other = AssetModel::factory()->create();
        $body['items'][1]['model_id'] = $other->id;
        $dispatcher = Asset::getEventDispatcher();
        Asset::setEventDispatcher(clone $dispatcher);
        Asset::created(function (Asset $asset) use ($other): void {
            if ($asset->asset_tag === '000123456789') {
                DB::table('models')->where('id', $other->id)->update(['deleted_at' => now()]);
            }
        });
        try {
            $this->postJson(route('hardware.receive.new-batches.store'), $body)->assertJsonPath('status', 'error');
        } finally {
            Asset::setEventDispatcher($dispatcher);
        }
        $this->assertDatabaseCount('assets', 0);
        $this->assertDatabaseCount('asset_receipt_batches', 0);
        $this->assertDatabaseCount('asset_receipts', 0);
        $this->assertNull($other->fresh()->deleted_at);
    }

    public function test_company_scope_applies_to_created_assets_and_audit_history(): void
    {
        $this->settings->enableMultipleFullCompanySupport();
        [$own, $other] = Company::factory()->count(2)->create();
        $actor = User::factory()->viewAssets()->createAssets()->forCompany($own->id)->create();
        $this->actingAs($actor);
        $body = $this->body();
        $body['company_id'] = $own->id;
        $body['location_id'] = Location::factory()->create(['company_id' => $own->id])->id;
        $this->postJson(route('hardware.receive.new-batches.store'), $body)->assertJsonPath('status', 'success');
        $this->assertDatabaseHas('assets', ['asset_tag' => '000123456789', 'company_id' => $own->id, 'location_id' => $body['location_id']]);
        $body['request_key'] = (string) Str::uuid();
        $body['company_id'] = $other->id;
        $body['items'] = [['asset_tag' => 'OTHER-COMPANY']];
        $this->postJson(route('hardware.receive.new-batches.store'), $body)->assertJsonPath('status', 'error');
        $this->assertDatabaseMissing('assets', ['asset_tag' => 'OTHER-COMPANY']);
    }
}
