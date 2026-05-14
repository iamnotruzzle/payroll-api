<?php

namespace App\Livewire\Schedule;

use App\Models\Schedule\EmployeeReference;
use App\Services\Schedule\EmployeeReferenceService;
use Livewire\Component;

class EmployeeReferences extends Component
{
    public string $search = '';

    public function render()
    {
        return view('livewire.schedule.employee-references', [
            'references' => EmployeeReference::query()
                ->when($this->search, function ($query) {
                    $query->where(function ($query) {
                        $query->where('hris_employee_id', 'like', "%{$this->search}%")
                            ->orWhere('payroll_employee_id', 'like', "%{$this->search}%")
                            ->orWhere('display_name', 'like', "%{$this->search}%");
                    });
                })
                ->orderBy('display_name')
                ->limit(200)
                ->get(),
        ]);
    }

    public function sync(EmployeeReferenceService $service): void
    {
        $count = $service->syncActiveEmployees();

        session()->flash('status', "{$count} active employee reference(s) synced.");
    }
}
