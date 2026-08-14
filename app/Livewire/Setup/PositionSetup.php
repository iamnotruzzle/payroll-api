<?php

namespace App\Livewire\Setup;

use App\Models\Hris\HrisReferenceMetadata;
use App\Models\Hris\Position;
use App\Services\Hris\HrisReferenceManagementService;
use Livewire\Component;
use Livewire\WithPagination;

class PositionSetup extends Component
{
    use WithPagination;

    public bool $showForm = false;

    public ?int $positionId = null;

    public string $positionTitle = '';

    public string $positionSalaryGrade = '';

    public string $positionRemarks = '';

    public bool $positionActive = true;

    public string $search = '';

    public function mount(): void
    {
        $this->authorizeAccess();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function edit(int $id): void
    {
        $row = Position::findOrFail($id);
        $this->positionId = $id;
        $this->positionTitle = $row->position_title;
        $this->positionSalaryGrade = (string) $row->salary_grade;
        $this->positionRemarks = (string) $row->remarks;
        $this->positionActive = $this->active($id);
        $this->showForm = true;
    }

    public function create(): void
    {
        $this->reset(['positionId', 'positionTitle', 'positionSalaryGrade', 'positionRemarks']);
        $this->positionActive = true;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetValidation();
    }

    public function save(HrisReferenceManagementService $service): void
    {
        $data = $this->validate(['positionTitle' => 'required|max:50', 'positionSalaryGrade' => 'required|integer|min:1|max:33', 'positionRemarks' => 'nullable|max:50', 'positionActive' => 'boolean']);
        $service->savePosition($this->positionId, ['position_title' => $data['positionTitle'], 'salary_grade' => $data['positionSalaryGrade'], 'remarks' => $data['positionRemarks'], 'is_active' => $data['positionActive']], auth()->user()?->emp_id);
        $this->reset(['positionId', 'positionTitle', 'positionSalaryGrade', 'positionRemarks']);
        $this->positionActive = true;
        $this->showForm = false;
        session()->flash('status', 'Position saved. Finalized payroll snapshots were not changed.');
    }

    public function toggle(int $id, HrisReferenceManagementService $service): void
    {
        $service->setActive('position', $id, ! $this->active($id), auth()->user()?->emp_id);
        session()->flash('status', 'Position availability updated.');
    }

    public function render()
    {
        return view('livewire.setup.position-setup', ['positions' => Position::withCount('employees')->when($this->search, fn ($q) => $q->where('position_title', 'like', '%'.$this->search.'%'))->orderBy('position_title')->paginate(40), 'metadata' => HrisReferenceMetadata::where('reference_type', 'position')->get()->keyBy('reference_id')]);
    }

    private function active(int $id): bool
    {
        return HrisReferenceMetadata::where(['reference_type' => 'position', 'reference_id' => $id])->value('is_active') ?? true;
    }

    private function authorizeAccess(): void
    {
        abort_unless(auth()->user()?->can('employees.manage') || auth()->user()?->can('payroll.configure'), 403);
    }
}
