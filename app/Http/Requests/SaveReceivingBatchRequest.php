<?php

namespace App\Http\Requests;

use App\Models\Asset;
use App\Models\Setting;
use Illuminate\Validation\Rule;

class SaveReceivingBatchRequest extends Request
{
    public function authorize(): bool
    {
        return $this->user()->can('update', Asset::class);
    }

    public function rules(): array
    {
        $rules = [
            'request_key' => ['required', 'uuid'],
            'order_number' => ['nullable', 'string', 'max:191'],
            'status_id' => ['required', 'integer', Rule::exists('status_labels', 'id')->whereNull('deleted_at')->where('archived', 0)],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->whereNull('deleted_at')],
            'items' => ['required', 'array', 'list', 'min:1', 'max:200'],
            'items.*.asset_id' => ['required', 'integer', 'distinct', Rule::exists('assets', 'id')->whereNull('deleted_at')],
            'items.*.receipt_token' => ['required', 'string', 'max:8192'],
            'items.*.asset_tag' => ['required', 'string', 'max:255', 'distinct:ignore_case'],
            'items.*.condition' => ['required', 'string', Rule::in(ReceiveAssetRequest::CONDITIONS)],
            'items.*.note' => ['nullable', 'string', 'max:5000'],
        ];
        $items = $this->input('items');
        if (is_array($items)) {
            foreach (array_slice($items, 0, 200, true) as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }
                $rules['items.'.$index.'.asset_tag'] = ['required', 'string', 'max:255', 'distinct:ignore_case', Rule::unique('assets', 'asset_tag')->ignore((int) ($item['asset_id'] ?? 0))];
                $rules['items.'.$index.'.note'] = [
                    Rule::requiredIf((bool) Setting::getSettings()->require_checkinout_notes || in_array($item['condition'] ?? null, ['damaged', 'faulty', 'missing_parts'], true)),
                    'nullable', 'string', 'max:5000',
                ];
            }
        }

        return $rules;
    }

    public function attributes(): array
    {
        return ['items.*.asset_tag' => trans('presentation.receive_tag_label'), 'items.*.note' => trans('general.notes'), 'items.*.receipt_token' => trans('presentation.receive_serial_label')];
    }
}
