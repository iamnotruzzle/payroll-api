<?php

namespace App\Livewire\Setup;

use App\Models\Hris\Department;
use App\Models\Hris\Division;
use App\Models\Hris\HrisReferenceMetadata;
use App\Services\Hris\HrisReferenceManagementService;
use Livewire\Component;

class OrganizationSetup extends Component
{
    public bool $showDivisionForm = false;

    public bool $showDepartmentForm = false;

    public ?int $divisionId = null;

    public string $divisionName = '';

    public string $divisionSpecialTitle = '';

    public bool $divisionActive = true;

    public ?int $departmentId = null;

    public string $departmentName = '';

    public ?int $departmentDivisionId = null;

    public bool $departmentActive = true;

    public string $search = '';

    public function mount(): void
    {
        $this->authorizeAccess();
    }

    public function editDivision(int $id): void
    {
        $row = Division::findOrFail($id);
        $this->divisionId = $id;
        $this->divisionName = $row->division;
        $this->divisionSpecialTitle = (string) $row->special_title;
        $this->divisionActive = $this->active('division', $id);
        $this->showDivisionForm = true;
    }

    public function createDivision(): void
    {
        $this->reset(['divisionId', 'divisionName', 'divisionSpecialTitle']);
        $this->divisionActive = true;
        $this->showDivisionForm = true;
    }

    public function closeDivisionForm(): void
    {
        $this->showDivisionForm = false;
        $this->resetValidation();
    }

    public function saveDivision(HrisReferenceManagementService $service): void
    {
        $data = $this->validate(['divisionName' => 'required|max:255', 'divisionSpecialTitle' => 'nullable|max:255', 'divisionActive' => 'boolean']);
        $service->saveDivision($this->divisionId, ['division' => $data['divisionName'], 'special_title' => $data['divisionSpecialTitle'], 'is_active' => $data['divisionActive']], auth()->user()?->emp_id);
        $this->reset(['divisionId', 'divisionName', 'divisionSpecialTitle']);
        $this->divisionActive = true;
        $this->showDivisionForm = false;
        session()->flash('status', 'Division saved.');
    }

    public function editDepartment(int $id): void
    {
        $row = Department::findOrFail($id);
        $this->departmentId = $id;
        $this->departmentName = $row->department;
        $this->departmentDivisionId = $row->division_id;
        $this->departmentActive = $this->active('department', $id);
        $this->showDepartmentForm = true;
    }

    public function createDepartment(): void
    {
        $this->reset(['departmentId', 'departmentName', 'departmentDivisionId']);
        $this->departmentActive = true;
        $this->showDepartmentForm = true;
    }

    public function closeDepartmentForm(): void
    {
        $this->showDepartmentForm = false;
        $this->resetValidation();
    }

    public function saveDepartment(HrisReferenceManagementService $service): void
    {
        $data = $this->validate(['departmentName' => 'required|max:255', 'departmentDivisionId' => 'required|integer|exists:hris.tbl_division,division_id', 'departmentActive' => 'boolean']);
        $service->saveDepartment($this->departmentId, ['department' => $data['departmentName'], 'division_id' => $data['departmentDivisionId'], 'is_active' => $data['departmentActive']], auth()->user()?->emp_id);
        $this->reset(['departmentId', 'departmentName', 'departmentDivisionId']);
        $this->departmentActive = true;
        $this->showDepartmentForm = false;
        session()->flash('status', 'Department saved.');
    }

    public function toggleReference(string $type, int $id, HrisReferenceManagementService $service): void
    {
        $service->setActive($type, $id, ! $this->active($type, $id), auth()->user()?->emp_id);
        session()->flash('status', 'Reference availability updated. Historical records remain unchanged.');
    }

    public function render()
    {
        return view('livewire.setup.organization-setup', ['divisions' => Division::withCount('departments')->orderBy('division')->get(), 'departments' => Department::with('division')->when($this->search, fn ($q) => $q->where('department', 'like', '%'.$this->search.'%'))->orderBy('department')->get(), 'metadata' => HrisReferenceMetadata::all()->keyBy(fn ($r) => $r->reference_type.'|'.$r->reference_id)]);
    }

    private function active(string $type, int $id): bool
    {
        return HrisReferenceMetadata::where(['reference_type' => $type, 'reference_id' => $id])->value('is_active') ?? true;
    }

    private function authorizeAccess(): void
    {
        abort_unless(auth()->user()?->can('employees.manage') || auth()->user()?->can('payroll.configure'), 403);
    }
}
