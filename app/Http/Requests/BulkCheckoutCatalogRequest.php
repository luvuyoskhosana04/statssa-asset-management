<?php

namespace App\Http\Requests;

use App\Models\Accessory;
use App\Models\Asset;

class BulkCheckoutCatalogRequest extends Request
{
    public function authorize(): bool
    {
        return $this->user()->can('checkout', Asset::class) || $this->user()->can('checkout', Accessory::class);
    }

    protected $rules = [
        'source_location_id' => 'nullable|integer|exists_undeleted:locations,id',
        'source_type' => 'nullable|in:any,order,supplier',
        'order_number' => 'nullable|string|max:191',
        'supplier_id' => 'nullable|integer|exists:suppliers,id',
        'q' => 'nullable|string|max:191',
        'scan' => 'nullable|string|max:191',
        'mode' => 'nullable|in:user,location,project',
        'target_id' => 'nullable|integer|min:1',
        'kind' => 'nullable|in:asset,accessory',
    ];
}
