<?php

namespace App\Livewire\Employees;

use App\Models\Hris\Department;
use App\Models\Hris\Employee;
use App\Models\Hris\EmployeeEmploymentHistory;
use App\Models\Hris\EmploymentStatus;
use App\Models\Hris\Position;
use App\Services\Hris\EmploymentHistoryService;
use Livewire\Component;

class EmployeeEmploymentHistoryPanel extends Component
{
    public string $empId;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $effective_from = '';

    public string $effective_to = '';

    public string $item_number = '';

    public ?int $position_id = null;

    public ?int $department_id = null;

    public ?int $empstat_id = null;

    public string $step = '';

    public string $salary_grade = '';

    public string $nature = EmploymentHistoryService::NATURE_PROMOTION;

    public string $remarks = '';

    public function mount(string $empId, EmploymentHistoryService $history): void
    {
        abort_unless(
            auth()->user()?->can('employees.view') || auth()->user()?->can('employees.manage'),
            403
        );
        $this->empId = $empId;

        $employee = Employee::query()->where('emp_id', $empId)->firstOrFail();
        $history->seedFromEmployeeIfEmpty($employee, auth()->user()?->emp_id);
    }

    public function startCreate(): void
    {
        abort_unless($this->canManage(), 403);

        $employee = Employee::query()->where('emp_id', $this->empId)->firstOrFail();

        $this->resetForm();
        $this->editingId = null;
        $this->effective_from = now()->toDateString();
        $this->effective_to = '';
        $this->position_id = $employee->position_id ? (int) $employee->position_id : null;
        $this->department_id = $employee->department_id ? (int) $employee->department_id : null;
        $this->empstat_id = $employee->empstat_id ? (int) $employee->empstat_id : null;
        $this->step = $employee->step !== null ? (string) $employee->step : '1';
        $this->nature = EmploymentHistoryService::NATURE_PROMOTION;
        $this->showForm = true;
    }

    public function startEdit(int $id): void
    {
        abort_unless($this->canManage(), 403);

        $row = EmployeeEmploymentHistory::query()
            ->where('emp_id', $this->empId)
            ->where('id', $id)
            ->firstOrFail();

        $this->editingId = $row->id;
        $this->effective_from = optional($row->effective_from)->format('Y-m-d') ?? '';
        $this->effective_to = optional($row->effective_to)->format('Y-m-d') ?? '';
        $this->item_number = (string) ($row->item_number ?? '');
        $this->position_id = $row->position_id;
        $this->department_id = $row->department_id;
        $this->empstat_id = $row->empstat_id;
        $this->step = $row->step !== null ? (string) $row->step : '';
        $this->salary_grade = $row->salary_grade !== null ? (string) $row->salary_grade : '';
        $this->nature = (string) $row->nature;
        $this->remarks = (string) ($row->remarks ?? '');
        $this->showForm = true;
    }

    public function save(EmploymentHistoryService $history): void
    {
        abort_unless($this->canManage(), 403);

        $rules = [
            'effective_from' => ['required', 'date'],
            'item_number' => ['nullable', 'string', 'max:64'],
            'position_id' => ['nullable', 'integer', 'exists:hris.tbl_position,position_id'],
            'department_id' => ['nullable', 'integer', 'exists:hris.tbl_department,department_id'],
            'empstat_id' => ['nullable', 'integer', 'exists:hris.tbl_employmentstat,empstat_id'],
            'step' => ['nullable', 'numeric', 'min:1', 'max:32767'],
            'salary_grade' => ['nullable', 'numeric', 'min:1', 'max:99'],
            'nature' => ['required', 'string', 'in:'.implode(',', array_keys(EmploymentHistoryService::natures()))],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];

        if ($this->editingId) {
            $rules['effective_to'] = ['nullable', 'date'];
        }

        if (trim($this->step) === '') {
            $this->step = '';
        }
        if (trim($this->salary_grade) === '') {
            $this->salary_grade = '';
        }

        $data = $this->validate($rules);
        $actor = auth()->user()?->emp_id;
        $step = trim((string) ($data['step'] ?? '')) === '' ? null : (int) $data['step'];
        $salaryGrade = trim((string) ($data['salary_grade'] ?? '')) === '' ? null : (int) $data['salary_grade'];

        if ($this->editingId) {
            $history->updateRow($this->editingId, $data + [
                'step' => $step,
                'salary_grade' => $salaryGrade,
            ], $this->empId);
            session()->flash('status', 'Employment history row updated.');
        } else {
            $history->recordChange($this->empId, [
                'effective_from' => $data['effective_from'],
                'item_number' => $data['item_number'] ?? null,
                'position_id' => $data['position_id'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'empstat_id' => $data['empstat_id'] ?? null,
                'step' => $step,
                'salary_grade' => $salaryGrade,
                'nature' => $data['nature'],
                'remarks' => $data['remarks'] ?? null,
            ], $actor, applyToEmployee: true);
            session()->flash('status', 'Employment change recorded. Employee master updated.');
        }

        $this->showForm = false;
        $this->resetForm();
    }

    public function deleteRow(int $id, EmploymentHistoryService $history): void
    {
        abort_unless($this->canManage(), 403);
        $history->deleteRow($id, $this->empId);
        session()->flash('status', 'Historical assignment removed.');
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function render()
    {
        $rows = EmployeeEmploymentHistory::query()
            ->with(['position', 'department', 'employmentStatus'])
            ->where('emp_id', $this->empId)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get();

        return view('livewire.employees.employee-employment-history-panel', [
            'rows' => $rows,
            'canManage' => $this->canManage(),
            'natures' => EmploymentHistoryService::natures(),
            'positions' => Position::query()->whereNotExists(fn ($query) => $query->selectRaw('1')->from('hris_reference_metadata')->whereColumn('reference_id', 'tbl_position.position_id')->where('reference_type', 'position')->where('is_active', false))->orderBy('position_title')->get(['position_id', 'position_title', 'salary_grade']),
            'departments' => Department::query()->whereNotExists(fn ($query) => $query->selectRaw('1')->from('hris_reference_metadata')->whereColumn('reference_id', 'tbl_department.department_id')->where('reference_type', 'department')->where('is_active', false))->orderBy('department')->get(['department_id', 'department']),
            'employmentStatuses' => EmploymentStatus::query()->orderBy('empstat_id')->get(),
        ]);
    }

    private function canManage(): bool
    {
        return (bool) auth()->user()?->can('employees.manage');
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingId',
            'effective_from',
            'effective_to',
            'item_number',
            'position_id',
            'department_id',
            'empstat_id',
            'step',
            'salary_grade',
            'remarks',
        ]);
        $this->nature = EmploymentHistoryService::NATURE_PROMOTION;
        $this->resetValidation();
    }
}
