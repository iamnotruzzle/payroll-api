<?php

namespace App\Livewire\SelfService;

use App\Models\Schedule\ScheduleAssignment;
use App\Models\Schedule\ScheduleSwap;
use App\Services\Schedule\ScheduleScopeService;
use App\Services\Schedule\ScheduleSwapService;
use Carbon\CarbonImmutable;
use Livewire\Component;

class MyShiftSwaps extends Component
{
    public string $empId = '';

    public ?int $my_assignment_id = null;

    public ?int $partner_assignment_id = null;

    public ?string $notes = null;

    public function mount(ScheduleScopeService $scopeService, ?string $empId = null): void
    {
        abort_unless(
            auth()->user()?->can('self-service.schedule')
            || auth()->user()?->can('self-service.access'),
            403
        );

        $this->empId = (string) ($empId ?: auth()->user()?->emp_id ?? '');
        abort_unless($this->empId !== '', 404);
        abort_unless($this->empId === (string) (auth()->user()?->emp_id ?? ''), 403);

        $departmentId = auth()->user()?->employee?->department_id;
        abort_unless($scopeService->profileForDepartment($departmentId)->uses_swaps, 404);
    }

    public function render()
    {
        $myAssignments = ScheduleAssignment::query()
            ->with(['shiftCode', 'monthlySchedule'])
            ->where('employee_id', $this->empId)
            ->whereDate('schedule_date', '>=', CarbonImmutable::today()->toDateString())
            ->whereHas('monthlySchedule', fn ($q) => $q->whereIn('status', ['draft', 'reviewed', 'approved']))
            ->orderBy('schedule_date')
            ->limit(60)
            ->get();

        $partnerAssignments = collect();
        if ($this->my_assignment_id) {
            $mine = $myAssignments->firstWhere('id', $this->my_assignment_id);
            if ($mine) {
                $partnerAssignments = ScheduleAssignment::query()
                    ->with(['shiftCode', 'monthlySchedule'])
                    ->whereDate('schedule_date', $mine->schedule_date->toDateString())
                    ->where('employee_id', '!=', $this->empId)
                    ->whereHas('monthlySchedule', function ($q) use ($mine) {
                        $q->where('department_id', $mine->monthlySchedule->department_id)
                            ->whereIn('status', ['draft', 'reviewed', 'approved']);
                    })
                    ->orderBy('employee_id')
                    ->limit(100)
                    ->get();
            }
        }

        $swaps = ScheduleSwap::query()
            ->with(['requesterAssignment.shiftCode', 'responderAssignment.shiftCode'])
            ->where(function ($q) {
                $q->where('requester_emp_id', $this->empId)
                    ->orWhere('responder_emp_id', $this->empId);
            })
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return view('livewire.self-service.my-shift-swaps', [
            'myAssignments' => $myAssignments,
            'partnerAssignments' => $partnerAssignments,
            'swaps' => $swaps,
        ]);
    }

    public function requestSwap(ScheduleSwapService $service): void
    {
        $data = $this->validate([
            'my_assignment_id' => ['required', 'integer'],
            'partner_assignment_id' => ['required', 'integer', 'different:my_assignment_id'],
            'notes' => ['nullable', 'string'],
        ]);

        $mine = ScheduleAssignment::with('monthlySchedule')
            ->where('employee_id', $this->empId)
            ->findOrFail($data['my_assignment_id']);
        $partner = ScheduleAssignment::with('monthlySchedule')->findOrFail($data['partner_assignment_id']);

        try {
            $service->request($mine, $partner, $this->empId, $data['notes'] ?? null);
            session()->flash('status', 'Swap request submitted. A scheduler must approve it.');
            $this->reset(['my_assignment_id', 'partner_assignment_id', 'notes']);
        } catch (\Throwable $e) {
            session()->flash('status', $e->getMessage());
        }
    }

    public function accept(int $id, ScheduleSwapService $service): void
    {
        $swap = ScheduleSwap::query()
            ->where('responder_emp_id', $this->empId)
            ->findOrFail($id);

        try {
            $service->accept($swap, $this->empId);
            session()->flash('status', 'Swap accepted. Waiting for scheduler approval.');
        } catch (\Throwable $e) {
            session()->flash('status', $e->getMessage());
        }
    }

    public function cancel(int $id, ScheduleSwapService $service): void
    {
        $swap = ScheduleSwap::query()
            ->where('requester_emp_id', $this->empId)
            ->findOrFail($id);

        try {
            $service->cancel($swap, $this->empId);
            session()->flash('status', 'Swap cancelled.');
        } catch (\Throwable $e) {
            session()->flash('status', $e->getMessage());
        }
    }
}
