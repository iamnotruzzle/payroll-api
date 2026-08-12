<?php

namespace App\Livewire\Schedule;

use App\Models\Hris\Department;
use App\Services\Schedule\ScheduleScopeService;
use App\Services\Schedule\Schedulev2BackfillService;
use App\Services\Schedule\Schedulev2SyncService;
use Carbon\CarbonImmutable;
use Livewire\Component;

class Schedulev2Sync extends Component
{
    public string $rangeMode = 'months';

    public string $from = '';

    public string $to = '';

    public int $months_back = 1;

    public int $months_ahead = 1;

    public ?int $department_id = null;

    public bool $filter_division = false;

    public ?array $result = null;

    public ?array $backfillResult = null;

    public string $backfillConfirm = '';

    public bool $backfillWithAssignments = false;

    public ?string $errorMessage = null;

    public function mount(ScheduleScopeService $scopeService): void
    {
        abort_unless(
            auth()->user()?->can('schedule.manage') || auth()->user()?->can('schedule.view'),
            403
        );

        $this->months_back = (int) config('schedule.schedulev2.default_months_back', 1);
        $this->months_ahead = (int) config('schedule.schedulev2.default_months_ahead', 1);

        $departmentId = request()->integer('department_id') ?: auth()->user()?->employee?->department_id;
        $this->department_id = $departmentId !== null && $departmentId !== 0 ? (int) $departmentId : null;

        $today = CarbonImmutable::today();
        $this->from = $today->subMonthsNoOverflow($this->months_back)->startOfMonth()->toDateString();
        $this->to = $today->addMonthsNoOverflow($this->months_ahead)->endOfMonth()->toDateString();

        $fromQuery = request()->query('from');
        $toQuery = request()->query('to');
        if (is_string($fromQuery) && $fromQuery !== '' && is_string($toQuery) && $toQuery !== '') {
            try {
                $this->from = CarbonImmutable::parse($fromQuery)->toDateString();
                $this->to = CarbonImmutable::parse($toQuery)->toDateString();
                $this->rangeMode = request()->query('range') === 'dates' ? 'dates' : $this->rangeMode;
            } catch (\Throwable) {
                // keep month-window defaults
            }
        }

        $filterDivisionQuery = request()->query('filter_division');
        if ($filterDivisionQuery !== null) {
            $this->filter_division = filter_var($filterDivisionQuery, FILTER_VALIDATE_BOOLEAN)
                || (string) $filterDivisionQuery === '1';
        } else {
            $this->filter_division = $scopeService->isCnoDepartment($this->department_id);
        }
    }

    public function updatedRangeMode(): void
    {
        if ($this->rangeMode === 'months') {
            $this->syncDatesFromMonths();
        }
    }

    public function updatedMonthsBack(): void
    {
        if ($this->rangeMode === 'months') {
            $this->syncDatesFromMonths();
        }
    }

    public function updatedMonthsAhead(): void
    {
        if ($this->rangeMode === 'months') {
            $this->syncDatesFromMonths();
        }
    }

    public function updatedDepartmentId(mixed $value): void
    {
        $this->department_id = filled($value) ? (int) $value : null;
    }

    public function dryRun(Schedulev2SyncService $service): void
    {
        $this->runSync($service, dryRun: true);
    }

    public function apply(Schedulev2SyncService $service): void
    {
        $this->runSync($service, dryRun: false);
    }

    public function dryRunBackfill(Schedulev2BackfillService $service): void
    {
        $this->runBackfill($service, dryRun: true);
    }

    public function applyBackfill(Schedulev2BackfillService $service): void
    {
        if (strtoupper(trim($this->backfillConfirm)) !== 'BACKFILL') {
            $this->addError('backfillConfirm', 'Type BACKFILL in capitals to confirm the destructive clear.');

            return;
        }

        $this->runBackfill($service, dryRun: false);
    }

    public function render(ScheduleScopeService $scopeService)
    {
        $departmentId = auth()->user()?->employee?->department_id;
        $cnoDivisionId = $scopeService->divisionService()->cnoDivisionId();

        return view('livewire.schedule.schedulev2-sync', [
            'canManage' => (bool) auth()->user()?->can('schedule.manage'),
            'isCno' => $scopeService->isCnoDepartment($departmentId),
            'cnoDivisionId' => $cnoDivisionId,
            'modeLabel' => $scopeService->modeLabelForDepartment($departmentId),
            'departments' => Department::query()
                ->orderBy('department')
                ->get(['department_id', 'department', 'division_id']),
        ]);
    }

    private function runSync(Schedulev2SyncService $service, bool $dryRun): void
    {
        abort_unless(auth()->user()?->can('schedule.manage'), 403);

        $this->result = null;
        $this->errorMessage = null;

        $data = $this->validate($this->rules());

        try {
            [$from, $to] = $this->resolveRange($data);
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            $this->addError('from', $e->getMessage());

            return;
        }

        $departmentId = filled($data['department_id'] ?? null) ? (int) $data['department_id'] : null;
        $divisionId = ! empty($data['filter_division'])
            ? (int) config('schedule.cno_division_id', 3)
            : null;

        $result = $service->sync(
            from: $from,
            to: $to,
            dryRun: $dryRun,
            departmentId: $departmentId,
            divisionId: $divisionId,
        );

        $this->result = $result;

        if (! ($result['connection_ok'] ?? false)) {
            $this->errorMessage = $result['message']
                ?? 'Cannot connect to NDOS. Configure DB_*_SCHEDULEV2 in .env, then try again.';

            return;
        }

        if (! empty($result['message'])) {
            $this->errorMessage = $result['message'];
        }

        $verb = $dryRun ? 'Dry-run preview complete' : 'Pull applied';
        session()->flash(
            'status',
            $verb.'. Created/would create '.$result['created']
            .', updated/would update '.$result['updated']
            .', unchanged '.($result['unchanged'] ?? 0)
            .', skipped '.$result['skipped']
            .'. Lock→DTR was not triggered.'
        );
    }

    private function runBackfill(Schedulev2BackfillService $service, bool $dryRun): void
    {
        abort_unless(auth()->user()?->can('schedule.manage'), 403);

        $this->backfillResult = null;
        $this->errorMessage = null;
        $this->resetErrorBag('backfillConfirm');

        $divisionId = $this->filter_division
            ? (int) config('schedule.cno_division_id', 3)
            : null;

        $today = CarbonImmutable::today();
        $from = $today->subMonthsNoOverflow(max(0, (int) $this->months_back))->startOfMonth();
        $to = $today->addMonthsNoOverflow(max(0, (int) $this->months_ahead))->endOfMonth();

        if ($this->rangeMode === 'dates') {
            try {
                $from = CarbonImmutable::createFromFormat('Y-m-d', $this->from)->startOfDay();
                $to = CarbonImmutable::createFromFormat('Y-m-d', $this->to)->startOfDay();
            } catch (\Throwable $e) {
                $this->errorMessage = $e->getMessage();

                return;
            }
        }

        $result = $service->backfill(
            dryRun: $dryRun,
            force: ! $dryRun,
            withAssignments: (bool) $this->backfillWithAssignments,
            assignmentFrom: $this->backfillWithAssignments ? $from : null,
            assignmentTo: $this->backfillWithAssignments ? $to : null,
            divisionId: $divisionId,
        );

        $this->backfillResult = $result;

        if (! ($result['connection_ok'] ?? false)) {
            $this->errorMessage = $result['message']
                ?? 'Cannot connect to NDOS. Configure DB_*_SCHEDULEV2 in .env, then try again.';

            return;
        }

        if (! empty($result['message'])) {
            $this->errorMessage = $result['message'];
        }

        $verb = $dryRun ? 'Full backfill dry-run complete' : 'Full backfill applied';
        session()->flash(
            'status',
            $verb.'. Cleared '.count($result['cleared'] ?? []).' tables; see summary below. Lock→DTR was not triggered.'
        );
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolveRange(array $data): array
    {
        $today = CarbonImmutable::today();

        if (($data['rangeMode'] ?? 'months') === 'months') {
            $monthsBack = max(0, (int) ($data['months_back'] ?? 1));
            $monthsAhead = max(0, (int) ($data['months_ahead'] ?? 1));
            $from = $today->subMonthsNoOverflow($monthsBack)->startOfMonth();
            $to = $today->addMonthsNoOverflow($monthsAhead)->endOfMonth();
        } else {
            $from = CarbonImmutable::createFromFormat('Y-m-d', (string) $data['from'])->startOfDay();
            $to = CarbonImmutable::createFromFormat('Y-m-d', (string) $data['to'])->startOfDay();
        }

        if ($from->greaterThan($to)) {
            throw new \InvalidArgumentException('From date must be on or before To date.');
        }

        return [$from, $to];
    }

    private function syncDatesFromMonths(): void
    {
        $today = CarbonImmutable::today();
        $back = max(0, (int) $this->months_back);
        $ahead = max(0, (int) $this->months_ahead);
        $this->from = $today->subMonthsNoOverflow($back)->startOfMonth()->toDateString();
        $this->to = $today->addMonthsNoOverflow($ahead)->endOfMonth()->toDateString();
    }

    /**
     * @return array<string, list<string>>
     */
    private function rules(): array
    {
        return [
            'rangeMode' => ['required', 'in:months,dates'],
            'from' => ['required_if:rangeMode,dates', 'nullable', 'date_format:Y-m-d'],
            'to' => ['required_if:rangeMode,dates', 'nullable', 'date_format:Y-m-d'],
            'months_back' => ['required_if:rangeMode,months', 'integer', 'min:0', 'max:36'],
            'months_ahead' => ['required_if:rangeMode,months', 'integer', 'min:0', 'max:36'],
            'department_id' => ['nullable', 'integer'],
            'filter_division' => ['boolean'],
            'backfillConfirm' => ['nullable', 'string', 'max:40'],
            'backfillWithAssignments' => ['boolean'],
        ];
    }
}
