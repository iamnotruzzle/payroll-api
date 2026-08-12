<?php

namespace App\Services\Schedule;

use App\Models\Schedule\MonthlySchedule;
use App\Models\Schedule\ScheduleAssignment;
use App\Models\Schedule\ScheduleSwap;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ScheduleSwapService
{
    public function __construct(private AuditLogService $auditLogService) {}

    public function request(
        ScheduleAssignment $requesterAssignment,
        ScheduleAssignment $responderAssignment,
        string $requestedBy,
        ?string $notes = null,
    ): ScheduleSwap {
        $this->assertSwappable($requesterAssignment, $responderAssignment);

        $departmentId = (int) $requesterAssignment->monthlySchedule->department_id;

        $existing = ScheduleSwap::query()
            ->whereIn('status', [ScheduleSwap::STATUS_PENDING, ScheduleSwap::STATUS_ACCEPTED])
            ->where(function ($query) use ($requesterAssignment, $responderAssignment) {
                $query->where('requester_assignment_id', $requesterAssignment->id)
                    ->orWhere('responder_assignment_id', $requesterAssignment->id)
                    ->orWhere('requester_assignment_id', $responderAssignment->id)
                    ->orWhere('responder_assignment_id', $responderAssignment->id);
            })
            ->exists();

        if ($existing) {
            throw new RuntimeException('An open swap already exists for one of these assignments.');
        }

        $swap = ScheduleSwap::query()->create([
            'department_id' => $departmentId,
            'requester_emp_id' => $requesterAssignment->employee_id,
            'responder_emp_id' => $responderAssignment->employee_id,
            'requester_assignment_id' => $requesterAssignment->id,
            'responder_assignment_id' => $responderAssignment->id,
            'schedule_date' => $requesterAssignment->schedule_date->toDateString(),
            'status' => ScheduleSwap::STATUS_PENDING,
            'notes' => $notes,
            'requested_by' => $requestedBy,
            'requested_at' => now(),
        ]);

        $this->auditLogService->record('schedule.swap.requested', $swap, null, $swap->toArray(), $requestedBy);

        return $swap;
    }

    public function accept(ScheduleSwap $swap, string $performedBy): ScheduleSwap
    {
        if ($swap->status !== ScheduleSwap::STATUS_PENDING) {
            throw new RuntimeException('Only pending swaps can be accepted.');
        }

        if ($performedBy !== $swap->responder_emp_id && ! auth()->user()?->can('schedule.manage')) {
            throw new RuntimeException('Only the responder (or a scheduler) can accept this swap.');
        }

        $before = $swap->toArray();
        $swap->update([
            'status' => ScheduleSwap::STATUS_ACCEPTED,
            'responded_at' => now(),
        ]);
        $this->auditLogService->record('schedule.swap.accepted', $swap, $before, $swap->fresh()->toArray(), $performedBy);

        return $swap->fresh();
    }

    public function reject(ScheduleSwap $swap, string $performedBy): ScheduleSwap
    {
        if (! $swap->isOpen()) {
            throw new RuntimeException('Only open swaps can be rejected.');
        }

        $before = $swap->toArray();
        $swap->update([
            'status' => ScheduleSwap::STATUS_REJECTED,
            'responded_at' => now(),
            'approved_by' => $performedBy,
            'approved_at' => now(),
        ]);
        $this->auditLogService->record('schedule.swap.rejected', $swap, $before, $swap->fresh()->toArray(), $performedBy);

        return $swap->fresh();
    }

    public function cancel(ScheduleSwap $swap, string $performedBy): ScheduleSwap
    {
        if (! $swap->isOpen()) {
            throw new RuntimeException('Only open swaps can be cancelled.');
        }

        if ($performedBy !== $swap->requester_emp_id && ! auth()->user()?->can('schedule.manage')) {
            throw new RuntimeException('Only the requester (or a scheduler) can cancel this swap.');
        }

        $before = $swap->toArray();
        $swap->update(['status' => ScheduleSwap::STATUS_CANCELLED]);
        $this->auditLogService->record('schedule.swap.cancelled', $swap, $before, $swap->fresh()->toArray(), $performedBy);

        return $swap->fresh();
    }

    public function approve(ScheduleSwap $swap, string $performedBy): ScheduleSwap
    {
        if (! in_array($swap->status, [ScheduleSwap::STATUS_PENDING, ScheduleSwap::STATUS_ACCEPTED], true)) {
            throw new RuntimeException('Only pending or accepted swaps can be approved.');
        }

        $requester = ScheduleAssignment::with('monthlySchedule')->findOrFail($swap->requester_assignment_id);
        $responder = ScheduleAssignment::with('monthlySchedule')->findOrFail($swap->responder_assignment_id);
        $this->assertSwappable($requester, $responder);

        return DB::connection('payroll_scheduler')->transaction(function () use ($swap, $requester, $responder, $performedBy) {
            $beforeRequester = $requester->toArray();
            $beforeResponder = $responder->toArray();

            $swapShift = $requester->shift_code_id;
            $swapUnit = $requester->unit_id;
            $swapFloater = $requester->is_temporary_floater;

            $requester->fill([
                'shift_code_id' => $responder->shift_code_id,
                'unit_id' => $responder->unit_id,
                'is_temporary_floater' => $responder->is_temporary_floater,
                'source' => 'swap',
            ])->save();

            $responder->fill([
                'shift_code_id' => $swapShift,
                'unit_id' => $swapUnit,
                'is_temporary_floater' => $swapFloater,
                'source' => 'swap',
            ])->save();

            $before = $swap->toArray();
            $swap->update([
                'status' => ScheduleSwap::STATUS_APPROVED,
                'approved_by' => $performedBy,
                'approved_at' => now(),
            ]);

            $this->auditLogService->record('schedule.assignment.swapped', $requester, $beforeRequester, $requester->fresh()->toArray(), $performedBy);
            $this->auditLogService->record('schedule.assignment.swapped', $responder, $beforeResponder, $responder->fresh()->toArray(), $performedBy);
            $this->auditLogService->record('schedule.swap.approved', $swap, $before, $swap->fresh()->toArray(), $performedBy);

            return $swap->fresh();
        });
    }

    private function assertSwappable(ScheduleAssignment $a, ScheduleAssignment $b): void
    {
        $a->loadMissing('monthlySchedule');
        $b->loadMissing('monthlySchedule');

        if ($a->id === $b->id) {
            throw new RuntimeException('Cannot swap an assignment with itself.');
        }

        if ($a->employee_id === $b->employee_id) {
            throw new RuntimeException('Swap partners must be different employees.');
        }

        if ($a->schedule_date->toDateString() !== $b->schedule_date->toDateString()) {
            throw new RuntimeException('Swap assignments must be on the same date.');
        }

        if ((int) $a->monthlySchedule->department_id !== (int) $b->monthlySchedule->department_id) {
            throw new RuntimeException('Swap assignments must be in the same department.');
        }

        foreach ([$a, $b] as $assignment) {
            $status = $assignment->monthlySchedule->status;
            if ($status === MonthlySchedule::STATUS_LOCKED) {
                throw new RuntimeException('Locked schedules cannot be swapped. Unlock is not allowed via swap; keep approve → lock → DTR intact.');
            }
            if (! in_array($status, [MonthlySchedule::STATUS_APPROVED, MonthlySchedule::STATUS_REVIEWED, MonthlySchedule::STATUS_DRAFT], true)) {
                throw new RuntimeException('Assignments on this roster status cannot be swapped.');
            }
        }
    }
}
