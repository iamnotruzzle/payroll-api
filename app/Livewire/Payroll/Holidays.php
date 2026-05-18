<?php

namespace App\Livewire\Payroll;

use App\Models\Payroll\PayrollHoliday;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Holidays extends Component
{
    public ?int $editingId = null;

    public string $search = '';

    public string $yearFilter = '';

    public string $statusFilter = 'active';

    public ?string $holiday_date = null;

    public string $name = '';

    public string $holiday_type = 'REGULAR';

    public string $holiday_scope = 'FULL_DAY';

    public string $label_code = 'HOLIDAY';

    public bool $is_paid = true;

    public bool $is_active = true;

    public function mount(): void
    {
        $this->yearFilter = (string) now()->year;
    }

    public function render()
    {
        return view('livewire.payroll.holidays', [
            'holidays' => PayrollHoliday::query()
                ->when($this->yearFilter !== '', fn ($query) => $query->whereYear('holiday_date', (int) $this->yearFilter))
                ->when($this->statusFilter === 'active', fn ($query) => $query->where('is_active', true))
                ->when($this->statusFilter === 'inactive', fn ($query) => $query->where('is_active', false))
                ->when($this->search, fn ($query) => $query->where(function ($query) {
                    $query->where('name', 'like', "%{$this->search}%")
                        ->orWhere('label_code', 'like', "%{$this->search}%")
                        ->orWhere('holiday_type', 'like', "%{$this->search}%");
                }))
                ->orderByDesc('holiday_date')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function edit(int $id): void
    {
        $holiday = PayrollHoliday::findOrFail($id);

        $this->editingId = $holiday->id;
        $this->holiday_date = $holiday->holiday_date?->format('Y-m-d');
        $this->name = $holiday->name;
        $this->holiday_type = $holiday->holiday_type;
        $this->holiday_scope = $holiday->holiday_scope;
        $this->label_code = $holiday->label_code;
        $this->is_paid = $holiday->is_paid;
        $this->is_active = $holiday->is_active;
    }

    public function save(): void
    {
        $data = $this->validate([
            'holiday_date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:255'],
            'holiday_type' => ['required', 'string', 'max:50'],
            'holiday_scope' => ['required', 'in:FULL_DAY,FIRST_HALF,SECOND_HALF'],
            'label_code' => ['required', 'string', 'max:30'],
            'is_paid' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        $duplicate = PayrollHoliday::query()
            ->whereDate('holiday_date', $data['holiday_date'])
            ->when($this->editingId, fn ($query) => $query->whereKeyNot($this->editingId))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'holiday_date' => 'A holiday already exists for this date.',
            ]);
        }

        $holiday = $this->editingId ? PayrollHoliday::findOrFail($this->editingId) : new PayrollHoliday;
        $holiday->fill($data);
        $holiday->save();

        session()->flash('status', 'Holiday saved.');
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->holiday_date = null;
        $this->name = '';
        $this->holiday_type = 'REGULAR';
        $this->holiday_scope = 'FULL_DAY';
        $this->label_code = 'HOLIDAY';
        $this->is_paid = true;
        $this->is_active = true;
    }
}
