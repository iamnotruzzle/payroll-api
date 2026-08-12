<?php

namespace App\Livewire\Employees;

use App\Models\Hris\IpcrEmployee;
use Livewire\Component;

class EmployeeIpcrPanel extends Component
{
    public string $empId;

    public function mount(string $empId): void
    {
        abort_unless(
            auth()->user()?->can('performance.view')
            || auth()->user()?->can('performance.manage')
            || auth()->user()?->can('performance.approve'),
            403
        );
        $this->empId = $empId;
    }

    public function render()
    {
        $sheets = IpcrEmployee::query()
            ->with(['mfoSet.period', 'ipcrType'])
            ->where('emp_id', $this->empId)
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        return view('livewire.employees.employee-ipcr-panel', [
            'sheets' => $sheets,
        ]);
    }
}
