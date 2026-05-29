<?php

namespace App\Livewire\Payroll;

use App\Models\Hris\Department;
use App\Models\Hris\Division;
use App\Models\Hris\Employee;
use App\Models\Payroll\PayrollType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Component;

class MedicarePayrollGeneration extends Component
{
    public ?int $divisionId = null;

    public ?int $departmentId = null;

    public string $period;

    public int $workingDays = 22;

    public string $employeeTypeFilter = Employee::EMPLOYEE_TYPE_PLANTILLA;

    public string $search = '';

    public function mount(): void
    {
        $userDepartmentId = auth()->user()?->employee?->department_id;
        $userDivisionId = $userDepartmentId
            ? Department::query()->where('department_id', $userDepartmentId)->value('division_id')
            : null;

        $this->divisionId = request()->integer('division_id') ?: $userDivisionId;
        $this->departmentId = request()->integer('department_id') ?: null;
        $this->period = request()->query('period', CarbonImmutable::today()->format('Y-m'));
        $this->workingDays = max(1, min(31, request()->integer('working_days') ?: 22));

        $employeeType = request()->query('employee_type', Employee::EMPLOYEE_TYPE_PLANTILLA);
        $this->employeeTypeFilter = array_key_exists($employeeType, Employee::employeeTypeOptions())
            ? $employeeType
            : Employee::EMPLOYEE_TYPE_PLANTILLA;
    }

    public function render()
    {
        $periodStart = CarbonImmutable::createFromFormat('Y-m', $this->period)->startOfMonth();
        $professionalFeePeriod = [
            'start' => $periodStart->subMonthNoOverflow()->startOfMonth(),
            'end' => $periodStart->subMonthNoOverflow()->endOfMonth(),
        ];
        $rows = $this->placeholderRows($professionalFeePeriod);

        return view('livewire.payroll.medicare-payroll-generation', [
            'rows' => $rows,
            'professionalFeePeriod' => $professionalFeePeriod,
            'scopeName' => $this->scopeName(),
            'employeeTypeOptions' => Employee::employeeTypeOptions(),
        ]);
    }

    private function placeholderRows(array $professionalFeePeriod): Collection
    {
        $query = Employee::query()
            ->with(['department.division', 'position'])
            ->where('is_active', 'Y')
            ->when($this->departmentId, fn ($query) => $query->where('department_id', $this->departmentId))
            ->when(! $this->departmentId && $this->divisionId, fn ($query) => $query->whereHas(
                'department',
                fn ($query) => $query->where('division_id', $this->divisionId)
            ))
            ->employeeType($this->employeeTypeFilter)
            ->when($this->search !== '', function ($query) {
                $search = '%'.strtolower($this->search).'%';

                $query->where(function ($query) use ($search) {
                    $query
                        ->whereRaw('LOWER(emp_id) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(firstname) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(lastname) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(middlename) LIKE ?', [$search]);
                });
            })
            ->whereHas('position', function ($query) {
                $query->where(function ($query) {
                    $query
                        ->where('position_title', 'like', '%doctor%')
                        ->orWhere('position_title', 'like', '%medical officer%')
                        ->orWhere('position_title', 'like', '%physician%')
                        ->orWhere('position_title', 'like', '%consultant%');
                });
            })
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->limit(100);

        return $query->get()->map(fn (Employee $employee) => [
            'emp_id' => $employee->emp_id,
            'employee_name' => $employee->full_name,
            'position' => $employee->position?->position_title,
            'department' => $employee->department?->department,
            'division' => $employee->department?->division?->division,
            'professional_fee_period' => $professionalFeePeriod['start']->format('M d').' - '.$professionalFeePeriod['end']->format('M d, Y'),
            'gross_professional_fees' => null,
            'tax_treatment' => 'TBD',
            'withholding_tax' => null,
            'net_medicare_pay' => null,
            'status' => 'Pending computation rule',
        ]);
    }

    private function scopeName(): string
    {
        if ($this->departmentId) {
            return Department::query()->where('department_id', $this->departmentId)->value('department') ?: 'Selected Department';
        }

        if ($this->divisionId) {
            return Division::query()->where('division_id', $this->divisionId)->value('division') ?: 'Selected Division';
        }

        return 'All Departments';
    }

    public function configurationRoute(): string
    {
        return route('payroll.generation.configuration', [
            'division_id' => $this->divisionId,
            'department_id' => $this->departmentId,
            'payroll_type' => PayrollType::CODE_MEDICARE,
            'period' => $this->period,
            'working_days' => $this->workingDays,
            'employee_type' => $this->employeeTypeFilter,
        ]);
    }
}
