<?php

namespace App\Livewire\Schedule;

use App\Models\Hris\Employee;
use App\Models\Schedule\MonthlySchedule;
use App\Models\Schedule\ScheduleTemplate;
use App\Services\Schedule\ScheduleDraftGenerationService;
use App\Services\Schedule\ScheduleScopeService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

class ScheduleList extends Component
{
    use WithPagination;

    public ?int $department_id = null;

    public string $yearFilter = '';

    public string $statusFilter = 'all';

    public bool $showGenerateModal = false;

    public int $year;

    public int $month;

    public ?int $schedule_template_id = null;

    public string $employeeTypeFilter = Employee::EMPLOYEE_TYPE_PLANTILLA;

    /** @var string automated|manual */
    public string $generationMode = ScheduleDraftGenerationService::MODE_AUTOMATED;

    public function mount(): void
    {
        $nextMonth = now()->addMonth();
        $this->year = (int) $nextMonth->format('Y');
        $this->month = (int) $nextMonth->format('n');
        $this->yearFilter = (string) now()->format('Y');
        $this->department_id = auth()->user()?->employee?->department_id;
        $this->generationMode = ScheduleDraftGenerationService::MODE_AUTOMATED;
    }

    public function updatedYearFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openGenerateModal(ScheduleScopeService $scopeService): void
    {
        abort_unless(auth()->user()?->can('schedule.manage'), 403);

        if ($scopeService->isCnoDepartment($this->department_id)) {
            throw ValidationException::withMessages([
                'generate' => 'CNO schedules must be imported from NDOS.',
            ]);
        }

        $this->showGenerateModal = true;
        $this->generationMode = ScheduleDraftGenerationService::MODE_AUTOMATED;
    }

    public function closeGenerateModal(): void
    {
        $this->showGenerateModal = false;
    }

    public function generate(ScheduleDraftGenerationService $service, ScheduleScopeService $scopeService)
    {
        abort_unless(auth()->user()?->can('schedule.manage'), 403);

        if ($scopeService->isCnoDepartment($this->department_id)) {
            throw ValidationException::withMessages([
                'generate' => 'CNO schedules must be imported from NDOS.',
            ]);
        }

        $data = $this->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'between:1,12'],
            'schedule_template_id' => ['nullable', 'integer'],
            'employeeTypeFilter' => ['required', Rule::in(array_keys(Employee::employeeTypeOptions()))],
            'generationMode' => ['required', Rule::in([
                ScheduleDraftGenerationService::MODE_AUTOMATED,
                ScheduleDraftGenerationService::MODE_MANUAL,
            ])],
        ]);

        try {
            $result = $service->generate(
                $data['year'],
                $data['month'],
                $this->department_id,
                $data['generationMode'] === ScheduleDraftGenerationService::MODE_MANUAL
                    ? null
                    : $data['schedule_template_id'],
                auth()->user()?->emp_id ?? 'web',
                $data['employeeTypeFilter'],
                $data['generationMode'],
            );
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages([
                'generate' => $e->getMessage(),
            ]);
        }

        $this->showGenerateModal = false;
        $this->dispatch('erp-overlay-close', name: 'schedule-generate');

        return $this->redirect(route('schedule.show', $result['schedule']), navigate: true);
    }

    public function ndosImportUrl(): string
    {
        $params = array_filter([
            'department_id' => $this->department_id,
            'filter_division' => 1,
            'from' => sprintf('%04d-%02d-01', $this->year, $this->month),
            'to' => now()->setDate($this->year, $this->month, 1)->endOfMonth()->toDateString(),
            'range' => 'custom',
        ], fn ($value) => $value !== null && $value !== '');

        return route('schedule.schedulev2-sync', $params);
    }

    public function render(ScheduleScopeService $scopeService)
    {
        $isCno = $scopeService->isCnoDepartment($this->department_id);

        $schedules = MonthlySchedule::query()
            ->when($this->department_id, fn ($q) => $q->where('department_id', $this->department_id))
            ->when($this->yearFilter !== '', fn ($q) => $q->where('year', (int) $this->yearFilter))
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderByDesc('id')
            ->paginate(20);

        return view('livewire.schedule.schedule-list', [
            'department' => auth()->user()?->employee?->department,
            'isCno' => $isCno,
            'modeLabel' => $scopeService->modeLabelForDepartment($this->department_id),
            'ndosImportUrl' => $this->ndosImportUrl(),
            'schedules' => $schedules,
            'templates' => ScheduleTemplate::query()
                ->where('is_active', true)
                ->where(function ($query) {
                    $query->whereNull('department_id')->orWhere('department_id', $this->department_id);
                })
                ->orderBy('name')
                ->get(),
            'employeeTypeOptions' => Employee::employeeTypeOptions(),
            'statusOptions' => [
                'all' => 'All statuses',
                MonthlySchedule::STATUS_DRAFT => 'Draft',
                MonthlySchedule::STATUS_REVIEWED => 'Reviewed',
                MonthlySchedule::STATUS_APPROVED => 'Approved',
                MonthlySchedule::STATUS_LOCKED => 'Locked',
            ],
        ]);
    }
}
