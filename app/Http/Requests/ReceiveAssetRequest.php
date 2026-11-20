<?php

namespace App\Http\Requests;

use App\Models\Asset;
use App\Models\Setting;
use Illuminate\Validation\Rule;

class ReceiveAssetRequest extends Request
{
    public const CONDITIONS = ['good', 'fair', 'damaged', 'faulty', 'missing_parts'];

    public function authorize(): bool
    {
        return $this->user()->can('update', Asset::class);
    }

    public function rules(): array
    {
        return [
            'receipt_token' => ['required', 'string', 'max:8192'],
            'asset_tag' => ['required', 'string', 'max:255', Rule::unique('assets', 'asset_tag')->ignore($this->route('assetId'))],
            'status_id' => ['required', 'integer', Rule::exists('status_labels', 'id')->whereNull('deleted_at')->where('archived', 0)],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->whereNull('deleted_at')],
            'condition' => ['required', 'string', Rule::in(self::CONDITIONS)],
            'note' => [Rule::requiredIf(fn () => (bool) Setting::getSettings()->require_checkinout_notes || in_array($this->input('condition'), ['damaged', 'faulty', 'missing_parts'], true)), 'nullable', 'string', 'max:5000'],
        ];
    }
}
