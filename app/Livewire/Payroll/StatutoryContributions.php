<?php

namespace App\Livewire\Payroll;

use App\Models\Payroll\PayrollStatutoryContribution;
use App\Models\Payroll\PayrollStatutoryContributionBracket;
use App\Rules\Payroll\NoOverlappingStatutoryContributionBracket;
use Illuminate\Validation\Rule;
use Livewire\Component;

class StatutoryContributions extends Component
{
    public ?int $selectedContributionId = null;

    public ?int $editingContributionId = null;

    public ?int $editingBracketId = null;

    public bool $showContributionModal = false;

    public bool $showBracketModal = false;

    public string $code = '';

    public string $name = '';

    public bool $isActive = true;

    public bool $splitAcrossCuts = false;

    public bool $isMpf = false;

    public ?string $remarks = null;

    public ?string $effectiveStart = null;

    public ?string $effectiveEnd = null;

    public float $minSalary = 0;

    public ?float $maxSalary = null;

    public float $employeeRate = 0;

    public float $employerRate = 0;

    public ?float $employeeCap = null;

    public ?float $employerCap = null;

    public ?string $bracketRemarks = null;

    public function mount(): void
    {
        $this->selectedContributionId = PayrollStatutoryContribution::query()
            ->orderBy('name')
            ->value('id');
    }

    public function selectContribution(int $id): void
    {
        $this->selectedContributionId = $id;
        $this->resetBracketForm();
    }

    public function createContribution(): void
    {
        $this->resetContributionForm();
        $this->showContributionModal = true;
    }

    public function createBracket(): void
    {
        $this->resetBracketForm();
        $this->showBracketModal = true;
    }

    public function saveContribution(): void
    {
        $data = $this->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('payroll.payroll_statutory_contributions', 'code')->ignore($this->editingContributionId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'isActive' => ['boolean'],
            'splitAcrossCuts' => ['boolean'],
            'isMpf' => ['boolean'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $contribution = $this->editingContributionId
            ? PayrollStatutoryContribution::findOrFail($this->editingContributionId)
            : new PayrollStatutoryContribution;

        $contribution->fill([
            'code' => $data['code'],
            'name' => $data['name'],
            'is_active' => $data['isActive'],
            'split_across_cuts' => $data['splitAcrossCuts'],
            'is_mpf' => $data['isMpf'],
            'remarks' => $data['remarks'],
        ]);
        $contribution->save();

        $this->selectedContributionId = $contribution->id;
        $this->resetContributionForm();
        $this->showContributionModal = false;
        session()->flash('status', 'Statutory contribution saved.');
    }

    public function editContribution(int $id): void
    {
        $contribution = PayrollStatutoryContribution::findOrFail($id);

        $this->selectedContributionId = $contribution->id;
        $this->editingContributionId = $contribution->id;
        $this->code = $contribution->code;
        $this->name = $contribution->name;
        $this->isActive = (bool) $contribution->is_active;
        $this->splitAcrossCuts = (bool) $contribution->split_across_cuts;
        $this->isMpf = (bool) $contribution->is_mpf;
        $this->remarks = $contribution->remarks;
        $this->showContributionModal = true;
    }

    public function deleteContribution(int $id): void
    {
        $contribution = PayrollStatutoryContribution::findOrFail($id);
        $contribution->brackets()->delete();
        $contribution->delete();

        $this->selectedContributionId = PayrollStatutoryContribution::query()
            ->orderBy('name')
            ->value('id');
        $this->resetContributionForm();
        $this->resetBracketForm();
        $this->showContributionModal = false;
        session()->flash('status', 'Statutory contribution deleted.');
    }

    public function saveBracket(): void
    {
        $this->validate([
            'selectedContributionId' => ['required', Rule::exists('payroll.payroll_statutory_contributions', 'id')],
        ]);

        $data = $this->validate([
            'effectiveStart' => ['nullable', 'date'],
            'effectiveEnd' => ['nullable', 'date', 'after_or_equal:effectiveStart'],
            'minSalary' => ['required', 'numeric', 'min:0', new NoOverlappingStatutoryContributionBracket],
            'maxSalary' => ['nullable', 'numeric', 'gte:minSalary'],
            'employeeRate' => ['required', 'numeric', 'min:0', 'max:1'],
            'employerRate' => ['required', 'numeric', 'min:0', 'max:1'],
            'employeeCap' => ['nullable', 'numeric', 'min:0'],
            'employerCap' => ['nullable', 'numeric', 'min:0'],
            'bracketRemarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $bracket = $this->editingBracketId
            ? PayrollStatutoryContributionBracket::findOrFail($this->editingBracketId)
            : new PayrollStatutoryContributionBracket;

        $bracket->fill([
            'statutory_contribution_id' => $this->selectedContributionId,
            'effective_start' => $data['effectiveStart'],
            'effective_end' => $data['effectiveEnd'],
            'min_salary' => $data['minSalary'],
            'max_salary' => $data['maxSalary'],
            'employee_rate' => $data['employeeRate'],
            'employer_rate' => $data['employerRate'],
            'employee_cap' => $data['employeeCap'],
            'employer_cap' => $data['employerCap'],
            'remarks' => $data['bracketRemarks'],
        ]);
        $bracket->save();

        $this->resetBracketForm();
        $this->showBracketModal = false;
        session()->flash('status', 'Contribution bracket saved.');
    }

    public function editBracket(int $id): void
    {
        $bracket = PayrollStatutoryContributionBracket::query()
            ->where('statutory_contribution_id', $this->selectedContributionId)
            ->findOrFail($id);

        $this->editingBracketId = $bracket->id;
        $this->effectiveStart = $bracket->effective_start?->toDateString();
        $this->effectiveEnd = $bracket->effective_end?->toDateString();
        $this->minSalary = (float) $bracket->min_salary;
        $this->maxSalary = $bracket->max_salary !== null ? (float) $bracket->max_salary : null;
        $this->employeeRate = (float) $bracket->employee_rate;
        $this->employerRate = (float) $bracket->employer_rate;
        $this->employeeCap = $bracket->employee_cap !== null ? (float) $bracket->employee_cap : null;
        $this->employerCap = $bracket->employer_cap !== null ? (float) $bracket->employer_cap : null;
        $this->bracketRemarks = $bracket->remarks;
        $this->showBracketModal = true;
    }

    public function deleteBracket(int $id): void
    {
        PayrollStatutoryContributionBracket::query()
            ->where('statutory_contribution_id', $this->selectedContributionId)
            ->findOrFail($id)
            ->delete();

        $this->resetBracketForm();
        $this->showBracketModal = false;
        session()->flash('status', 'Contribution bracket deleted.');
    }

    public function closeContributionModal(): void
    {
        $this->resetContributionForm();
        $this->showContributionModal = false;
    }

    public function closeBracketModal(): void
    {
        $this->resetBracketForm();
        $this->showBracketModal = false;
    }

    public function resetContributionForm(): void
    {
        $this->editingContributionId = null;
        $this->code = '';
        $this->name = '';
        $this->isActive = true;
        $this->splitAcrossCuts = false;
        $this->isMpf = false;
        $this->remarks = null;
    }

    public function resetBracketForm(): void
    {
        $this->editingBracketId = null;
        $this->effectiveStart = null;
        $this->effectiveEnd = null;
        $this->minSalary = 0;
        $this->maxSalary = null;
        $this->employeeRate = 0;
        $this->employerRate = 0;
        $this->employeeCap = null;
        $this->employerCap = null;
        $this->bracketRemarks = null;
    }

    public function render()
    {
        $contributions = PayrollStatutoryContribution::query()
            ->withCount('brackets')
            ->orderBy('name')
            ->get();

        $selectedContribution = $this->selectedContributionId
            ? PayrollStatutoryContribution::query()
                ->with(['brackets' => fn ($query) => $query
                    ->orderByDesc('effective_start')
                    ->orderBy('min_salary')])
                ->find($this->selectedContributionId)
            : null;

        return view('livewire.payroll.statutory-contributions', [
            'contributions' => $contributions,
            'selectedContribution' => $selectedContribution,
        ]);
    }
}
