<?php

namespace App\Livewire\Payroll;

use App\Enums\PayrollOperatingMode;
use App\Services\Payroll\ApiCanonicalSyncService;
use App\Services\Payroll\HrisTimekeepingSyncService;
use App\Services\Payroll\HrisApiTimekeepingSyncService;
use App\Services\Payroll\PayrollOperatingModeService;
use App\Models\Payroll\Canonical\Department;
use App\Models\Payroll\Canonical\Division;
use App\Models\Payroll\Canonical\Employee;
use App\Models\Payroll\Canonical\LeaveType;
use App\Models\Payroll\PayrollBatch;
use App\Models\Payroll\PayrollGenerationDraft;
use App\Models\Payroll\PayrollHoliday;
use App\Models\Payroll\PayrollType;
use App\Services\Payroll\HolidayService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class PayrollConfiguration extends Component
{
    private const DEFAULT_UNCHECKED_LEAVE_TYPE_IDS = [14, 15, 16, 20];

    #[\Livewire\Attributes\Locked]
    public array $apiPreparation = [];

    #[\Livewire\Attributes\Locked]
    public string $configurationStep = 'details';

    public string $timekeepingSyncSource = 'api';

    private function configurationSteps(): array
    {
        $steps = ['details' => 'Run details', 'scope' => 'Payroll scope'];
        if (app(PayrollOperatingModeService::class)->current() === PayrollOperatingMode::Standalone) {
            $steps['preparation'] = 'Prepare data';
        }

        return $steps + ['review' => 'Review'];
    }

    public function nextConfigurationStep(): void
    {
        $this->dismissExistingGenerationNotice();
        if ($this->configurationStep === 'details') {
            $this->gsisDays = $this->daysInPayrollMonth($this->period);
            $fields = ['period', 'workingDays', 'gsisDays', 'leavePeriodStart', 'leavePeriodEnd'];
            $this->validate(array_intersect_key($this->configurationRules(), array_flip($fields)));
        } elseif ($this->configurationStep === 'preparation') {
            if ($this->apiPreparation !== [] && count($this->preparationResults()) < count($this->preparationSteps())) {
                $this->addError('sync', 'Complete all four preparation steps before continuing.');

                return;
            }
        } elseif ($this->configurationStep === 'scope') {
            $this->validatedConfiguration();
        }
        $steps = array_keys($this->configurationSteps());
        $index = array_search($this->configurationStep, $steps, true);
        $this->configurationStep = $steps[min((int) $index + 1, count($steps) - 1)];
    }

    public function previousConfigurationStep(): void
    {
        $this->dismissExistingGenerationNotice();
        $steps = array_keys($this->configurationSteps());
        $index = array_search($this->configurationStep, $steps, true);
        $this->configurationStep = $steps[max(0, (int) $index - 1)];
    }

    public function submitConfiguration()
    {
        if ($this->configurationStep !== 'review') {
            $this->nextConfigurationStep();

            return null;
        }

        return $this->proceed();
    }

    private function preparationSteps(): array
    {
        return ApiCanonicalSyncService::STEPS + ['timekeeping' => [
            'title' => 'Timekeeping',
            'description' => 'Sync the previous attendance month through the HRIS API, or choose the HRIS database to copy raw DTR punches.',
        ]];
    }

    private function preparationContext(): string
    {
        return $this->period.'|'.$this->leavePeriodStart.'|'.$this->leavePeriodEnd.'|'.$this->timekeepingSyncSource.'|'.hash('sha256', json_encode($this->syncScope()));
    }

    private function syncScope(): array
    {
        return [
            'division_ids' => $this->normalizedIds($this->selectedDivisionIds),
            'department_ids' => $this->normalizedIds($this->selectedDepartmentIds),
            'employee_types' => Employee::normalizeEmployeeTypes($this->employeeTypeFilter),
        ];
    }

    private function preparationResults(): array
    {
        return ($this->apiPreparation['context'] ?? null) === $this->preparationContext()
            ? ($this->apiPreparation['steps'] ?? []) : [];
    }

    public function canSyncPayrollInputs(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->can('payroll.system.sync') || $user?->can('payroll.generate')
            || $user?->can('payroll.generation.hr') || $user?->can('payroll.generation.accounting'));
    }

    public ?int $divisionId = null;

    public ?int $departmentId = null;

    public array $selectedDivisionIds = [];

    public array $selectedDepartmentIds = [];

    public string $payrollType = PayrollType::CODE_GENERAL;

    public string $period;

    public int $workingDays = 22;

    public int $gsisDays = 30;

    public array $selectedLeaveTypeIds = [];

    public string $leavePeriodStart;

    public string $leavePeriodEnd;

    public array $employeeTypeFilter = [Employee::EMPLOYEE_TYPE_PLANTILLA];

    public bool $showExistingGenerationNotice = false;

    public array $existingGenerations = [];

    public ?string $noticedConfigurationKey = null;

    public ?int $holidayEditingId = null;

    public string $holidayDate = '';

    public string $holidayName = '';

    public string $holidayType = 'REGULAR';

    public string $holidayScope = 'FULL_DAY';

    public bool $holidayIsPaid = true;

    public bool $holidayIsActive = true;

    public function mount(): void
    {
        $userDepartmentId = auth()->user()?->employee?->department_id;
        $userDivisionId = $userDepartmentId
            ? Department::query()->where('external_id', $userDepartmentId)->value('division_external_id')
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
                ->whereIn('external_id', $this->selectedDepartmentIds)
                ->whereIn('division_external_id', $this->selectedDivisionIds)
                ->pluck('external_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $this->syncLegacyScopeIds();
        }

        $this->period = request()->query('period', CarbonImmutable::today()->format('Y-m'));
        $defaultLeaveMonth = CarbonImmutable::createFromFormat('!Y-m', $this->period)->subMonthNoOverflow();
        $this->leavePeriodStart = (string) request()->query('leave_period_start', $defaultLeaveMonth->startOfMonth()->toDateString());
        $this->leavePeriodEnd = (string) request()->query('leave_period_end', $defaultLeaveMonth->endOfMonth()->toDateString());
        $this->workingDays = max(1, min(31, request()->integer('working_days') ?: 22));
        $this->gsisDays = $this->daysInPayrollMonth($this->period);
        $this->selectedLeaveTypeIds = $this->hasExplicitLeaveTypeSelection(request()->query('leave_type_ids'))
            ? $this->parseSelectedLeaveTypeIds(request()->query('leave_type_ids', []))
            : $this->defaultSelectedLeaveTypeIds();

        $this->employeeTypeFilter = Employee::normalizeEmployeeTypes(
            request()->query('employee_type', Employee::EMPLOYEE_TYPE_PLANTILLA)
        );
    }

    public function syncApi(ApiCanonicalSyncService $service, string $step = 'employees'): void
    {
        abort_unless($this->canSyncPayrollInputs(), 403);
        abort_unless(app(PayrollOperatingModeService::class)->current() === PayrollOperatingMode::Standalone, 422);
        $this->resetValidation('sync');
        $steps = array_keys($this->preparationSteps());
        $index = array_search($step, $steps, true);
        abort_if($index === false, 422);
        $results = $this->preparationResults();
        if ($index > 0 && ! isset($results[$steps[$index - 1]])) {
            $this->addError('sync', 'Complete the preceding synchronization step first.');

            return;
        }
        $this->validate([
            'period' => ['required', 'date_format:Y-m'],
            'leavePeriodStart' => ['required', 'date_format:Y-m-d'],
            'leavePeriodEnd' => ['required', 'date_format:Y-m-d', 'after_or_equal:leavePeriodStart'],
        ]);
        $scopeFields = ['selectedDivisionIds', 'selectedDivisionIds.*', 'selectedDepartmentIds', 'selectedDepartmentIds.*', 'employeeTypeFilter', 'employeeTypeFilter.*'];
        $this->validate(array_intersect_key($this->configurationRules(), array_flip($scopeFields)));
        $this->validate(['timekeepingSyncSource' => ['required', Rule::in(['api', 'hris_db'])]]);
        try {
            $batch = $step === 'timekeeping'
                ? ($this->timekeepingSyncSource === 'api'
                    ? app(HrisApiTimekeepingSyncService::class)->sync($this->period, auth()->user()?->emp_id, $this->syncScope())
                    : app(HrisTimekeepingSyncService::class)->sync($this->period, auth()->user()?->emp_id, $this->syncScope()))
                : $service->sync($this->period, auth()->user()?->emp_id, $this->leavePeriodStart, $this->leavePeriodEnd, $step, $this->syncScope());
        } catch (\Illuminate\Database\QueryException $e) {
            $this->addError('sync', $step === 'timekeeping'
                ? 'Timekeeping synchronization failed. Check the selected attendance source and payroll database schema. Existing attendance was retained.'
                : 'Payroll database synchronization failed. Active data was not replaced.');

            return;
        } catch (\RuntimeException $e) {
            $this->addError('sync', $e->getMessage());

            return;
        }
        foreach (array_slice($steps, $index) as $laterStep) {
            unset($results[$laterStep]);
        }
        $results[$step] = ['batch' => $batch->id, 'counts' => $batch->statistics ?? []];
        $this->apiPreparation = ['context' => $this->preparationContext(), 'steps' => $results];
        $this->dismissExistingGenerationNotice();
        session()->flash('api_sync_status', $this->preparationSteps()[$step]['title']." synchronized in batch #{$batch->id}.");
    }

    public function proceed()
    {
        if (app(PayrollOperatingModeService::class)->current() === PayrollOperatingMode::Standalone
            && $this->apiPreparation !== [] && count($this->preparationResults()) < count($this->preparationSteps())) {
            $this->configurationStep = 'preparation';
            $this->addError('sync', 'Complete all four preparation steps before proceeding to payroll generation.');

            return null;
        }
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
        if (app(PayrollOperatingModeService::class)->current() === PayrollOperatingMode::Standalone
            && $this->apiPreparation !== [] && count($this->preparationResults()) < count($this->preparationSteps())) {
            return $this->proceed();
        }
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

    public function saveHoliday(HolidayService $service): void
    {
        abort_unless($this->canManageHolidayCalendar(), 403);
        $data = $this->validate([
            'holidayEditingId' => ['nullable', 'integer'],
            'holidayDate' => ['required', 'date_format:Y-m-d'],
            'holidayName' => ['required', 'string', 'max:255'],
            'holidayType' => ['required', Rule::in(['REGULAR', 'SPECIAL', 'WORK_SUSPENSION'])],
            'holidayScope' => ['required', Rule::in(['FULL_DAY', 'FIRST_HALF', 'SECOND_HALF'])],
            'holidayIsPaid' => ['boolean'],
            'holidayIsActive' => ['boolean'],
        ]);

        if (! str_starts_with($data['holidayDate'], $this->period.'-')) {
            throw ValidationException::withMessages([
                'holidayDate' => 'Choose a date within the selected payroll month.',
            ]);
        }
        if ($service->duplicateExists($data['holidayDate'], (int) ($data['holidayEditingId'] ?? 0))) {
            throw ValidationException::withMessages([
                'holidayDate' => 'A holiday or work suspension already exists for this date.',
            ]);
        }

        $service->save([
            'holiday_date' => $data['holidayDate'],
            'name' => $data['holidayName'],
            'holiday_type' => $data['holidayType'],
            'holiday_scope' => $data['holidayScope'],
            'label_code' => 'HOLIDAY',
            'is_paid' => $data['holidayIsPaid'],
            'is_active' => $data['holidayIsActive'],
        ], (int) ($data['holidayEditingId'] ?? 0));

        $this->resetHolidayForm();
        $this->dispatch('erp-overlay-close', name: 'payroll-holiday');
        session()->flash('holiday_status', 'Holiday calendar updated.');
    }

    private function resetHolidayForm(): void
    {
        $this->holidayEditingId = null;
        $this->holidayDate = '';
        $this->holidayName = '';
        $this->holidayType = 'REGULAR';
        $this->holidayScope = 'FULL_DAY';
        $this->holidayIsPaid = true;
        $this->holidayIsActive = true;
        $this->resetValidation([
            'holidayDate',
            'holidayName',
            'holidayType',
            'holidayScope',
            'holidayIsPaid',
            'holidayIsActive',
        ]);
    }

    private function canManageHolidayCalendar(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->can('timekeeping.manage') || $user?->can('payroll.generation.hr'));
    }

    private function configurationRules(): array
    {
        return [
            'selectedDivisionIds' => ['required', 'array', 'min:1'],
            'selectedDivisionIds.*' => ['integer', 'exists:payroll.payroll_canonical_divisions,external_id'],
            'selectedDepartmentIds' => ['array'],
            'selectedDepartmentIds.*' => ['integer', Rule::exists('payroll.payroll_canonical_departments', 'external_id')->whereIn('division_external_id', $this->selectedDivisionIds)],
            'payrollType' => ['required', Rule::exists('payroll.payroll_types', 'code')->where('is_active', true)],
            'period' => ['required', 'date_format:Y-m'],
            'workingDays' => ['required', 'integer', 'min:1', 'max:31'],
            'gsisDays' => ['required', 'integer', 'min:0', 'max:31'],
            'selectedLeaveTypeIds' => ['array'],
            'selectedLeaveTypeIds.*' => ['integer', 'exists:payroll.payroll_canonical_leave_types,external_id'],
            'leavePeriodStart' => ['required', 'date'],
            'leavePeriodEnd' => ['required', 'date', 'after_or_equal:leavePeriodStart'],
            'employeeTypeFilter' => ['required', 'array', 'min:1'],
            'employeeTypeFilter.*' => ['required', Rule::in(array_keys(Employee::employeeTypeOptions()))],
        ];
    }

    private function validatedConfiguration(): array
    {
        $this->gsisDays = $this->daysInPayrollMonth($this->period);
        try {
            $data = $this->validate($this->configurationRules());
        } catch (ValidationException $e) {
            $detailsFields = ['period', 'workingDays', 'gsisDays', 'leavePeriodStart', 'leavePeriodEnd'];
            $this->configurationStep = array_intersect(array_keys($e->errors()), $detailsFields) ? 'details' : 'scope';

            throw $e;
        }

        $data['selectedDivisionIds'] = $this->normalizedIds($data['selectedDivisionIds'] ?? []);
        $data['selectedDepartmentIds'] = $this->normalizedIds($data['selectedDepartmentIds'] ?? []);
        $data['selectedLeaveTypeIds'] = $this->normalizedLeaveTypeIds($data['selectedLeaveTypeIds'] ?? []);
        $data['employeeTypeFilter'] = Employee::normalizeEmployeeTypes($data['employeeTypeFilter'] ?? []);

        if ($data['selectedDepartmentIds'] !== []) {
            $departmentDivisionIds = Department::query()
                ->whereIn('external_id', $data['selectedDepartmentIds'])
                ->pluck('division_external_id')
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

    public function updatedSelectedDivisionIds(): void
    {
        $this->selectedDivisionIds = $this->normalizedIds($this->selectedDivisionIds);
        $this->selectedDepartmentIds = Department::query()
            ->whereIn('division_external_id', $this->selectedDivisionIds)
            ->whereIn('external_id', $this->selectedDepartmentIds)
            ->pluck('external_id')->map(fn ($id) => (int) $id)->all();
        $this->syncLegacyScopeIds();
        $this->dismissExistingGenerationNotice();
    }

    public function updatedPeriod(): void
    {
        $this->gsisDays = $this->daysInPayrollMonth($this->period);
    }

    private function daysInPayrollMonth(string $period): int
    {
        try {
            return CarbonImmutable::createFromFormat('!Y-m', $period)->daysInMonth;
        } catch (\Throwable) {
            return 30;
        }
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
            'leave_period_start' => $data['leavePeriodStart'],
            'leave_period_end' => $data['leavePeriodEnd'],
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
                ->whereIn('external_id', $selectedDepartmentIds)
                ->where('division_external_id', $batchDivisionId)
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
                ->whereIn('external_id', $selectedDepartmentIds)
                ->whereIn('division_external_id', $draftDivisionIds)
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
            ->whereIn('division_external_id', $divisionIds)
            ->pluck('external_id')
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
            (string) $data['leavePeriodStart'],
            (string) $data['leavePeriodEnd'],
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
            ->pluck('external_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function render()
    {
        try {
            $month = CarbonImmutable::createFromFormat('!Y-m', $this->period);
            $periodLabel = $month->format('F Y');
            $holidays = Schema::connection('payroll')->hasTable('payroll_holidays')
                ? PayrollHoliday::query()
                    ->whereBetween('holiday_date', [$month->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString()])
                    ->orderBy('holiday_date')
                    ->get()
                : collect();
        } catch (\Throwable) {
            $periodLabel = 'the selected month';
            $holidays = collect();
        }

        return view('livewire.payroll.payroll-configuration', [
            'divisions' => Division::query()->orderBy('name')->get(),
            'payrollTypes' => PayrollType::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'departments' => Department::query()
                ->whereIn('division_external_id', $this->selectedDivisionIds)
                ->orderBy('name')
                ->get(),
            'employeeTypeOptions' => Employee::employeeTypeOptions(),
            'leaveTypes' => LeaveType::query()
                ->orderBy('name')
                ->orderBy('external_id')
                ->get(),
            'holidays' => $holidays,
            'periodLabel' => $periodLabel,
            'canManageHolidays' => $this->canManageHolidayCalendar(),
            'isStandalone' => app(PayrollOperatingModeService::class)->current() === PayrollOperatingMode::Standalone,
            'configurationSteps' => $this->configurationSteps(),
            'apiPreparationSteps' => $this->preparationSteps(),
            'apiPreparationResults' => $this->preparationResults(),
            'canSyncPayrollInputs' => $this->canSyncPayrollInputs(),
        ]);
    }
}
