<?php

namespace App\Livewire\Payroll;

use App\Models\Hris\Department;
use App\Models\Hris\Division;
use App\Models\Hris\Employee;
use App\Models\Payroll\PayrollType;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Livewire\Component;

class PayrollConfiguration extends Component
{
    public ?int $divisionId = null;

    public ?int $departmentId = null;

    public string $payrollType = PayrollType::CODE_GENERAL;

    public string $period;

    public int $workingDays = 22;

    public string $employeeTypeFilter = Employee::EMPLOYEE_TYPE_PLANTILLA;

    public function mount(): void
    {
        $userDepartmentId = auth()->user()?->employee?->department_id;
        $userDivisionId = $userDepartmentId
            ? Department::query()->where('department_id', $userDepartmentId)->value('division_id')
            : null;

        $this->divisionId = request()->integer('division_id') ?: $userDivisionId;
        $this->departmentId = request()->integer('department_id') ?: null;
        $requestedPayrollType = (string) request()->query('payroll_type', PayrollType::CODE_GENERAL);
        $this->payrollType = PayrollType::query()
            ->where('code', $requestedPayrollType)
            ->where('is_active', true)
            ->exists()
                ? $requestedPayrollType
                : PayrollType::CODE_GENERAL;

        if ($this->departmentId && $this->divisionId && ! Department::query()
            ->where('department_id', $this->departmentId)
            ->where('division_id', $this->divisionId)
            ->exists()) {
            $this->departmentId = null;
        }

        $this->period = request()->query('period', CarbonImmutable::today()->format('Y-m'));
        $this->workingDays = max(1, min(31, request()->integer('working_days') ?: 22));

        $employeeType = request()->query('employee_type', Employee::EMPLOYEE_TYPE_PLANTILLA);
        $this->employeeTypeFilter = array_key_exists($employeeType, Employee::employeeTypeOptions())
            ? $employeeType
            : Employee::EMPLOYEE_TYPE_PLANTILLA;
    }

    public function updatedDivisionId(): void
    {
        $this->departmentId = null;
    }

    public function proceed()
    {
        $data = $this->validate([
            'divisionId' => ['required', 'integer'],
            'departmentId' => ['nullable', 'integer'],
            'payrollType' => ['required', Rule::exists('payroll.payroll_types', 'code')->where('is_active', true)],
            'period' => ['required', 'date_format:Y-m'],
            'workingDays' => ['required', 'integer', 'min:1', 'max:31'],
            'employeeTypeFilter' => ['required', 'in:plantilla,cos,all'],
        ]);

        return redirect()->route(PayrollType::generationRouteFor($data['payrollType']), [
            'division_id' => $data['divisionId'],
            'department_id' => $data['departmentId'] ?: null,
            'payroll_type' => $data['payrollType'],
            'period' => $data['period'],
            'working_days' => $data['workingDays'],
            'employee_type' => $data['employeeTypeFilter'],
        ]);
    }

    public function render()
    {
        return view('livewire.payroll.payroll-configuration', [
            'divisions' => Division::query()->orderBy('division')->get(),
            'payrollTypes' => PayrollType::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'departments' => Department::query()
                ->when($this->divisionId, fn ($query) => $query->where('division_id', $this->divisionId))
                ->orderBy('department')
                ->get(),
            'employeeTypeOptions' => Employee::employeeTypeOptions(),
        ]);
    }
}
