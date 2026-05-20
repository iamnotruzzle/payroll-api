<?php

namespace App\Livewire\Payroll;

use App\Models\Payroll\PayrollLoanEntity;
use App\Models\Payroll\PayrollLoanType;
use Livewire\Component;

class LoanReferences extends Component
{
    public ?int $editingEntityId = null;
    public bool $showEntityModal = false;
    public string $entityCode = '';
    public string $entityName = '';
    public int $entitySortOrder = 0;
    public bool $entityIsActive = true;

    public ?int $editingTypeId = null;
    public bool $showTypeModal = false;
    public ?int $selectedEntityId = null;
    public ?int $typeEntityId = null;
    public string $typeCode = '';
    public string $typeName = '';
    public string $reviewGroup = 'Bank Loans';
    public string $reviewColumnKey = '';
    public string $reviewColumnLabel = '';
    public string $matchKeywords = '';
    public int $typeSortOrder = 0;
    public bool $typeIsActive = true;

    public function render()
    {
        $entities = PayrollLoanEntity::query()
            ->withCount('loanTypes')
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();
        $this->selectedEntityId ??= $entities->first()?->id;

        return view('livewire.payroll.loan-references', [
            'entities' => $entities,
            'types' => PayrollLoanType::query()
                ->with('entity')
                ->when($this->selectedEntityId, fn ($query) => $query->where('entity_id', $this->selectedEntityId))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'selectedEntity' => $entities->firstWhere('id', $this->selectedEntityId),
        ]);
    }

    public function selectEntity(int $id): void
    {
        $this->selectedEntityId = $id;
        $this->resetTypeForm(false);
        $this->typeEntityId = $id;
    }

    public function openEntityModal(?int $id = null): void
    {
        $this->resetEntityForm();
        if ($id) {
            $this->editEntity($id);
        }
        $this->showEntityModal = true;
    }

    public function closeEntityModal(): void
    {
        $this->showEntityModal = false;
        $this->resetEntityForm();
    }

    public function openTypeModal(?int $id = null): void
    {
        $this->resetTypeForm();
        if ($id) {
            $this->editType($id);
        }
        $this->showTypeModal = true;
    }

    public function closeTypeModal(): void
    {
        $this->showTypeModal = false;
        $this->resetTypeForm();
    }

    public function saveEntity(): void
    {
        $data = $this->validate([
            'entityCode' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'],
            'entityName' => ['required', 'string', 'max:120'],
            'entitySortOrder' => ['required', 'integer', 'min:0', 'max:9999'],
            'entityIsActive' => ['boolean'],
        ]);

        $item = $this->editingEntityId ? PayrollLoanEntity::findOrFail($this->editingEntityId) : new PayrollLoanEntity();
        $item->fill([
            'code' => strtoupper($data['entityCode']),
            'name' => $data['entityName'],
            'sort_order' => $data['entitySortOrder'],
            'is_active' => $data['entityIsActive'],
        ]);
        $item->save();

        $this->resetEntityForm();
        $this->selectedEntityId = $item->id;
        $this->showEntityModal = false;
        session()->flash('status', 'Loan entity saved.');
    }

    public function editEntity(int $id): void
    {
        $item = PayrollLoanEntity::findOrFail($id);
        $this->editingEntityId = $item->id;
        $this->entityCode = $item->code;
        $this->entityName = $item->name;
        $this->entitySortOrder = (int) $item->sort_order;
        $this->entityIsActive = (bool) $item->is_active;
        $this->selectedEntityId = $item->id;
    }

    public function deleteEntity(int $id): void
    {
        PayrollLoanEntity::findOrFail($id)->delete();
        $this->resetEntityForm();
        $this->selectedEntityId = PayrollLoanEntity::query()->orderBy('sort_order')->value('id');
        session()->flash('status', 'Loan entity deleted.');
    }

    public function saveType(): void
    {
        $data = $this->validate([
            'typeEntityId' => ['required', 'integer'],
            'typeCode' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9_-]+$/'],
            'typeName' => ['required', 'string', 'max:160'],
            'reviewGroup' => ['required', 'string', 'max:80'],
            'reviewColumnKey' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/'],
            'reviewColumnLabel' => ['required', 'string', 'max:120'],
            'matchKeywords' => ['nullable', 'string', 'max:1000'],
            'typeSortOrder' => ['required', 'integer', 'min:0', 'max:9999'],
            'typeIsActive' => ['boolean'],
        ]);

        PayrollLoanEntity::findOrFail($data['typeEntityId']);
        $item = $this->editingTypeId ? PayrollLoanType::findOrFail($this->editingTypeId) : new PayrollLoanType();
        $item->fill([
            'entity_id' => $data['typeEntityId'],
            'code' => strtoupper($data['typeCode']),
            'name' => $data['typeName'],
            'review_group' => $data['reviewGroup'],
            'review_column_key' => $data['reviewColumnKey'],
            'review_column_label' => $data['reviewColumnLabel'],
            'match_keywords' => collect(explode(',', (string) $data['matchKeywords']))
                ->map(fn ($keyword) => trim($keyword))
                ->filter()
                ->values()
                ->all(),
            'sort_order' => $data['typeSortOrder'],
            'is_active' => $data['typeIsActive'],
        ]);
        $item->save();

        $this->resetTypeForm();
        $this->selectedEntityId = $item->entity_id;
        $this->showTypeModal = false;
        session()->flash('status', 'Loan type saved.');
    }

    public function editType(int $id): void
    {
        $item = PayrollLoanType::findOrFail($id);
        $this->editingTypeId = $item->id;
        $this->typeEntityId = $item->entity_id;
        $this->typeCode = $item->code;
        $this->typeName = $item->name;
        $this->reviewGroup = $item->review_group;
        $this->reviewColumnKey = $item->review_column_key;
        $this->reviewColumnLabel = $item->review_column_label;
        $this->matchKeywords = implode(', ', $item->match_keywords ?: []);
        $this->typeSortOrder = (int) $item->sort_order;
        $this->typeIsActive = (bool) $item->is_active;
    }

    public function deleteType(int $id): void
    {
        PayrollLoanType::findOrFail($id)->delete();
        $this->resetTypeForm();
        session()->flash('status', 'Loan type deleted.');
    }

    public function resetEntityForm(): void
    {
        $this->editingEntityId = null;
        $this->entityCode = '';
        $this->entityName = '';
        $this->entitySortOrder = 0;
        $this->entityIsActive = true;
        $this->resetValidation();
    }

    public function resetTypeForm(bool $resetSelectedEntity = true): void
    {
        $this->editingTypeId = null;
        $this->typeEntityId = $resetSelectedEntity
            ? ($this->selectedEntityId ?: PayrollLoanEntity::query()->orderBy('sort_order')->value('id'))
            : $this->selectedEntityId;
        $this->typeCode = '';
        $this->typeName = '';
        $this->reviewGroup = 'Bank Loans';
        $this->reviewColumnKey = '';
        $this->reviewColumnLabel = '';
        $this->matchKeywords = '';
        $this->typeSortOrder = 0;
        $this->typeIsActive = true;
        $this->resetValidation();
    }
}
