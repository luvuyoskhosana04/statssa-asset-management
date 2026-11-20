<?php

namespace App\Http\Requests;

use App\Models\Asset;

class LookupReceivingAssetRequest extends Request
{
    protected $rules = [
        'for_batch' => 'nullable|boolean',
        'serial' => 'required|string|max:255',
        'order_number' => 'nullable|string|max:191',
    ];

    public function authorize(): bool
    {
        return $this->user()->can('update', Asset::class);
    }
}
