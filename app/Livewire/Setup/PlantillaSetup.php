<?php

namespace App\Livewire\Setup;

use App\Models\Hris\Department;
use App\Models\Hris\Employee;
use App\Models\Hris\PlantillaItem;
use App\Models\Hris\Position;
use App\Services\Hris\EmploymentHistoryService;
use App\Services\Hris\HrisReferenceManagementService;
use Livewire\Component;
use Livewire\WithPagination;

class PlantillaSetup extends Component
{
    use WithPagination;

    public bool $showItemForm = false;

    public bool $showAssignmentForm = false;

    public string $search = '';

    public ?int $itemId = null;

    public string $itemNumber = '';

    public ?int $positionId = null;

    public ?int $departmentId = null;

    public string $salaryGrade = '';

    public string $fundType = '';

    public string $authorizationYear = '';

    public string $status = 'vacant';

    public string $effectiveFrom = '';

    public string $effectiveTo = '';

    public string $remarks = '';

    public ?int $assignmentItemId = null;

    public string $employeeId = '';

    public string $assignmentDate = '';

    public string $nature = 'original';

    public string $assignmentRemarks = '';

    public function mount(): void
    {
        $this->authorizeAccess();
        $this->effectiveFrom = now()->toDateString();
        $this->assignmentDate = now()->toDateString();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function edit(int $id): void
    {
        $r = PlantillaItem::findOrFail($id);
        $this->itemId = $id;
        $this->itemNumber = $r->item_number;
        $this->positionId = $r->position_id;
        $this->departmentId = $r->department_id;
        $this->salaryGrade = (string) $r->salary_grade;
        $this->fundType = (string) $r->fund_type;
        $this->authorizationYear = (string) $r->authorization_year;
        $this->status = $r->status;
        $this->effectiveFrom = $r->effective_from->toDateString();
        $this->effectiveTo = $r->effective_to?->toDateString() ?? '';
        $this->remarks = (string) $r->remarks;
        $this->showItemForm = true;
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showItemForm = true;
    }

    public function closeItemForm(): void
    {
        $this->showItemForm = false;
        $this->resetValidation();
    }

    public function save(HrisReferenceManagementService $service): void
    {
        $d = $this->validate(['itemNumber' => 'required|max:64', 'positionId' => 'required|integer|exists:hris.tbl_position,position_id', 'departmentId' => 'required|integer|exists:hris.tbl_department,department_id', 'salaryGrade' => 'required|integer|min:1|max:33', 'fundType' => 'nullable|max:128', 'authorizationYear' => 'nullable|integer|min:1900|max:2200', 'status' => 'required|in:vacant,occupied,frozen,abolished', 'effectiveFrom' => 'required|date', 'effectiveTo' => 'nullable|date|after_or_equal:effectiveFrom', 'remarks' => 'nullable|max:1000']);
        $service->savePlantilla($this->itemId, ['item_number' => $d['itemNumber'], 'position_id' => $d['positionId'], 'department_id' => $d['departmentId'], 'salary_grade' => $d['salaryGrade'], 'fund_type' => $d['fundType'], 'authorization_year' => $d['authorizationYear'] ?: null, 'status' => $d['status'], 'effective_from' => $d['effectiveFrom'], 'effective_to' => $d['effectiveTo'] ?: null, 'remarks' => $d['remarks']], auth()->user()?->emp_id);
        $this->resetForm();
        $this->showItemForm = false;
        $this->dispatch('erp-overlay-close', name: 'plantilla-item');
        session()->flash('status', 'Plantilla item saved.');
    }

    public function selectAssignment(int $id): void
    {
        $this->assignmentItemId = $id;
        $this->showAssignmentForm = true;
    }

    public function closeAssignmentForm(): void
    {
        $this->showAssignmentForm = false;
        $this->resetValidation();
    }

    public function assign(HrisReferenceManagementService $service): void
    {
        $d = $this->validate(['assignmentItemId' => 'required|integer', 'employeeId' => 'required|exists:hris.tbl_employee,emp_id', 'assignmentDate' => 'required|date', 'nature' => 'required|in:'.implode(',', array_keys(EmploymentHistoryService::natures())), 'assignmentRemarks' => 'nullable|max:1000']);
        $service->assignPlantilla($d['assignmentItemId'], $d['employeeId'], $d['assignmentDate'], $d['nature'], $d['assignmentRemarks'], auth()->user()?->emp_id);
        $this->reset(['assignmentItemId', 'employeeId', 'assignmentRemarks']);
        $this->assignmentDate = now()->toDateString();
        $this->nature = 'original';
        $this->showAssignmentForm = false;
        $this->dispatch('erp-overlay-close', name: 'plantilla-assignment');
        session()->flash('status', 'Plantilla assignment recorded with effective-dated employment history.');
    }

    public function render()
    {
        return view('livewire.setup.plantilla-setup', ['items' => PlantillaItem::with(['position', 'department.division', 'currentAssignment.employee'])->when($this->search, fn ($q) => $q->where('item_number', 'like', '%'.$this->search.'%'))->orderBy('item_number')->paginate(40), 'itemOptions' => PlantillaItem::with('position')->orderBy('item_number')->get(), 'positions' => Position::orderBy('position_title')->get(), 'departments' => Department::with('division')->orderBy('department')->get(), 'employees' => Employee::where('is_active', 'Y')->orderBy('lastname')->orderBy('firstname')->get(['emp_id', 'firstname', 'lastname']), 'natures' => EmploymentHistoryService::natures()]);
    }

    private function resetForm(): void
    {
        $this->reset(['itemId', 'itemNumber', 'positionId', 'departmentId', 'salaryGrade', 'fundType', 'authorizationYear', 'effectiveTo', 'remarks']);
        $this->status = 'vacant';
        $this->effectiveFrom = now()->toDateString();
    }

    private function authorizeAccess(): void
    {
        abort_unless(auth()->user()?->can('employees.manage') || auth()->user()?->can('payroll.configure'), 403);
    }
}
