<?php

namespace App\Livewire\Employees;

use App\Models\Hris\Department;
use App\Models\Hris\EmployeeMasterlistImport;
use App\Models\Hris\Position;
use App\Services\Hris\EmployeeMasterlistImportService;
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

    public string $positionSource = '';

    public ?int $positionTarget = null;

    public string $departmentDivisionSource = '';

    public string $departmentSource = '';

    public ?int $departmentTarget = null;

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

    public function mapPosition(EmployeeMasterlistImportService $service): void
    {
        $this->validate(['positionSource' => ['required'], 'positionTarget' => ['required', 'integer']]);
        $service->mapPosition($this->import(), $this->positionSource, (int) $this->positionTarget);
        $this->reset(['positionSource', 'positionTarget']);
        session()->flash('status', 'Position mapping applied to staged rows.');
    }

    public function choosePositionSource(string $label): void
    {
        $this->positionSource = $label;
    }

    public function chooseDepartmentSource(string $division, string $department): void
    {
        $this->departmentDivisionSource = $division;
        $this->departmentSource = $department;
    }

    public function mapDepartment(EmployeeMasterlistImportService $service): void
    {
        $this->validate([
            'departmentDivisionSource' => ['required'], 'departmentSource' => ['required'],
            'departmentTarget' => ['required', 'integer'],
        ]);
        $service->mapDepartment($this->import(), $this->departmentDivisionSource, $this->departmentSource, (int) $this->departmentTarget);
        $this->reset(['departmentDivisionSource', 'departmentSource', 'departmentTarget']);
        session()->flash('status', 'Department mapping applied to staged rows.');
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
            $unresolvedPositions = $errorRows->filter(fn ($row) => in_array('Position title is not mapped.', $row->errors ?? [], true))
                ->pluck('source_payload')->pluck('position_title')->filter()->unique()->values();
            $unresolvedDepartments = $errorRows->filter(fn ($row) => in_array('Division and department are not mapped.', $row->errors ?? [], true))
                ->map(fn ($row) => ['division' => $row->source_payload['division'], 'department' => $row->source_payload['department']])
                ->unique(fn ($row) => $row['division'].'|'.$row['department'])->values();
        }

        return view('livewire.employees.employee-masterlist-import-page', [
            'import' => $import, 'rows' => $rows,
            'positions' => Position::query()->orderBy('position_title')->get(),
            'departments' => Department::query()->with('division')->orderBy('department')->get(),
            'unresolvedPositions' => $unresolvedPositions, 'unresolvedDepartments' => $unresolvedDepartments,
        ]);
    }

    private function import(): EmployeeMasterlistImport
    {
        abort_unless(auth()->user()?->can('employees.manage'), 403);

        return EmployeeMasterlistImport::query()->findOrFail($this->importId);
    }
}
