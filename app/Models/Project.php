<?php

namespace App\Models;

use App\Models\Traits\CompanyableTrait;
use App\Presenters\Presentable;
use App\Presenters\ProjectPresenter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Watson\Validating\ValidatingTrait;

class Project extends SnipeModel
{
    use CompanyableTrait, HasFactory, Presentable, ValidatingTrait;

    protected $guarded = ['id'];

    protected $presenter = ProjectPresenter::class;

    protected $rules = [
        'name' => 'required|string|max:255',
        'code' => 'required|string|max:100',
        'status' => 'required|in:planned,active,suspended,ended',
        'start_date' => 'required|date',
        'end_date' => 'required|date|after_or_equal:start_date',
    ];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_user')->withTimestamps();
    }

    public function accessories(): BelongsToMany
    {
        return $this->belongsToMany(Accessory::class, 'project_accessory_allocations')
            ->withPivot('qty', 'from_location_id', 'reference')->withTimestamps();
    }

    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'project_asset')->withPivot('original_status_id', 'reserved_by')->withTimestamps();
    }
}
