<?php

namespace App\Http\Requests;

use App\Models\Asset;

class SaveReceivingDraftRequest extends Request
{
    protected $rules = [
        'request_key' => 'required|uuid',
        'company_id' => 'nullable|integer',
        'version' => 'nullable|integer|in:1,2',
        'items.*.id' => 'nullable|uuid',
        'items.*.serial' => 'required_if:version,2|nullable|string|max:255|not_regex:/[\x00-\x1F\x7F]/u',
        'items.*.stage' => 'required_if:version,2|nullable|in:pending,received',
        'items' => 'required|array|list|min:1|max:200',
        'items.*.asset_tag' => 'present|nullable|string|max:255',
        'items.*.model_id' => 'nullable|integer',
    ];

    public function authorize(): bool
    {
        return $this->user()->can('receive', Asset::class) && $this->user()->can('view', Asset::class);
    }
}
