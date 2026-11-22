<?php

namespace App\Actions\BulkCheckout;

use App\Events\CheckoutableCheckedOut;
use App\Models\Accessory;
use App\Models\AccessoryCheckout;
use App\Models\Asset;
use App\Models\Location;
use App\Models\Project;
use App\Models\Setting;
use App\Models\Statuslabel;
use App\Models\User;
use App\Support\OperationalAssetStatuses;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CommitBulkCheckoutAction
{
    public static function run(User $actor, array $input): object
    {
        return DB::transaction(function () use ($actor, $input): object {
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $hash = hash('sha256', json_encode(collect($input)->except(['idempotency_key', 'reviewed'])->all()));
            $existing = DB::table('bulk_checkout_batches')->where('id', $input['idempotency_key'])->first();
            if ($existing) {
                if ((int) $existing->created_by !== (int) $actor->id || $existing->request_hash !== $hash) {
                    throw ValidationException::withMessages(['items' => trans('bulk_checkout.retry_changed')]);
                }

                return $existing;
            }
            $mode = $input['mode'];
            $class = ['user' => User::class, 'location' => Location::class, 'project' => Project::class][$mode];
            $target = $class::whereKey($input['target_id'])->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize($mode === 'project' ? 'update' : 'view', $target);
            if ($mode === 'user' && (! $target->activated || ($target->end_date && \Carbon\Carbon::parse($target->end_date)->isBefore(today())))) {
                throw ValidationException::withMessages(['target_id' => trans('bulk_checkout.recipient_inactive')]);
            }
            if ($mode === 'location' && (int) $input['source_location_id'] === (int) $target->id) {
                throw ValidationException::withMessages(['target_id' => trans('bulk_checkout.same_location')]);
            }
            $project = $mode === 'project' ? $target : (! empty($input['project_id']) ? Project::findOrFail($input['project_id']) : null);
            if ($project) {
                Gate::forUser($actor)->authorize($mode === 'project' ? 'update' : 'view', $project);
                if (! in_array($project->status, ['active', 'planned']) || $project->end_date->isBefore(today())) {
                    throw ValidationException::withMessages(['project_id' => trans('bulk_checkout.project_unavailable')]);
                }
                if ($mode === 'user' && (! $project->members()->where('users.id', $target->id)->exists() || $project->start_date->isAfter(today()))) {
                    throw ValidationException::withMessages(['project_id' => trans('bulk_checkout.not_member')]);
                }
            }
            if (Setting::getSettings()->require_checkinout_notes && empty(trim($input['note'] ?? ''))) {
                throw ValidationException::withMessages(['note' => trans('validation.required', ['attribute' => trans('general.notes')])]);
            }
            $selected = collect($input['items']);
            if ($selected->map(fn ($row) => $row['kind'].':'.$row['id'])->unique()->count() !== $selected->count()) {
                throw ValidationException::withMessages(['items' => trans('bulk_checkout.duplicate')]);
            }
            $assets = Asset::whereIn('id', $selected->where('kind', 'asset')->pluck('id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $accessories = Accessory::whereIn('id', $selected->where('kind', 'accessory')->pluck('id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $available = collect(BuildBulkCheckoutInventoryAction::run($actor, array_merge($input, [
                'asset_ids' => $assets->keys()->all(), 'accessory_ids' => $accessories->keys()->all(),
            ])))->keyBy(fn ($row) => $row['kind'].':'.$row['id']);
            foreach ($selected as $row) {
                $item = $row['kind'] === 'asset' ? $assets->get($row['id']) : $accessories->get($row['id']);
                $stock = $available->get($row['kind'].':'.$row['id']);
                if (! $item || ! $stock || $stock['remaining'] < $row['quantity'] || ($row['kind'] === 'asset' && (int) $row['quantity'] !== 1)) {
                    throw ValidationException::withMessages(['items' => trans('bulk_checkout.stock_changed')]);
                }
                Gate::forUser($actor)->authorize('checkout', $item);
                if (! $item->canCheckoutTo($target)) {
                    throw ValidationException::withMessages(['items' => trans('bulk_checkout.company_mismatch')]);
                }
            }
            $status = null;
            if ($mode === 'project') {
                $status = $project->reservation_status_id ? Statuslabel::find($project->reservation_status_id) : null;
                if (! $status) {
                    $status = new Statuslabel(['name' => 'Reserved for project '.$project->code, 'deployable' => 0, 'pending' => 0, 'archived' => 0, 'created_by' => $actor->id]);
                    if (! $status->save()) {
                        throw ValidationException::withMessages($status->getErrors()->toArray());
                    }
                    $project->reservation_status_id = $status->id;
                    $project->saveOrFail();
                }
            } elseif ($mode === 'location') {
                $status = Statuslabel::where('name', 'In Transit')->where('archived', 0)->first();
                if (! $status) {
                    $status = new Statuslabel(['name' => 'In Transit'] + OperationalAssetStatuses::DEFINITIONS['In Transit']);
                    if (! $status->save()) {
                        throw ValidationException::withMessages($status->getErrors()->toArray());
                    }
                }
            }
            $snapshots = [];
            foreach ($selected as $row) {
                $item = $row['kind'] === 'asset' ? $assets[$row['id']] : $accessories[$row['id']];
                $condition = $row['condition'] ?? $input['condition'] ?? null;
                $note = trim(($input['note'] ?? '').($mode === 'user' && $project ? "\n".'Project: '.$project->code : '').($condition ? "\n".trans('bulk_checkout.condition').': '.trans('bulk_checkout.conditions.'.$condition) : ''));
                if ($mode !== 'user') {
                    $note = trans('bulk_checkout.actions.'.$mode).': '.$target->name.($note ? "\n".$note : '');
                }
                $snapshot = $available[$row['kind'].':'.$row['id']];
                $snapshot['quantity'] = $row['quantity'];
                $snapshot['condition'] = $condition;
                $snapshot['original_status_id'] = $row['kind'] === 'asset' ? $item->status_id : null;
                $snapshot['checkout_ids'] = [];
                if ($row['kind'] === 'asset') {
                    if ($mode === 'project') {
                        Gate::forUser($actor)->authorize('update', $item);
                        $project->assets()->attach($item->id, ['original_status_id' => $item->status_id, 'reserved_by' => $actor->id]);
                        $item->status_id = $status->id;
                        if (! $item->save()) {
                            throw ValidationException::withMessages($item->getErrors()->toArray());
                        }
                        DB::table('project_events')->insert(['project_id' => $project->id, 'actor_id' => $actor->id, 'action' => 'reserved', 'description' => $item->asset_tag, 'created_at' => now(), 'updated_at' => now()]);
                    } elseif ($mode === 'location') {
                        $item->assignedTo()->associate($target);
                        $item->status_id = $status->id;
                        $item->last_checkout = now();
                        // Dispatch records a destination, not a completed receipt.
                        // Preserve the current physical location until it is received.
                        if (! $item->save()) {
                            throw ValidationException::withMessages($item->getErrors()->toArray());
                        }
                        $item->increment('checkout_counter');
                        $item->logCheckout($note, $target, now());
                    } else {
                        $statusId = $row['status_id'] ?? $input['status_id'] ?? null;
                        if ($statusId) {
                            $item->status_id = $statusId;
                        }
                        if (! $item->checkOut($target, $actor, $input['checkout_at'] ?? now(), $input['expected_checkin'] ?? null, $note, $item->name)) {
                            throw ValidationException::withMessages($item->getErrors()->toArray());
                        }
                    }
                    $item->unsetRelation('status');
                    $snapshot['status'] = $item->status?->name;
                } else {
                    for ($unit = 0; $unit < $row['quantity']; $unit++) {
                        $checkout = new AccessoryCheckout(['accessory_id' => $item->id, 'assigned_to' => $target->id, 'assigned_type' => $class, 'note' => $note]);
                        $checkout->created_by = $actor->id;
                        $checkout->saveOrFail();
                        $snapshot['checkout_ids'][] = $checkout->id;
                    }
                    if ($mode === 'user') {
                        $item->checkout_qty = $row['quantity'];
                        DB::afterCommit(fn () => event(new CheckoutableCheckedOut($item, $target, $actor, $note, [], $row['quantity'])));
                    } else {
                        $item->logCheckout($note, $target, now(), [], $row['quantity']);
                    }
                    $snapshot['status'] = trans('bulk_checkout.actions.'.$mode);
                }
                $snapshots[] = $snapshot;
            }
            DB::table('bulk_checkout_batches')->insert([
                'id' => $input['idempotency_key'], 'created_by' => $actor->id, 'request_hash' => $hash, 'mode' => $mode,
                'target_type' => $class, 'target_id' => $target->id, 'target_name' => $target->name,
                'project_id' => $project?->id, 'source_location_id' => $input['source_location_id'] ?? null,
                'source' => json_encode(collect($input)->only(['source_type', 'order_number', 'supplier_id'])->all()),
                'items' => json_encode($snapshots), 'note' => $input['note'] ?? null, 'created_at' => now(), 'updated_at' => now(),
            ]);

            return DB::table('bulk_checkout_batches')->where('id', $input['idempotency_key'])->first();
        }, 3);
    }
}
