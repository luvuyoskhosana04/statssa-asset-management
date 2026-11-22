<?php

namespace App\Http\Requests;

use App\Models\Project;

class ProjectAssetRequest extends Request
{
    public function authorize(): bool
    {
        return ! $this->routeIs('projects.members.*') && $this->user()->can('update', Project::class);
    }

    public function rules(): array
    {
        return [
            'identifiers' => ($this->routeIs('projects.reserve') ? 'required' : 'sometimes|required').'|string|max:10000',
            'user_id' => ($this->routeIs('projects.members.store') ? 'required' : 'sometimes|required').'|integer',
            'worker' => ($this->routeIs('projects.issue.store') ? 'required' : 'sometimes|required').'|string|max:255',
            'asset' => ($this->routeIs('projects.issue.store') ? 'required' : 'sometimes|required').'|string|max:255',
            'project_id' => ($this->routeIs('projects.issue.start') ? 'required' : 'sometimes|required').'|integer',
            'expected_checkin' => ($this->routeIs('projects.issue.store') ? 'required' : 'sometimes|required').'|date_format:Y-m-d|after_or_equal:today',
            'reviewed' => ($this->routeIs('projects.issue.store') ? 'accepted' : 'sometimes|accepted'),
            'note' => 'nullable|string|max:5000',
        ];
    }
}
