<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Employee extends Authenticatable
{
    use Notifiable;

    public const CONTRACT_OF_SERVICE_POSITION_ID = 100;

    public const EMPSTAT_PERMANENT = 1;

    public const EMPSTAT_CASUAL = 2;

    public const EMPSTAT_PART_TIME = 3;

    public const EMPSTAT_CONTRACTUAL = 4;

    public const EMPSTAT_TEMPORARY = 5;

    public const EMPSTAT_VISITING_CONSULTANT = 6;

    public const EMPSTAT_CONTRACT_OF_SERVICE = 7;

    public const EMPSTAT_PROBATIONARY = 8;

    public const EMPSTAT_INTERN = 9;

    public const EMPSTAT_EXTERNAL = 10;

    public const EMPLOYEE_TYPE_PLANTILLA = 'plantilla';

    public const EMPLOYEE_TYPE_CASUAL = 'casual';

    public const EMPLOYEE_TYPE_PART_TIME = 'part_time';

    public const EMPLOYEE_TYPE_CONTRACTUAL = 'contractual';

    public const EMPLOYEE_TYPE_TEMPORARY = 'temporary';

    public const EMPLOYEE_TYPE_VISITING_CONSULTANT = 'visiting_consultant';

    public const EMPLOYEE_TYPE_COS = 'cos';

    public const EMPLOYEE_TYPE_PROBATIONARY = 'probationary';

    public const EMPLOYEE_TYPE_INTERN = 'intern';

    public const EMPLOYEE_TYPE_EXTERNAL = 'external';

    public const EMPLOYEE_TYPE_ALL = 'all';

    public const EXTERNAL_DIVISION_NAME = 'external';

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
            $this->middlename ? mb_substr($this->middlename, 0, 1).'.' : null,
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

    public function scopeEmployeeType(Builder $query, string|array|null $type = self::EMPLOYEE_TYPE_PLANTILLA): Builder
    {
        $types = self::normalizeEmployeeTypes($type);

        if (in_array(self::EMPLOYEE_TYPE_ALL, $types, true)) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($types) {
            foreach ($types as $employeeType) {
                $query->orWhere(fn (Builder $typeQuery) => self::applyEmployeeTypeConstraint($typeQuery, $employeeType));
            }
        });
    }

    public static function employeeTypeOptions(): array
    {
        return [
            self::EMPLOYEE_TYPE_PLANTILLA => 'Permanent / Plantilla Positions',
            self::EMPLOYEE_TYPE_CASUAL => 'Casual',
            self::EMPLOYEE_TYPE_PART_TIME => 'Part Time',
            self::EMPLOYEE_TYPE_CONTRACTUAL => 'Contractual',
            self::EMPLOYEE_TYPE_TEMPORARY => 'Temporary',
            self::EMPLOYEE_TYPE_VISITING_CONSULTANT => 'Visiting Consultant',
            self::EMPLOYEE_TYPE_COS => 'Contract of Service',
            self::EMPLOYEE_TYPE_PROBATIONARY => 'Probationary',
            self::EMPLOYEE_TYPE_INTERN => 'Intern',
            self::EMPLOYEE_TYPE_EXTERNAL => 'External',
            self::EMPLOYEE_TYPE_ALL => 'All Employees',
        ];
    }

    public static function normalizeEmployeeTypes(mixed $types): array
    {
        if ($types === null || $types === '') {
            $types = [self::EMPLOYEE_TYPE_PLANTILLA];
        }

        if (is_string($types)) {
            $types = explode(',', $types);
        }

        $validTypes = array_keys(self::employeeTypeOptions());
        $normalized = collect(is_array($types) ? $types : [$types])
            ->map(fn ($type) => trim((string) $type))
            ->filter(fn (string $type) => $type !== '' && in_array($type, $validTypes, true))
            ->unique()
            ->values()
            ->all();

        if ($normalized === []) {
            return [self::EMPLOYEE_TYPE_PLANTILLA];
        }

        if (in_array(self::EMPLOYEE_TYPE_ALL, $normalized, true)) {
            return [self::EMPLOYEE_TYPE_ALL];
        }

        return collect($validTypes)
            ->filter(fn (string $type) => in_array($type, $normalized, true))
            ->values()
            ->all();
    }

    public static function employeeTypeQueryValue(mixed $types): string
    {
        return implode(',', self::normalizeEmployeeTypes($types));
    }

    public static function employeeTypeLabel(mixed $types): string
    {
        $options = self::employeeTypeOptions();

        return collect(self::normalizeEmployeeTypes($types))
            ->map(fn (string $type) => $options[$type] ?? ucfirst($type))
            ->implode(', ');
    }

    private static function applyEmployeeTypeConstraint(Builder $query, string $type): Builder
    {
        $excludeExternalDivision = fn (Builder $employeeQuery) => $employeeQuery
            ->whereDoesntHave('department.division', fn (Builder $divisionQuery) => $divisionQuery
                ->whereRaw('LOWER(TRIM(division)) = ?', [self::EXTERNAL_DIVISION_NAME]));

        return match ($type) {
            self::EMPLOYEE_TYPE_CASUAL => $excludeExternalDivision($query->where('empstat_id', self::EMPSTAT_CASUAL)),
            self::EMPLOYEE_TYPE_PART_TIME => $excludeExternalDivision($query->where('empstat_id', self::EMPSTAT_PART_TIME)),
            self::EMPLOYEE_TYPE_CONTRACTUAL => $excludeExternalDivision($query->where('empstat_id', self::EMPSTAT_CONTRACTUAL)),
            self::EMPLOYEE_TYPE_TEMPORARY => $excludeExternalDivision($query->where('empstat_id', self::EMPSTAT_TEMPORARY)),
            self::EMPLOYEE_TYPE_VISITING_CONSULTANT => $excludeExternalDivision($query->where('empstat_id', self::EMPSTAT_VISITING_CONSULTANT)),
            self::EMPLOYEE_TYPE_COS => $excludeExternalDivision($query->where('empstat_id', self::EMPSTAT_CONTRACT_OF_SERVICE)),
            self::EMPLOYEE_TYPE_PROBATIONARY => $excludeExternalDivision($query->where('empstat_id', self::EMPSTAT_PROBATIONARY)),
            self::EMPLOYEE_TYPE_INTERN => $excludeExternalDivision($query->where('empstat_id', self::EMPSTAT_INTERN)),
            self::EMPLOYEE_TYPE_EXTERNAL => $query->whereHas('department.division', fn (Builder $divisionQuery) => $divisionQuery
                ->whereRaw('LOWER(TRIM(division)) = ?', [self::EXTERNAL_DIVISION_NAME])),
            default => $excludeExternalDivision($query->where('empstat_id', self::EMPSTAT_PERMANENT)),
        };
    }
}
