<?php

namespace App\Models\HrisV2;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeCharacterReference extends HrisV2Model
{
    protected $table = 'employee_character_references';

    protected $fillable = [
        'employee_id',
        'name',
        'address',
        'telephone_no',
        'legacy_reference_id',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
