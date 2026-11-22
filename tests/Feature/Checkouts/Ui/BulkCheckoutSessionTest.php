<?php

namespace Tests\Feature\Checkouts\Ui;

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\Location;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class BulkCheckoutSessionTest extends TestCase
{
    private function batchPayload(array $items, array $extra = []): array
    {
        return array_merge([
            'mode' => 'user', 'target_id' => User::factory()->create()->id,
            'source_type' => 'any', 'items' => $items,
            'note' => 'Bulk checkout test', 'idempotency_key' => (string) Str::uuid(), 'reviewed' => true,
        ], $extra);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
    }

    public function test_session_and_catalog_require_checkout_permission(): void
    {
        $this->actingAs(User::factory()->create())->get(route('hardware.bulkcheckout.show'))->assertForbidden();
        $this->getJson(route('hardware.bulkcheckout.catalog'))->assertForbidden();
        $this->getJson(route('hardware.bulkcheckout.context'))->assertForbidden();
        $this->postJson(route('hardware.bulkcheckout.session'), $this->batchPayload([]))->assertForbidden();
    }

    public function test_workspace_renders_and_scan_enforces_source(): void
    {
        $asset = Asset::factory()->create(['order_number' => 'BULK-001']);
        $this->actingAs(User::factory()->superuser()->create())->get(route('hardware.bulkcheckout.show'))->assertOk()->assertSee('bulk-session', false);
        $this->getJson(route('hardware.bulkcheckout.catalog', ['scan' => $asset->asset_tag]))->assertOk()->assertJsonPath('payload.rows.0.id', $asset->id);
        $this->getJson(route('hardware.bulkcheckout.catalog', ['scan' => $asset->asset_tag, 'source_type' => 'order', 'order_number' => 'WRONG']))->assertOk()->assertJsonPath('status', 'error');
    }

    public function test_mixed_user_batch_and_identical_retry_only_allocate_once(): void
    {
        $asset = Asset::factory()->create();
        $accessory = Accessory::factory()->create(['qty' => 5]);
        $payload = $this->batchPayload([['kind' => 'asset', 'id' => $asset->id, 'quantity' => 1], ['kind' => 'accessory', 'id' => $accessory->id, 'quantity' => 3]]);
        $this->actingAs(User::factory()->superuser()->create())->postJson(route('hardware.bulkcheckout.session'), $payload)->assertOk()->assertJsonPath('status', 'success');
        $this->assertSame($payload['target_id'], $asset->fresh()->assigned_to);
        $this->assertSame(2, $accessory->fresh()->numRemaining());
        $this->postJson(route('hardware.bulkcheckout.session'), $payload)->assertOk();
        $this->assertDatabaseCount('bulk_checkout_batches', 1);
        $this->assertDatabaseCount('accessories_checkout', 3);
        $payload['note'] = 'Changed after success';
        $this->postJson(route('hardware.bulkcheckout.session'), $payload)->assertOk()->assertJsonPath('status', 'error');
        $this->get(route('hardware.bulkcheckout.receipt', $payload['idempotency_key']))->assertOk();
    }

    public function test_stock_shortage_rolls_back_the_entire_batch(): void
    {
        $asset = Asset::factory()->create();
        $accessory = Accessory::factory()->create(['qty' => 1]);
        $payload = $this->batchPayload([['kind' => 'asset', 'id' => $asset->id, 'quantity' => 1], ['kind' => 'accessory', 'id' => $accessory->id, 'quantity' => 2]]);
        $this->actingAs(User::factory()->superuser()->create())->postJson(route('hardware.bulkcheckout.session'), $payload)->assertOk()->assertJsonPath('status', 'error');
        $this->assertNull($asset->fresh()->assigned_to);
        $this->assertDatabaseCount('bulk_checkout_batches', 0);
        $this->assertDatabaseCount('accessories_checkout', 0);
    }

    public function test_duplicate_assets_are_rejected_without_checkout(): void
    {
        $asset = Asset::factory()->create();
        $row = ['kind' => 'asset', 'id' => $asset->id, 'quantity' => 1];
        $this->actingAs(User::factory()->superuser()->create())->postJson(route('hardware.bulkcheckout.session'), $this->batchPayload([$row, $row]))->assertOk()->assertJsonPath('status', 'error');
        $this->assertNull($asset->fresh()->assigned_to);
    }

    public function test_location_dispatch_preserves_physical_location(): void
    {
        $source = Location::factory()->create();
        $destination = Location::factory()->create();
        $asset = Asset::factory()->create(['location_id' => $source->id, 'rtd_location_id' => $source->id]);
        $accessory = Accessory::factory()->create(['qty' => 3, 'location_id' => $source->id]);
        $payload = $this->batchPayload([['kind' => 'asset', 'id' => $asset->id, 'quantity' => 1], ['kind' => 'accessory', 'id' => $accessory->id, 'quantity' => 2]], ['mode' => 'location', 'source_location_id' => $source->id, 'target_id' => $destination->id]);
        $this->actingAs(User::factory()->superuser()->create())->postJson(route('hardware.bulkcheckout.session'), $payload)->assertOk();
        $this->assertSame($source->id, $asset->fresh()->location_id);
        $this->assertSame($destination->id, $asset->fresh()->assigned_to);
        $this->assertSame(Location::class, $asset->fresh()->assigned_type);
        $this->assertSame('In Transit', $asset->fresh()->status->name);
        $this->assertSame(1, $accessory->fresh()->numRemaining());
    }

    public function test_project_reservation_does_not_issue_or_move_asset(): void
    {
        $project = Project::factory()->create();
        $asset = Asset::factory()->create(['order_number' => 'PROJECT-ORDER']);
        $originalLocation = $asset->location_id;
        $originalStatus = $asset->status_id;
        $payload = $this->batchPayload([['kind' => 'asset', 'id' => $asset->id, 'quantity' => 1]], ['mode' => 'project', 'target_id' => $project->id, 'source_type' => 'order', 'order_number' => 'PROJECT-ORDER']);
        $this->actingAs(User::factory()->superuser()->create())->postJson(route('hardware.bulkcheckout.session'), $payload)->assertOk();
        $this->assertNull($asset->fresh()->assigned_to);
        $this->assertSame($originalLocation, $asset->fresh()->location_id);
        $this->assertFalse((bool) $asset->fresh()->status->deployable);
        $this->assertDatabaseHas('project_asset', ['project_id' => $project->id, 'asset_id' => $asset->id, 'original_status_id' => $originalStatus]);
    }

    public function test_project_source_and_user_membership_are_required(): void
    {
        $project = Project::factory()->create();
        $asset = Asset::factory()->create();
        $payload = $this->batchPayload([['kind' => 'asset', 'id' => $asset->id, 'quantity' => 1]], ['mode' => 'project', 'target_id' => $project->id]);
        $this->actingAs(User::factory()->superuser()->create())->postJson(route('hardware.bulkcheckout.session'), $payload)->assertOk()->assertJsonPath('status', 'error');
        $payload['mode'] = 'user';
        $payload['target_id'] = User::factory()->create()->id;
        $payload['project_id'] = $project->id;
        $this->postJson(route('hardware.bulkcheckout.session'), $payload)->assertOk()->assertJsonPath('status', 'error');
        $this->assertNull($asset->fresh()->assigned_to);
    }

    public function test_inactive_recipient_and_unreviewed_session_are_rejected(): void
    {
        $asset = Asset::factory()->create();
        $payload = $this->batchPayload([['kind' => 'asset', 'id' => $asset->id, 'quantity' => 1]], ['target_id' => User::factory()->create(['activated' => 0])->id]);
        $this->actingAs(User::factory()->superuser()->create())->postJson(route('hardware.bulkcheckout.session'), $payload)->assertOk()->assertJsonPath('status', 'error');
        $payload['reviewed'] = false;
        $this->postJson(route('hardware.bulkcheckout.session'), $payload)->assertOk()->assertJsonPath('status', 'error');
        $this->assertNull($asset->fresh()->assigned_to);
    }

    public function test_project_accessory_reservation_respects_order_stock_and_resolves_target(): void
    {
        $accessory = Accessory::factory()->create(['qty' => 5]);
        $order = $accessory->orderItems()->firstOrFail()->order;
        $order->update(['order_number' => 'ACCESSORY-ORDER']);
        $project = Project::factory()->create();
        $payload = $this->batchPayload([['kind' => 'accessory', 'id' => $accessory->id, 'quantity' => 2]], ['mode' => 'project', 'target_id' => $project->id, 'source_type' => 'order', 'order_number' => 'ACCESSORY-ORDER']);
        $this->actingAs(User::factory()->superuser()->create())->postJson(route('hardware.bulkcheckout.session'), $payload)->assertOk()->assertJsonPath('status', 'success');
        $this->assertSame(3, $accessory->fresh()->numRemaining());
        $checkout = \App\Models\AccessoryCheckout::where('accessory_id', $accessory->id)->firstOrFail();
        $this->assertTrue($checkout->assignedTo->is($project));
        $this->getJson(route('hardware.bulkcheckout.context', ['mode' => 'project', 'target_id' => $project->id]))->assertOk()->assertJsonPath('payload.rows.0.quantity', 2);
        $this->getJson(route('hardware.bulkcheckout.catalog', ['kind' => 'accessory', 'source_type' => 'order', 'order_number' => 'ACCESSORY-ORDER']))->assertOk()->assertJsonPath('payload.rows.0.remaining', 3);
    }

    public function test_ended_recipient_and_same_location_dispatch_are_rejected(): void
    {
        $asset = Asset::factory()->create();
        $row = ['kind' => 'asset', 'id' => $asset->id, 'quantity' => 1];
        $recipient = User::factory()->create(['start_date' => today()->subYear()->toDateString(), 'end_date' => today()->subDay()->toDateString()]);
        $this->actingAs(User::factory()->superuser()->create())->postJson(route('hardware.bulkcheckout.session'), $this->batchPayload([$row], ['target_id' => $recipient->id]))->assertOk()->assertJsonPath('status', 'error');
        $location = Location::factory()->create();
        $this->postJson(route('hardware.bulkcheckout.session'), $this->batchPayload([$row], ['mode' => 'location', 'target_id' => $location->id, 'source_location_id' => $location->id]))->assertOk()->assertJsonPath('status', 'error');
        $this->assertNull($asset->fresh()->assigned_to);
    }

    public function test_session_record_is_private_to_issuer_and_superusers(): void
    {
        $asset = Asset::factory()->create();
        $payload = $this->batchPayload([['kind' => 'asset', 'id' => $asset->id, 'quantity' => 1]]);
        $issuer = User::factory()->checkoutAssets()->viewAssets()->viewUsers()->create();
        $this->actingAs($issuer)->postJson(route('hardware.bulkcheckout.session'), $payload)->assertOk()->assertJsonPath('status', 'success');
        $this->get(route('hardware.bulkcheckout.receipt', $payload['idempotency_key']))->assertOk();
        $this->actingAs(User::factory()->checkoutAssets()->viewAssets()->create())->get(route('hardware.bulkcheckout.receipt', $payload['idempotency_key']))->assertNotFound();
    }
}
