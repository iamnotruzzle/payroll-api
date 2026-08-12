<?php

namespace App\Livewire\Schedule;

use App\Models\Hris\Employee;
use App\Models\Schedule\ScheduleAssignment;
use App\Models\Schedule\ScheduleSwap;
use App\Services\Schedule\ScheduleScopeService;
use App\Services\Schedule\ScheduleSwapService;
use Livewire\Component;

class ScheduleSwaps extends Component
{
    public string $statusFilter = 'open';

    public ?int $requester_assignment_id = null;

    public ?int $responder_assignment_id = null;

    public ?string $notes = null;

    public function mount(ScheduleScopeService $scopeService): void
    {
        abort_unless(auth()->user()?->can('schedule.view'), 403);
        abort_unless($scopeService->profileForDepartment($this->departmentId())->uses_swaps, 404);
    }

    public function render()
    {
        $departmentId = $this->departmentId();

        $swaps = ScheduleSwap::query()
            ->with(['requesterAssignment.shiftCode', 'responderAssignment.shiftCode'])
            ->where('department_id', $departmentId)
            ->when($this->statusFilter === 'open', fn ($q) => $q->whereIn('status', [ScheduleSwap::STATUS_PENDING, ScheduleSwap::STATUS_ACCEPTED]))
            ->when($this->statusFilter !== 'open' && $this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $assignments = ScheduleAssignment::query()
            ->with(['shiftCode', 'monthlySchedule', 'employee'])
            ->whereHas('monthlySchedule', function ($query) use ($departmentId) {
                $query->where('department_id', $departmentId)
                    ->whereIn('status', ['draft', 'reviewed', 'approved']);
            })
            ->whereDate('schedule_date', '>=', now()->toDateString())
            ->orderBy('schedule_date')
            ->orderBy('employee_id')
            ->limit(400)
            ->get();

        $names = Employee::query()
            ->whereIn('emp_id', $swaps->pluck('requester_emp_id')->merge($swaps->pluck('responder_emp_id'))->unique())
            ->get(['emp_id', 'firstname', 'lastname'])
            ->keyBy('emp_id');

        return view('livewire.schedule.schedule-swaps', [
            'department' => auth()->user()?->employee?->department,
            'swaps' => $swaps,
            'assignments' => $assignments,
            'names' => $names,
            'canManage' => (bool) (auth()->user()?->can('schedule.manage') || auth()->user()?->can('schedule.approve')),
        ]);
    }

    public function createSwap(ScheduleSwapService $service): void
    {
        abort_unless(auth()->user()?->can('schedule.manage') || auth()->user()?->can('schedule.approve'), 403);

        $data = $this->validate([
            'requester_assignment_id' => ['required', 'integer'],
            'responder_assignment_id' => ['required', 'integer', 'different:requester_assignment_id'],
            'notes' => ['nullable', 'string'],
        ]);

        $requester = $this->findDeptAssignment((int) $data['requester_assignment_id']);
        $responder = $this->findDeptAssignment((int) $data['responder_assignment_id']);

        try {
            $service->request($requester, $responder, auth()->user()?->emp_id ?? 'web', $data['notes'] ?? null);
            session()->flash('status', 'Swap request created.');
            $this->reset(['requester_assignment_id', 'responder_assignment_id', 'notes']);
        } catch (\Throwable $e) {
            session()->flash('status', $e->getMessage());
        }
    }

    public function approve(int $id, ScheduleSwapService $service): void
    {
        abort_unless(auth()->user()?->can('schedule.manage') || auth()->user()?->can('schedule.approve'), 403);

        try {
            $swap = $this->findDeptSwap($id);
            $service->approve($swap, auth()->user()?->emp_id ?? 'web');
            session()->flash('status', 'Swap approved and assignments updated.');
        } catch (\Throwable $e) {
            session()->flash('status', $e->getMessage());
        }
    }

    public function reject(int $id, ScheduleSwapService $service): void
    {
        abort_unless(auth()->user()?->can('schedule.manage') || auth()->user()?->can('schedule.approve'), 403);

        try {
            $service->reject($this->findDeptSwap($id), auth()->user()?->emp_id ?? 'web');
            session()->flash('status', 'Swap rejected.');
        } catch (\Throwable $e) {
            session()->flash('status', $e->getMessage());
        }
    }

    private function findDeptAssignment(int $id): ScheduleAssignment
    {
        return ScheduleAssignment::with('monthlySchedule')
            ->whereHas('monthlySchedule', fn ($q) => $q->where('department_id', $this->departmentId()))
            ->findOrFail($id);
    }

    private function findDeptSwap(int $id): ScheduleSwap
    {
        return ScheduleSwap::query()
            ->where('department_id', $this->departmentId())
            ->findOrFail($id);
    }

    private function departmentId(): ?int
    {
        $id = auth()->user()?->employee?->department_id;

        return $id !== null ? (int) $id : null;
    }
}
