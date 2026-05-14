<?php

namespace App\Models\Schedule;

use Illuminate\Database\Eloquent\Relations\HasMany;

class RotationGroup extends PayrollSchedulerModel
{
    protected $connection = 'payroll_scheduler';

    protected $fillable = ['department_id', 'name', 'description', 'is_active'];

    protected function casts(): array
    {
        return [
            'department_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function members(): HasMany
    {
        return $this->hasMany(RotationGroupMember::class);
    }
}
