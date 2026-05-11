<?php

namespace App\Models\Schedule;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleTemplate extends PayrollSchedulerModel
{
    protected $fillable = ['name', 'department_id', 'rotation_group_id', 'is_active'];

    protected function casts(): array
    {
        return ['department_id' => 'integer', 'is_active' => 'boolean'];
    }

    public function rotationGroup(): BelongsTo
    {
        return $this->belongsTo(RotationGroup::class);
    }

    public function days(): HasMany
    {
        return $this->hasMany(ScheduleTemplateDay::class)->orderBy('day_sequence');
    }
}
