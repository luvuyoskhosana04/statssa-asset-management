<?php

namespace App\Http\Requests;

use App\Models\Asset;

class ReceiptHistoryRequest extends Request
{
    protected $rules = ['q' => 'nullable|string|max:200', 'page' => 'nullable|integer|min:1'];

    public function authorize(): bool
    {
        return $this->user()->can('update', Asset::class) || ($this->user()->can('receive', Asset::class) && $this->user()->can('view', Asset::class));
    }
}
