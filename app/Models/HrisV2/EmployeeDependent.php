<?php

namespace App\Models\HrisV2;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDependent extends HrisV2Model
{
    protected $table = 'employee_dependents';

    protected $fillable = [
        'employee_id',
        'firstname',
        'middlename',
        'lastname',
        'extension',
        'relationship',
        'birthdate',
        'sex',
        'occupation',
        'employer_name',
        'employer_address',
        'telephone_no',
        'legacy_dependent_id',
    ];

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function getFullNameAttribute(): string
    {
        return collect([$this->firstname, $this->middlename, $this->lastname, $this->extension])
            ->filter()
            ->implode(' ');
    }
}
