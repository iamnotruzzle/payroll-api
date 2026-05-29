<?php

namespace App\Livewire\Payroll;

use App\Models\Payroll\PayrollAdditional;
use Livewire\Component;

class Compensations extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public string $computationType = 'fixed';

    public float $value = 0;

    public ?string $formula = null;

    public ?string $variableName = null;

    public bool $includeInNetPay = true;

    public string $taxTreatment = 'regular_taxable';

    public ?float $annualExemptLimit = null;

    public ?float $supplementalTaxRate = null;

    public int $sortOrder = 0;

    public bool $isActive = true;

    public function render()
    {
        return view('livewire.payroll.compensations', [
            'items' => PayrollAdditional::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'computationType' => ['required', 'in:fixed,percentage,formula'],
            'value' => ['required', 'numeric', 'min:0'],
            'formula' => ['nullable', 'required_if:computationType,formula', 'string', 'max:1000'],
            'variableName' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/'],
            'includeInNetPay' => ['boolean'],
            'taxTreatment' => ['required', 'in:regular_taxable,non_taxable,de_minimis_annual_limit,supplemental_flat_rate'],
            'annualExemptLimit' => ['nullable', 'required_if:taxTreatment,de_minimis_annual_limit', 'numeric', 'min:0'],
            'supplementalTaxRate' => ['nullable', 'required_if:taxTreatment,supplemental_flat_rate', 'numeric', 'min:0', 'max:1'],
            'sortOrder' => ['required', 'integer', 'min:0', 'max:999'],
            'isActive' => ['boolean'],
        ]);

        $item = $this->editingId ? PayrollAdditional::findOrFail($this->editingId) : new PayrollAdditional;
        $item->fill([
            'name' => $data['name'],
            'computation_type' => $data['computationType'],
            'is_percentage' => $data['computationType'] === 'percentage',
            'value' => $data['value'],
            'formula' => $data['formula'],
            'variable_name' => $data['variableName'],
            'include_in_net_pay' => $data['includeInNetPay'],
            'tax_treatment' => $data['taxTreatment'],
            'annual_exempt_limit' => $data['taxTreatment'] === 'de_minimis_annual_limit' ? $data['annualExemptLimit'] : null,
            'supplemental_tax_rate' => $data['taxTreatment'] === 'supplemental_flat_rate' ? $data['supplementalTaxRate'] : null,
            'sort_order' => $data['sortOrder'],
            'is_active' => $data['isActive'],
        ]);
        $item->save();

        $this->resetForm();
        session()->flash('status', 'Compensation rule saved.');
    }

    public function edit(int $id): void
    {
        $item = PayrollAdditional::findOrFail($id);

        $this->editingId = $item->id;
        $this->name = $item->name;
        $this->computationType = $item->computation_type ?: ($item->is_percentage ? 'percentage' : 'fixed');
        $this->value = (float) $item->value;
        $this->formula = $item->formula;
        $this->variableName = $item->variable_name;
        $this->includeInNetPay = $item->include_in_net_pay ?? true;
        $this->taxTreatment = $item->tax_treatment ?: 'regular_taxable';
        $this->annualExemptLimit = $item->annual_exempt_limit !== null ? (float) $item->annual_exempt_limit : null;
        $this->supplementalTaxRate = $item->supplemental_tax_rate !== null ? (float) $item->supplemental_tax_rate : null;
        $this->sortOrder = (int) ($item->sort_order ?? 0);
        $this->isActive = (bool) $item->is_active;
    }

    public function delete(int $id): void
    {
        PayrollAdditional::findOrFail($id)->delete();
        $this->resetForm();
        session()->flash('status', 'Compensation rule deleted.');
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->computationType = 'fixed';
        $this->value = 0;
        $this->formula = null;
        $this->variableName = null;
        $this->includeInNetPay = true;
        $this->taxTreatment = 'regular_taxable';
        $this->annualExemptLimit = null;
        $this->supplementalTaxRate = null;
        $this->sortOrder = 0;
        $this->isActive = true;
    }
}
