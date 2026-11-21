<?php

namespace App\Http\Requests;

use App\Models\Project;

class SearchProjectIssueAssetsRequest extends Request
{
    public function authorize(): bool
    {
        return $this->user()->can('issue', Project::class);
    }

    protected $rules = ['q' => 'nullable|string|max:255|not_regex:/[\x00-\x1F\x7F]/u'];
}
