<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Employee extends Authenticatable
{
    use Notifiable;

    protected $table = 'tbl_employee';
    protected $primaryKey = 'emp_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'emp_id',
        'firstname',
        'middlename',
        'lastname',
        'extension',
        'prefix',
        'suffix',
        'gender',
        'position_id',
        'step',
        'birthdate',
        'birthplace',
        'address_id',
        'brgy',
        'house_no',
        'street',
        'subdiv',
        'address2_id',
        'brgy2',
        'house_no2',
        'street2',
        'subdiv2',
        'department_id',
        'email',
        'religion_id',
        'citizenship_id',
        'civil_stat',
        'height',
        'weight',
        'blood_type',
        'mobile_no',
        'tel_no',
        'tin_no',
        'phic_no',
        'gsis_no',
        'pagibig_no',
        'sss_no',
        'vacation_leave_credits',
        'sick_leave_credits',
        'date_gain_lc',
        'empstat_id',
        'date_hired',
        'is_degree3',
        'is_degree4',
        'is_adminoffense',
        'is_criminallycharged',
        'is_convictedtocourt',
        'is_separated',
        'is_candidate',
        'is_campaign',
        'is_immigrant',
        'is_indigenous',
        'is_pwd',
        'is_soloparent',
        'gov_id',
        'govid_no',
        'govid_dateplace',
        'fingerprint_1',
        'fingerprint_2',
        'is_active',
        'profile_pic',
        'is_section_head',
        'separationdate',
        'separationtype',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'fingerprint_1',
        'fingerprint_2',
    ];

    protected function casts(): array
    {
        return [
            'birthdate'              => 'date',
            'date_gain_lc'           => 'date',
            'date_hired'             => 'date',
            'separationdate'         => 'date',
            'height'                 => 'double',
            'weight'                 => 'double',
            'vacation_leave_credits' => 'double',
            'sick_leave_credits'     => 'double',
            'position_id'            => 'integer',
            'step'                   => 'integer',
            'address_id'             => 'integer',
            'address2_id'            => 'integer',
            'department_id'          => 'integer',
            'religion_id'            => 'integer',
            'citizenship_id'         => 'integer',
            'civil_stat'             => 'integer',
            'empstat_id'             => 'integer',
            'separationtype'         => 'integer',
            'password'               => 'hashed',
        ];
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id', 'position_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id', 'department_id');
    }

    public function userAccount(): HasOne
    {
        return $this->hasOne(UserAccount::class, 'emp_id', 'emp_id');
    }

    public function getFullNameAttribute(): string
    {
        $parts = array_filter([
            $this->prefix,
            $this->firstname,
            $this->middlename ? mb_substr($this->middlename, 0, 1) . '.' : null,
            $this->lastname,
            $this->extension,
            $this->suffix,
        ]);

        return implode(' ', $parts);
    }

    public function getIsActiveStatusAttribute(): bool
    {
        return $this->is_active === 'Y';
    }

    public function getIsSectionHeadStatusAttribute(): bool
    {
        return $this->is_section_head === 'Y';
    }
}
