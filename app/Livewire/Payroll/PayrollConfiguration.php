<?php

namespace App\Livewire\Payroll;

use App\Models\Hris\Department;
use App\Models\Hris\Division;
use App\Models\Hris\Employee;
use App\Models\Hris\LeaveType;
use App\Models\Payroll\PayrollBatch;
use App\Models\Payroll\PayrollGenerationDraft;
use App\Models\Payroll\PayrollType;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Livewire\Component;

class PayrollConfiguration extends Component
{
    private const DEFAULT_UNCHECKED_LEAVE_TYPE_IDS = [4, 14, 15, 16, 20, 22];

    public ?int $divisionId = null;

    public ?int $departmentId = null;

    public string $payrollType = PayrollType::CODE_GENERAL;

    public string $period;

    public int $workingDays = 22;

    public int $gsisDays = 30;

    public array $selectedLeaveTypeIds = [];

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
        $this->gsisDays = max(0, min(31, request()->integer('gsis_days') ?: 30));
        $this->selectedLeaveTypeIds = $this->hasExplicitLeaveTypeSelection(request()->query('leave_type_ids'))
            ? $this->parseSelectedLeaveTypeIds(request()->query('leave_type_ids', []))
            : $this->defaultSelectedLeaveTypeIds();

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
            'gsisDays' => ['required', 'integer', 'min:0', 'max:31'],
            'selectedLeaveTypeIds' => ['array'],
            'selectedLeaveTypeIds.*' => ['integer', 'exists:mysql.tbl_leave_type,leave_type_id'],
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
            'gsis_days' => $data['gsisDays'],
            'leave_type_ids' => $this->leaveTypeIdsQueryValue($data['selectedLeaveTypeIds'] ?? []),
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

        $selectedLeaveTypeIds = $this->normalizedLeaveTypeIds($data['selectedLeaveTypeIds'] ?? []);
        $batches = PayrollBatch::query()
            ->where('division_id', (int) $data['divisionId'])
            ->where('department_id', $data['departmentId'] ? (int) $data['departmentId'] : null)
            ->where('payroll_period', (string) $data['period'])
            ->where('payroll_type_code', (string) $data['payrollType'])
            ->where('working_days', (int) $data['workingDays'])
            ->where('employee_type', (string) $data['employeeTypeFilter'])
            ->where('gsis_days', (int) $data['gsisDays'])
            ->whereJsonLength('included_leave_type_ids', count($selectedLeaveTypeIds))
            ->where(function ($query) use ($selectedLeaveTypeIds) {
                foreach ($selectedLeaveTypeIds as $leaveTypeId) {
                    $query->whereJsonContains('included_leave_type_ids', $leaveTypeId);
                }
            })
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
            (int) $data['gsisDays'],
            $this->normalizedLeaveTypeIds($data['selectedLeaveTypeIds'] ?? []),
        );
    }

    private function parseSelectedLeaveTypeIds(mixed $value): array
    {
        if ($value === 'none') {
            return [];
        }

        $values = is_array($value) ? $value : explode(',', (string) $value);

        return $this->normalizedLeaveTypeIds($values);
    }

    private function hasExplicitLeaveTypeSelection(mixed $value): bool
    {
        return $value === 'none' || (is_string($value) && trim($value) !== '') || (is_array($value) && $value !== []);
    }

    private function leaveTypeIdsQueryValue(array $values): string
    {
        $ids = $this->normalizedLeaveTypeIds($values);

        return $ids === [] ? 'none' : implode(',', $ids);
    }

    private function normalizedLeaveTypeIds(array $values): array
    {
        $validLeaveTypeIds = $this->validLeaveTypeIds();

        return collect($values)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => in_array($id, $validLeaveTypeIds, true))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function defaultSelectedLeaveTypeIds(): array
    {
        return collect($this->validLeaveTypeIds())
            ->reject(fn (int $id) => in_array($id, self::DEFAULT_UNCHECKED_LEAVE_TYPE_IDS, true))
            ->values()
            ->all();
    }

    private function validLeaveTypeIds(): array
    {
        return LeaveType::query()
            ->pluck('leave_type_id')
            ->map(fn ($id) => (int) $id)
            ->all();
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
            'leaveTypes' => LeaveType::query()
                ->orderBy('leave_name')
                ->orderBy('leave_type_id')
                ->get(),
        ]);
    }
}
