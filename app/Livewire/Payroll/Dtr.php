<?php

namespace App\Livewire\Payroll;

use App\Models\Hris\Employee;
use App\Models\Hris\EmployeeDtr;
use App\Models\Payroll\PayrollDtrAdjustment;
use App\Models\Payroll\PayrollDtrLabel;
use App\Models\Payroll\PayrollDtrLabelOption;
use App\Models\Payroll\PayrollMraReport;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Dtr extends Component
{
    public string $from;
    public string $to;
    public array $entries = [];
    public bool $loaded = false;
    public bool $isLocked = false;

    public function mount(): void
    {
        $today = CarbonImmutable::today();
        $this->from = $today->startOfMonth()->toDateString();
        $this->to = $today->endOfMonth()->toDateString();
        $this->loadState();
    }

    public function render()
    {
        return view('livewire.payroll.dtr', [
            'department' => auth()->user()?->employee?->department,
            'employees' => $this->employees(),
            'dates' => $this->dates(),
            'dtrs' => $this->dtrs(),
            'labelOptions' => PayrollDtrLabelOption::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function loadState(): void
    {
        $this->validatePeriod();

        $employeeIds = $this->employees()->pluck('emp_id')->all();
        $labels = PayrollDtrLabel::query()
            ->whereIn('emp_id', $employeeIds)
            ->whereBetween('dtr_date', [$this->from, $this->to])
            ->get()
            ->keyBy(fn ($label) => $this->key($label->emp_id, $label->dtr_date->toDateString()));

        $adjustments = PayrollDtrAdjustment::query()
            ->whereIn('emp_id', $employeeIds)
            ->whereBetween('dtr_date', [$this->from, $this->to])
            ->whereIn('adjustment_type', ['TARDINESS', 'UNDERTIME'])
            ->get()
            ->groupBy(fn ($adjustment) => $this->key($adjustment->emp_id, $adjustment->dtr_date->toDateString()));

        $entries = [];
        foreach ($employeeIds as $employeeId) {
            foreach ($this->dates() as $date) {
                $key = $this->key($employeeId, $date);
                $dateAdjustments = $adjustments->get($key, collect())->keyBy('adjustment_type');
                $label = $labels->get($key);

                $entries[$key] = [
                    'emp_id' => $employeeId,
                    'dtr_date' => $date,
                    'label' => $label?->label ?? '',
                    'label_remarks' => $label?->remarks ?? '',
                    'tardiness' => (int) ($dateAdjustments->get('TARDINESS')?->minutes ?? 0),
                    'undertime' => (int) ($dateAdjustments->get('UNDERTIME')?->minutes ?? 0),
                    'adjustment_remarks' => $dateAdjustments->get('TARDINESS')?->remarks
                        ?? $dateAdjustments->get('UNDERTIME')?->remarks
                        ?? '',
                ];
            }
        }

        $this->entries = $entries;
        $this->isLocked = PayrollMraReport::query()
            ->where('department_id', $this->departmentId())
            ->whereDate('period_start', $this->from)
            ->whereDate('period_end', $this->to)
            ->exists();
        $this->loaded = true;
    }

    public function save(): void
    {
        $this->validatePeriod();

        if ($this->isLocked) {
            session()->flash('status', 'This DTR period already has an MRA report and cannot be edited.');
            return;
        }

        $employeeIds = $this->employees()->pluck('emp_id')->all();
        $encodedBy = auth()->user()?->emp_id ?? 'web';

        DB::connection('payroll')->transaction(function () use ($employeeIds, $encodedBy) {
            foreach ($this->entries as $entry) {
                if (! in_array($entry['emp_id'], $employeeIds, true)) {
                    continue;
                }

                $label = trim((string) ($entry['label'] ?? ''));
                $existingLabel = PayrollDtrLabel::query()
                    ->where('emp_id', $entry['emp_id'])
                    ->whereDate('dtr_date', $entry['dtr_date'])
                    ->first();

                if ($label === '') {
                    $existingLabel?->delete();
                } else {
                    PayrollDtrLabel::updateOrCreate(
                        ['emp_id' => $entry['emp_id'], 'dtr_date' => $entry['dtr_date']],
                        ['label' => $label, 'remarks' => $entry['label_remarks'] ?? null],
                    );
                }

                foreach (['TARDINESS' => 'tardiness', 'UNDERTIME' => 'undertime'] as $type => $field) {
                    $minutes = max(0, (int) ($entry[$field] ?? 0));
                    $existingAdjustment = PayrollDtrAdjustment::query()
                        ->where('emp_id', $entry['emp_id'])
                        ->whereDate('dtr_date', $entry['dtr_date'])
                        ->where('adjustment_type', $type)
                        ->first();

                    if ($minutes <= 0) {
                        $existingAdjustment?->delete();
                        continue;
                    }

                    PayrollDtrAdjustment::updateOrCreate(
                        [
                            'emp_id' => $entry['emp_id'],
                            'dtr_date' => $entry['dtr_date'],
                            'adjustment_type' => $type,
                        ],
                        [
                            'minutes' => $minutes,
                            'remarks' => $entry['adjustment_remarks'] ?? null,
                            'encoded_by' => $encodedBy,
                        ],
                    );
                }
            }
        });

        $this->loadState();
        session()->flash('status', 'DTR labels and adjustments saved.');
    }

    public function key(string $employeeId, string $date): string
    {
        return $employeeId.'|'.$date;
    }

    private function employees()
    {
        return Employee::query()
            ->with('position')
            ->where('department_id', $this->departmentId())
            ->where('is_active', 'Y')
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->get(['emp_id', 'firstname', 'middlename', 'lastname', 'position_id', 'department_id']);
    }

    private function dates(): array
    {
        $this->validatePeriod();

        return collect(CarbonPeriod::create($this->from, $this->to))
            ->map(fn ($date) => $date->toDateString())
            ->all();
    }

    private function dtrs()
    {
        return EmployeeDtr::query()
            ->whereIn('emp_id', $this->employees()->pluck('emp_id')->all())
            ->whereBetween('dtr_date', [$this->from, $this->to])
            ->get()
            ->keyBy(fn ($dtr) => $this->key($dtr->emp_id, $dtr->dtr_date->toDateString()));
    }

    private function validatePeriod(): void
    {
        $this->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        if (CarbonImmutable::parse($this->from)->diffInDays(CarbonImmutable::parse($this->to)) > 31) {
            throw ValidationException::withMessages([
                'to' => 'Use a date range of 32 days or less.',
            ]);
        }
    }

    private function departmentId(): ?int
    {
        return auth()->user()?->employee?->department_id;
    }
}
