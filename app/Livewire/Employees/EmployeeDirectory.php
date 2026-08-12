<?php

namespace App\Livewire\Employees;

use App\Support\Hris\EmployeeDirectoryQuery;
use Livewire\Component;
use Livewire\WithPagination;

class EmployeeDirectory extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = 'active';

    public int $perPage = 20;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('employees.view') || auth()->user()?->can('employees.manage'), 403);

        return view('livewire.employees.employee-directory', [
            'employees' => EmployeeDirectoryQuery::paginate($this->search, $this->status, $this->perPage),
        ]);
    }
}
