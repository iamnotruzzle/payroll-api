<?php

namespace App\Livewire\Payroll;

use App\Models\Hris\Department;
use App\Models\Hris\Division;
use App\Models\Hris\Employee;
use App\Models\Payroll\PayrollAdditional;
use App\Models\Payroll\PayrollType;
use App\Services\Payroll\ProfessionalFeeImportService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithFileUploads;

class MedicarePayrollGeneration extends Component
{
    use WithFileUploads;

    public ?int $divisionId = null;

    public ?int $departmentId = null;

    public string $period;

    public int $workingDays = 22;

    public array $employeeTypeFilter = [Employee::EMPLOYEE_TYPE_PLANTILLA];

    public string $search = '';

    /** @var array<string, float|string> */
    public array $professionalFees = [];

    /** @var array<string, float|string> */
    public array $adjustments = [];

    public $professionalFeeFile;

    public array $professionalFeeImportPreview = [];

    public ?string $professionalFeeImportMessage = null;

    public int $currentStep = 1;

    public array $steps = [
        1 => 'Professional Fees',
        2 => 'Review',
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
        $this->workingDays = max(1, min(31, request()->integer('working_days') ?: 22));
        $this->employeeTypeFilter = Employee::normalizeEmployeeTypes(
            request()->query('employee_type', Employee::EMPLOYEE_TYPE_PLANTILLA)
        );
        $this->search = (string) request()->query('search', '');
    }

    public function render()
    {
        $periodStart = CarbonImmutable::createFromFormat('Y-m', $this->period)->startOfMonth();
        $professionalFeePeriod = [
            'start' => $periodStart->subMonthNoOverflow()->startOfMonth(),
            'end' => $periodStart->subMonthNoOverflow()->endOfMonth(),
        ];
        $taxRule = $this->medicareTaxRule();
        $rows = $this->medicareRows($professionalFeePeriod, $taxRule);

        return view('livewire.payroll.medicare-payroll-generation', [
            'rows' => $rows,
            'totals' => [
                'gross_professional_fees' => $rows->sum('gross_professional_fees'),
                'adjustment' => $rows->sum('adjustment'),
                'adjusted_gross_professional_fees' => $rows->sum('adjusted_gross_professional_fees'),
                'withholding_tax' => $rows->sum('withholding_tax'),
                'net_medicare_pay' => $rows->sum('net_medicare_pay'),
            ],
            'professionalFeePeriod' => $professionalFeePeriod,
            'taxRule' => $taxRule,
            'scopeName' => $this->scopeName(),
            'employeeTypeOptions' => Employee::employeeTypeOptions(),
            'employeeTypeLabel' => Employee::employeeTypeLabel($this->employeeTypeFilter),
            'employeeTypeQueryValue' => Employee::employeeTypeQueryValue($this->employeeTypeFilter),
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

    public function previewProfessionalFeeImport(): void
    {
        $data = $this->validate([
            'professionalFeeFile' => ['required', 'file', 'mimes:xlsx,xls,xlsm', 'max:20480'],
        ]);

        $this->professionalFeeImportPreview = app(ProfessionalFeeImportService::class)
            ->preview($data['professionalFeeFile']->getRealPath());
        $this->professionalFeeImportMessage = collect($this->professionalFeeImportPreview)
            ->where('valid', true)
            ->count().' valid employee row(s) ready for confirmation.';
    }

    public function exportProfessionalFeeTemplate()
    {
        $employees = $this->doctorEmployeesQuery()->get();
        $path = app(ProfessionalFeeImportService::class)->template($employees);

        return response()
            ->download($path, 'medicare_professional_fees.xlsx')
            ->deleteFileAfterSend(true);
    }

    public function confirmProfessionalFeeImport(): void
    {
        $validRows = collect($this->professionalFeeImportPreview)->where('valid', true);
        if ($validRows->isEmpty()) {
            $this->addError('professionalFeeFile', 'The workbook has no valid professional fee rows.');

            return;
        }

        foreach ($validRows as $row) {
            $this->professionalFees[(string) $row['emp_id']] = round((float) $row['gross_professional_fees'], 2);
        }

        $this->professionalFeeFile = null;
        $this->professionalFeeImportPreview = [];
        $this->professionalFeeImportMessage = "Applied professional fees for {$validRows->count()} employee(s).";
    }

    private function medicareRows(array $professionalFeePeriod, array $taxRule): Collection
    {
        if (! $this->divisionId && ! $this->departmentId) {
            return collect();
        }

        $periodLabel = $professionalFeePeriod['start']->format('M d').' - '.$professionalFeePeriod['end']->format('M d, Y');
        $rate = (float) ($taxRule['supplemental_tax_rate'] ?? 0);
        $treatmentLabel = $this->taxTreatmentLabel(
            (string) ($taxRule['tax_treatment'] ?? 'supplemental_flat_rate'),
            $rate
        );

        return $this->doctorEmployeesQuery()
            ->get()
            ->map(function (Employee $employee) use ($periodLabel, $rate, $treatmentLabel, $taxRule) {
                $empId = (string) $employee->emp_id;
                $gross = round((float) ($this->professionalFees[$empId] ?? 0), 2);
                $adjustment = round((float) ($this->adjustments[$empId] ?? 0), 2);
                $adjustedGross = round($gross + $adjustment, 2);
                $withholdingTax = $taxRule['tax_treatment'] === 'supplemental_flat_rate'
                    ? round(max(0, $adjustedGross) * max(0, $rate), 2)
                    : 0.0;
                $net = round($adjustedGross - $withholdingTax, 2);
                $status = $adjustedGross > 0
                    ? 'Ready'
                    : 'Awaiting professional fees';

                return [
                    'emp_id' => $empId,
                    'employee_name' => $this->formatPayrollEmployeeName($employee),
                    'position' => $employee->position?->position_title,
                    'department' => $employee->department?->department,
                    'division' => $employee->department?->division?->division,
                    'professional_fee_period' => $periodLabel,
                    'gross_professional_fees' => $gross,
                    'adjustment' => $adjustment,
                    'adjusted_gross_professional_fees' => $adjustedGross,
                    'tax_treatment' => $treatmentLabel,
                    'supplemental_tax_rate' => $rate,
                    'withholding_tax' => $withholdingTax,
                    'net_medicare_pay' => $net,
                    'status' => $status,
                ];
            });
    }

    private function doctorEmployeesQuery()
    {
        return Employee::query()
            ->with(['department.division', 'position'])
            ->where('is_active', 'Y')
            ->when($this->departmentId, fn ($query) => $query->where('department_id', $this->departmentId))
            ->when(! $this->departmentId && $this->divisionId, fn ($query) => $query->whereHas(
                'department',
                fn ($query) => $query->where('division_id', $this->divisionId)
            ))
            ->employeeType($this->employeeTypeFilter)
            ->when(trim($this->search) !== '', function ($query) {
                $search = '%'.strtolower(trim($this->search)).'%';

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
            ->orderBy('firstname');
    }

    private function medicareTaxRule(): array
    {
        $item = PayrollAdditional::query()
            ->where(function ($query) {
                $query->where('variable_name', 'medicare')
                    ->orWhere('name', 'like', '%medicare%');
            })
            ->orderByDesc('is_active')
            ->orderBy('sort_order')
            ->first();

        return [
            'tax_treatment' => $item?->tax_treatment ?: 'supplemental_flat_rate',
            'supplemental_tax_rate' => $item?->supplemental_tax_rate !== null
                ? (float) $item->supplemental_tax_rate
                : 0.15,
            'name' => $item?->name ?: 'Medicare',
        ];
    }

    private function taxTreatmentLabel(string $treatment, float $rate): string
    {
        return match ($treatment) {
            'supplemental_flat_rate' => 'Supplemental flat rate ('.rtrim(rtrim(number_format($rate * 100, 2), '0'), '.').'%)',
            'non_taxable' => 'Non-taxable',
            'de_minimis_annual_limit' => 'De minimis annual limit',
            default => 'Regular taxable',
        };
    }

    private function scopeName(): string
    {
        if ($this->departmentId) {
            return Department::query()->where('department_id', $this->departmentId)->value('department') ?: 'Selected Department';
        }

        if ($this->divisionId) {
            return Division::query()->where('division_id', $this->divisionId)->value('division') ?: 'Selected Division';
        }

        return 'Select a division or department';
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

    public function configurationRoute(): string
    {
        return route('payroll.generation.configuration', [
            'division_id' => $this->divisionId,
            'department_id' => $this->departmentId,
            'payroll_type' => PayrollType::CODE_MEDICARE,
            'period' => $this->period,
            'working_days' => $this->workingDays,
            'employee_type' => Employee::employeeTypeQueryValue($this->employeeTypeFilter),
        ]);
    }
}
