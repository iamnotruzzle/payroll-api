<?php

namespace App\Models\HrisV2;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeContact extends HrisV2Model
{
    protected $fillable = [
        'employee_id',
        'email',
        'mobile_no',
        'telephone_no',
        'emergency_contact_name',
        'emergency_contact_no',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
