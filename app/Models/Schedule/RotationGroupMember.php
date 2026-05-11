<?php

namespace App\Models\Schedule;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RotationGroupMember extends PayrollSchedulerModel
{
    protected $fillable = ['rotation_group_id', 'employee_id', 'rotation_order'];

    protected function casts(): array
    {
        return ['rotation_order' => 'integer'];
    }

    public function rotationGroup(): BelongsTo
    {
        return $this->belongsTo(RotationGroup::class);
    }
}
