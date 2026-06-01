<?php

namespace App\Services\Payroll;

use App\Models\Hris\Employee;
use App\Models\Hris\EmployeeDtr;
use App\Models\Payroll\PayrollDtrCorrectionApprover;
use App\Models\Payroll\PayrollDtrCorrectionRequest;
use App\Models\Payroll\PayrollMraReport;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DtrCorrectionRequestService
{
    public function approversFor(string $employeeId): Collection
    {
        $employee = $this->employeeOrFail($employeeId);

        return Employee::query()
            ->with('position')
            ->where('department_id', $employee->department_id)
            ->where('is_active', 'Y')
            ->where('emp_id', '!=', $employee->emp_id)
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->get(['emp_id', 'firstname', 'middlename', 'lastname', 'position_id', 'department_id']);
    }

    public function configuredApproverFor(string $employeeId): ?Employee
    {
        $configuration = PayrollDtrCorrectionApprover::query()
            ->where('emp_id', $employeeId)
            ->first();

        if (! $configuration) {
            return null;
        }

        $employee = Employee::query()
            ->where('emp_id', $employeeId)
            ->where('is_active', 'Y')
            ->first();

        if (! $employee) {
            return null;
        }

        return Employee::query()
            ->where('emp_id', $configuration->approver_emp_id)
            ->where('is_active', 'Y')
            ->where('department_id', $employee->department_id)
            ->where('emp_id', '!=', $employee->emp_id)
            ->first();
    }

    public function saveApproverConfiguration(string $employeeId, string $approverId, string $configuredBy): PayrollDtrCorrectionApprover
    {
        $employee = $this->employeeOrFail($employeeId);
        $approver = $this->sameDepartmentApproverOrFail($employee, $approverId);

        return PayrollDtrCorrectionApprover::updateOrCreate(
            ['emp_id' => $employee->emp_id],
            [
                'department_id' => $employee->department_id,
                'approver_emp_id' => $approver->emp_id,
                'configured_by_emp_id' => $configuredBy,
            ],
        )->fresh(['employee', 'approver']);
    }

    public function create(array $data, string $requestedBy): PayrollDtrCorrectionRequest
    {
        $employee = $this->employeeOrFail($data['emp_id']);
        $this->assertActorCanRequestFor($employee, $requestedBy);
        $approver = isset($data['approver_emp_id'])
            ? $this->sameDepartmentApproverOrFail($employee, $data['approver_emp_id'])
            : $this->configuredApproverFor($employee->emp_id);

        if (! $approver) {
            throw ValidationException::withMessages([
                'approver_emp_id' => 'No DTR correction approver is configured for this employee.',
            ]);
        }

        $this->assertCorrectionPayload($data);
        $this->assertPeriodOpen($employee, $data['dtr_date']);
        $this->assertNoPendingRequest($employee->emp_id, $data['dtr_date']);

        return PayrollDtrCorrectionRequest::create([
            'emp_id' => $employee->emp_id,
            'department_id' => $employee->department_id,
            'dtr_date' => $data['dtr_date'],
            'request_type' => $data['request_type'],
            'requested_time_in' => $data['requested_time_in'] ?? null,
            'requested_time_out' => $data['requested_time_out'] ?? null,
            'requested_timeout_nextday' => (bool) ($data['requested_timeout_nextday'] ?? false),
            'reason' => $data['reason'],
            'attachment_path' => $data['attachment_path'] ?? null,
            'attachment_original_name' => $data['attachment_original_name'] ?? null,
            'attachment_mime_type' => $data['attachment_mime_type'] ?? null,
            'attachment_size' => $data['attachment_size'] ?? null,
            'status' => PayrollDtrCorrectionRequest::STATUS_PENDING,
            'requested_by_emp_id' => $requestedBy,
            'requested_at' => now(),
            'approver_emp_id' => $approver->emp_id,
        ])->fresh(['employee', 'requestedBy', 'approver']);
    }

    public function approve(PayrollDtrCorrectionRequest $request, string $approvedBy, ?string $remarks = null): PayrollDtrCorrectionRequest
    {
        $this->assertPending($request);

        if ($request->approver_emp_id !== $approvedBy) {
            throw ValidationException::withMessages([
                'approved_by_emp_id' => 'Only the delegated approver can approve this DTR correction request.',
            ]);
        }

        $employee = $this->employeeOrFail($request->emp_id);
        $this->sameDepartmentApproverOrFail($employee, $approvedBy);
        $this->assertPeriodOpen($employee, $request->dtr_date->toDateString());

        DB::connection('payroll')->transaction(function () use ($request, $approvedBy, $remarks) {
            $dtr = $this->applyToDtr($request);

            $request->forceFill([
                'status' => PayrollDtrCorrectionRequest::STATUS_APPROVED,
                'approved_at' => now(),
                'approved_by_emp_id' => $approvedBy,
                'approver_remarks' => $remarks,
                'applied_dtr' => $this->dtrSnapshot($dtr),
            ])->save();
        });

        return $request->fresh(['employee', 'requestedBy', 'approver']);
    }

    public function reject(PayrollDtrCorrectionRequest $request, string $rejectedBy, ?string $remarks = null): PayrollDtrCorrectionRequest
    {
        $this->assertPending($request);

        if ($request->approver_emp_id !== $rejectedBy) {
            throw ValidationException::withMessages([
                'rejected_by_emp_id' => 'Only the delegated approver can reject this DTR correction request.',
            ]);
        }

        $request->forceFill([
            'status' => PayrollDtrCorrectionRequest::STATUS_REJECTED,
            'rejected_at' => now(),
            'rejected_by_emp_id' => $rejectedBy,
            'approver_remarks' => $remarks,
        ])->save();

        return $request->fresh(['employee', 'requestedBy', 'approver']);
    }

    private function applyToDtr(PayrollDtrCorrectionRequest $request): EmployeeDtr
    {
        $dtr = EmployeeDtr::query()
            ->where('emp_id', $request->emp_id)
            ->whereDate('dtr_date', $request->dtr_date)
            ->first();

        $request->forceFill(['previous_dtr' => $this->dtrSnapshot($dtr)])->save();

        $date = $request->dtr_date->toDateString();
        $values = [
            'emp_id' => $request->emp_id,
            'dtr_date' => $date,
            'machine_id' => $dtr?->machine_id ?: 'TIMEKEEPING_CORRECTION',
        ];

        if (in_array($request->request_type, [PayrollDtrCorrectionRequest::TYPE_TIME_IN, PayrollDtrCorrectionRequest::TYPE_BOTH], true)) {
            $values['timein_am'] = $request->requested_time_in;
        }

        if (in_array($request->request_type, [PayrollDtrCorrectionRequest::TYPE_TIME_OUT, PayrollDtrCorrectionRequest::TYPE_BOTH], true)) {
            $values['timeout_pm'] = $request->requested_time_out;
            $values['timeout_nextday'] = $request->requested_timeout_nextday
                ? CarbonImmutable::parse($date.' '.$request->requested_time_out)->addDay()->toDateTimeString()
                : null;
        }

        return EmployeeDtr::updateOrCreate(
            ['emp_id' => $request->emp_id, 'dtr_date' => $date],
            $values
        );
    }

    private function dtrSnapshot(?EmployeeDtr $dtr): ?array
    {
        if (! $dtr) {
            return null;
        }

        return [
            'dtr_id' => $dtr->dtr_id,
            'emp_id' => $dtr->emp_id,
            'dtr_date' => $dtr->dtr_date?->toDateString(),
            'timein_am' => $dtr->timein_am,
            'timeout_am' => $dtr->timeout_am,
            'timein_pm' => $dtr->timein_pm,
            'timeout_pm' => $dtr->timeout_pm,
            'timeout_nextday' => $dtr->timeout_nextday?->toDateTimeString(),
            'machine_id' => $dtr->machine_id,
        ];
    }

    private function employeeOrFail(string $employeeId): Employee
    {
        $employee = Employee::query()
            ->where('emp_id', $employeeId)
            ->where('is_active', 'Y')
            ->first();

        if (! $employee) {
            throw ValidationException::withMessages(['emp_id' => 'Active employee not found.']);
        }

        return $employee;
    }

    private function sameDepartmentApproverOrFail(Employee $employee, string $approverId): Employee
    {
        $approver = Employee::query()
            ->where('emp_id', $approverId)
            ->where('is_active', 'Y')
            ->first();

        if (! $approver || $approver->emp_id === $employee->emp_id || (int) $approver->department_id !== (int) $employee->department_id) {
            throw ValidationException::withMessages([
                'approver_emp_id' => 'The approver must be an active employee from the same department and cannot be the requester.',
            ]);
        }

        return $approver;
    }

    private function assertActorCanRequestFor(Employee $employee, string $requestedBy): void
    {
        if ($employee->emp_id !== $requestedBy) {
            throw ValidationException::withMessages([
                'requested_by_emp_id' => 'Employees can only submit DTR correction requests for their own DTR.',
            ]);
        }
    }

    private function assertCorrectionPayload(array $data): void
    {
        $type = $data['request_type'];

        if (in_array($type, [PayrollDtrCorrectionRequest::TYPE_TIME_IN, PayrollDtrCorrectionRequest::TYPE_BOTH], true) && blank($data['requested_time_in'] ?? null)) {
            throw ValidationException::withMessages(['requested_time_in' => 'A requested time in is required.']);
        }

        if (in_array($type, [PayrollDtrCorrectionRequest::TYPE_TIME_OUT, PayrollDtrCorrectionRequest::TYPE_BOTH], true) && blank($data['requested_time_out'] ?? null)) {
            throw ValidationException::withMessages(['requested_time_out' => 'A requested time out is required.']);
        }
    }

    private function assertPeriodOpen(Employee $employee, string $date): void
    {
        $locked = PayrollMraReport::query()
            ->where('department_id', $employee->department_id)
            ->whereDate('period_start', '<=', $date)
            ->whereDate('period_end', '>=', $date)
            ->exists();

        if ($locked) {
            throw ValidationException::withMessages([
                'dtr_date' => 'This DTR date is already covered by an MRA report and cannot be corrected.',
            ]);
        }
    }

    private function assertNoPendingRequest(string $employeeId, string $date): void
    {
        $exists = PayrollDtrCorrectionRequest::query()
            ->where('emp_id', $employeeId)
            ->whereDate('dtr_date', $date)
            ->where('status', PayrollDtrCorrectionRequest::STATUS_PENDING)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'dtr_date' => 'A pending DTR correction request already exists for this date.',
            ]);
        }
    }

    private function assertPending(PayrollDtrCorrectionRequest $request): void
    {
        if ($request->status !== PayrollDtrCorrectionRequest::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => 'Only pending DTR correction requests can be acted on.',
            ]);
        }
    }
}
