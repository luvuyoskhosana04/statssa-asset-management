<?php

namespace App\Actions\Assets;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Company;
use App\Models\Location;
use App\Models\Setting;
use App\Models\Statuslabel;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateReceivedAssetsAction
{
    public static function run(User $actor, array $data): array
    {
        Gate::forUser($actor)->authorize('receive', Asset::class);
        $items = array_map(fn ($item) => ['asset_tag' => trim($item['asset_tag']), 'model_id' => (int) ($item['model_id'] ?? $data['model_id'] ?? 0)] + (isset($item['serial']) ? ['serial' => trim($item['serial'])] : []), $data['items']);
        $sorted = $items;
        usort($sorted, fn ($a, $b) => strcmp($a['asset_tag'], $b['asset_tag']));
        $settings = ['company_id' => (int) ($data['company_id'] ?? 0), 'location_id' => (int) ($data['location_id'] ?? 0),
            'status_id' => (int) $data['status_id'], 'order_number' => trim((string) ($data['order_number'] ?? '')), 'note' => trim((string) ($data['note'] ?? ''))];
        $hash = hash('sha256', json_encode(['mode' => 'create-from-barcode', 'settings' => $settings, 'items' => $sorted], JSON_THROW_ON_ERROR));
        $key = strtolower($data['request_key']);
        $twoScan = ($data['intake_mode'] ?? null) === 'two_scan';
        try {
            return DB::transaction(function () use ($actor, $items, $settings, $hash, $key, $twoScan): array {
                if ($batch = DB::table('asset_receipt_batches')->where('request_key', $key)->lockForUpdate()->first()) {
                    return self::replay($actor, $batch, $hash);
                }
                $company = $settings['company_id'] ? Company::findOrFail($settings['company_id']) : null;
                if (Setting::getSettings()->full_multiple_companies_support && ! $actor->hasAccess('superuser') && ! $company) {
                    throw ValidationException::withMessages(['company_id' => trans('intake.company_required')]);
                }
                $location = $settings['location_id'] ? Location::findOrFail($settings['location_id']) : null;
                $status = Statuslabel::whereKey($settings['status_id'])->where('archived', 0)->firstOrFail();
                if ($twoScan && in_array(0, array_column($items, 'model_id'), true)) {
                    $unclassified = self::unclassifiedModel($actor);
                    foreach ($items as $index => $item) {
                        if (! $item['model_id']) {
                            $items[$index]['model_id'] = $unclassified->id;
                        }
                    }
                }
                $models = AssetModel::with('category')->whereIn('id', array_column($items, 'model_id'))->get()->keyBy('id');
                $codes = [];
                foreach ($items as $index => $item) {
                    foreach (['asset_tag', 'serial'] as $field) {
                        $code = $item[$field] ?? '';
                        if ($code === '') {
                            continue;
                        }
                        $normal = mb_strtolower($code);
                        if (isset($codes[$normal])) {
                            throw ValidationException::withMessages(['items.'.$index.'.'.$field => trans('intake.duplicate', ['barcode' => $code])]);
                        }
                        $codes[$normal] = true;
                        if (DB::table('assets')->where(fn ($query) => $query->where('asset_tag', $code)->orWhere('serial', $code))->lockForUpdate()->exists()
                            || DB::table('asset_receipts')->where('asset_tag', $code)->orWhere('serial', $code)->exists()) {
                            throw ValidationException::withMessages(['items.'.$index.'.'.$field => trans('intake.exists', ['barcode' => $code])]);
                        }
                    }
                    if (! $item['asset_tag']) {
                        throw ValidationException::withMessages(['items.'.$index.'.asset_tag' => trans('intake.invalid_barcode')]);
                    }
                    if (! isset($models[$item['model_id']]) || $models[$item['model_id']]->category?->category_type !== 'asset') {
                        throw ValidationException::withMessages(['items.'.$index.'.model_id' => trans('intake.model_required')]);
                    }

                }
                $time = now();
                $batchId = DB::table('asset_receipt_batches')->insertGetId([
                    'request_key' => $key, 'request_hash' => $hash, 'received_by' => $actor->id,
                    'receiver_name' => $actor->display_name ?: trim($actor->first_name.' '.$actor->last_name),
                    'order_number' => $settings['order_number'] ?: null, 'status_id' => $status->id, 'status_name' => $status->name,
                    'location_id' => $location?->id, 'location_name' => $location?->name, 'created_at' => $time, 'updated_at' => $time,
                ]);
                foreach ($items as $index => $item) {
                    $asset = new Asset;
                    $asset->asset_tag = $item['asset_tag'];
                    $asset->serial = $item['serial'] ?? null;
                    $asset->model_id = $item['model_id'];
                    $asset->status_id = $status->id;
                    $asset->company_id = $company?->id;
                    $asset->location_id = $location?->id;
                    $asset->rtd_location_id = $location?->id;
                    $asset->order_number = $settings['order_number'] ?: null;
                    $asset->notes = $settings['note'] ?: null;
                    $asset->created_by = $actor->id;
                    $asset->model()->associate($models[$item['model_id']]);
                    $asset->setRules(array_merge($asset->getRules(), $asset->customFieldValidationRules()));
                    if (! $asset->save()) {
                        $messages = [];
                        foreach ($asset->getErrors()->toArray() as $field => $errors) {
                            $messages['items.'.$index.'.'.$field] = $errors;
                        }
                        throw ValidationException::withMessages($messages);
                    }
                    DB::table('asset_receipts')->insert([
                        'batch_id' => $batchId, 'asset_id' => $asset->id, 'received_by' => $actor->id,
                        'serial' => $item['serial'] ?? '', 'previous_tag' => '', 'asset_tag' => $asset->asset_tag, 'condition' => 'not_recorded',
                        'note' => $settings['note'] ?: null, 'model_name' => $models[$item['model_id']]->name,
                        'status_id' => $status->id, 'status_name' => $status->name, 'location_id' => $location?->id,
                        'location_name' => $location?->name, 'order_number' => $settings['order_number'] ?: null,
                        'created_at' => $time, 'updated_at' => $time,
                    ]);
                    $asset->logCreate(trans('intake.audit_note', ['batch' => 'RCV-'.str_pad((string) $batchId, 6, '0', STR_PAD_LEFT)]));
                }

                return ['batch_id' => $batchId, 'item_count' => count($items)];
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            if ($batch = DB::table('asset_receipt_batches')->where('request_key', $key)->first()) {
                return self::replay($actor, $batch, $hash);
            }
            throw ValidationException::withMessages(['items' => trans('intake.conflict')]);
        }
    }

    private static function unclassifiedModel(User $actor): AssetModel
    {
        // The inventory requires a model relation. Keep unknown equipment explicitly
        // unclassified instead of inventing a make or model from a barcode.
        Setting::query()->orderBy('id')->lockForUpdate()->firstOrFail();
        $category = Category::firstOrNew(['name' => 'Unclassified received assets', 'category_type' => 'asset']);
        if (! $category->exists) {
            $category->created_by = $actor->id;
            $category->require_acceptance = false;
            $category->use_default_eula = false;
            if (! $category->save()) {
                throw ValidationException::withMessages(['items' => trans('intake.classification_failed')]);
            }
        }
        $model = AssetModel::firstOrNew(['name' => 'Model not specified', 'model_number' => 'STATSSA-UNCLASSIFIED-RECEIPT', 'category_id' => $category->id]);
        if (! $model->exists) {
            $model->created_by = $actor->id;
            if (! $model->save()) {
                throw ValidationException::withMessages(['items' => trans('intake.classification_failed')]);
            }
        }

        return $model;
    }

    private static function replay(User $actor, object $batch, string $hash): array
    {
        abort_unless((int) $batch->received_by === $actor->id, 403);
        if ($batch->request_hash !== $hash) {
            throw ValidationException::withMessages(['request_key' => trans('presentation.receive_batch_already_saved')]);
        }
        $ids = DB::table('asset_receipts')->where('batch_id', $batch->id)->pluck('asset_id');
        abort_unless(Asset::withTrashed()->whereIn('id', $ids)->count() === $ids->count(), 403);

        return ['batch_id' => $batch->id, 'item_count' => $ids->count()];
    }
}
