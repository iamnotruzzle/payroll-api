<?php

namespace App\Livewire\Setup;

use App\Models\Hris\SalaryGrade;
use App\Services\Hris\HrisReferenceManagementService;
use Livewire\Component;

class SalaryScheduleSetup extends Component
{
    public bool $showForm = false;

    public string $selection = '';

    public int $tranche = 1;

    public string $effectiveDate = '';

    public array $matrix = [];

    public function mount(): void
    {
        $this->authorizeAccess();
        $this->effectiveDate = now()->toDateString();
    }

    public function updatedSelection(string $value): void
    {
        if ($value === '') {
            $this->matrix = [];

            return;
        } [$tranche,$date] = array_pad(explode('|', $value, 2), 2, null);
        if (! is_numeric($tranche) || ! $date) {
            $this->addError('selection', 'Invalid salary schedule.');

            return;
        } $this->tranche = (int) $tranche;
        $this->effectiveDate = $date;
        $this->load();
        $this->showForm = true;
    }

    public function create(): void
    {
        $this->selection = '';
        $this->tranche = 1;
        $this->effectiveDate = now()->toDateString();
        $this->matrix = [];
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetValidation();
    }

    public function load(): void
    {
        $this->validate(['tranche' => 'integer|min:1|max:99', 'effectiveDate' => 'required|date']);
        $this->matrix = [];
        foreach (SalaryGrade::where('tranche_number', $this->tranche)->whereDate('effectivity_date', $this->effectiveDate)->get() as $row) {
            $this->matrix[$row->salary_grade][$row->step_increment] = (string) $row->salary;
        }
    }

    public function publish(HrisReferenceManagementService $service): void
    {
        $this->validate(['tranche' => 'required|integer|min:1|max:99', 'effectiveDate' => 'required|date']);
        $count = $service->publishSalarySchedule($this->tranche, $this->effectiveDate, $this->matrix);
        $this->selection = $this->tranche.'|'.$this->effectiveDate;
        $this->showForm = false;
        session()->flash('status', "Published {$count} salary cells. Prior schedules and finalized payrolls remain unchanged.");
    }

    public function render()
    {
        return view('livewire.setup.salary-schedule-setup', ['schedules' => SalaryGrade::select('tranche_number', 'effectivity_date')->distinct()->orderByDesc('effectivity_date')->get()]);
    }

    private function authorizeAccess(): void
    {
        abort_unless(auth()->user()?->can('employees.manage') || auth()->user()?->can('payroll.configure'), 403);
    }
}
