<?php

namespace App\Livewire\Schedule;

use App\Models\Schedule\ScheduleDepartmentProfile;
use App\Services\Schedule\ScheduleScopeService;
use Livewire\Component;

class DepartmentProfiles extends Component
{
    public bool $uses_units = false;

    public bool $uses_floaters = false;

    public bool $uses_on_call = false;

    public bool $uses_swaps = false;

    public bool $uses_census = false;

    public function mount(ScheduleScopeService $scopeService): void
    {
        abort_unless(auth()->user()?->can('schedule.manage') || auth()->user()?->can('schedule.view'), 403);

        $profile = $scopeService->profileForDepartment($this->departmentId());
        $this->uses_units = (bool) $profile->uses_units;
        $this->uses_floaters = (bool) $profile->uses_floaters;
        $this->uses_on_call = (bool) $profile->uses_on_call;
        $this->uses_swaps = (bool) $profile->uses_swaps;
        $this->uses_census = (bool) $profile->uses_census;
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can('schedule.manage'), 403);

        $departmentId = $this->departmentId();
        abort_unless($departmentId !== null, 404);

        $data = $this->validate([
            'uses_units' => ['boolean'],
            'uses_floaters' => ['boolean'],
            'uses_on_call' => ['boolean'],
            'uses_swaps' => ['boolean'],
            'uses_census' => ['boolean'],
        ]);

        ScheduleDepartmentProfile::forDepartment($departmentId)->saveProfile($data);

        session()->flash('status', 'Department schedule profile saved. Optional features follow these flags in navigation and screens.');
    }

    public function render(ScheduleScopeService $scopeService)
    {
        $departmentId = $this->departmentId();
        $isCno = $scopeService->isCnoDepartment($departmentId);

        return view('livewire.schedule.department-profiles', [
            'department' => auth()->user()?->employee?->department,
            'profile' => $scopeService->profileForDepartment($departmentId),
            'canManage' => (bool) auth()->user()?->can('schedule.manage'),
            'isCno' => $isCno,
            'modeLabel' => $scopeService->modeLabelForDepartment($departmentId),
            'unitNoun' => $scopeService->unitNoun($departmentId, true),
            'cnoDivisionId' => $scopeService->divisionService()->cnoDivisionId(),
        ]);
    }

    private function departmentId(): ?int
    {
        return auth()->user()?->employee?->department_id;
    }
}
