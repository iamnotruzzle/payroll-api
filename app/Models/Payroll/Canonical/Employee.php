<?php

namespace App\Models\Payroll\Canonical;

use App\Models\Hris\Employee as HrisEmployee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Employee extends HrisEmployee
{
    protected $connection = 'payroll';

    protected $table = 'payroll_canonical_employees';

    protected $primaryKey = 'emp_id';

    protected $fillable = ['source_batch_id', 'emp_id', 'firstname', 'middlename', 'lastname', 'extension', 'suffix', 'position_external_id', 'department_external_id', 'step', 'empstat_id', 'date_hired', 'separation_date', 'tin_no', 'gsis_no', 'phic_no', 'pagibig_no', 'vacation_leave_credits', 'sick_leave_credits', 'is_external', 'is_active', 'responsibility_center', 'lbp_account_no', 'fund_type'];

    protected $casts = ['date_hired' => 'date', 'separation_date' => 'date', 'vacation_leave_credits' => 'float', 'sick_leave_credits' => 'float', 'is_external' => 'boolean', 'is_active' => 'boolean'];

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_external_id', 'external_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_external_id', 'external_id');
    }

    public function getPositionIdAttribute()
    {
        return $this->position_external_id;
    }

    public function getDepartmentIdAttribute()
    {
        return $this->department_external_id;
    }

    public function getIsActiveStatusAttribute(): bool
    {
        return (bool) $this->is_active;
    }

    public function scopeEmployeeType(Builder $query, string|array|null $type = self::EMPLOYEE_TYPE_PLANTILLA): Builder
    {
        $types = self::normalizeEmployeeTypes($type);
        if (in_array(self::EMPLOYEE_TYPE_ALL, $types, true)) {
            return $query;
        }
        $map = [self::EMPLOYEE_TYPE_PLANTILLA => self::EMPSTAT_PERMANENT, self::EMPLOYEE_TYPE_CASUAL => self::EMPSTAT_CASUAL, self::EMPLOYEE_TYPE_PART_TIME => self::EMPSTAT_PART_TIME, self::EMPLOYEE_TYPE_CONTRACTUAL => self::EMPSTAT_CONTRACTUAL, self::EMPLOYEE_TYPE_TEMPORARY => self::EMPSTAT_TEMPORARY, self::EMPLOYEE_TYPE_VISITING_CONSULTANT => self::EMPSTAT_VISITING_CONSULTANT, self::EMPLOYEE_TYPE_COS => self::EMPSTAT_CONTRACT_OF_SERVICE, self::EMPLOYEE_TYPE_PROBATIONARY => self::EMPSTAT_PROBATIONARY, self::EMPLOYEE_TYPE_INTERN => self::EMPSTAT_INTERN, self::EMPLOYEE_TYPE_EXTERNAL => self::EMPSTAT_EXTERNAL];

        return $query->where(function (Builder $q) use ($types, $map) {
            foreach ($types as $t) {
                $q->orWhere(fn (Builder $x) => $t === self::EMPLOYEE_TYPE_EXTERNAL ? $x->where(fn (Builder $e) => $e->where('empstat_id', $map[$t])->orWhere('is_external', true)) : $x->where('empstat_id', $map[$t])->where('is_external', false));
            }
        });
    }
}
