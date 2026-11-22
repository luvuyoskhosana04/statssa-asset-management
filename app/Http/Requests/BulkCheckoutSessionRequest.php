<?php

namespace App\Http\Requests;

use App\Models\Accessory;
use App\Models\Asset;
use Illuminate\Validation\Rule;

class BulkCheckoutSessionRequest extends Request
{
    public function authorize(): bool
    {
        return $this->user()->can('checkout', Asset::class) || $this->user()->can('checkout', Accessory::class);
    }

    public function rules(): array
    {
        return [
            'mode' => 'required|in:user,location,project',
            'target_id' => 'required|integer|min:1',
            'project_id' => 'nullable|integer|min:1',
            'source_location_id' => 'nullable|required_if:mode,location|integer|exists_undeleted:locations,id',
            'source_type' => ['required', Rule::in($this->input('mode') === 'project' ? ['order', 'supplier'] : ['any', 'order', 'supplier'])],
            'order_number' => 'nullable|required_if:source_type,order|string|max:191',
            'supplier_id' => 'nullable|required_if:source_type,supplier|integer|exists:suppliers,id',
            'items' => 'required|array|min:1|max:200',
            'items.*.kind' => 'required|in:asset,accessory',
            'items.*.id' => 'required|integer|min:1',
            'items.*.quantity' => 'required|integer|min:1|max:500',
            'items.*.status_id' => ['nullable', 'integer', Rule::exists('status_labels', 'id')->where('deployable', 1)->where('archived', 0)],
            'items.*.condition' => 'nullable|in:good,fair,damaged,faulty,missing_parts',
            'status_id' => ['nullable', 'integer', Rule::exists('status_labels', 'id')->where('deployable', 1)->where('archived', 0)],
            'condition' => 'nullable|in:good,fair,damaged,faulty,missing_parts',
            'checkout_at' => 'nullable|date',
            'expected_checkin' => 'nullable|date|after_or_equal:checkout_at',
            'note' => 'nullable|string|max:5000',
            'idempotency_key' => 'required|uuid',
            'reviewed' => 'accepted',
        ];
    }
}
