<?php

namespace App\Livewire\SelfService;

use App\Models\Hris\IpcrEmployee;
use App\Models\Hris\IpcrPeriod;
use App\Services\Hris\IpcrService;
use Livewire\Component;

class MyIpcr extends Component
{
    public string $empId = '';

    public ?int $selectedPeriodId = null;

    public function mount(?string $empId = null): void
    {
        abort_unless(
            auth()->user()?->can('self-service.ipcr')
            || auth()->user()?->can('performance.view')
            || auth()->user()?->can('self-service.access'),
            403
        );

        $this->empId = (string) ($empId ?: auth()->user()?->emp_id ?? '');
        abort_unless($this->empId !== '', 404);
    }

    public function selectPeriod(int $periodId): void
    {
        $this->selectedPeriodId = $periodId;
    }

    public function render(IpcrService $ipcrService)
    {
        $periodIds = IpcrEmployee::query()
            ->where('emp_id', $this->empId)
            ->whereHas('mfoSet')
            ->with('mfoSet')
            ->get()
            ->pluck('mfoSet.period_id')
            ->filter()
            ->unique()
            ->values();

        $periods = IpcrPeriod::query()
            ->whereIn('id', $periodIds)
            ->orderByDesc('year')
            ->orderBy('period')
            ->get();

        if ($this->selectedPeriodId === null && $periods->isNotEmpty()) {
            $this->selectedPeriodId = (int) $periods->first()->id;
        }

        $targets = $this->selectedPeriodId
            ? $ipcrService->targetsForEmployeePeriod($this->empId, $this->selectedPeriodId)
            : collect();

        return view('livewire.self-service.my-ipcr', [
            'periods' => $periods,
            'targets' => $targets,
            'selectedPeriod' => $this->selectedPeriodId
                ? IpcrPeriod::query()->find($this->selectedPeriodId)
                : null,
        ]);
    }
}
