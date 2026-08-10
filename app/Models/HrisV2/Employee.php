<?php

namespace App\Models\HrisV2;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends HrisV2Model
{
    protected $fillable = [
        'emp_id',
        'firstname',
        'middlename',
        'lastname',
        'extension',
        'prefix',
        'suffix',
        'is_active',
        'is_external',
        'date_hired',
        'date_separated',
        'separation_reason',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_external' => 'boolean',
            'date_hired' => 'date',
            'date_separated' => 'date',
        ];
    }

    public function personal(): HasOne
    {
        return $this->hasOne(EmployeePersonal::class);
    }

    public function governmentIds(): HasOne
    {
        return $this->hasOne(EmployeeGovernmentId::class);
    }

    public function contact(): HasOne
    {
        return $this->hasOne(EmployeeContact::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(EmploymentAssignment::class);
    }

    public function currentAssignment(): HasOne
    {
        return $this->hasOne(EmploymentAssignment::class)->where('is_current', true);
    }

    public function dependents(): HasMany
    {
        return $this->hasMany(EmployeeDependent::class);
    }

    public function educations(): HasMany
    {
        return $this->hasMany(EmployeeEducation::class);
    }

    public function eligibilities(): HasMany
    {
        return $this->hasMany(EmployeeEligibility::class);
    }

    public function workExperiences(): HasMany
    {
        return $this->hasMany(EmployeeWorkExperience::class);
    }

    public function trainings(): HasMany
    {
        return $this->hasMany(EmployeeTraining::class);
    }

    public function voluntaryWorks(): HasMany
    {
        return $this->hasMany(EmployeeVoluntaryWork::class);
    }

    public function otherInfos(): HasMany
    {
        return $this->hasMany(EmployeeOtherInfo::class);
    }

    public function characterReferences(): HasMany
    {
        return $this->hasMany(EmployeeCharacterReference::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function getFullNameAttribute(): string
    {
        return collect([
            $this->firstname,
            $this->middlename,
            $this->lastname,
            $this->extension,
        ])->filter()->implode(' ');
    }
}
