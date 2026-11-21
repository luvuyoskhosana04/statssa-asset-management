<?php

namespace App\Http\Requests;

use App\Models\Asset;

class CheckIntakeBarcodeRequest extends Request
{
    protected $rules = [
        'barcode' => ['required', 'string', 'max:255', 'not_regex:/[\x00-\x1F\x7F]/u'],
    ];

    public function authorize(): bool
    {
        return $this->user()->can('create', Asset::class) && $this->user()->can('view', Asset::class);
    }
}
