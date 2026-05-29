<?php

namespace App\Livewire\Payroll;

use App\Models\Hris\Department;
use App\Models\Hris\Division;
use App\Models\Hris\Employee;
use App\Models\Payroll\PayrollBatch;
use App\Models\Payroll\PayrollGenerationDraft;
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

    public bool $showExistingGenerationNotice = false;

    public array $existingGenerations = [];

    public ?string $noticedConfigurationKey = null;

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
        $this->dismissExistingGenerationNotice();
    }

    public function proceed()
    {
        $data = $this->validatedConfiguration();
        $this->existingGenerations = $this->existingGenerationsFor($data);

        if ($this->existingGenerations !== []) {
            $this->noticedConfigurationKey = $this->configurationKeyFor($data);
            $this->showExistingGenerationNotice = true;

            return null;
        }

        return $this->redirectToGeneration($data);
    }

    public function continueToPayrollGeneration()
    {
        $data = $this->validatedConfiguration();
        if ($this->noticedConfigurationKey !== $this->configurationKeyFor($data)) {
            return $this->proceed();
        }

        $this->dismissExistingGenerationNotice();

        return $this->redirectToGeneration($data);
    }

    public function dismissExistingGenerationNotice(): void
    {
        $this->showExistingGenerationNotice = false;
        $this->existingGenerations = [];
        $this->noticedConfigurationKey = null;
    }

    private function validatedConfiguration(): array
    {
        return $this->validate([
            'divisionId' => ['required', 'integer'],
            'departmentId' => ['nullable', 'integer'],
            'payrollType' => ['required', Rule::exists('payroll.payroll_types', 'code')->where('is_active', true)],
            'period' => ['required', 'date_format:Y-m'],
            'workingDays' => ['required', 'integer', 'min:1', 'max:31'],
            'employeeTypeFilter' => ['required', 'in:plantilla,cos,all'],
        ]);
    }

    private function redirectToGeneration(array $data)
    {
        return redirect()->route(PayrollType::generationRouteFor($data['payrollType']), [
            'division_id' => $data['divisionId'],
            'department_id' => $data['departmentId'] ?: null,
            'payroll_type' => $data['payrollType'],
            'period' => $data['period'],
            'working_days' => $data['workingDays'],
            'employee_type' => $data['employeeTypeFilter'],
        ]);
    }

    private function existingGenerationsFor(array $data): array
    {
        $matches = [];
        $configurationKey = $this->configurationKeyFor($data);

        $draft = PayrollGenerationDraft::query()
            ->where('configuration_key', $configurationKey)
            ->first();

        if ($draft) {
            $matches[] = [
                'type' => 'draft',
                'label' => 'Saved draft',
                'description' => 'This exact configuration has an unfinished payroll draft.',
                'date' => $draft->saved_at?->format('M d, Y g:i A'),
                'by' => $draft->saved_by,
            ];
        }

        $batches = PayrollBatch::query()
            ->where('division_id', (int) $data['divisionId'])
            ->where('department_id', $data['departmentId'] ? (int) $data['departmentId'] : null)
            ->where('payroll_period', (string) $data['period'])
            ->where('payroll_type_code', (string) $data['payrollType'])
            ->where('working_days', (int) $data['workingDays'])
            ->where('employee_type', (string) $data['employeeTypeFilter'])
            ->latest('snapshot_created_at')
            ->limit(3)
            ->get();

        foreach ($batches as $batch) {
            $matches[] = [
                'type' => 'finalized',
                'label' => 'Finalized payroll',
                'description' => 'A payroll run with this exact configuration was already finalized.',
                'date' => $batch->snapshot_created_at?->format('M d, Y g:i A'),
                'by' => $batch->generated_by,
            ];
        }

        if (! $data['departmentId'] || $batches->isNotEmpty()) {
            return $matches;
        }

        $legacyBatch = PayrollBatch::query()
            ->whereNull('division_id')
            ->where('department_id', (int) $data['departmentId'])
            ->where('payroll_period', (string) $data['period'])
            ->whereNull('payroll_type_code')
            ->latest('snapshot_created_at')
            ->first();

        if ($legacyBatch) {
            $matches[] = [
                'type' => 'legacy',
                'label' => 'Possible earlier payroll',
                'description' => 'An older payroll snapshot exists for this department and month; its complete configuration was not recorded.',
                'date' => $legacyBatch->snapshot_created_at?->format('M d, Y g:i A'),
                'by' => $legacyBatch->generated_by,
            ];
        }

        return $matches;
    }

    private function configurationKeyFor(array $data): string
    {
        return PayrollGenerationDraft::configurationKey(
            (int) $data['divisionId'],
            $data['departmentId'] ? (int) $data['departmentId'] : null,
            (string) $data['payrollType'],
            (string) $data['period'],
            (int) $data['workingDays'],
            (string) $data['employeeTypeFilter'],
        );
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
