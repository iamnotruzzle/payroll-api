<?php

namespace App\Livewire\Payroll;

use App\Models\Payroll\PayrollAdjustmentType;
use Livewire\Component;

class AdjustmentTypes extends Component
{
    public string $name = '';

    public bool $isActive = true;

    public int $sortOrder = 0;

    public ?int $editingId = null;

    public function render()
    {
        return view('livewire.payroll.adjustment-types', [
            'types' => PayrollAdjustmentType::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'isActive' => ['boolean'],
            'sortOrder' => ['required', 'integer', 'min:0'],
        ]);

        PayrollAdjustmentType::updateOrCreate(
            ['id' => $this->editingId],
            [
                'name' => $data['name'],
                'code' => PayrollAdjustmentType::codeFor($data['name']),
                'is_active' => $data['isActive'],
                'sort_order' => $data['sortOrder'],
            ],
        );

        $this->resetForm();
        session()->flash('status', 'Adjustment type saved.');
    }

    public function edit(int $id): void
    {
        $type = PayrollAdjustmentType::findOrFail($id);
        $this->editingId = $type->id;
        $this->name = $type->name;
        $this->isActive = $type->is_active;
        $this->sortOrder = $type->sort_order;
    }

    public function resetForm(): void
    {
        $this->reset(['name', 'editingId']);
        $this->isActive = true;
        $this->sortOrder = 0;
        $this->resetValidation();
    }
}
