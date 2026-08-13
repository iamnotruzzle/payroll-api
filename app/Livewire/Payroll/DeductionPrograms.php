<?php

namespace App\Livewire\Payroll;

use App\Models\Payroll\PayrollDeduction;
use Livewire\Component;

class DeductionPrograms extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public string $computationType = 'fixed';

    public float $value = 0;

    public int $sortOrder = 0;

    public string $insertAfterColumn = '';

    public string $section = 'other';

    public string $impactType = 'employee_deduction';

    public bool $isRecurring = true;

    public bool $isActive = true;

    public function render()
    {
        return view('livewire.payroll.deduction-programs', [
            'items' => PayrollDeduction::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'computationType' => ['required', 'in:fixed,percentage'],
            'value' => ['required', 'numeric', 'min:0'],
            'sortOrder' => ['required', 'integer', 'min:0', 'max:999'],
            'insertAfterColumn' => ['nullable', 'string', 'max:80'],
            'section' => ['required', 'in:mandatory,other'],
            'impactType' => ['required', 'in:employee_deduction,employer_contribution'],
            'isRecurring' => ['boolean'],
            'isActive' => ['boolean'],
        ]);

        $item = $this->editingId ? PayrollDeduction::findOrFail($this->editingId) : new PayrollDeduction;
        $item->fill([
            'name' => $data['name'],
            'is_percentage' => $data['computationType'] === 'percentage',
            'value' => $data['value'],
            'sort_order' => $data['sortOrder'],
            'insert_after_column' => $data['insertAfterColumn'] ?: null,
            'section' => $data['section'],
            'impact_type' => $data['impactType'],
            'is_recurring' => $data['isRecurring'],
            'is_active' => $data['isActive'],
        ]);
        $item->save();

        $this->dispatch('deduction-programs-changed');

        $this->resetForm();
        session()->flash('status', 'Deduction program saved.');
    }

    public function edit(int $id): void
    {
        $item = PayrollDeduction::findOrFail($id);

        $this->editingId = $item->id;
        $this->name = $item->name;
        $this->computationType = $item->is_percentage ? 'percentage' : 'fixed';
        $this->value = (float) $item->value;
        $this->sortOrder = (int) ($item->sort_order ?? 0);
        $this->insertAfterColumn = (string) ($item->insert_after_column ?? '');
        $this->section = (string) ($item->section ?? 'other');
        $this->impactType = (string) ($item->impact_type ?? 'employee_deduction');
        $this->isRecurring = (bool) ($item->is_recurring ?? true);
        $this->isActive = (bool) $item->is_active;
    }

    public function delete(int $id): void
    {
        PayrollDeduction::findOrFail($id)->delete();
        $this->dispatch('deduction-programs-changed');
        $this->resetForm();
        session()->flash('status', 'Deduction program deleted.');
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->computationType = 'fixed';
        $this->value = 0;
        $this->sortOrder = 0;
        $this->insertAfterColumn = '';
        $this->section = 'other';
        $this->impactType = 'employee_deduction';
        $this->isRecurring = true;
        $this->isActive = true;
    }
}
