<?php

namespace App\Livewire\Timekeeping;

use App\Models\Hris\Department;
use App\Models\Hris\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Read-only fingerprint registration status from legacy tbl_employee columns.
 * Does not expose template blobs — only whether fingerprint_1 / fingerprint_2 are present.
 */
class FingerprintRegistrationStatus extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    public ?int $departmentId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDepartmentId(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $columnsExist = Schema::connection('hris')->hasColumn('tbl_employee', 'fingerprint_1')
            && Schema::connection('hris')->hasColumn('tbl_employee', 'fingerprint_2');

        $query = Employee::query()
            ->with('department')
            ->where('is_active', 'Y')
            ->orderBy('lastname')
            ->orderBy('firstname');

        if ($this->departmentId) {
            $query->where('department_id', $this->departmentId);
        }

        if (filled($this->search)) {
            $term = '%'.trim($this->search).'%';
            $query->where(function ($q) use ($term) {
                $q->where('emp_id', 'like', $term)
                    ->orWhere('firstname', 'like', $term)
                    ->orWhere('lastname', 'like', $term);
            });
        }

        $summary = [
            'total_active' => 0,
            'registered' => 0,
            'partial' => 0,
            'missing' => 0,
            'columns_exist' => $columnsExist,
        ];

        if ($columnsExist) {
            $base = Employee::query()->where('is_active', 'Y');
            if ($this->departmentId) {
                $base->where('department_id', $this->departmentId);
            }

            $summary['total_active'] = (clone $base)->count();
            $summary['registered'] = (clone $base)
                ->whereNotNull('fingerprint_1')
                ->whereNotNull('fingerprint_2')
                ->count();
            $summary['partial'] = (clone $base)
                ->where(function ($q) {
                    $q->where(function ($inner) {
                        $inner->whereNotNull('fingerprint_1')->whereNull('fingerprint_2');
                    })->orWhere(function ($inner) {
                        $inner->whereNull('fingerprint_1')->whereNotNull('fingerprint_2');
                    });
                })
                ->count();
            $summary['missing'] = (clone $base)
                ->whereNull('fingerprint_1')
                ->whereNull('fingerprint_2')
                ->count();

            if ($this->statusFilter === 'registered') {
                $query->whereNotNull('fingerprint_1')->whereNotNull('fingerprint_2');
            } elseif ($this->statusFilter === 'partial') {
                $query->where(function ($q) {
                    $q->where(function ($inner) {
                        $inner->whereNotNull('fingerprint_1')->whereNull('fingerprint_2');
                    })->orWhere(function ($inner) {
                        $inner->whereNull('fingerprint_1')->whereNotNull('fingerprint_2');
                    });
                });
            } elseif ($this->statusFilter === 'missing') {
                $query->whereNull('fingerprint_1')->whereNull('fingerprint_2');
            }

            $employees = $query
                ->select([
                    'emp_id',
                    'firstname',
                    'middlename',
                    'lastname',
                    'department_id',
                    DB::raw('CASE WHEN fingerprint_1 IS NULL THEN 0 ELSE 1 END as has_fingerprint_1'),
                    DB::raw('CASE WHEN fingerprint_2 IS NULL THEN 0 ELSE 1 END as has_fingerprint_2'),
                ])
                ->paginate(25);
        } else {
            $employees = $query->select([
                'emp_id',
                'firstname',
                'middlename',
                'lastname',
                'department_id',
            ])->paginate(25);
        }

        return view('livewire.timekeeping.fingerprint-registration-status', [
            'employees' => $employees,
            'departments' => Department::query()->orderBy('department')->get(),
            'summary' => $summary,
        ]);
    }
}
