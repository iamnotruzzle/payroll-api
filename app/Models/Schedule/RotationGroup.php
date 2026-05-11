<?php

namespace App\Models\Schedule;

use Illuminate\Database\Eloquent\Relations\HasMany;

class RotationGroup extends PayrollSchedulerModel
{
    protected $fillable = ['name', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function members(): HasMany
    {
        return $this->hasMany(RotationGroupMember::class);
    }
}
