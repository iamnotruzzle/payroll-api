<?php

namespace App\Livewire\Employees;

use App\Models\Hris\Department;
use App\Models\Hris\Division;
use App\Models\Hris\EmployeeMasterlistImport;
use App\Models\Hris\Position;
use App\Services\Hris\EmployeeMasterlistImportService;
use App\Services\Hris\HrisReferenceManagementService;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class EmployeeMasterlistImportPage extends Component
{
    use WithFileUploads;
    use WithPagination;

    public $file;

    public string $effectiveDate = '';

    public bool $createNew = false;

    public bool $identity = true;

    public bool $employment = true;

    public bool $governmentIds = false;

    public bool $payrollProfile = false;

    public ?int $importId = null;

    public string $filter = 'changes';

    public string $search = '';

    public string $confirmation = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('employees.manage'), 403);
        $this->effectiveDate = now()->toDateString();
    }

    public function preview(EmployeeMasterlistImportService $service): void
    {
        abort_unless(auth()->user()?->can('employees.manage'), 403);
        $data = $this->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xlsm,xls', 'max:20480'],
            'effectiveDate' => ['required', 'date'],
            'createNew' => ['boolean'], 'identity' => ['boolean'], 'employment' => ['boolean'],
            'governmentIds' => ['boolean'], 'payrollProfile' => ['boolean'],
        ]);

        $import = $service->stage(
            $this->file->getRealPath(),
            $this->file->getClientOriginalName(),
            $data['effectiveDate'],
            [
                'create_new' => $data['createNew'], 'identity' => $data['identity'],
                'employment' => $data['employment'], 'government_ids' => $data['governmentIds'],
                'payroll_profile' => $data['payrollProfile'],
            ],
            auth()->user()?->emp_id,
        );
        $this->importId = $import->id;
        $this->file = null;
        $this->confirmation = '';
        $this->resetPage();
        session()->flash('status', "Preview ready: {$import->total_rows} Masterlist rows staged. No employee records have been changed.");
    }

    public function toggleRow(int $rowId): void
    {
        $row = $this->import()->rows()->whereKey($rowId)->firstOrFail();
        if ($row->action !== 'unchanged') {
            $row->update(['selected' => ! $row->selected]);
        }
    }

    public function selectActionable(bool $selected): void
    {
        $this->import()->rows()->whereIn('action', ['new', 'update'])->update(['selected' => $selected]);
    }

    public function mapPositionValue(string $source, mixed $target, EmployeeMasterlistImportService $service): void
    {
        $data = validator(['source' => $source, 'target' => $target], [
            'source' => ['required', 'string'], 'target' => ['required', 'integer', 'exists:hris.tbl_position,position_id'],
        ])->validate();
        $service->mapPosition($this->import(), $data['source'], (int) $data['target']);
        session()->flash('status', 'Position mapping applied to staged rows.');
    }

    public function mapDepartmentValue(string $division, string $department, mixed $target, EmployeeMasterlistImportService $service): void
    {
        $data = validator(['division' => $division, 'department' => $department, 'target' => $target], [
            'division' => ['required', 'string'], 'department' => ['required', 'string'],
            'target' => ['required', 'integer', 'exists:hris.tbl_department,department_id'],
        ])->validate();
        $service->mapDepartment($this->import(), $data['division'], $data['department'], (int) $data['target']);
        session()->flash('status', 'Department mapping applied to staged rows.');
    }

    /** @param array{source?:string,title?:string,salary_grade?:mixed,remarks?:string} $form */
    public function createPositionFromBrowser(array $form, EmployeeMasterlistImportService $importService, HrisReferenceManagementService $referenceService): void
    {
        $import = $this->import();
        $data = validator($form, [
            'source' => ['required', 'string'], 'title' => ['required', 'string', 'max:50'],
            'salary_grade' => ['required', 'integer', 'between:1,33'], 'remarks' => ['nullable', 'string', 'max:50'],
        ], [], ['title' => 'position title', 'salary_grade' => 'salary grade'])->validate();
        $duplicate = Position::query()->whereRaw('LOWER(TRIM(position_title)) = ?', [mb_strtolower(trim($data['title']))])->exists();
        if ($duplicate) {
            throw \Illuminate\Validation\ValidationException::withMessages(['title' => 'This position already exists. Map the workbook value to the existing position instead.']);
        }
        $position = $referenceService->savePosition(null, [
            'position_title' => $data['title'], 'salary_grade' => $data['salary_grade'],
            'remarks' => $data['remarks'] ?? null, 'is_active' => true,
        ], auth()->user()?->emp_id);
        $importService->mapPosition($import, $data['source'], $position->position_id);
        $this->dispatch('masterlist-reference-created');
        session()->flash('status', "Position {$position->position_title} created and mapped to this import.");
    }

    /** @param array<string,mixed> $form */
    public function createDepartmentFromBrowser(array $form, EmployeeMasterlistImportService $importService, HrisReferenceManagementService $referenceService): void
    {
        $import = $this->import();
        $rules = [
            'source_division' => ['required', 'string'], 'source_department' => ['required', 'string'],
            'department_name' => ['required', 'string', 'max:255'],
            'division_id' => ['nullable', 'integer', 'exists:hris.tbl_division,division_id'],
        ];
        if (empty($form['division_id'])) {
            $rules['division_name'] = ['required', 'string', 'max:255'];
            $rules['division_special_title'] = ['nullable', 'string', 'max:255'];
        }
        $data = validator($form, $rules, [], ['department_name' => 'department name', 'division_name' => 'division name'])->validate();
        $divisionId = $data['division_id'] ?? null;
        if (! $divisionId) {
            $existingDivision = Division::query()->whereRaw('LOWER(TRIM(division)) = ?', [mb_strtolower(trim($data['division_name']))])->first();
            $division = $existingDivision ?: $referenceService->saveDivision(null, [
                'division' => $data['division_name'], 'special_title' => $data['division_special_title'] ?? null, 'is_active' => true,
            ], auth()->user()?->emp_id);
            $divisionId = $division->division_id;
        }
        $duplicate = Department::query()->where('division_id', $divisionId)
            ->whereRaw('LOWER(TRIM(department)) = ?', [mb_strtolower(trim($data['department_name']))])->exists();
        if ($duplicate) {
            throw \Illuminate\Validation\ValidationException::withMessages(['department_name' => 'This department already exists in the selected division. Map to it instead.']);
        }
        $department = $referenceService->saveDepartment(null, [
            'department' => $data['department_name'], 'division_id' => $divisionId, 'is_active' => true,
        ], auth()->user()?->emp_id);
        $importService->mapDepartment($import, $data['source_division'], $data['source_department'], $department->department_id);
        $this->dispatch('masterlist-reference-created');
        session()->flash('status', "Department {$department->department} created and mapped to this import.");
    }

    public function apply(EmployeeMasterlistImportService $service): void
    {
        $import = $this->import();
        $this->validate(['confirmation' => ['required', 'in:IMPORT '.$import->id]]);
        $import = $service->apply($import, auth()->user()?->emp_id);
        $this->confirmation = '';
        session()->flash('status', "Import completed: {$import->applied_rows} rows applied; {$import->failed_rows} failed.");
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $import = $this->importId ? EmployeeMasterlistImport::query()->find($this->importId) : null;
        $rows = null;
        $unresolvedPositions = collect();
        $unresolvedDepartments = collect();

        if ($import) {
            $query = $import->rows()->orderBy('source_row');
            $query->when($this->filter === 'changes', fn ($query) => $query->whereIn('action', ['new', 'update']))
                ->when(in_array($this->filter, ['new', 'update', 'unchanged'], true), fn ($query) => $query->where('action', $this->filter))
                ->when($this->filter === 'errors', fn ($query) => $query->whereJsonLength('errors', '>', 0))
                ->when($this->filter === 'warnings', fn ($query) => $query->whereJsonLength('warnings', '>', 0))
                ->when($this->search !== '', fn ($query) => $query->where('emp_id', 'like', '%'.$this->search.'%'));
            $rows = $query->paginate(30);

            $errorRows = $import->rows()->where('status', 'pending')->get(['source_payload', 'errors']);
            $unresolvedPositions = $errorRows
                ->filter(fn ($row) => in_array('Position title is not mapped.', $row->errors ?? [], true))
                ->map(fn ($row) => [
                    'label' => trim((string) ($row->source_payload['position_title'] ?? '')),
                    'salary_grade' => $row->source_payload['salary_grade'] ?? null,
                ])
                ->filter(fn ($row) => $row['label'] !== '')
                ->groupBy(fn ($row) => mb_strtolower($row['label']))
                ->map(function ($rows) {
                    $grades = $rows->pluck('salary_grade')
                        ->filter(fn ($grade) => $grade !== null && $grade !== '' && is_numeric($grade) && (int) $grade >= 1 && (int) $grade <= 33)
                        ->map(fn ($grade) => (int) $grade)->unique()->values();

                    return [
                        'label' => $rows->first()['label'],
                        'salary_grade' => $grades->count() === 1 ? $grades->first() : null,
                        'salary_grades' => $grades->all(),
                    ];
                })->values();
            $unresolvedDepartments = $errorRows->filter(fn ($row) => in_array('Division and department are not mapped.', $row->errors ?? [], true))
                ->map(fn ($row) => ['division' => $row->source_payload['division'], 'department' => $row->source_payload['department']])
                ->unique(fn ($row) => $row['division'].'|'.$row['department'])->values();
        }

        return view('livewire.employees.employee-masterlist-import-page', [
            'import' => $import, 'rows' => $rows,
            'positions' => Position::query()->orderBy('position_title')->get(),
            'departments' => Department::query()->with('division')->orderBy('department')->get(),
            'divisions' => Division::query()->orderBy('division')->get(),
            'unresolvedPositions' => $unresolvedPositions, 'unresolvedDepartments' => $unresolvedDepartments,
        ]);
    }

    private function import(): EmployeeMasterlistImport
    {
        abort_unless(auth()->user()?->can('employees.manage'), 403);

        return EmployeeMasterlistImport::query()->findOrFail($this->importId);
    }
}
