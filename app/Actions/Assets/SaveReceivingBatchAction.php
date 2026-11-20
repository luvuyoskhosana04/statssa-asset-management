<?php

namespace App\Actions\Assets;

use App\Models\Actionlog;
use App\Models\Asset;
use App\Models\Location;
use App\Models\Statuslabel;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveReceivingBatchAction
{
    public static function run(User $actor, array $data): array
    {
        $items = array_map(fn ($item) => [
            'asset_id' => (int) $item['asset_id'], 'receipt_token' => $item['receipt_token'],
            'asset_tag' => trim($item['asset_tag']), 'condition' => $item['condition'], 'note' => trim((string) ($item['note'] ?? '')),
        ], $data['items']);
        $hashItems = array_map(function ($item) {
            unset($item['receipt_token']);

            return $item;
        }, $items);
        usort($hashItems, fn ($a, $b) => $a['asset_id'] <=> $b['asset_id']);
        $hash = hash('sha256', json_encode([
            'status_id' => (int) $data['status_id'], 'location_id' => ! empty($data['location_id']) ? (int) $data['location_id'] : null,
            'order_number' => trim((string) ($data['order_number'] ?? '')), 'items' => $hashItems,
        ], JSON_THROW_ON_ERROR));
        $key = strtolower($data['request_key']);
        try {
            return DB::transaction(function () use ($actor, $data, $items, $hash, $key): array {
                $existing = DB::table('asset_receipt_batches')->where('request_key', $key)->lockForUpdate()->first();
                if ($existing) {
                    return self::replay($actor, $existing, $hash);
                }
                $ids = array_column($items, 'asset_id');
                $assets = Asset::with('model')->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                abort_unless($assets->count() === count($items), 404);
                $received = DB::table('asset_receipts')->whereIn('asset_id', $ids)->pluck('asset_id')->all();
                $pending = Statuslabel::idsFor('pending')->all();
                $status = Statuslabel::findOrFail($data['status_id']);
                $location = ! empty($data['location_id']) ? Location::findOrFail($data['location_id']) : null;
                if ($location) {
                    Gate::authorize('view', $location);
                }
                foreach ($items as $index => $item) {
                    $asset = $assets[$item['asset_id']];
                    Gate::authorize('update', $asset);
                    try {
                        $token = json_decode(Crypt::decryptString($item['receipt_token']), true, 512, JSON_THROW_ON_ERROR);
                    } catch (\Throwable $exception) {
                        throw ValidationException::withMessages(['items.'.$index.'.receipt_token' => trans('presentation.receive_expired')]);
                    }
                    if (($token['id'] ?? null) !== $asset->id || ($token['user'] ?? null) !== $actor->id || ($token['expires'] ?? 0) < now()->timestamp) {
                        throw ValidationException::withMessages(['items.'.$index.'.receipt_token' => trans('presentation.receive_expired')]);
                    }
                    if ($asset->assigned_to !== null || ! in_array($asset->status_id, $pending) || in_array($asset->id, $received) || ($token['version'] ?? '') !== hash('sha256', json_encode($asset->getRawOriginal()))) {
                        throw ValidationException::withMessages(['items.'.$index.'.receipt_token' => trans('presentation.receive_batch_changed', ['serial' => $asset->serial])]);
                    }
                    if (! empty($data['order_number']) && $asset->order_number !== $data['order_number']) {
                        throw ValidationException::withMessages(['items.'.$index.'.asset_id' => trans('presentation.receive_not_waiting')]);
                    }
                }
                $savedAt = now();
                $batchId = DB::table('asset_receipt_batches')->insertGetId([
                    'request_key' => $key, 'request_hash' => $hash, 'received_by' => $actor->id,
                    'receiver_name' => $actor->display_name ?: trim($actor->first_name.' '.$actor->last_name),
                    'order_number' => $data['order_number'] ?? null,
                    'status_id' => $status->id, 'status_name' => $status->name,
                    'location_id' => $location?->id, 'location_name' => $location?->name,
                    'created_at' => $savedAt, 'updated_at' => $savedAt,
                ]);
                foreach ($items as $index => $item) {
                    $asset = $assets[$item['asset_id']];
                    $previousTag = $asset->asset_tag;
                    $asset->asset_tag = $item['asset_tag'];
                    $asset->status_id = $status->id;
                    if ($location) {
                        $asset->location_id = $location->id;
                        $asset->rtd_location_id = $location->id;
                    }
                    if (! $asset->save()) {
                        throw ValidationException::withMessages($asset->getErrors()->toArray());
                    }
                    DB::table('asset_receipts')->insert([
                        'batch_id' => $batchId, 'asset_id' => $asset->id, 'received_by' => $actor->id,
                        'serial' => $asset->serial, 'previous_tag' => $previousTag, 'asset_tag' => $asset->asset_tag,
                        'condition' => $item['condition'], 'note' => $item['note'] ?: null,
                        'model_name' => $asset->model?->name, 'asset_name' => $asset->name,
                        'status_id' => $status->id, 'status_name' => $status->name,
                        'location_id' => $asset->location_id, 'location_name' => $location?->name ?? $asset->location?->name,
                        'order_number' => $asset->order_number,
                        'created_at' => $savedAt, 'updated_at' => $savedAt,
                    ]);
                    $log = new Actionlog;
                    $log->item_type = Asset::class;
                    $log->item_id = $asset->id;
                    $log->company_id = $asset->company_id;
                    $log->created_by = $actor->id;
                    $log->action_date = $savedAt;
                    $log->note = trans('presentation.receive_history', ['condition' => trans('presentation.receive_condition_'.$item['condition'])])."\n".$item['note'];
                    if (! $log->logaction('note added')) {
                        throw new \RuntimeException('Could not save receipt history.');
                    }
                }

                return self::result($batchId);
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = DB::table('asset_receipt_batches')->where('request_key', $key)->first();
            if ($existing) {
                return self::replay($actor, $existing, $hash);
            }
            throw ValidationException::withMessages(['items' => trans('presentation.receive_conflict')]);
        }
    }

    private static function replay(User $actor, object $batch, string $hash): array
    {
        abort_unless((int) $batch->received_by === $actor->id, 403);
        if ($batch->request_hash !== $hash) {
            throw ValidationException::withMessages(['request_key' => trans('presentation.receive_batch_already_saved')]);
        }
        $ids = DB::table('asset_receipts')->where('batch_id', $batch->id)->pluck('asset_id');
        $assets = Asset::withTrashed()->whereIn('id', $ids)->get();
        abort_unless($assets->count() === $ids->count(), 403);
        foreach ($assets as $asset) {
            Gate::authorize('update', $asset);
        }

        return self::result((int) $batch->id);
    }

    private static function result(int $batchId): array
    {
        $batch = DB::table('asset_receipt_batches')->where('id', $batchId)->first();
        $items = DB::table('asset_receipts')->where('batch_id', $batchId)->orderBy('id')->get(['asset_id as id', 'previous_tag', 'asset_tag'])->map(fn ($row) => (array) $row)->all();

        return ['batch_id' => $batchId, 'item_count' => count($items), 'saved_at' => $batch->created_at, 'items' => $items];
    }
}
