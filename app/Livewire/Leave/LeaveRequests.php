<?php

namespace App\Livewire\Leave;

use App\Models\Hris\Employee;
use App\Models\Hris\EmployeeLeave;
use App\Models\Hris\EmployeeLeaveLog;
use App\Models\Hris\LeaveType;
use App\Services\Hris\LeaveService;
use App\Support\Hris\LeaveDates;
use App\Support\Hris\LeaveStatuses;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class LeaveRequests extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public string $statusFilter = 'all';

    public int $perPage = 20;

    public bool $drawerOpen = false;

    public ?int $editingId = null;

    public string $empId = '';

    public ?int $leaveType = null;

    public string $filingDate = '';

    /** @var string pick|weekdays|calendar */
    public string $dateMode = LeaveDates::MODE_WEEKDAYS;

    public string $startDate = '';

    public string $endDate = '';

    /** Comma-separated Y-m-d for pick mode. */
    public string $selectedDatesCsv = '';

    public string $daysWpay = '';

    public string $daysWopay = '0';

    public bool $autoSplitCredits = true;

    public string $leaveSpent = '';

    public string $commutation = '';

    public string $applicantNote = '';

    public string $employeeSearch = '';

    public function mount(): void
    {
        $this->filingDate = CarbonImmutable::today()->toDateString();
        $this->startDate = CarbonImmutable::today()->toDateString();
        $this->endDate = CarbonImmutable::today()->toDateString();
        $this->recomputeFromMode();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDateMode(): void
    {
        $this->recomputeFromMode();
    }

    public function updatedStartDate(): void
    {
        $this->recomputeFromMode();
    }

    public function updatedEndDate(): void
    {
        $this->recomputeFromMode();
    }

    public function updatedSelectedDatesCsv(): void
    {
        if ($this->dateMode === LeaveDates::MODE_PICK) {
            $this->recomputeFromMode();
        }
    }

    public function create(): void
    {
        abort_unless($this->canRequest(), 403);

        $this->resetForm();
        $this->drawerOpen = true;
    }

    public function edit(int $leaveId): void
    {
        abort_unless(auth()->user()?->can('leave.view') || $this->canRequest(), 403);

        $leave = EmployeeLeave::query()->findOrFail($leaveId);
        abort_unless(app(LeaveService::class)->isPending($leave), 403);

        $dates = LeaveDates::for($leave);

        $this->editingId = $leave->leave_id;
        $this->empId = (string) $leave->emp_id;
        $this->leaveType = (int) $leave->leave_type;
        $this->filingDate = optional($leave->filing_date)?->toDateString() ?: CarbonImmutable::today()->toDateString();
        $this->startDate = $dates[0] ?? (optional($leave->start_date)?->toDateString() ?: '');
        $this->endDate = $dates !== [] ? $dates[array_key_last($dates)] : (optional($leave->end_date)?->toDateString() ?: '');
        $this->selectedDatesCsv = LeaveDates::toCsv($dates);
        $this->dateMode = LeaveDates::MODE_PICK;
        $this->daysWpay = (string) ($leave->days_wpay ?? '');
        $this->daysWopay = (string) ($leave->days_wopay ?? '0');
        $this->autoSplitCredits = false;
        $this->leaveSpent = (string) ($leave->leave_spent ?? '');
        $this->commutation = (string) ($leave->commutation ?? '');
        $this->applicantNote = (string) ($leave->applicant_note ?? '');
        $this->employeeSearch = (string) $leave->emp_id;
        $this->drawerOpen = true;
        $this->resetValidation();
    }

    public function save(LeaveService $leaveService): void
    {
        abort_unless($this->canRequest(), 403);

        $data = $this->validate([
            'empId' => ['required', 'string', 'exists:hris.tbl_employee,emp_id'],
            'leaveType' => ['required', 'integer', 'exists:hris.tbl_leave_type,leave_type_id'],
            'filingDate' => ['required', 'date'],
            'dateMode' => ['required', 'in:pick,weekdays,calendar'],
            'startDate' => ['nullable', 'date', 'required_unless:dateMode,pick'],
            'endDate' => ['nullable', 'date', 'after_or_equal:startDate', 'required_unless:dateMode,pick'],
            'selectedDatesCsv' => ['nullable', 'string', 'required_if:dateMode,pick'],
            'daysWpay' => ['nullable', 'numeric', 'min:0'],
            'daysWopay' => ['nullable', 'numeric', 'min:0'],
            'autoSplitCredits' => ['boolean'],
            'leaveSpent' => ['nullable', 'string', 'max:20'],
            'commutation' => ['nullable', 'string', 'max:50'],
            'applicantNote' => ['nullable', 'string', 'max:2000'],
        ]);

        $payload = [
            'emp_id' => $data['empId'],
            'leave_type' => (int) $data['leaveType'],
            'filing_date' => $data['filingDate'],
            'date_mode' => $data['dateMode'],
            'start_date' => $data['startDate'] ?: null,
            'end_date' => $data['endDate'] ?: null,
            'selected_dates' => $data['selectedDatesCsv'] ?: null,
            'auto_split_credits' => (bool) $data['autoSplitCredits'],
            'leave_spent' => $data['leaveSpent'] ?: null,
            'commutation' => $data['commutation'] ?: null,
            'applicant_note' => $data['applicantNote'] ?: null,
        ];

        if (! $payload['auto_split_credits']) {
            $payload['days_wpay'] = $data['daysWpay'];
            $payload['days_wopay'] = $data['daysWopay'];
        }

        $actor = (string) (auth()->user()?->emp_id ?? auth()->user()?->username ?? 'system');

        if ($this->editingId) {
            $leave = EmployeeLeave::query()->findOrFail($this->editingId);
            $leaveService->update($leave, $payload, $actor);
            session()->flash('status', 'Leave request updated.');
        } else {
            $leaveService->apply($payload, $actor);
            session()->flash('status', 'Leave request filed.');
        }

        $this->drawerOpen = false;
        $this->resetForm();
    }

    public function cancelRequest(int $leaveId, LeaveService $leaveService): void
    {
        abort_unless($this->canRequest() || auth()->user()?->can('leave.approve'), 403);

        $leave = EmployeeLeave::query()->findOrFail($leaveId);
        $actor = (string) (auth()->user()?->emp_id ?? auth()->user()?->username ?? 'system');
        $leaveService->cancel($leave, $actor);

        session()->flash('status', 'Leave request cancelled.');
    }

    public function closeDrawer(): void
    {
        $this->drawerOpen = false;
        $this->resetForm(skipRecompute: true);
    }

    public function render()
    {
        abort_unless(
            auth()->user()?->can('leave.view')
            || auth()->user()?->can('leave.request')
            || auth()->user()?->can('leave.approve'),
            403
        );

        $leaveService = app(LeaveService::class);

        $query = EmployeeLeave::query()
            ->select([
                'leave_id',
                'emp_id',
                'leave_type',
                'filing_date',
                'start_date',
                'end_date',
                'days_wpay',
                'days_wopay',
                'status',
                'remarks',
            ])
            ->with([
                'leaveType:leave_type_id,leave_name',
                'employee:emp_id,firstname,middlename,lastname,extension,prefix,suffix',
            ])
            ->whereNotNull('start_date')
            ->whereNotIn('status', LeaveService::LEDGER_STATUS_IDS)
            ->when($this->search !== '', function ($builder) {
                $search = trim($this->search);
                $builder->where(function ($inner) use ($search) {
                    $inner->where('emp_id', 'like', "%{$search}%")
                        ->orWhere('remarks', 'like', "%{$search}%")
                        ->orWhereHas('employee', function ($employeeQuery) use ($search) {
                            $employeeQuery->where('firstname', 'like', "%{$search}%")
                                ->orWhere('lastname', 'like', "%{$search}%")
                                ->orWhere('middlename', 'like', "%{$search}%");
                        });

                    if ($this->hasApplicantNoteColumn()) {
                        $inner->orWhere('applicant_note', 'like', "%{$search}%");
                    }
                });
            })
            ->when($this->statusFilter !== 'all', function ($builder) {
                $ids = LeaveStatuses::idsFor($this->statusFilter);
                if ($ids !== []) {
                    $builder->whereIn('status', $ids);
                }
            })
            ->orderByDesc('filing_date')
            ->orderByDesc('leave_id');

        $leaves = $query->paginate($this->perPage);

        // Resolve terminal logs in one query (avoid correlated EXISTS on unindexed leave_id).
        $terminalLeaveIds = [];
        if ($leaves->isNotEmpty()) {
            $terminalLeaveIds = EmployeeLeaveLog::query()
                ->whereIn('leave_id', $leaves->getCollection()->pluck('leave_id'))
                ->whereIn('action', [
                    LeaveService::ACTION_CANCELLED,
                    LeaveService::ACTION_DISAPPROVED,
                ])
                ->distinct()
                ->pluck('leave_id')
                ->flip()
                ->all();
        }

        $pendingById = [];
        foreach ($leaves as $leave) {
            $leave->setAttribute('has_terminal_log', isset($terminalLeaveIds[$leave->leave_id]));
            $pendingById[$leave->leave_id] = $leaveService->isPending($leave);
        }

        return view('livewire.leave.leave-requests', [
            'leaves' => $leaves,
            'pendingById' => $pendingById,
            'leaveTypes' => $this->drawerOpen ? $this->leaveTypeOptions() : collect(),
            'employees' => $this->drawerOpen ? $this->employeeOptions() : collect(),
            'canRequest' => $this->canRequest(),
            'previewDayCount' => $this->previewDayCount(),
        ]);
    }

    private function canRequest(): bool
    {
        return (bool) auth()->user()?->can('leave.request');
    }

    private function recomputeFromMode(): void
    {
        try {
            $selected = LeaveDates::resolveSelection(
                $this->dateMode,
                $this->startDate !== '' ? $this->startDate : null,
                $this->endDate !== '' ? $this->endDate : null,
                $this->selectedDatesCsv !== '' ? $this->selectedDatesCsv : null,
            );
        } catch (\Throwable) {
            return;
        }

        if ($selected === []) {
            return;
        }

        $this->selectedDatesCsv = LeaveDates::toCsv($selected);
        $this->startDate = $selected[0];
        $this->endDate = $selected[array_key_last($selected)];

        if ($this->autoSplitCredits) {
            $this->daysWpay = (string) count($selected);
            $this->daysWopay = '0';
        }
    }

    private function previewDayCount(): int
    {
        if ($this->selectedDatesCsv === '') {
            return 0;
        }

        try {
            return count(LeaveDates::normalize($this->selectedDatesCsv));
        } catch (\Throwable) {
            return 0;
        }
    }

    private function resetForm(bool $skipRecompute = false): void
    {
        $this->editingId = null;
        $this->empId = '';
        $this->leaveType = null;
        $this->filingDate = CarbonImmutable::today()->toDateString();
        $this->dateMode = LeaveDates::MODE_WEEKDAYS;
        $this->startDate = CarbonImmutable::today()->toDateString();
        $this->endDate = CarbonImmutable::today()->toDateString();
        $this->selectedDatesCsv = '';
        $this->daysWpay = '1';
        $this->daysWopay = '0';
        $this->autoSplitCredits = true;
        $this->leaveSpent = '';
        $this->commutation = '';
        $this->applicantNote = '';
        $this->employeeSearch = '';
        if (! $skipRecompute) {
            $this->recomputeFromMode();
        }
        $this->resetValidation();
    }

    private function leaveTypeOptions()
    {
        return LeaveType::query()
            ->select(['leave_type_id', 'leave_name'])
            ->when(
                $this->hasLeaveTypeDisplayColumn(),
                fn ($q) => $q->where(function ($inner) {
                    $inner->where('to_display', 1)->orWhereNull('to_display');
                })
            )
            ->orderBy('leave_name')
            ->get();
    }

    private function employeeOptions()
    {
        $search = trim($this->employeeSearch);

        $employees = Employee::query()
            ->select(['emp_id', 'firstname', 'middlename', 'lastname', 'extension', 'prefix', 'suffix', 'is_active'])
            ->where('is_active', 'Y')
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($inner) use ($search) {
                    $inner->where('emp_id', 'like', "%{$search}%")
                        ->orWhere('firstname', 'like', "%{$search}%")
                        ->orWhere('lastname', 'like', "%{$search}%");
                });
            })
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->limit(40)
            ->get();

        if ($this->empId !== '' && ! $employees->contains(fn ($e) => (string) $e->emp_id === $this->empId)) {
            $selected = Employee::query()
                ->select(['emp_id', 'firstname', 'middlename', 'lastname', 'extension', 'prefix', 'suffix', 'is_active'])
                ->where('emp_id', $this->empId)
                ->first();
            if ($selected) {
                $employees->prepend($selected);
            }
        }

        return $employees;
    }

    private function hasApplicantNoteColumn(): bool
    {
        return Cache::remember('hris.schema.tbl_employee_leave.applicant_note', 3600, function () {
            return Schema::connection('hris')->hasColumn('tbl_employee_leave', 'applicant_note');
        });
    }

    private function hasLeaveTypeDisplayColumn(): bool
    {
        return Cache::remember('hris.schema.tbl_leave_type.to_display', 3600, function () {
            return Schema::connection('hris')->hasColumn('tbl_leave_type', 'to_display');
        });
    }
}
