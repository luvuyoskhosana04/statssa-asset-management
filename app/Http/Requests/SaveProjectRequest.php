<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Validation\Rule;

class SaveProjectRequest extends Request
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('project') ? 'update' : 'create', Project::class);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => ['required', 'string', 'max:100', Rule::unique('projects', 'code')->ignore($this->route('project'))],
            'status' => 'required|in:planned,active,suspended,ended',
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
            'location_id' => 'nullable|integer',
            'company_id' => 'nullable|integer',
            'notes' => 'nullable|string|max:10000',
        ];
    }
}
