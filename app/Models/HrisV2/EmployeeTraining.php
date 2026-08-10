<?php

namespace App\Models\HrisV2;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeTraining extends HrisV2Model
{
    protected $table = 'employee_trainings';

    protected $fillable = [
        'employee_id',
        'training_name',
        'training_venue',
        'sponsor',
        'start_date',
        'end_date',
        'hours',
        'type_id',
        'type_name',
        'url',
        'legacy_training_id',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'hours' => 'float',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
