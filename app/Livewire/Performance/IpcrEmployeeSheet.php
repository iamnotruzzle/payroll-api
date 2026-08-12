<?php

namespace App\Livewire\Performance;

use App\Models\Hris\Employee;
use App\Models\Hris\IpcrEmployee;
use App\Models\Hris\IpcrMfo;
use App\Models\Hris\IpcrMfoType;
use App\Models\Hris\IpcrPeriod;
use App\Services\Hris\IpcrService;
use Livewire\Component;

class IpcrEmployeeSheet extends Component
{
    public string $empId = '';

    public int $periodId = 0;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $mfoText = '';

    public ?int $mfoId = null;

    public int $functionTypeId = 2;

    public string $target = '';

    public string $accomplishment = '';

    public string $accomplishmentDate = '';

    public ?int $ratingIpcrId = null;

    public string $quality = '3';

    public string $effectiveness = '3';

    public string $timeliness = '3';

    public string $ratingRemarks = '';

    public function mount(string $empId, int $periodId): void
    {
        $this->empId = $empId;
        $this->periodId = $periodId;
    }

    public function openCreate(): void
    {
        abort_unless(auth()->user()?->can('performance.manage'), 403);
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $ipcrId): void
    {
        abort_unless(auth()->user()?->can('performance.manage'), 403);
        $row = IpcrEmployee::query()->with('mfoSet.mfo')->findOrFail($ipcrId);
        abort_unless((string) $row->emp_id === (string) $this->empId, 403);

        $this->editingId = $row->id;
        $this->mfoId = (int) $row->mfo_set_id ? (int) ($row->mfoSet?->mfo_id) : null;
        $this->mfoText = (string) ($row->mfoSet?->mfo?->mfo ?? '');
        $this->functionTypeId = (int) ($row->mfoSet?->mfo?->function_type_id ?? 2);
        $this->target = (string) $row->target;
        $this->accomplishment = (string) ($row->accomplishment ?? '');
        $this->accomplishmentDate = optional($row->accomplishment_date)?->toDateString() ?: '';
        $this->showForm = true;
        $this->resetValidation();
    }

    public function save(IpcrService $ipcrService): void
    {
        abort_unless(auth()->user()?->can('performance.manage'), 403);

        $data = $this->validate([
            'mfoText' => ['required_without:mfoId', 'nullable', 'string', 'max:5000'],
            'mfoId' => ['nullable', 'integer', 'exists:hris.ipcr_mfos,id'],
            'functionTypeId' => ['required', 'integer', 'exists:hris.ipcr_mfo_types,id'],
            'target' => ['required', 'string', 'max:5000'],
            'accomplishment' => ['nullable', 'string', 'max:5000'],
            'accomplishmentDate' => ['nullable', 'date'],
        ]);

        $actor = (string) (auth()->user()?->emp_id ?? auth()->user()?->username ?? 'system');

        $ipcrService->upsertTarget($this->empId, $this->periodId, [
            'mfo_id' => $data['mfoId'] ?? 0,
            'mfo' => $data['mfoText'] ?? '',
            'function_type_id' => (int) $data['functionTypeId'],
            'target' => $data['target'],
            'accomplishment' => $data['accomplishment'] ?: null,
            'accomplishment_date' => $data['accomplishmentDate'] ?: null,
        ], $actor);

        $this->showForm = false;
        $this->resetForm();
        session()->flash('status', 'IPCR target saved.');
    }

    public function openRating(int $ipcrId): void
    {
        abort_unless(
            auth()->user()?->can('performance.manage')
            || auth()->user()?->can('performance.approve'),
            403
        );

        $row = IpcrEmployee::query()->with('ratings')->findOrFail($ipcrId);
        abort_unless((string) $row->emp_id === (string) $this->empId, 403);

        $actor = (string) (auth()->user()?->emp_id ?? '');
        $mine = $row->ratings->firstWhere('rating_by', $actor);

        $this->ratingIpcrId = $ipcrId;
        $this->quality = (string) ($mine?->quality ?? '3');
        $this->effectiveness = (string) ($mine?->effectiveness ?? '3');
        $this->timeliness = (string) ($mine?->timeliness ?? '3');
        $this->ratingRemarks = (string) ($mine?->remarks ?? '');
    }

    public function saveRating(IpcrService $ipcrService): void
    {
        abort_unless(
            auth()->user()?->can('performance.manage')
            || auth()->user()?->can('performance.approve'),
            403
        );

        $data = $this->validate([
            'ratingIpcrId' => ['required', 'integer'],
            'quality' => ['required', 'in:1,2,3,4,5'],
            'effectiveness' => ['required', 'in:1,2,3,4,5'],
            'timeliness' => ['required', 'in:1,2,3,4,5'],
            'ratingRemarks' => ['nullable', 'string', 'max:255'],
        ]);

        $row = IpcrEmployee::query()->findOrFail($data['ratingIpcrId']);
        abort_unless((string) $row->emp_id === (string) $this->empId, 403);

        $actor = (string) (auth()->user()?->emp_id ?? auth()->user()?->username ?? 'system');
        $ipcrService->saveRating($row, $actor, [
            'quality' => $data['quality'],
            'effectiveness' => $data['effectiveness'],
            'timeliness' => $data['timeliness'],
            'remarks' => $data['ratingRemarks'] ?: null,
        ]);

        $this->ratingIpcrId = null;
        session()->flash('status', 'IPCR rating saved.');
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function closeRating(): void
    {
        $this->ratingIpcrId = null;
    }

    public function render(IpcrService $ipcrService)
    {
        $user = auth()->user();
        abort_unless(
            $user?->can('performance.view')
            || $user?->can('performance.manage')
            || $user?->can('performance.approve')
            || ($user?->can('self-service.ipcr') && (string) $user->emp_id === (string) $this->empId),
            403
        );

        $employee = Employee::query()->with(['department', 'position'])->where('emp_id', $this->empId)->firstOrFail();
        $period = IpcrPeriod::query()->findOrFail($this->periodId);
        $targets = $ipcrService->targetsForEmployeePeriod($this->empId, $this->periodId);

        return view('livewire.performance.ipcr-employee-sheet', [
            'employee' => $employee,
            'period' => $period,
            'targets' => $targets,
            'mfoTypes' => IpcrMfoType::query()->orderBy('id')->get(),
            'existingMfos' => IpcrMfo::query()->with('functionType')->orderByDesc('id')->limit(50)->get(),
            'canManage' => (bool) $user?->can('performance.manage'),
            'canRate' => (bool) ($user?->can('performance.manage') || $user?->can('performance.approve')),
        ]);
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->mfoText = '';
        $this->mfoId = null;
        $this->functionTypeId = 2;
        $this->target = '';
        $this->accomplishment = '';
        $this->accomplishmentDate = '';
        $this->resetValidation();
    }
}
