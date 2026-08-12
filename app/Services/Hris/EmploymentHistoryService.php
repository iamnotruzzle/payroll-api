<?php

namespace App\Services\Hris;

use App\Models\Hris\Employee;
use App\Models\Hris\EmployeeEmploymentHistory;
use App\Models\Hris\Position;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmploymentHistoryService
{
    public const NATURE_ORIGINAL = 'original';

    public const NATURE_PROMOTION = 'promotion';

    public const NATURE_TRANSFER = 'transfer';

    public const NATURE_REAPPOINTMENT = 'reappointment';

    public const NATURE_DEMOTION = 'demotion';

    public const NATURE_STEP_INCREMENT = 'step_increment';

    public const NATURE_OTHER = 'other';

    /**
     * @return array<string, string>
     */
    public static function natures(): array
    {
        return [
            self::NATURE_ORIGINAL => 'Original appointment',
            self::NATURE_PROMOTION => 'Promotion',
            self::NATURE_TRANSFER => 'Transfer',
            self::NATURE_REAPPOINTMENT => 'Reappointment',
            self::NATURE_DEMOTION => 'Demotion',
            self::NATURE_STEP_INCREMENT => 'Step increment',
            self::NATURE_OTHER => 'Other',
        ];
    }

    public static function natureLabel(string $nature): string
    {
        return self::natures()[$nature] ?? ucfirst(str_replace('_', ' ', $nature));
    }

    /**
     * Seed one open "original" row from the employee master when history is empty.
     */
    public function seedFromEmployeeIfEmpty(Employee $employee, ?string $recordedBy = null): ?EmployeeEmploymentHistory
    {
        $exists = EmployeeEmploymentHistory::query()
            ->where('emp_id', $employee->emp_id)
            ->exists();

        if ($exists) {
            return null;
        }

        $from = $employee->date_hired
            ? CarbonImmutable::parse($employee->date_hired)->toDateString()
            : CarbonImmutable::today()->toDateString();

        return EmployeeEmploymentHistory::query()->create([
            'emp_id' => $employee->emp_id,
            'effective_from' => $from,
            'effective_to' => null,
            'item_number' => null,
            'position_id' => $employee->position_id,
            'department_id' => $employee->department_id,
            'empstat_id' => $employee->empstat_id,
            'step' => $employee->step,
            'salary_grade' => $this->salaryGradeFor($employee->position_id),
            'nature' => self::NATURE_ORIGINAL,
            'remarks' => 'Seeded from current employee record',
            'recorded_by_emp_id' => $recordedBy,
        ]);
    }

    /**
     * Close the open assignment and insert a new current row; optionally sync tbl_employee.
     *
     * @param  array{
     *     effective_from: string,
     *     item_number?: ?string,
     *     position_id?: ?int,
     *     department_id?: ?int,
     *     empstat_id?: ?int,
     *     step?: ?int,
     *     salary_grade?: ?int,
     *     nature: string,
     *     remarks?: ?string,
     * }  $data
     */
    public function recordChange(string $empId, array $data, ?string $recordedBy = null, bool $applyToEmployee = true): EmployeeEmploymentHistory
    {
        $employee = Employee::query()->where('emp_id', $empId)->firstOrFail();
        $from = CarbonImmutable::parse($data['effective_from'])->startOfDay();

        return DB::connection('hris')->transaction(function () use ($employee, $data, $from, $recordedBy, $applyToEmployee) {
            $openRows = EmployeeEmploymentHistory::query()
                ->where('emp_id', $employee->emp_id)
                ->whereNull('effective_to')
                ->orderBy('effective_from')
                ->lockForUpdate()
                ->get();

            foreach ($openRows as $open) {
                $openFrom = CarbonImmutable::parse($open->effective_from)->startOfDay();
                $closeOn = $from->subDay();
                if ($closeOn->lt($openFrom)) {
                    $closeOn = $openFrom;
                }
                $open->effective_to = $closeOn->toDateString();
                $open->save();
            }

            $positionId = $data['position_id'] ?? $employee->position_id;
            $salaryGrade = array_key_exists('salary_grade', $data) && $data['salary_grade'] !== null && $data['salary_grade'] !== ''
                ? (int) $data['salary_grade']
                : $this->salaryGradeFor($positionId !== null ? (int) $positionId : null);

            $row = EmployeeEmploymentHistory::query()->create([
                'emp_id' => $employee->emp_id,
                'effective_from' => $from->toDateString(),
                'effective_to' => null,
                'item_number' => $this->blankToNull($data['item_number'] ?? null),
                'position_id' => $positionId,
                'department_id' => $data['department_id'] ?? $employee->department_id,
                'empstat_id' => $data['empstat_id'] ?? $employee->empstat_id,
                'step' => $data['step'] ?? $employee->step,
                'salary_grade' => $salaryGrade,
                'nature' => $data['nature'],
                'remarks' => $this->blankToNull($data['remarks'] ?? null),
                'recorded_by_emp_id' => $recordedBy,
            ]);

            if ($applyToEmployee) {
                $this->applyRowToEmployee($employee, $row);
            }

            return $row->fresh(['position', 'department', 'employmentStatus']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateRow(int $id, array $data, string $empId): EmployeeEmploymentHistory
    {
        $row = EmployeeEmploymentHistory::query()
            ->where('emp_id', $empId)
            ->where('id', $id)
            ->firstOrFail();

        $from = CarbonImmutable::parse($data['effective_from'])->toDateString();
        $to = ! empty($data['effective_to'])
            ? CarbonImmutable::parse($data['effective_to'])->toDateString()
            : null;

        if ($to !== null && $to < $from) {
            throw ValidationException::withMessages([
                'effective_to' => 'Effective to must be on or after effective from.',
            ]);
        }

        if ($to === null) {
            $otherOpen = EmployeeEmploymentHistory::query()
                ->where('emp_id', $empId)
                ->whereNull('effective_to')
                ->where('id', '!=', $id)
                ->exists();

            if ($otherOpen) {
                throw ValidationException::withMessages([
                    'effective_to' => 'Another current assignment already exists. Close it first or set an end date.',
                ]);
            }
        }

        $positionId = $data['position_id'] ?? null;
        $salaryGrade = array_key_exists('salary_grade', $data) && $data['salary_grade'] !== null && $data['salary_grade'] !== ''
            ? (int) $data['salary_grade']
            : $this->salaryGradeFor($positionId !== null ? (int) $positionId : null);

        $row->fill([
            'effective_from' => $from,
            'effective_to' => $to,
            'item_number' => $this->blankToNull($data['item_number'] ?? null),
            'position_id' => $positionId,
            'department_id' => $data['department_id'] ?? null,
            'empstat_id' => $data['empstat_id'] ?? null,
            'step' => $data['step'] ?? null,
            'salary_grade' => $salaryGrade,
            'nature' => $data['nature'],
            'remarks' => $this->blankToNull($data['remarks'] ?? null),
        ])->save();

        if ($row->isCurrent()) {
            $employee = Employee::query()->where('emp_id', $empId)->firstOrFail();
            $this->applyRowToEmployee($employee, $row);
        }

        return $row->fresh(['position', 'department', 'employmentStatus']);
    }

    public function deleteRow(int $id, string $empId): void
    {
        $row = EmployeeEmploymentHistory::query()
            ->where('emp_id', $empId)
            ->where('id', $id)
            ->firstOrFail();

        if ($row->isCurrent()) {
            throw ValidationException::withMessages([
                'id' => 'Cannot delete the current assignment. Record a new change or edit it instead.',
            ]);
        }

        $row->delete();
    }

    private function applyRowToEmployee(Employee $employee, EmployeeEmploymentHistory $row): void
    {
        $employee->fill([
            'position_id' => $row->position_id,
            'department_id' => $row->department_id,
            'empstat_id' => $row->empstat_id,
            'step' => $row->step ?? $employee->step,
        ])->save();
    }

    private function salaryGradeFor(?int $positionId): ?int
    {
        if (! $positionId) {
            return null;
        }

        $sg = Position::query()->where('position_id', $positionId)->value('salary_grade');

        return $sg !== null ? (int) $sg : null;
    }

    private function blankToNull(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }
}
