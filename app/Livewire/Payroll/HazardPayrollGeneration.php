<?php

namespace App\Livewire\Payroll;

use App\Models\Hris\Department;
use App\Models\Hris\Division;
use App\Models\Hris\Employee;
use App\Models\Hris\SalaryGrade;
use App\Services\Payroll\PayrollTaxService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Component;

class HazardPayrollGeneration extends Component
{
    public ?int $divisionId = null;

    public ?int $departmentId = null;

    public string $period;

    public int $workingDays = 22;

    public string $search = '';

    public string $employeeTypeFilter = Employee::EMPLOYEE_TYPE_PLANTILLA;

    public array $adjustments = [];

    public array $overpayments = [];

    public int $currentStep = 1;

    public array $steps = [
        1 => 'Hazard Computation',
        2 => 'Tax Calculation',
        3 => 'Review',
    ];

    public function mount(): void
    {
        $userDepartmentId = auth()->user()?->employee?->department_id;
        $userDivisionId = $userDepartmentId
            ? Department::query()->where('department_id', $userDepartmentId)->value('division_id')
            : null;

        $this->divisionId = request()->integer('division_id') ?: $userDivisionId;
        $this->departmentId = request()->integer('department_id') ?: null;

        if ($this->departmentId && $this->divisionId && ! Department::query()
            ->where('department_id', $this->departmentId)
            ->where('division_id', $this->divisionId)
            ->exists()) {
            $this->departmentId = null;
        }

        $this->period = request()->query('period', CarbonImmutable::today()->format('Y-m'));
        $this->workingDays = max(1, min(31, request()->integer('working_days') ?: $this->workingDays));

        $employeeType = request()->query('employee_type', Employee::EMPLOYEE_TYPE_PLANTILLA);
        $this->employeeTypeFilter = array_key_exists($employeeType, Employee::employeeTypeOptions())
            ? $employeeType
            : Employee::EMPLOYEE_TYPE_PLANTILLA;
        $this->search = (string) request()->query('search', '');
    }

    public function render()
    {
        $rows = $this->hazardRows();

        return view('livewire.payroll.hazard-payroll-generation', [
            'departments' => Department::query()->orderBy('department')->get(),
            'divisions' => Division::query()->orderBy('division')->get(),
            'employeeTypeOptions' => Employee::employeeTypeOptions(),
            'rows' => $rows,
            'totals' => [
                'basic_salary' => $rows->sum('basic_salary'),
                'gross_hazard_pay' => $rows->sum('gross_hazard_pay'),
                'adjustments' => $rows->sum('adjustment'),
                'overpayments' => $rows->sum('overpayment'),
                'adjusted_gross_hazard_pay' => $rows->sum('adjusted_gross_hazard_pay'),
                'withholding_tax' => $rows->sum('tax.monthly_tax_due'),
                'net_after_tax' => $rows->sum('net_after_tax'),
            ],
        ]);
    }

    public function goToStep(int $step): void
    {
        $this->currentStep = max(1, min(count($this->steps), $step));
    }

    public function nextStep(): void
    {
        $this->goToStep($this->currentStep + 1);
    }

    public function previousStep(): void
    {
        $this->goToStep($this->currentStep - 1);
    }

    private function hazardRows(): Collection
    {
        if (! $this->divisionId && ! $this->departmentId) {
            return collect();
        }

        $salaryMatrix = $this->salaryMatrix();

        return Employee::query()
            ->with(['position', 'department'])
            ->when(
                $this->departmentId,
                fn ($query) => $query->where('department_id', $this->departmentId),
                fn ($query) => $query->whereHas('department', fn ($departmentQuery) => $departmentQuery->where('division_id', $this->divisionId))
            )
            ->where('is_active', 'Y')
            ->employeeType($this->employeeTypeFilter)
            ->when(trim($this->search) !== '', function ($query) {
                $search = trim($this->search);
                $query->where(function ($query) use ($search) {
                    $query->where('emp_id', 'like', "%{$search}%")
                        ->orWhere('firstname', 'like', "%{$search}%")
                        ->orWhere('lastname', 'like', "%{$search}%");
                });
            })
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->get()
            ->map(function (Employee $employee) use ($salaryMatrix) {
                $salaryGrade = (int) ($employee->position?->salary_grade ?? 0);
                $step = max(1, min(8, (int) ($employee->step ?: 1)));
                $basicSalary = (float) ($salaryMatrix[$salaryGrade][$step] ?? 0);
                $hazardRate = $this->hazardRate($salaryGrade);
                $grossHazardPay = round($basicSalary * $hazardRate, 2);
                $adjustment = round((float) ($this->adjustments[$employee->emp_id] ?? 0), 2);
                $overpayment = round((float) ($this->overpayments[$employee->emp_id] ?? 0), 2);
                $adjustedGrossHazardPay = round($grossHazardPay + $adjustment - $overpayment, 2);
                $tax = [
                    'entry_date' => $employee->date_hired?->format('Y-m-d'),
                    'salary_grade' => $salaryGrade ?: null,
                    'salary' => $basicSalary,
                    'subsistence' => 0.0,
                    'hazard' => $adjustedGrossHazardPay,
                    'tax_adjustment' => 0.0,
                    'total_months' => PayrollTaxService::ANNUALIZED_MONTHS,
                    'leave_without_pay_months' => 0.0,
                    ...app(PayrollTaxService::class)->calculation($adjustedGrossHazardPay, 0),
                    'monthly_net_income' => $adjustedGrossHazardPay,
                ];

                return [
                    'emp_id' => $employee->emp_id,
                    'employee_name' => $employee->full_name,
                    'department' => $employee->department?->department,
                    'position' => $employee->position?->position_title,
                    'salary_grade' => $salaryGrade ?: null,
                    'step' => $step,
                    'sg_step' => $salaryGrade ? 'SG '.$salaryGrade.' / Step '.$step : '-',
                    'basic_salary' => $basicSalary,
                    'hazard_rate' => $hazardRate,
                    'gross_hazard_pay' => $grossHazardPay,
                    'adjustment' => $adjustment,
                    'overpayment' => $overpayment,
                    'adjusted_gross_hazard_pay' => $adjustedGrossHazardPay,
                    'tax' => $tax,
                    'net_after_tax' => round($adjustedGrossHazardPay - $tax['monthly_tax_due'], 2),
                ];
            });
    }

    private function salaryMatrix(): array
    {
        $grades = SalaryGrade::query()
            ->select(['salary_grade', 'step_increment', 'salary', 'effectivity_date'])
            ->orderByDesc('effectivity_date')
            ->get()
            ->groupBy(fn ($grade) => $grade->salary_grade.'|'.$grade->step_increment);

        $matrix = [];
        foreach ($grades as $key => $items) {
            [$salaryGrade, $step] = explode('|', $key);
            $matrix[(int) $salaryGrade][(int) $step] = (float) $items->first()->salary;
        }

        return $matrix;
    }

    private function hazardRate(int $salaryGrade): float
    {
        return match (true) {
            $salaryGrade <= 19 => 0.25,
            $salaryGrade === 20 => 0.15,
            $salaryGrade === 21 => 0.13,
            $salaryGrade === 22 => 0.12,
            $salaryGrade === 23 => 0.11,
            in_array($salaryGrade, [24, 25], true) => 0.10,
            $salaryGrade === 26 => 0.09,
            $salaryGrade === 27 => 0.08,
            $salaryGrade === 28 => 0.07,
            in_array($salaryGrade, [29, 30], true) => 0.06,
            $salaryGrade === 31 => 0.05,
            default => 0.0,
        };
    }
}
