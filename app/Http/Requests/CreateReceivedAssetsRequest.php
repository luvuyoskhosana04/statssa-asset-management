<?php

namespace App\Http\Requests;

use App\Models\Asset;
use Illuminate\Validation\Rule;

class CreateReceivedAssetsRequest extends Request
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Asset::class) && $this->user()->can('view', Asset::class);
    }

    public function rules(): array
    {
        return [
            'request_key' => ['required', 'uuid'],
            'model_id' => ['nullable', 'integer', Rule::exists('models', 'id')->whereNull('deleted_at')],
            'status_id' => ['required', 'integer', Rule::exists('status_labels', 'id')->whereNull('deleted_at')->where('archived', 0)],
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')->whereNull('deleted_at')],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->whereNull('deleted_at')],
            'order_number' => ['nullable', 'string', 'max:191'],
            'note' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'list', 'min:1', 'max:200'],
            'items.*.asset_tag' => ['required', 'string', 'max:255', 'distinct:ignore_case', 'not_regex:/[\x00-\x1F\x7F]/u'],
            'items.*.model_id' => ['nullable', 'integer', Rule::exists('models', 'id')->whereNull('deleted_at')],
        ];
    }
}
