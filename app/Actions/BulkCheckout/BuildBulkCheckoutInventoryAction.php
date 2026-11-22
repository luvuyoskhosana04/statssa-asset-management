<?php

namespace App\Actions\BulkCheckout;

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class BuildBulkCheckoutInventoryAction
{
    public static function run(User $actor, array $input): array
    {
        $result = [];
        foreach (['asset' => Asset::class, 'accessory' => Accessory::class] as $kind => $class) {
            if (($input['kind'] ?? $kind) !== $kind || ! Gate::forUser($actor)->allows('checkout', $class)) {
                continue;
            }
            $query = $class::query();
            if (isset($input[$kind.'_ids'])) {
                $query->whereIn('id', $input[$kind.'_ids']);
            }
            if (! empty($input['scan'])) {
                if ($kind !== 'asset') {
                    continue;
                }
                $query->where(fn ($q) => $q->where('asset_tag', $input['scan'])->orWhere('serial', $input['scan']));
            } elseif (! empty($input['q'])) {
                $query->where(function ($q) use ($kind, $input): void {
                    $q->where('name', 'like', '%'.$input['q'].'%');
                    if ($kind === 'asset') {
                        $q->orWhere('asset_tag', 'like', '%'.$input['q'].'%')->orWhere('serial', 'like', '%'.$input['q'].'%');
                    }
                });
            }
            if (! empty($input['source_location_id'])) {
                $office = (int) $input['source_location_id'];
                $query->where(function ($q) use ($kind, $office): void {
                    $q->where('location_id', $office);
                    if ($kind === 'asset') {
                        $q->orWhere(fn ($fallback) => $fallback->whereNull('location_id')->where('rtd_location_id', $office));
                    }
                });
            }
            $source = $input['source_type'] ?? 'any';
            $orderFilter = function ($q) use ($source, $input): void {
                if ($source === 'order') {
                    $q->where('order_number', $input['order_number'] ?? '');
                } else {
                    $q->where('supplier_id', $input['supplier_id'] ?? 0);
                }
            };
            if ($source !== 'any') {
                $query->where(function ($q) use ($kind, $source, $input, $orderFilter): void {
                    $q->whereHas('orders', $orderFilter);
                    if ($kind === 'asset') {
                        $q->orWhere($source === 'order' ? 'order_number' : 'supplier_id', $source === 'order' ? ($input['order_number'] ?? '') : ($input['supplier_id'] ?? 0));
                    }
                });
            }
            if ($kind === 'asset' && empty($input['scan'])) {
                $query->where(fn ($q) => $q->whereNull('assigned_to')->orWhere('assigned_to', 0))->whereHas('status', fn ($q) => $q->where('deployable', 1)->where('archived', 0));
            }
            foreach ($query->orderBy('id')->limit(isset($input[$kind.'_ids']) ? 200 : 60)->get() as $item) {
                if (! Gate::forUser($actor)->allows('checkout', $item)) {
                    continue;
                }
                $remaining = $kind === 'asset' ? (int) $item->availableForCheckout() : max(0, $item->numRemaining());
                if ($kind === 'accessory' && $source !== 'any') {
                    $ordered = $item->orderItems()->whereHas('order', $orderFilter)->sum('qty');
                    $losses = max(0, $item->orderItems()->sum('qty') - $item->qty);
                    // Historical checkouts have no acquisition-lot identifier. Subtract
                    // them conservatively rather than claiming unavailable source units.
                    $remaining = min($remaining, max(0, $ordered - $item->numCheckedOut() - $losses));
                }
                if ($kind === 'asset' && ! $remaining && empty($input['scan'])) {
                    continue;
                }
                $result[] = [
                    'kind' => $kind, 'id' => $item->id, 'name' => $item->name ?: ($item->model?->name ?? $item->asset_tag),
                    'tag' => $kind === 'asset' ? $item->asset_tag : null,
                    'serial' => $kind === 'asset' ? $item->serial : null,
                    'category' => $kind === 'accessory' ? $item->category?->name : $item->model?->name,
                    'location' => $item->location?->name, 'location_id' => $item->location_id ?? ($kind === 'asset' ? $item->rtd_location_id : null),
                    'remaining' => (int) $remaining, 'total' => $kind === 'asset' ? 1 : (int) $item->qty,
                    'checked_out' => $kind === 'asset' ? (int) (bool) $item->assigned_to : $item->numCheckedOut(),
                ];
            }
        }

        return $result;
    }
}
