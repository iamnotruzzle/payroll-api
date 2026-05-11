<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Employee extends Authenticatable
{
    use Notifiable;

    protected $connection = 'mysql';
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
        'position_id',
        'department_id',
        'gender',
        'is_active',
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
        'profile_pic',
        'is_section_head',
        'separationdate',
        'separationtype',
        'role',
    ];

    protected $hidden = ['password', 'fingerprint_1', 'fingerprint_2'];

    protected $casts = [
        'birthdate' => 'datetime',
        'date_hired' => 'datetime',
        'date_gain_lc' => 'datetime',
        'separationdate' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'height' => 'float',
        'weight' => 'float',
        'vacation_leave_credits' => 'float',
        'sick_leave_credits' => 'float',
    ];

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
