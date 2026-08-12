<?php

namespace App\Livewire\Schedule;

use App\Models\Hris\Employee;
use App\Models\Schedule\ScheduleUnit;
use App\Models\Schedule\ScheduleUserUnit;
use App\Services\Schedule\ScheduleScopeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ScheduleUnits extends Component
{
    public ?int $editingId = null;

    public string $search = '';

    public string $code = '';

    public string $name = '';

    public string $unit_type = 'section';

    public int $sort_order = 0;

    public bool $is_active = true;

    public ?string $description = null;

    /** @var array<string, list<int>> */
    public array $handlerUnits = [];

    public function mount(ScheduleScopeService $scopeService): void
    {
        abort_unless(auth()->user()?->can('schedule.view'), 403);

        $profile = $scopeService->profileForDepartment($this->departmentId());
        abort_unless($profile->uses_units, 404);

        if (! $scopeService->isCnoDepartment($this->departmentId())) {
            $this->unit_type = 'area';
        }

        $this->loadHandlerMatrix();
    }

    public function render(ScheduleScopeService $scopeService)
    {
        $departmentId = $this->departmentId();

        $units = ScheduleUnit::query()
            ->where('department_id', $departmentId)
            ->when($this->search !== '', function ($query) {
                $query->where(function ($inner) {
                    $inner->where('code', 'like', "%{$this->search}%")
                        ->orWhere('name', 'like', "%{$this->search}%");
                });
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $schedulers = Employee::query()
            ->with('userAccount')
            ->where('department_id', $departmentId)
            ->where('is_active', 'Y')
            ->whereHas('userAccount')
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->get(['emp_id', 'firstname', 'middlename', 'lastname'])
            ->filter(function (Employee $employee) {
                $account = $employee->userAccount;

                return $account
                    && (
                        $account->can('schedule.view')
                        || $account->can('schedule.manage')
                        || $account->can('schedule.approve')
                    );
            })
            ->values();

        $isCno = $scopeService->isCnoDepartment($departmentId);

        return view('livewire.schedule.schedule-units', [
            'department' => auth()->user()?->employee?->department,
            'units' => $units,
            'schedulers' => $schedulers,
            'unitTypes' => ScheduleUnit::TYPES,
            'canManage' => (bool) auth()->user()?->can('schedule.manage'),
            'profile' => $scopeService->profileForDepartment($departmentId),
            'isCno' => $isCno,
            'unitNoun' => $scopeService->unitNoun($departmentId),
            'unitNounPlural' => $scopeService->unitNoun($departmentId, true),
        ]);
    }

    public function edit(int $id): void
    {
        $unit = $this->findUnit($id);
        $this->editingId = $unit->id;
        $this->code = $unit->code;
        $this->name = $unit->name;
        $this->unit_type = $unit->unit_type;
        $this->sort_order = (int) $unit->sort_order;
        $this->is_active = (bool) $unit->is_active;
        $this->description = $unit->description;
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can('schedule.manage'), 403);

        $departmentId = $this->departmentId();
        abort_unless($departmentId !== null, 404);

        $data = $this->validate([
            'code' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:255'],
            'unit_type' => ['required', Rule::in(array_keys(ScheduleUnit::TYPES))],
            'sort_order' => ['integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string'],
        ]);

        $data['department_id'] = $departmentId;
        $data['code'] = strtoupper(trim($data['code']));

        $duplicate = ScheduleUnit::query()
            ->where('department_id', $departmentId)
            ->where('code', $data['code'])
            ->when($this->editingId, fn ($query) => $query->whereKeyNot($this->editingId))
            ->exists();

        if ($duplicate) {
            $this->addError('code', 'A unit with this code already exists for the department.');

            return;
        }

        if ($this->editingId) {
            $this->findUnit($this->editingId)->update($data);
        } else {
            ScheduleUnit::query()->create($data);
        }

        session()->flash('status', 'Schedule '.strtolower($this->unitNounForFlash()).' saved.');
        $this->resetForm();
        $this->loadHandlerMatrix();
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->code = '';
        $this->name = '';
        $this->unit_type = app(ScheduleScopeService::class)->isCnoDepartment($this->departmentId()) ? 'section' : 'area';
        $this->sort_order = 0;
        $this->is_active = true;
        $this->description = null;
    }

    public function saveHandlers(): void
    {
        abort_unless(auth()->user()?->can('schedule.manage'), 403);

        $departmentId = $this->departmentId();
        abort_unless($departmentId !== null, 404);

        $validUnitIds = ScheduleUnit::query()
            ->where('department_id', $departmentId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        DB::connection('payroll_scheduler')->transaction(function () use ($validUnitIds): void {
            foreach ($this->handlerUnits as $empId => $selectedUnitIds) {
                $empId = (string) $empId;
                $selected = collect($selectedUnitIds)
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn (int $id) => in_array($id, $validUnitIds, true))
                    ->unique()
                    ->values();

                ScheduleUserUnit::query()
                    ->where('emp_id', $empId)
                    ->whereIn('schedule_unit_id', $validUnitIds)
                    ->delete();

                foreach ($selected as $unitId) {
                    ScheduleUserUnit::query()->create([
                        'emp_id' => $empId,
                        'schedule_unit_id' => $unitId,
                    ]);
                }
            }
        });

        session()->flash('status', 'Handled units updated. Schedulers with no units selected keep full department scope.');
        $this->loadHandlerMatrix();
    }

    private function loadHandlerMatrix(): void
    {
        $departmentId = $this->departmentId();
        if ($departmentId === null) {
            $this->handlerUnits = [];

            return;
        }

        $unitIds = ScheduleUnit::query()
            ->where('department_id', $departmentId)
            ->pluck('id');

        $rows = ScheduleUserUnit::query()
            ->whereIn('schedule_unit_id', $unitIds)
            ->get()
            ->groupBy('emp_id');

        $matrix = [];
        foreach ($rows as $empId => $assignments) {
            $matrix[(string) $empId] = $assignments->pluck('schedule_unit_id')->map(fn ($id) => (int) $id)->values()->all();
        }

        $this->handlerUnits = $matrix;
    }

    private function findUnit(int $id): ScheduleUnit
    {
        return ScheduleUnit::query()
            ->where('department_id', $this->departmentId())
            ->findOrFail($id);
    }

    private function unitNounForFlash(): string
    {
        return app(ScheduleScopeService::class)->unitNoun($this->departmentId());
    }

    private function departmentId(): ?int
    {
        return auth()->user()?->employee?->department_id;
    }
}
