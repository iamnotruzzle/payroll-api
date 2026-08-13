<?php

namespace App\Livewire\Payroll;

use App\Models\Hris\Department;
use App\Models\Hris\Division;
use App\Models\Hris\Employee;
use App\Models\Hris\SalaryGrade;
use App\Services\Payroll\PayrollTaxService;
use App\Services\Payroll\StatutoryContributionService;
use App\Services\Payroll\TaxInputImportService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithFileUploads;

class HazardPayrollGeneration extends Component
{
    use WithFileUploads;

    public ?int $divisionId = null;

    public ?int $departmentId = null;

    public string $period;

    public int $workingDays = 22;

    public string $search = '';

    public array $employeeTypeFilter = [Employee::EMPLOYEE_TYPE_PLANTILLA];

    public array $adjustments = [];

    public array $overpayments = [];

    public array $taxOverrides = [];

    public $taxInputFile;

    public array $taxInputImportPreview = [];

    public ?string $taxInputImportMessage = null;

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

        $this->employeeTypeFilter = Employee::normalizeEmployeeTypes(
            request()->query('employee_type', Employee::EMPLOYEE_TYPE_PLANTILLA)
        );
        $this->search = (string) request()->query('search', '');
    }

    public function render()
    {
        $rows = $this->hazardRows();

        return view('livewire.payroll.hazard-payroll-generation', [
            'departments' => Department::query()->orderBy('department')->get(),
            'divisions' => Division::query()->orderBy('division')->get(),
            'employeeTypeOptions' => Employee::employeeTypeOptions(),
            'employeeTypeLabel' => Employee::employeeTypeLabel($this->employeeTypeFilter),
            'employeeTypeQueryValue' => Employee::employeeTypeQueryValue($this->employeeTypeFilter),
            'rows' => $rows,
            'totals' => [
                'basic_salary' => $rows->sum('basic_salary'),
                'gross_hazard_pay' => $rows->sum('gross_hazard_pay'),
                'adjustments' => $rows->sum('adjustment'),
                'overpayments' => $rows->sum('overpayment'),
                'adjusted_gross_hazard_pay' => $rows->sum('adjusted_gross_hazard_pay'),
                'withholding_tax' => $rows->sum('tax.hazard_withholding_tax'),
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

    public function previewTaxInputImport(): void
    {
        $data = $this->validate([
            'taxInputFile' => ['required', 'file', 'mimes:xlsx,xls,xlsm', 'max:20480'],
        ]);
        $this->taxInputImportPreview = app(TaxInputImportService::class)
            ->preview($data['taxInputFile']->getRealPath());
        $this->taxInputImportMessage = collect($this->taxInputImportPreview)->where('valid', true)->count()
            .' valid employee row(s) ready for confirmation.';
    }

    public function exportTaxInputTemplate()
    {
        $path = app(TaxInputImportService::class)->template();

        return response()->download($path, 'hazard_payroll_tax_inputs.xlsx')->deleteFileAfterSend(true);
    }

    public function confirmTaxInputImport(): void
    {
        $validRows = collect($this->taxInputImportPreview)->where('valid', true);
        if ($validRows->isEmpty()) {
            $this->addError('taxInputFile', 'The workbook has no valid tax input rows.');

            return;
        }

        foreach ($validRows as $row) {
            $empId = (string) $row['emp_id'];
            $this->taxOverrides[$empId] = [
                ...app(TaxInputImportService::class)->retainedOverrides($this->taxOverrides[$empId] ?? []),
                ...$row['values'],
            ];
        }

        $this->taxInputFile = null;
        $this->taxInputImportPreview = [];
        $this->taxInputImportMessage = "Applied tax inputs for {$validRows->count()} employee(s).";
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
                $taxInputs = app(TaxInputImportService::class)->retainedOverrides($this->taxOverrides[$employee->emp_id] ?? []);
                $contributions = app(StatutoryContributionService::class)->calculate($basicSalary, $this->period.'-01');
                $currentMandatoryDeductions = round((float) ($contributions['employee_total'] ?? 0), 2);
                $currentSubsistence = PayrollTaxService::PROJECTED_MONTHLY_SUBSISTENCE;
                $futureMonths = $this->futureMonthsForTax($employee->date_hired, CarbonImmutable::createFromFormat('Y-m', $this->period)->startOfMonth());
                $annualization = app(PayrollTaxService::class)->annualization([
                    'current_basic' => $basicSalary,
                    'current_hazard' => $adjustedGrossHazardPay,
                    'current_subsistence' => $currentSubsistence,
                    'current_mandatory_deductions' => $currentMandatoryDeductions,
                    'previous_basic' => $this->taxInputValue($taxInputs, 'previous_basic'),
                    'previous_hazard' => $this->taxInputValue($taxInputs, 'previous_hazard'),
                    'previous_subsistence' => $this->taxInputValue($taxInputs, 'previous_subsistence'),
                    'previous_mandatory_deductions' => $this->taxInputValue($taxInputs, 'previous_mandatory_deductions'),
                    'previous_tax_withheld' => $this->taxInputValue($taxInputs, 'previous_tax_withheld'),
                    'future_months' => $futureMonths,
                    'hazard_rate' => $hazardRate,
                    'withholding_tax_adjustment' => $this->taxInputValue($taxInputs, 'withholding_tax_adjustment'),
                ]);
                $hazardWithholdingTax = app(PayrollTaxService::class)->hazardWithholdingTax($annualization);
                $tax = [
                    'entry_date' => $employee->date_hired?->format('Y-m-d'),
                    'salary_grade' => $salaryGrade ?: null,
                    'salary' => $basicSalary,
                    'subsistence' => $currentSubsistence,
                    'hazard' => $adjustedGrossHazardPay,
                    'tax_adjustment' => $this->taxInputValue($taxInputs, 'withholding_tax_adjustment'),
                    'total_months' => PayrollTaxService::ANNUALIZED_MONTHS,
                    'leave_without_pay_months' => 0.0,
                    ...$annualization,
                    'monthly_mandatory_deductions' => $currentMandatoryDeductions,
                    'monthly_net_income' => round($basicSalary + $currentSubsistence + $adjustedGrossHazardPay - $currentMandatoryDeductions, 2),
                    'hazard_withholding_tax' => $hazardWithholdingTax,
                ];

                return [
                    'emp_id' => $employee->emp_id,
                    'employee_name' => $this->formatPayrollEmployeeName($employee),
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
                    'net_after_tax' => round($adjustedGrossHazardPay - $hazardWithholdingTax, 2),
                ];
            });
    }

    private function taxInputValue(array $inputs, string $key): float
    {
        $value = $inputs[$key] ?? 0;

        if (! is_numeric($value)) {
            return 0.0;
        }

        $number = (float) $value;

        return round($key === 'withholding_tax_adjustment' ? $number : max(0, $number), 2);
    }

    private function futureMonthsForTax(mixed $appointmentDate, CarbonImmutable $periodStart): float
    {
        if ($periodStart->month >= 12) {
            return 0.0;
        }

        $futureStart = $periodStart->addMonthNoOverflow()->startOfMonth();
        $futureEnd = $periodStart->endOfYear();
        if ($appointmentDate) {
            $appointment = CarbonImmutable::parse($appointmentDate)->startOfDay();
            if ($appointment->greaterThan($futureEnd)) {
                return 0.0;
            }
            if ($appointment->greaterThan($futureStart)) {
                $futureStart = $appointment;
            }
        }

        $weekdays = 0;
        for ($date = $futureStart; $date->lessThanOrEqualTo($futureEnd); $date = $date->addDay()) {
            if ($date->isWeekday()) {
                $weekdays++;
            }
        }

        return round(min(12 - $periodStart->month, max(0, $weekdays / 22)), 4);
    }

    private function salaryMatrix(): array
    {
        $grades = SalaryGrade::query()
            ->select(['salary_grade', 'step_increment', 'salary', 'effectivity_date'])
            ->whereDate('effectivity_date', '<=', CarbonImmutable::createFromFormat('Y-m', $this->period)->endOfMonth()->toDateString())
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

    private function formatPayrollEmployeeName(Employee $employee): string
    {
        $lastName = trim(implode(' ', array_filter([
            $employee->lastname,
            $employee->extension,
            $employee->suffix,
        ])));
        $firstName = trim((string) $employee->firstname);
        $middleInitial = $employee->middlename
            ? mb_strtoupper(mb_substr(trim((string) $employee->middlename), 0, 1)).'.'
            : null;

        $givenName = trim(implode(' ', array_filter([$firstName, $middleInitial])));

        return trim($lastName.', '.$givenName, ' ,');
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
