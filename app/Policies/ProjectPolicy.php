<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class ProjectPolicy
{
    public function view(User $user, ?Project $project = null): bool
    {
        return Gate::forUser($user)->allows('view', Asset::class);
    }

    public function create(User $user): bool
    {
        return $this->view($user) && Gate::forUser($user)->allows('create', Asset::class);
    }

    public function update(User $user, ?Project $project = null): bool
    {
        return $this->view($user) && Gate::forUser($user)->allows('update', Asset::class);
    }

    public function issue(User $user, ?Project $project = null): bool
    {
        return $this->update($user) && Gate::forUser($user)->allows('checkout', Asset::class)
            && Gate::forUser($user)->allows('view', User::class);
    }
}
