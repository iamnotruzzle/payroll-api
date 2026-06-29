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

    public array $selectedDivisionIds = [];

    public array $selectedDepartmentIds = [];

    public string $payrollType = PayrollType::CODE_GENERAL;

    public string $period;

    public int $workingDays = 22;

    public int $gsisDays = 30;

    public array $selectedLeaveTypeIds = [];

    public array $employeeTypeFilter = [Employee::EMPLOYEE_TYPE_PLANTILLA];

    public bool $showExistingGenerationNotice = false;

    public array $existingGenerations = [];

    public ?string $noticedConfigurationKey = null;

    public function mount(): void
    {
        $userDepartmentId = auth()->user()?->employee?->department_id;
        $userDivisionId = $userDepartmentId
            ? Department::query()->where('department_id', $userDepartmentId)->value('division_id')
            : null;

        $this->selectedDivisionIds = $this->parseIdList(request()->query('division_ids', request()->query('division_id')));
        if ($this->selectedDivisionIds === [] && $userDivisionId) {
            $this->selectedDivisionIds = [(int) $userDivisionId];
        }
        $this->selectedDepartmentIds = $this->parseIdList(request()->query('department_ids', request()->query('department_id')));
        $this->syncLegacyScopeIds();
        $requestedPayrollType = (string) request()->query('payroll_type', PayrollType::CODE_GENERAL);
        $this->payrollType = PayrollType::query()
            ->where('code', $requestedPayrollType)
            ->where('is_active', true)
            ->exists()
                ? $requestedPayrollType
                : PayrollType::CODE_GENERAL;

        if ($this->selectedDepartmentIds !== [] && $this->selectedDivisionIds !== []) {
            $this->selectedDepartmentIds = Department::query()
                ->whereIn('department_id', $this->selectedDepartmentIds)
                ->whereIn('division_id', $this->selectedDivisionIds)
                ->pluck('department_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $this->syncLegacyScopeIds();
        }

        $this->period = request()->query('period', CarbonImmutable::today()->format('Y-m'));
        $this->workingDays = max(1, min(31, request()->integer('working_days') ?: 22));
        $this->gsisDays = max(0, min(31, request()->integer('gsis_days') ?: 30));
        $this->selectedLeaveTypeIds = $this->hasExplicitLeaveTypeSelection(request()->query('leave_type_ids'))
            ? $this->parseSelectedLeaveTypeIds(request()->query('leave_type_ids', []))
            : $this->defaultSelectedLeaveTypeIds();

        $this->employeeTypeFilter = Employee::normalizeEmployeeTypes(
            request()->query('employee_type', Employee::EMPLOYEE_TYPE_PLANTILLA)
        );
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
        $data = $this->validate([
            'selectedDivisionIds' => ['required', 'array', 'min:1'],
            'selectedDivisionIds.*' => ['integer', 'exists:mysql.tbl_division,division_id'],
            'selectedDepartmentIds' => ['array'],
            'selectedDepartmentIds.*' => ['integer', 'exists:mysql.tbl_department,department_id'],
            'payrollType' => ['required', Rule::exists('payroll.payroll_types', 'code')->where('is_active', true)],
            'period' => ['required', 'date_format:Y-m'],
            'workingDays' => ['required', 'integer', 'min:1', 'max:31'],
            'gsisDays' => ['required', 'integer', 'min:0', 'max:31'],
            'selectedLeaveTypeIds' => ['array'],
            'selectedLeaveTypeIds.*' => ['integer', 'exists:mysql.tbl_leave_type,leave_type_id'],
            'employeeTypeFilter' => ['required', 'array', 'min:1'],
            'employeeTypeFilter.*' => ['required', Rule::in(array_keys(Employee::employeeTypeOptions()))],
        ]);

        $data['selectedDivisionIds'] = $this->normalizedIds($data['selectedDivisionIds'] ?? []);
        $data['selectedDepartmentIds'] = $this->normalizedIds($data['selectedDepartmentIds'] ?? []);
        $data['selectedLeaveTypeIds'] = $this->normalizedLeaveTypeIds($data['selectedLeaveTypeIds'] ?? []);
        $data['employeeTypeFilter'] = Employee::normalizeEmployeeTypes($data['employeeTypeFilter'] ?? []);

        if ($data['selectedDepartmentIds'] !== []) {
            $departmentDivisionIds = Department::query()
                ->whereIn('department_id', $data['selectedDepartmentIds'])
                ->pluck('division_id')
                ->all();
            $data['selectedDivisionIds'] = $this->normalizedIds([
                ...$data['selectedDivisionIds'],
                ...$departmentDivisionIds,
            ]);
        }

        $this->selectedDivisionIds = $data['selectedDivisionIds'];
        $this->selectedDepartmentIds = $data['selectedDepartmentIds'];
        $this->selectedLeaveTypeIds = $data['selectedLeaveTypeIds'];
        $this->employeeTypeFilter = $data['employeeTypeFilter'];
        $this->syncLegacyScopeIds();

        return $data;
    }

    private function redirectToGeneration(array $data)
    {
        return redirect()->route(PayrollType::generationRouteFor($data['payrollType']), [
            'division_ids' => implode(',', $data['selectedDivisionIds']),
            'department_ids' => implode(',', $data['selectedDepartmentIds'] ?? []),
            'division_id' => $data['selectedDivisionIds'][0] ?? null,
            'department_id' => $data['selectedDepartmentIds'][0] ?? null,
            'payroll_type' => $data['payrollType'],
            'period' => $data['period'],
            'working_days' => $data['workingDays'],
            'gsis_days' => $data['gsisDays'],
            'leave_type_ids' => $this->leaveTypeIdsQueryValue($data['selectedLeaveTypeIds'] ?? []),
            'employee_type' => Employee::employeeTypeQueryValue($data['employeeTypeFilter']),
        ]);
    }

    private function existingGenerationsFor(array $data): array
    {
        $matches = [];
        $selectedDivisionIds = $this->normalizedIds($data['selectedDivisionIds'] ?? []);
        $selectedDepartmentIds = $this->normalizedIds($data['selectedDepartmentIds'] ?? []);
        $selectedDivisionDepartmentIds = $this->departmentIdsForDivisions($selectedDivisionIds);

        $drafts = PayrollGenerationDraft::query()
            ->where('payroll_period', (string) $data['period'])
            ->where('payroll_type_code', (string) $data['payrollType'])
            ->latest('saved_at')
            ->get()
            ->filter(fn (PayrollGenerationDraft $draft) => $this->draftOverlapsConfiguration(
                $draft,
                $selectedDivisionIds,
                $selectedDepartmentIds,
                $selectedDivisionDepartmentIds,
            ))
            ->take(5);

        foreach ($drafts as $draft) {
            $matches[] = [
                'type' => 'draft',
                'label' => 'Saved draft',
                'description' => $this->draftOverlapDescription($draft, $selectedDivisionIds, $selectedDepartmentIds),
                'date' => $draft->saved_at?->format('M d, Y g:i A'),
                'by' => $draft->saved_by,
            ];
        }

        $batches = PayrollBatch::query()
            ->where('payroll_period', (string) $data['period'])
            ->where('payroll_type_code', (string) $data['payrollType'])
            ->latest('snapshot_created_at')
            ->get()
            ->filter(fn (PayrollBatch $batch) => $this->batchOverlapsConfiguration(
                $batch,
                $selectedDivisionIds,
                $selectedDepartmentIds,
                $selectedDivisionDepartmentIds,
            ))
            ->take(5);

        foreach ($batches as $batch) {
            $matches[] = [
                'type' => 'finalized',
                'label' => 'Finalized payroll',
                'description' => $this->batchOverlapDescription($batch, $selectedDivisionIds, $selectedDepartmentIds),
                'date' => $batch->snapshot_created_at?->format('M d, Y g:i A'),
                'by' => $batch->generated_by,
            ];
        }

        if ($selectedDepartmentIds === [] || count($selectedDepartmentIds) !== 1 || $batches->isNotEmpty()) {
            return $matches;
        }

        $legacyBatch = PayrollBatch::query()
            ->whereNull('division_id')
            ->where('department_id', (int) $selectedDepartmentIds[0])
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

    private function batchOverlapsConfiguration(PayrollBatch $batch, array $selectedDivisionIds, array $selectedDepartmentIds, array $selectedDivisionDepartmentIds): bool
    {
        $batchDivisionId = (int) ($batch->division_id ?? 0);
        $batchDepartmentId = (int) ($batch->department_id ?? 0);

        if ($batchDepartmentId > 0 && in_array($batchDepartmentId, $selectedDepartmentIds, true)) {
            return true;
        }

        if ($batchDepartmentId === 0 && $batchDivisionId > 0 && in_array($batchDivisionId, $selectedDivisionIds, true)) {
            return true;
        }

        if ($selectedDepartmentIds !== [] && $batchDepartmentId === 0 && $batchDivisionId > 0) {
            return Department::query()
                ->whereIn('department_id', $selectedDepartmentIds)
                ->where('division_id', $batchDivisionId)
                ->exists();
        }

        return $batchDepartmentId > 0 && in_array($batchDepartmentId, $selectedDivisionDepartmentIds, true);
    }

    private function draftOverlapsConfiguration(PayrollGenerationDraft $draft, array $selectedDivisionIds, array $selectedDepartmentIds, array $selectedDivisionDepartmentIds): bool
    {
        $state = $draft->state_json ?? [];
        $draftDivisionIds = $this->normalizedIds($state['selected_division_ids'] ?? ($draft->division_id ? [$draft->division_id] : []));
        $draftDepartmentIds = $this->normalizedIds($state['selected_department_ids'] ?? ($draft->department_id ? [$draft->department_id] : []));

        if (array_intersect($draftDepartmentIds, $selectedDepartmentIds) !== []) {
            return true;
        }

        if ($draftDepartmentIds === [] && array_intersect($draftDivisionIds, $selectedDivisionIds) !== []) {
            return true;
        }

        if ($selectedDepartmentIds !== [] && $draftDepartmentIds === [] && $draftDivisionIds !== []) {
            return Department::query()
                ->whereIn('department_id', $selectedDepartmentIds)
                ->whereIn('division_id', $draftDivisionIds)
                ->exists();
        }

        return array_intersect($draftDepartmentIds, $selectedDivisionDepartmentIds) !== [];
    }

    private function batchOverlapDescription(PayrollBatch $batch, array $selectedDivisionIds, array $selectedDepartmentIds): string
    {
        if ($batch->department_id && in_array((int) $batch->department_id, $selectedDepartmentIds, true)) {
            return 'A finalized payroll already exists for this department/office in the same period and payroll type.';
        }

        if (! $batch->department_id && $batch->division_id && in_array((int) $batch->division_id, $selectedDivisionIds, true)) {
            return 'A finalized payroll already exists for this division in the same period and payroll type.';
        }

        return 'A finalized payroll already exists for an overlapping department/office or division in the same period and payroll type.';
    }

    private function draftOverlapDescription(PayrollGenerationDraft $draft, array $selectedDivisionIds, array $selectedDepartmentIds): string
    {
        $state = $draft->state_json ?? [];
        $draftDivisionIds = $this->normalizedIds($state['selected_division_ids'] ?? ($draft->division_id ? [$draft->division_id] : []));
        $draftDepartmentIds = $this->normalizedIds($state['selected_department_ids'] ?? ($draft->department_id ? [$draft->department_id] : []));

        if (array_intersect($draftDepartmentIds, $selectedDepartmentIds) !== []) {
            return 'A saved draft already exists for this department/office in the same period and payroll type.';
        }

        if ($draftDepartmentIds === [] && array_intersect($draftDivisionIds, $selectedDivisionIds) !== []) {
            return 'A saved draft already exists for this division in the same period and payroll type.';
        }

        return 'A saved draft already exists for an overlapping department/office or division in the same period and payroll type.';
    }

    private function departmentIdsForDivisions(array $divisionIds): array
    {
        if ($divisionIds === []) {
            return [];
        }

        return Department::query()
            ->whereIn('division_id', $divisionIds)
            ->pluck('department_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function configurationKeyFor(array $data): string
    {
        return PayrollGenerationDraft::configurationKeyForScope(
            $this->normalizedIds($data['selectedDivisionIds'] ?? []),
            $this->normalizedIds($data['selectedDepartmentIds'] ?? []),
            (string) $data['payrollType'],
            (string) $data['period'],
            (int) $data['workingDays'],
            Employee::employeeTypeQueryValue($data['employeeTypeFilter']),
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

    private function parseIdList(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        return $this->normalizedIds(is_array($value) ? $value : explode(',', (string) $value));
    }

    private function normalizedIds(array $values): array
    {
        return collect($values)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function syncLegacyScopeIds(): void
    {
        $this->selectedDivisionIds = $this->normalizedIds($this->selectedDivisionIds);
        $this->selectedDepartmentIds = $this->normalizedIds($this->selectedDepartmentIds);
        $this->divisionId = $this->selectedDivisionIds[0] ?? null;
        $this->departmentId = $this->selectedDepartmentIds[0] ?? null;
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
