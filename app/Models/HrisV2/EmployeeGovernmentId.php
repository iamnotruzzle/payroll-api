<?php

namespace App\Models\HrisV2;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeGovernmentId extends HrisV2Model
{
    protected $fillable = [
        'employee_id',
        'tin_no',
        'gsis_no',
        'pagibig_no',
        'phic_no',
        'sss_no',
        'issued_id_type',
        'issued_id_no',
        'issued_id_date_place',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
