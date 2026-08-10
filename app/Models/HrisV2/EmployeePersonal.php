<?php

namespace App\Models\HrisV2;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePersonal extends HrisV2Model
{
    protected $fillable = [
        'employee_id',
        'birthdate',
        'birthplace',
        'sex',
        'civil_status',
        'citizenship',
        'religion',
        'blood_type',
        'height',
        'weight',
        'residential_address',
        'permanent_address',
        'is_related_third_degree',
        'is_related_fourth_degree',
        'is_admin_offense',
        'is_criminally_charged',
        'is_convicted',
        'is_separated_service',
        'is_election_candidate',
        'is_resigned_for_campaign',
        'is_immigrant',
        'is_indigenous',
        'is_pwd',
        'is_solo_parent',
    ];

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'height' => 'float',
            'weight' => 'float',
            'is_related_third_degree' => 'boolean',
            'is_related_fourth_degree' => 'boolean',
            'is_admin_offense' => 'boolean',
            'is_criminally_charged' => 'boolean',
            'is_convicted' => 'boolean',
            'is_separated_service' => 'boolean',
            'is_election_candidate' => 'boolean',
            'is_resigned_for_campaign' => 'boolean',
            'is_immigrant' => 'boolean',
            'is_indigenous' => 'boolean',
            'is_pwd' => 'boolean',
            'is_solo_parent' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
