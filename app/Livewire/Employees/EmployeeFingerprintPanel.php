<?php

namespace App\Livewire\Employees;

use App\Models\Hris\Employee;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class EmployeeFingerprintPanel extends Component
{
    public string $empId;

    public function mount(string $empId): void
    {
        abort_unless(auth()->user()?->can('employees.view') || auth()->user()?->can('employees.manage'), 403);
        abort_unless(auth()->user()?->can('timekeeping.view') || auth()->user()?->can('timekeeping.manage'), 403);
        $this->empId = $empId;
    }

    public function render()
    {
        $employee = Employee::query()->where('emp_id', $this->empId)->firstOrFail();
        $status = DB::connection('hris')->table('tbl_employee')
            ->where('emp_id', $this->empId)
            ->selectRaw('CASE WHEN OCTET_LENGTH(fingerprint_1) > 0 THEN 1 ELSE 0 END AS finger_1')
            ->selectRaw('CASE WHEN OCTET_LENGTH(fingerprint_2) > 0 THEN 1 ELSE 0 END AS finger_2')
            ->first();

        return view('livewire.employees.employee-fingerprint-panel', [
            'employee' => $employee,
            'finger1' => (bool) ($status?->finger_1 ?? false),
            'finger2' => (bool) ($status?->finger_2 ?? false),
            'canManage' => (bool) auth()->user()?->can('timekeeping.manage') && strtoupper((string) $employee->is_active) === 'Y',
        ]);
    }
}
