<?php

namespace App\Livewire\Payroll;

use App\Models\Hris\Employee;
use App\Models\Hris\LeaveType;
use Livewire\Component;
use Livewire\WithPagination;

use App\Models\Payroll\PayrollBatch;
use App\Models\Payroll\PayrollBatchRecord;
use App\Models\Payroll\PayrollGenerationDraft;
use App\Models\Payroll\PayrollType;

class PayrollHistory extends Component
{
    use WithPagination;

    public string $activeTab = 'finalized';

    public ?string $period = null;

    public string $payrollTypeFilter = '';

    public string $employeeTypeFilter = '';

    public ?int $selectedBatchId = null;

    public string $search = '';

    public function updated($property): void
    {
        if (in_array($property, ['period', 'payrollTypeFilter', 'employeeTypeFilter', 'search'], true)) {
            $this->resetPage();
            $this->resetPage('draftsPage');
        }
    }

    public function showTab(string $tab): void
    {
        if (! in_array($tab, ['finalized', 'drafts'], true)) {
            return;
        }

        $this->activeTab = $tab;
        $this->selectedBatchId = null;
        $this->resetPage();
        $this->resetPage('draftsPage');
    }

    public function selectBatch(int $batchId): void
    {
        $this->selectedBatchId = $batchId;
    }

    public function getSelectedBatchRecordsProperty()
    {
        if (! $this->selectedBatchId) {
            return collect();
        }

        return PayrollBatchRecord::query()
            ->where('payroll_batch_id', $this->selectedBatchId)
            ->get();
    }

    public function getSelectedBatchProperty(): ?PayrollBatch
    {
        if (! $this->selectedBatchId) {
            return null;
        }

        return PayrollBatch::query()->find($this->selectedBatchId);
    }

    public function getBatchesProperty()
    {
        return PayrollBatch::query()

            ->when(
                $this->period,
                fn($q) =>
                $q->where('payroll_period', $this->period)
            )

            ->when(
                $this->payrollTypeFilter,
                fn($q) =>
                $q->where('payroll_type_code', $this->payrollTypeFilter)
            )

            ->when(
                $this->employeeTypeFilter,
                fn($q) =>
                $q->where('employee_type', $this->employeeTypeFilter)
            )

            ->when(
                $this->search,
                fn($q) =>
                $q->where(function ($query) {
                    $query
                        ->where('payroll_period', 'like', '%' . $this->search . '%')
                        ->orWhere('payroll_type', 'like', '%' . $this->search . '%')
                        ->orWhere('payroll_type_code', 'like', '%' . $this->search . '%')
                        ->orWhere('generated_by', 'like', '%' . $this->search . '%');
                })
            )

            ->latest('snapshot_created_at')

            ->paginate(10);
    }

    public function getDraftsProperty()
    {
        return PayrollGenerationDraft::query()
            ->when(
                $this->period,
                fn ($q) => $q->where('payroll_period', $this->period)
            )
            ->when(
                $this->payrollTypeFilter,
                fn ($q) => $q->where('payroll_type_code', $this->payrollTypeFilter)
            )
            ->when(
                $this->employeeTypeFilter,
                fn ($q) => $q->where('employee_type', $this->employeeTypeFilter)
            )
            ->when(
                $this->search,
                fn ($q) => $q->where(function ($query) {
                    $query
                        ->where('payroll_period', 'like', '%' . $this->search . '%')
                        ->orWhere('payroll_type_code', 'like', '%' . $this->search . '%')
                        ->orWhere('saved_by', 'like', '%' . $this->search . '%');
                })
            )
            ->latest('saved_at')
            ->paginate(10, ['*'], 'draftsPage');
    }

    public function continueDraft(int $draftId)
    {
        $draft = PayrollGenerationDraft::query()->findOrFail($draftId);
        $state = $draft->state_json ?? [];
        $divisionIds = $this->normalizedDraftIds($state['selected_division_ids'] ?? ($draft->division_id ? [$draft->division_id] : []));
        $departmentIds = $this->normalizedDraftIds($state['selected_department_ids'] ?? ($draft->department_id ? [$draft->department_id] : []));
        $payrollTypeCode = $draft->payroll_type_code ?: PayrollType::CODE_GENERAL;

        return redirect()->route(PayrollType::generationRouteFor($payrollTypeCode), [
            'division_ids' => implode(',', $divisionIds),
            'department_ids' => implode(',', $departmentIds),
            'division_id' => $divisionIds[0] ?? null,
            'department_id' => $departmentIds[0] ?? null,
            'payroll_type' => $payrollTypeCode,
            'period' => $draft->payroll_period,
            'working_days' => $draft->working_days,
            'gsis_days' => $draft->gsis_days ?? 30,
            'leave_type_ids' => $this->leaveTypeIdsQueryValue($draft->included_leave_type_ids ?? []),
            'employee_type' => $draft->employee_type,
        ]);
    }

    public function deleteDraft(int $draftId): void
    {
        PayrollGenerationDraft::query()->whereKey($draftId)->delete();
    }

    public function generationConfigurationFor(PayrollBatch $batch): array
    {
        $leaveTypeIds = $batch->included_leave_type_ids;

        return [
            'payroll_type' => $batch->payroll_type,
            'payroll_type_code' => $batch->payroll_type_code ?: null,
            'working_days' => $batch->working_days,
            'gsis_days' => $batch->gsis_days,
            'employee_type' => $this->employeeTypeLabel($batch->employee_type),
            'leave_type_ids_recorded' => is_array($leaveTypeIds),
            'leave_types' => $this->includedLeaveTypeLabels(is_array($leaveTypeIds) ? $leaveTypeIds : null),
        ];
    }

    public function draftConfigurationFor(PayrollGenerationDraft $draft): array
    {
        $state = $draft->state_json ?? [];
        $divisionIds = $this->normalizedDraftIds($state['selected_division_ids'] ?? ($draft->division_id ? [$draft->division_id] : []));
        $departmentIds = $this->normalizedDraftIds($state['selected_department_ids'] ?? ($draft->department_id ? [$draft->department_id] : []));
        $leaveTypeIds = $draft->included_leave_type_ids;
        $currentStep = PayrollGenerationDraft::restoredWizardStep((int) $draft->current_step, $state);

        return [
            'scope' => $this->scopeLabel($divisionIds, $departmentIds),
            'payroll_type_code' => $draft->payroll_type_code ?: null,
            'working_days' => $draft->working_days,
            'gsis_days' => $draft->gsis_days,
            'employee_type' => $this->employeeTypeLabel($draft->employee_type),
            'current_step' => $currentStep,
            'step_count' => PayrollGenerationDraft::currentWizardStepCount(),
            'step_label' => PayrollGenerationDraft::wizardStepLabel($currentStep),
            'leave_types' => $this->includedLeaveTypeLabels(is_array($leaveTypeIds) ? $leaveTypeIds : null),
        ];
    }

    public function render()
    {
        $batches = $this->batches;
        $drafts = $this->drafts;
        $records = $this->selectedBatchRecords;
        $selectedBatch = $this->selectedBatch;
        $selectedBatchConfiguration = $selectedBatch
            ? $this->generationConfigurationFor($selectedBatch)
            : null;
        $batchConfigurations = $batches
            ->getCollection()
            ->mapWithKeys(fn (PayrollBatch $batch) => [
                $batch->id => $this->generationConfigurationFor($batch),
            ])
            ->all();
        $draftConfigurations = $drafts
            ->getCollection()
            ->mapWithKeys(fn (PayrollGenerationDraft $draft) => [
                $draft->id => $this->draftConfigurationFor($draft),
            ])
            ->all();

        $compensationColumns = [];

        $loanColumns = [];

        foreach ($records as $record) {

            $snapshot = $record->snapshot_json ?? [];

            foreach (($snapshot['earnings']['compensations'] ?? []) as $key => $comp) {

                $compensationColumns[$key] = $comp['name'];
            }

            foreach (($snapshot['loan_deductions']['columns'] ?? []) as $key => $value) {

                $loanColumns[$key] = strtoupper(str_replace('_', ' ', $key));
            }
        }

        return view('livewire.payroll.payroll-history', [
            'activeTab' => $this->activeTab,
            'batches' => $batches,
            'drafts' => $drafts,
            'records' => $records,
            'selectedBatch' => $selectedBatch,
            'selectedBatchConfiguration' => $selectedBatchConfiguration,
            'batchConfigurations' => $batchConfigurations,
            'draftConfigurations' => $draftConfigurations,
            'compensationColumns' => $compensationColumns,
            'loanColumns' => $loanColumns,
            'payrollTypeOptions' => $this->payrollTypeOptions(),
            'employeeTypeOptions' => Employee::employeeTypeOptions(),
        ]);
    }

    public function clearFilters(): void
    {
        $this->period = null;
        $this->payrollTypeFilter = '';
        $this->employeeTypeFilter = '';
        $this->search = '';
        $this->resetPage();
        $this->resetPage('draftsPage');
    }

    private function employeeTypeLabel(?string $employeeType): string
    {
        if (! $employeeType) {
            return 'Not recorded';
        }

        return Employee::employeeTypeLabel($employeeType);
    }

    private function payrollTypeOptions(): array
    {
        return [
            PayrollType::CODE_GENERAL => 'General',
            PayrollType::CODE_HAZARD => 'Hazard',
            PayrollType::CODE_MEDICARE => 'Medicare',
        ];
    }

    private function includedLeaveTypeLabels(?array $leaveTypeIds): array
    {
        if ($leaveTypeIds === null) {
            return ['Not recorded'];
        }

        $ids = collect($leaveTypeIds)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($ids->isEmpty()) {
            return ['None'];
        }

        $labels = LeaveType::query()
            ->whereIn('leave_type_id', $ids->all())
            ->pluck('leave_name', 'leave_type_id');

        return $ids
            ->map(fn (int $id) => $labels[$id] ?? "Leave #{$id}")
            ->all();
    }

    private function normalizedDraftIds(mixed $values): array
    {
        return collect(is_array($values) ? $values : [$values])
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function leaveTypeIdsQueryValue(array $values): string
    {
        $ids = collect($values)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $ids === [] ? 'none' : implode(',', $ids);
    }

    private function scopeLabel(array $divisionIds, array $departmentIds): string
    {
        if ($departmentIds !== []) {
            $departments = \App\Models\Hris\Department::query()
                ->whereIn('department_id', $departmentIds)
                ->pluck('department')
                ->all();

            return $departments === []
                ? count($departmentIds) . ' department(s)'
                : implode(', ', $departments);
        }

        if ($divisionIds !== []) {
            $divisions = \App\Models\Hris\Division::query()
                ->whereIn('division_id', $divisionIds)
                ->pluck('division')
                ->all();

            return $divisions === []
                ? count($divisionIds) . ' division(s)'
                : implode(', ', $divisions);
        }

        return 'Not recorded';
    }
}
