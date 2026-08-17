<?php

namespace App\Livewire\Performance;

use App\Models\Hris\Employee;
use App\Models\Hris\IpcrEmployee;
use App\Models\Hris\IpcrPeriod;
use App\Services\Hris\IpcrService;
use Livewire\Component;
use Livewire\WithPagination;

class IpcrPeriods extends Component
{
    use WithPagination;

    public string $search = '';

    public int $perPage = 20;

    public bool $showCreate = false;

    public string $year = '';

    public string $periodType = 'semester';

    public string $period = '1';

    public string $employeeSearch = '';

    public string $selectedEmpId = '';

    public ?int $openPeriodId = null;

    public function mount(): void
    {
        $this->year = (string) now()->year;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        abort_unless(auth()->user()?->can('performance.manage'), 403);
        $this->showCreate = true;
    }

    public function createPeriod(IpcrService $ipcrService): void
    {
        abort_unless(auth()->user()?->can('performance.manage'), 403);

        $data = $this->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'periodType' => ['required', 'in:semester,quarter'],
            'period' => ['required', 'in:1,2,3,4'],
        ]);

        if ($data['periodType'] === 'semester' && ! in_array($data['period'], ['1', '2'], true)) {
            $this->addError('period', 'Semester period must be 1 or 2.');

            return;
        }

        $ipcrService->createPeriod([
            'year' => (int) $data['year'],
            'period_type' => $data['periodType'],
            'period' => $data['period'],
        ]);

        $this->showCreate = false;
        $this->dispatch('erp-overlay-close', name: 'ipcr-period');
        session()->flash('status', 'IPCR period saved.');
    }

    public function openEmployeePicker(int $periodId): void
    {
        abort_unless(
            auth()->user()?->can('performance.view')
            || auth()->user()?->can('performance.manage'),
            403
        );
        $this->openPeriodId = $periodId;
        $this->selectedEmpId = '';
        $this->employeeSearch = '';
    }

    public function goToEmployee(): void
    {
        $this->validate([
            'openPeriodId' => ['required', 'integer'],
            'selectedEmpId' => ['required', 'string', 'exists:hris.tbl_employee,emp_id'],
        ]);

        $this->redirect(route('performance.employee', [
            'empId' => $this->selectedEmpId,
            'periodId' => $this->openPeriodId,
        ]), navigate: true);
    }

    public function render()
    {
        abort_unless(
            auth()->user()?->can('performance.view')
            || auth()->user()?->can('performance.manage')
            || auth()->user()?->can('performance.approve'),
            403
        );

        $periods = IpcrPeriod::query()
            ->when($this->search !== '', function ($builder) {
                $search = trim($this->search);
                $builder->where(function ($inner) use ($search) {
                    $inner->where('year', 'like', "%{$search}%")
                        ->orWhere('period_type', 'like', "%{$search}%")
                        ->orWhere('period', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('year')
            ->orderBy('period_type')
            ->orderBy('period')
            ->paginate($this->perPage);

        $targetCounts = IpcrEmployee::query()
            ->selectRaw('ipcr_mfo_sets.period_id, count(distinct ipcr_employees.emp_id) as employee_count')
            ->join('ipcr_mfo_sets', 'ipcr_mfo_sets.id', '=', 'ipcr_employees.mfo_set_id')
            ->whereNull('ipcr_employees.deleted_at')
            ->groupBy('ipcr_mfo_sets.period_id')
            ->pluck('employee_count', 'period_id');

        return view('livewire.performance.ipcr-periods', [
            'periods' => $periods,
            'targetCounts' => $targetCounts,
            'employees' => $this->employeeOptions(),
            'canManage' => (bool) auth()->user()?->can('performance.manage'),
        ]);
    }

    private function employeeOptions()
    {
        $search = trim($this->employeeSearch);

        return Employee::query()
            ->select(['emp_id', 'firstname', 'middlename', 'lastname', 'extension', 'department_id', 'is_active'])
            ->with('department')
            ->where('is_active', 'Y')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('emp_id', 'like', "%{$search}%")
                        ->orWhere('firstname', 'like', "%{$search}%")
                        ->orWhere('lastname', 'like', "%{$search}%");
                });
            })
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->limit(40)
            ->get();
    }
}
