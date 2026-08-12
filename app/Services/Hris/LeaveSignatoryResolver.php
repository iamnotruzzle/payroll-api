<?php

namespace App\Services\Hris;

use App\Models\Hris\Employee;
use App\Models\Hris\Signatory;

/**
 * Legacy GettersController signatory helpers used by leave print (CSC Form 6).
 */
class LeaveSignatoryResolver
{
    /**
     * @return array{name: string, pos: string, response: int, emp_id: ?string}
     */
    public function departmentHead(int|string|null $departmentId): array
    {
        $signatory = '';
        $sigTitle = '';
        $response = 0;
        $empId = null;
        $head = null;

        $sig = Signatory::query()
            ->with(['employee.position'])
            ->where('assignment_type', 'DEP')
            ->where('status', 'A')
            ->where('section_id', $departmentId)
            ->first();

        if ($sig?->employee) {
            $head = $sig->employee;
            $sigTitle = $sig->title ?: (string) ($head->position?->position_title ?? '');
        } else {
            $head = Employee::query()
                ->with('position')
                ->where('is_section_head', 'Y')
                ->where('department_id', $departmentId)
                ->where('is_active', 'Y')
                ->first();
            if ($head) {
                $sigTitle = (string) ($head->position?->position_title ?? '');
            }
        }

        if ($head) {
            $signatory = $this->givenNameFirst($head);
            $empId = (string) $head->emp_id;
            $response = 1;
        }

        return [
            'name' => $signatory,
            'pos' => $sigTitle,
            'response' => $response,
            'emp_id' => $empId,
        ];
    }

    /**
     * @return array{name: string, pos: string, response: int, emp_id: ?string}
     */
    public function specialDepartmentSignatory(int|string|null $departmentId): array
    {
        $sig = Signatory::query()
            ->with(['employee.position'])
            ->where('assignment_type', 'DEPX')
            ->where('status', 'A')
            ->where('section_id', $departmentId)
            ->first();

        if (! $sig?->employee) {
            return ['name' => '', 'pos' => '', 'response' => 0, 'emp_id' => null];
        }

        return [
            'name' => $this->givenNameFirst($sig->employee),
            'pos' => $sig->title ?: (string) ($sig->employee->position?->position_title ?? ''),
            'response' => 1,
            'emp_id' => (string) $sig->emp_id,
        ];
    }

    /**
     * @return array{emp_id: string, name: string, pos: string}
     */
    public function regionalDirector(string $empId): array
    {
        $sig = Signatory::query()
            ->where('assignment_type', 'DIR')
            ->where('status', 'A')
            ->where('emp_id', $empId)
            ->first();

        $name = '';
        $pos = '';
        if ($sig?->title) {
            $parts = explode(';', (string) $sig->title);
            $name = trim((string) ($parts[0] ?? ''));
            $pos = trim((string) ($parts[1] ?? ''));
        }

        return ['emp_id' => '', 'name' => $name, 'pos' => $pos];
    }

    /**
     * @return array{emp_id: ?string, name: string, pos: string}
     */
    public function divisionChief(int|string|null $divisionId): array
    {
        $fallbackPositionIds = [
            1 => 50,
            2 => 36,
            3 => 65,
            4 => 20,
            5 => 44,
        ];

        $div = (int) $divisionId;
        $empId = null;
        $name = '';
        $pos = '';

        if (! in_array($div, [1, 2, 3, 4, 5, 7], true)) {
            return ['emp_id' => null, 'name' => '', 'pos' => ''];
        }

        $sig = Signatory::query()
            ->with(['employee.position'])
            ->where('assignment_type', 'DIV')
            ->where('status', 'A')
            ->where('section_id', $div)
            ->first();

        if ($sig?->employee) {
            $name = $this->givenNameFirst($sig->employee);
            $pos = $sig->title ?: (string) ($sig->employee->position?->position_title ?? '');
            $empId = (string) $sig->employee->emp_id;
        } elseif ($div !== 7 && isset($fallbackPositionIds[$div])) {
            $emp = Employee::query()
                ->with('position')
                ->where('position_id', $fallbackPositionIds[$div])
                ->where('is_active', 'Y')
                ->first();
            if ($emp) {
                $name = $this->givenNameFirst($emp);
                $pos = (string) ($emp->position?->position_title ?? '');
                $empId = (string) $emp->emp_id;
            }
        }

        return ['emp_id' => $empId, 'name' => $name, 'pos' => $pos];
    }

    public function isSpecialDepartment(int|string|null $departmentId): bool
    {
        return Signatory::query()
            ->where('assignment_type', 'DEPX')
            ->where('status', 'A')
            ->where('section_id', $departmentId)
            ->exists();
    }

    /** @return int 0 = no, 1 = chief, 2 = special chief (position 125) */
    public function isChief(string $empId): int
    {
        $chiefIds = Signatory::query()
            ->where('assignment_type', 'DIV')
            ->where('status', 'A')
            ->pluck('emp_id')
            ->map(fn ($id) => (string) $id)
            ->all();

        if (! in_array($empId, $chiefIds, true)) {
            return 0;
        }

        $employee = Employee::query()->where('emp_id', $empId)->first();

        return ((int) ($employee?->position_id) === 125) ? 2 : 1;
    }

    public function isHead(string $empId): bool
    {
        $employee = Employee::query()->where('emp_id', $empId)->first();
        if ($employee && $employee->is_section_head === 'Y') {
            return true;
        }

        return Signatory::query()
            ->where('emp_id', $empId)
            ->where('assignment_type', 'DEP')
            ->where('status', 'A')
            ->exists();
    }

    public function givenNameFirst(Employee $employee): string
    {
        $name = trim(sprintf(
            '%s %s%s',
            (string) $employee->firstname,
            $employee->middlename ? mb_substr((string) $employee->middlename, 0, 1).'. ' : '',
            (string) $employee->lastname
        ));

        if ($employee->extension) {
            $name .= ', '.$employee->extension;
        }
        if ($employee->suffix) {
            $name .= ', '.$employee->suffix;
        }

        return $name;
    }

    public function familyNameFirst(Employee $employee): string
    {
        $name = (string) $employee->lastname.', '.(string) $employee->firstname;
        if ($employee->middlename) {
            $name .= ' '.mb_substr((string) $employee->middlename, 0, 1).'.';
        }

        return $name;
    }
}
