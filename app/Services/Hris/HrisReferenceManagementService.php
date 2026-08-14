<?php

namespace App\Services\Hris;

use App\Models\Hris\Department;
use App\Models\Hris\Division;
use App\Models\Hris\Employee;
use App\Models\Hris\HrisReferenceMetadata;
use App\Models\Hris\PlantillaAssignment;
use App\Models\Hris\PlantillaItem;
use App\Models\Hris\Position;
use App\Models\Hris\SalaryGrade;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HrisReferenceManagementService
{
    public function saveDivision(?int $id, array $data, ?string $actor): Division
    {
        return DB::connection('hris')->transaction(function () use ($id, $data, $actor) {
            $division = $id ? Division::query()->findOrFail($id) : new Division;
            $division->division = trim($data['division']);
            $division->special_title = $this->blank($data['special_title'] ?? null);
            $division->updated_by = is_numeric($actor) ? (int) $actor : null;
            $division->updated_date = now();
            $division->save();
            $this->metadata('division', $division->division_id, $data, $actor);

            return $division;
        });
    }

    public function saveDepartment(?int $id, array $data, ?string $actor): Department
    {
        return DB::connection('hris')->transaction(function () use ($id, $data, $actor) {
            $department = $id ? Department::query()->findOrFail($id) : new Department;
            $department->fill(['department' => trim($data['department']), 'division_id' => $data['division_id']])->save();
            $this->metadata('department', $department->department_id, $data, $actor);

            return $department;
        });
    }

    public function savePosition(?int $id, array $data, ?string $actor): Position
    {
        $data = validator($data, [
            'position_title' => ['required', 'string', 'max:50'],
            'salary_grade' => ['required', 'integer', 'between:1,33'],
            'remarks' => ['nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ])->validate();

        return DB::connection('hris')->transaction(function () use ($id, $data, $actor) {
            $position = $id ? Position::query()->findOrFail($id) : new Position;
            $position->fill(['position_title' => trim($data['position_title']), 'salary_grade' => $data['salary_grade'], 'remarks' => trim((string) ($data['remarks'] ?? ''))])->save();
            $this->metadata('position', $position->position_id, $data, $actor);

            return $position;
        });
    }

    public function setActive(string $type, int $id, bool $active, ?string $actor): void
    {
        if (! in_array($type, ['division', 'department', 'position'], true)) {
            throw ValidationException::withMessages(['reference' => 'Unsupported reference type.']);
        }
        HrisReferenceMetadata::query()->updateOrCreate(
            ['reference_type' => $type, 'reference_id' => $id],
            ['is_active' => $active, 'updated_by_emp_id' => $actor]
        );
    }

    public function publishSalarySchedule(int $tranche, string $effectiveDate, array $matrix): int
    {
        $date = CarbonImmutable::parse($effectiveDate)->toDateString();
        $values = [];
        foreach (range(1, 33) as $grade) {
            foreach (range(1, 8) as $step) {
                $salary = $matrix[$grade][$step] ?? null;
                if ($salary === null || $salary === '') {
                    continue;
                }
                if (! is_numeric($salary) || (float) $salary < 0) {
                    throw ValidationException::withMessages(["salaryMatrix.{$grade}.{$step}" => "SG {$grade} Step {$step} must be a non-negative amount."]);
                }
                $values[] = ['salary_grade' => $grade, 'step_increment' => $step, 'salary' => round((float) $salary, 2)];
            }
        }
        if (count($values) !== 264) {
            throw ValidationException::withMessages(['salaryMatrix' => 'A published schedule must contain all 33 salary grades and 8 steps.']);
        }

        DB::connection('hris')->transaction(function () use ($tranche, $date, $values) {
            foreach ($values as $value) {
                SalaryGrade::query()->updateOrCreate(
                    ['tranche_number' => $tranche, 'effectivity_date' => $date, 'salary_grade' => $value['salary_grade'], 'step_increment' => $value['step_increment']],
                    ['salary' => $value['salary']]
                );
            }
        });

        return count($values);
    }

    public function savePlantilla(?int $id, array $data, ?string $actor): PlantillaItem
    {
        return DB::connection('hris')->transaction(function () use ($id, $data, $actor) {
            $item = $id ? PlantillaItem::query()->findOrFail($id) : new PlantillaItem;
            $item->fill([...$data, 'item_number' => trim($data['item_number']), 'fund_type' => $this->blank($data['fund_type'] ?? null), 'remarks' => $this->blank($data['remarks'] ?? null), 'updated_by_emp_id' => $actor])->save();

            return $item;
        });
    }

    public function assignPlantilla(int $itemId, string $empId, string $effectiveDate, string $nature, ?string $remarks, ?string $actor): PlantillaAssignment
    {
        return DB::connection('hris')->transaction(function () use ($itemId, $empId, $effectiveDate, $nature, $remarks, $actor) {
            $item = PlantillaItem::query()->lockForUpdate()->findOrFail($itemId);
            $employee = Employee::query()->where('emp_id', $empId)->lockForUpdate()->firstOrFail();
            $from = CarbonImmutable::parse($effectiveDate);

            foreach (PlantillaAssignment::query()->where(fn ($q) => $q->where('plantilla_item_id', $itemId)->orWhere('emp_id', $empId))->whereNull('effective_to')->lockForUpdate()->get() as $open) {
                $open->update(['effective_to' => $from->subDay()->toDateString()]);
                if ((int) $open->plantilla_item_id !== $itemId) {
                    PlantillaItem::query()->whereKey($open->plantilla_item_id)->update(['status' => 'vacant']);
                }
            }

            $assignment = PlantillaAssignment::query()->create(['plantilla_item_id' => $itemId, 'emp_id' => $empId, 'effective_from' => $from->toDateString(), 'nature' => $nature, 'remarks' => $this->blank($remarks), 'recorded_by_emp_id' => $actor]);
            $item->update(['status' => 'occupied']);
            app(EmploymentHistoryService::class)->recordChange($employee->emp_id, [
                'effective_from' => $from->toDateString(), 'item_number' => $item->item_number,
                'position_id' => $item->position_id, 'department_id' => $item->department_id,
                'empstat_id' => $employee->empstat_id, 'step' => $employee->step,
                'salary_grade' => $item->salary_grade, 'nature' => $nature,
                'remarks' => 'Assigned through Plantilla Registry'.($remarks ? ': '.$remarks : ''),
            ], $actor);

            return $assignment;
        });
    }

    private function metadata(string $type, int $id, array $data, ?string $actor): void
    {
        HrisReferenceMetadata::query()->updateOrCreate(['reference_type' => $type, 'reference_id' => $id], ['is_active' => $data['is_active'] ?? true, 'remarks' => $this->blank($data['metadata_remarks'] ?? null), 'updated_by_emp_id' => $actor]);
    }

    private function blank(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
