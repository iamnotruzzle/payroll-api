<?php

namespace App\Models\HrisV2;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeOtherInfo extends HrisV2Model
{
    protected $table = 'employee_other_infos';

    protected $fillable = [
        'employee_id',
        'title',
        'type',
        'legacy_otherinfo_id',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
