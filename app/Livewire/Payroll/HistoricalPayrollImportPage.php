<?php

namespace App\Livewire\Payroll;

use App\Models\Hris\Department;
use App\Models\Hris\Division;
use App\Models\Hris\Employee;
use App\Models\Payroll\HistoricalPayrollImport;
use App\Models\Payroll\HistoricalPayrollImportRow;
use App\Models\Payroll\HistoricalPayrollImportSheet;
use App\Services\Payroll\HistoricalPayrollImportService;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class HistoricalPayrollImportPage extends Component
{
    use WithFileUploads;
    use WithPagination;

    public $file;

    public string $period = '';

    public string $payrollType = 'general';

    public int $workflowStep = 1;

    public ?int $importId = null;

    public ?int $selectedSheetId = null;

    public array $sheetMappings = [];

    public array $organizationMappings = [];

    public array $reviewedOrganizationSheetIds = [];

    public string $selectedSourceDivision = '';

    public array $employeeMappings = [];

    public string $comparisonFilter = 'all';

    public string $confirmation = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('payroll.generation.hr') || auth()->user()?->can('payroll.generation.accounting'), 403);
        $this->period = now()->subMonth()->format('Y-m');
        if (request()->integer('import_id')) {
            $this->resumeImport(request()->integer('import_id'));
        }
    }

    public function preview(HistoricalPayrollImportService $service): void
    {
        $data = $this->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xlsm,xls', 'max:102400'],
            'period' => ['required', 'date_format:Y-m'],
            'payrollType' => ['required', 'in:general,hazard,medicare'],
        ]);
        $reportProgress = function (int $percent, string $label): void {
            $percent = max(0, min(100, $percent));
            $this->stream(to: 'import-processing-percent', content: $percent.'%', replace: true);
            $this->stream(to: 'import-processing-label', content: e($label), replace: true);
            $this->stream(to: 'import-processing-bar', content: '<span class="block h-full rounded-full bg-indigo-600 transition-all" style="width: '.$percent.'%"></span>', replace: true);
        };
        $import = $service->stage($this->file->getRealPath(), $this->file->getClientOriginalName(), $data['period'], $data['payrollType'], auth()->user()?->emp_id, $reportProgress);
        $reportProgress(100, 'Preview ready');
        $this->importId = $import->id;
        $this->selectedSheetId = $import->sheets->first()?->id;
        $this->file = null;
        $this->loadMappings($import);
        $this->workflowStep = 2;
        $this->selectInitialSourceDivision();
        $this->persistWorkflowState();
        session()->flash('status', "{$import->sheet_count} payroll sheets and {$import->row_count} employee rows staged. No payroll history has been changed.");
    }

    public function clearFile(): void
    {
        $this->file = null;
        $this->resetValidation('file');
    }

    public function selectSheet(int $sheetId): void
    {
        $this->sheet($sheetId);
        $this->selectedSheetId = $sheetId;
        $this->selectInitialSourceDivision();
        $this->resetPage();
        $this->persistWorkflowState();
    }

    public function resumeImport(int $importId): void
    {
        $import = HistoricalPayrollImport::query()->with('sheets')->findOrFail($importId);
        $this->importId = $import->id;
        $this->period = $import->payroll_period;
        $this->payrollType = $import->payroll_type_code;
        $this->loadMappings($import);
        $state = $import->workflow_state ?? [];
        $this->reviewedOrganizationSheetIds = collect($state['reviewed_organization_sheet_ids'] ?? [])
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
        $savedSheetId = (int) ($state['selected_sheet_id'] ?? 0);
        $this->selectedSheetId = $import->sheets->contains('id', $savedSheetId)
            ? $savedSheetId
            : $import->sheets->first()?->id;
        $this->workflowStep = $import->status === 'applied'
            ? 5
            : max(2, min(5, (int) ($state['workflow_step'] ?? ($import->comparison_drafts ? 4 : 2))));
        $this->selectInitialSourceDivision();
        $this->resetPage();
        session()->flash('status', $import->status === 'applied'
            ? 'Finalized historical payroll import opened in read-only mode.'
            : 'Staged historical payroll import resumed. Your saved workbook and review progress were restored.');
    }

    public function goToWorkflowStep(int $step): void
    {
        $maximum = $this->importId ? 5 : 1;
        $minimum = $this->importId ? 2 : 1;
        $this->workflowStep = max($minimum, min($maximum, $step));
        $this->resetPage();
        $this->persistWorkflowState();
    }

    public function nextWorkflowStep(HistoricalPayrollImportService $service): void
    {
        if ($this->workflowStep === 2) {
            $import = $this->import()->load('sheets');
            foreach ($import->sheets as $sheet) {
                $service->setSheetIncluded($sheet, (bool) ($this->sheetMappings[$sheet->id]['included'] ?? false));
            }
            $firstIncluded = $import->sheets->first(fn ($sheet) => (bool) ($this->sheetMappings[$sheet->id]['included'] ?? false));
            if (! $firstIncluded) {
                $this->addError('sheets', 'Include at least one payroll sheet before continuing.');

                return;
            }
            $includedSheetIds = $import->sheets
                ->filter(fn ($sheet) => (bool) ($this->sheetMappings[$sheet->id]['included'] ?? false))
                ->pluck('id')->map(fn ($id) => (int) $id)->all();
            $this->reviewedOrganizationSheetIds = array_values(array_intersect($this->reviewedOrganizationSheetIds, $includedSheetIds));
            $this->selectedSheetId = $firstIncluded->id;
            $this->selectInitialSourceDivision();
        }
        if ($this->workflowStep === 3) {
            $import = $this->import()->load('sheets');
            $includedSheets = $import->sheets->where('included', true)->values();
            $sheet = $includedSheets->firstWhere('id', $this->selectedSheetId) ?? $includedSheets->first();
            if (! $sheet || ! $this->saveReviewedOrganizationSheet($sheet, $service)) {
                return;
            }
            $nextSheet = $includedSheets->first(fn ($candidate) => ! in_array($candidate->id, $this->reviewedOrganizationSheetIds, true));
            if ($nextSheet) {
                $this->selectedSheetId = $nextSheet->id;
                $this->selectInitialSourceDivision();
                $this->resetPage();
                session()->flash('status', "{$sheet->sheet_name} reviewed. Continue with {$nextSheet->sheet_name}.");
                $this->persistWorkflowState();

                return;
            }
            if ($import->payroll_type_code === 'general') {
                $service->createComparisonDrafts($import->fresh('sheets'), auth()->user()?->emp_id);
                session()->flash('status', 'Organization mappings saved. Workbook-configured comparison drafts are ready for each included worksheet.');
            }
        }
        $this->goToWorkflowStep($this->workflowStep + 1);
    }

    public function previousWorkflowStep(): void
    {
        $this->goToWorkflowStep($this->workflowStep - 1);
    }

    public function saveSheetMapping(int $sheetId, HistoricalPayrollImportService $service): void
    {
        $sheet = $this->sheet($sheetId);
        $mapping = $this->sheetMappings[$sheetId] ?? [];
        $data = validator($mapping, ['included' => ['nullable', 'boolean']])->validate();
        $service->setSheetIncluded($sheet, (bool) ($data['included'] ?? false));
        session()->flash('status', "{$sheet->sheet_name} inclusion updated.");
    }

    public function saveOrganizationMapping(int $sheetId, string $organizationKey, HistoricalPayrollImportService $service): void
    {
        $sheet = $this->sheet($sheetId);
        $mapping = $this->organizationMappings[$sheetId][$organizationKey] ?? [];
        $data = validator($mapping, [
            'included' => ['nullable', 'boolean'],
            'division_id' => ['nullable', 'integer', 'exists:hris.tbl_division,division_id'],
            'department_id' => ['nullable', 'integer', 'exists:hris.tbl_department,department_id'],
        ])->validate();
        $departmentId = filled($data['department_id'] ?? null) ? (int) $data['department_id'] : null;
        $divisionId = filled($data['division_id'] ?? null) ? (int) $data['division_id'] : null;
        if ($departmentId) {
            $department = Department::query()->findOrFail($departmentId);
            $divisionId = (int) $department->division_id;
            $this->organizationMappings[$sheetId][$organizationKey]['division_id'] = $divisionId;
        }
        $service->mapOrganization($sheet, $organizationKey, $divisionId, $departmentId, (bool) ($data['included'] ?? false));
        session()->flash('status', 'Organization mapping saved and matching refreshed.');
    }

    public function saveAllOrganizationMappings(int $sheetId, HistoricalPayrollImportService $service): void
    {
        $sheet = $this->sheet($sheetId);
        if (! $this->saveReviewedOrganizationSheet($sheet, $service)) {
            return;
        }
        session()->flash('status', "{$sheet->sheet_name} organization mappings reviewed.");
    }

    private function saveReviewedOrganizationSheet(HistoricalPayrollImportSheet $sheet, HistoricalPayrollImportService $service): bool
    {
        $updates = [];
        foreach ($this->organizationMappings[$sheet->id] ?? [] as $organizationKey => $mapping) {
            $departmentId = filled($mapping['department_id'] ?? null) ? (int) $mapping['department_id'] : null;
            $divisionId = filled($mapping['division_id'] ?? null) ? (int) $mapping['division_id'] : null;
            if ($departmentId) {
                $department = Department::query()->findOrFail($departmentId);
                $divisionId = (int) $department->division_id;
                $this->organizationMappings[$sheet->id][$organizationKey]['division_id'] = $divisionId;
            }
            if (($mapping['included'] ?? false) && ! $divisionId) {
                $this->selectedSheetId = $sheet->id;
                $this->selectedSourceDivision = $this->sourceDivisionLabel($mapping);
                $this->addError('organizations', 'Map every included workbook organization to an HRIS division before confirming this sheet.');

                return false;
            }
            $updates[$organizationKey] = ['division_id' => $divisionId, 'department_id' => $departmentId, 'included' => (bool) ($mapping['included'] ?? false)];
        }
        $service->mapOrganizations($sheet, $updates);
        $this->reviewedOrganizationSheetIds = collect([...$this->reviewedOrganizationSheetIds, $sheet->id])
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
        $this->persistWorkflowState();

        return true;
    }

    public function mapEmployee(int $rowId, HistoricalPayrollImportService $service): void
    {
        $row = HistoricalPayrollImportRow::query()->whereKey($rowId)
            ->whereHas('sheet', fn ($query) => $query->where('historical_payroll_import_id', $this->importId))->firstOrFail();
        $employeeId = trim((string) ($this->employeeMappings[$rowId] ?? ''));
        validator(['employee_id' => $employeeId], ['employee_id' => ['required', 'string', 'exists:hris.tbl_employee,emp_id']])->validate();
        $service->mapEmployee($row, $employeeId);
        unset($this->employeeMappings[$rowId]);
        session()->flash('status', "Workbook row {$row->source_row} mapped to employee {$employeeId}.");
    }

    public function refreshComparison(HistoricalPayrollImportService $service): void
    {
        $sheet = $this->sheet($this->selectedSheetId);
        $service->reconcileSheet($sheet);
        session()->flash('status', 'Workbook and system values compared again.');
    }

    public function regenerateComparisonDraft(HistoricalPayrollImportService $service): void
    {
        $service->createComparisonDrafts($this->import()->fresh('sheets'), auth()->user()?->emp_id);
        session()->flash('status', 'The per-worksheet comparison drafts were regenerated from the current workbook mappings.');
    }

    public function apply(HistoricalPayrollImportService $service): void
    {
        $import = $this->import();
        $this->validate(['confirmation' => ['required', 'in:IMPORT '.$import->id]]);
        $service->apply($import, auth()->user()?->emp_id);
        $this->confirmation = '';
        $this->workflowStep = 5;
        $this->persistWorkflowState();
        session()->flash('status', 'Historical payroll imported as finalized read-only snapshots. Workbook and system comparisons were retained.');
    }

    public function render()
    {
        $import = $this->importId ? HistoricalPayrollImport::query()->with('sheets')->find($this->importId) : null;
        $selectedSheet = $import?->sheets->firstWhere('id', $this->selectedSheetId);
        $rows = null;
        $employeeSuggestions = collect();
        $organizationCounts = collect();
        $sourceDivisionGroups = collect();
        $visibleOrganizationMappings = collect();
        $sheetUnmappedCounts = collect();
        if ($selectedSheet) {
            $sourceDivisionGroups = collect($this->organizationMappings[$selectedSheet->id] ?? [])
                ->groupBy(fn ($mapping) => $this->sourceDivisionLabel($mapping), true);
            if ($this->selectedSourceDivision === '' || ! $sourceDivisionGroups->has($this->selectedSourceDivision)) {
                $this->selectedSourceDivision = (string) ($sourceDivisionGroups->keys()->first() ?? '');
            }
            $visibleOrganizationMappings = collect($this->organizationMappings[$selectedSheet->id] ?? []);
            $organizationCounts = $selectedSheet->rows()->selectRaw('organization_key, COUNT(*) as total')->groupBy('organization_key')->pluck('total', 'organization_key');
            $rows = $selectedSheet->rows()->orderBy('source_row')
                ->when($this->comparisonFilter !== 'all', fn ($query) => $this->comparisonFilter === 'unmatched'
                    ? $query->whereNull('matched_emp_id')
                    : $query->where('comparison_status', $this->comparisonFilter))
                ->paginate(25);
            $mappedScopes = collect($selectedSheet->organization_mappings ?? [])->where('included', true)->filter(fn ($mapping) => filled($mapping['division_id'] ?? null));
            if ($mappedScopes->isNotEmpty()) {
                $employeeSuggestions = Employee::query()->whereHas('department', function ($query) use ($mappedScopes) {
                    $query->where(function ($query) use ($mappedScopes) {
                        foreach ($mappedScopes as $mapping) {
                            $query->orWhere(function ($query) use ($mapping) {
                                $query->where('division_id', $mapping['division_id'])
                                    ->when($mapping['department_id'] ?? null, fn ($query, $departmentId) => $query->where('department_id', $departmentId));
                            });
                        }
                    });
                })->orderBy('lastname')->limit(2000)->get(['emp_id', 'firstname', 'middlename', 'lastname']);
            }
        }

        if ($import) {
            $sheetUnmappedCounts = $import->sheets->mapWithKeys(function ($sheet) {
                $count = collect($this->organizationMappings[$sheet->id] ?? [])
                    ->filter(fn ($mapping) => ($mapping['included'] ?? false) && ! filled($mapping['division_id'] ?? null))
                    ->count();

                return [$sheet->id => $count];
            });
        }

        return view('livewire.payroll.historical-payroll-import-page', [
            'import' => $import, 'selectedSheet' => $selectedSheet, 'rows' => $rows,
            'divisions' => Division::query()->orderBy('division')->get(),
            'departments' => Department::query()->with('division')->orderBy('department')->get(),
            'employeeSuggestions' => $employeeSuggestions,
            'organizationCounts' => $organizationCounts,
            'sourceDivisionGroups' => $sourceDivisionGroups,
            'visibleOrganizationMappings' => $visibleOrganizationMappings,
            'includedOrganizationSheets' => $import?->sheets->where('included', true)->values() ?? collect(),
            'sheetUnmappedCounts' => $sheetUnmappedCounts,
            'comparisonDrafts' => collect($import?->comparison_drafts ?? [])->map(function ($draft) use ($import) {
                return [...$draft, 'url' => $this->comparisonDraftUrl($import, $draft)];
            }),
        ]);
    }

    private function loadMappings(HistoricalPayrollImport $import): void
    {
        foreach ($import->sheets as $sheet) {
            $this->sheetMappings[$sheet->id] = ['included' => $sheet->included];
            $this->organizationMappings[$sheet->id] = $sheet->organization_mappings ?? [];
            foreach ($this->organizationMappings[$sheet->id] as $key => $mapping) {
                if (filled($mapping['division_id'] ?? null)) {
                    continue;
                }
                $division = Division::query()->whereRaw('LOWER(TRIM(division)) = ?', [mb_strtolower(trim($mapping['source_division'] ?? ''))])->first();
                if (! $division) {
                    continue;
                }
                $this->organizationMappings[$sheet->id][$key]['division_id'] = $division->division_id;
                $department = Department::query()->where('division_id', $division->division_id)
                    ->whereRaw('LOWER(TRIM(department)) = ?', [mb_strtolower(trim($mapping['source_department'] ?? ''))])->first();
                if ($department) {
                    $this->organizationMappings[$sheet->id][$key]['department_id'] = $department->department_id;
                    $this->organizationMappings[$sheet->id][$key]['match_status'] = 'exact';
                } elseif (! filled($mapping['source_department'] ?? null)) {
                    $this->organizationMappings[$sheet->id][$key]['match_status'] = 'exact';
                } else {
                    $this->organizationMappings[$sheet->id][$key]['match_status'] = 'division_only';
                }
            }
        }
    }

    private function import(): HistoricalPayrollImport
    {
        return HistoricalPayrollImport::query()->findOrFail($this->importId);
    }

    private function sheet(?int $sheetId): HistoricalPayrollImportSheet
    {
        return HistoricalPayrollImportSheet::query()->whereKey($sheetId)->where('historical_payroll_import_id', $this->importId)->firstOrFail();
    }

    private function selectInitialSourceDivision(): void
    {
        $mappings = $this->selectedSheetId ? ($this->organizationMappings[$this->selectedSheetId] ?? []) : [];
        $this->selectedSourceDivision = collect($mappings)->map(fn ($mapping) => $this->sourceDivisionLabel($mapping))->first() ?? '';
    }

    private function sourceDivisionLabel(array $mapping): string
    {
        return filled($mapping['source_division'] ?? null) ? $mapping['source_division'] : 'Unspecified division';
    }

    private function comparisonDraftUrl(HistoricalPayrollImport $import, array $draft): string
    {
        $configuration = $draft['configuration'] ?? [];

        return route('payroll.generation', [
            'division_ids' => implode(',', $configuration['division_ids'] ?? []),
            'department_ids' => implode(',', $configuration['department_ids'] ?? []),
            'payroll_type' => $import->payroll_type_code,
            'period' => $import->payroll_period,
            'working_days' => $configuration['working_days'] ?? 22,
            'gsis_days' => $configuration['gsis_days'] ?? 30,
            'leave_type_ids' => implode(',', $configuration['leave_type_ids'] ?? []),
            'employee_type' => $configuration['employee_type'] ?? Employee::EMPLOYEE_TYPE_ALL,
            'draft_id' => $draft['draft_id'] ?? null,
            'step' => 7,
        ]);
    }

    private function persistWorkflowState(): void
    {
        if (! $this->importId) {
            return;
        }
        HistoricalPayrollImport::query()->whereKey($this->importId)->update(['workflow_state' => [
            'workflow_step' => $this->workflowStep,
            'selected_sheet_id' => $this->selectedSheetId,
            'reviewed_organization_sheet_ids' => $this->reviewedOrganizationSheetIds,
        ]]);
    }
}
