<?php

namespace App\Livewire\Training;

use App\Models\Hris\Employee;
use App\Models\Hris\TrainingDetail;
use App\Models\Hris\TrainingTypeLookup;
use App\Services\Hris\TrainingService;
use App\Support\Hris\TarfStatuses;
use Carbon\CarbonImmutable;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class TarfRequests extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    public int $perPage = 20;

    public bool $drawerOpen = false;

    public ?string $editingTarfNo = null;

    public string $trainingName = '';

    public string $trainingVenue = '';

    public string $sponsor = '';

    public int $sponsorType = 1;

    public string $startDate = '';

    public string $endDate = '';

    public string $hrs = '8';

    public ?int $type = null;

    public string $mode = 'f2f';

    public string $description = '';

    public string $requestorEmpId = '';

    /** @var list<string> */
    public array $participantEmpIds = [];

    public string $employeeSearch = '';

    /** @var mixed */
    public $supportingFiles = [];

    public function mount(): void
    {
        $this->startDate = CarbonImmutable::today()->toDateString();
        $this->endDate = CarbonImmutable::today()->toDateString();
        $this->requestorEmpId = (string) (auth()->user()?->emp_id ?? '');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        abort_unless($this->canManage(), 403);
        $this->resetForm();
        $this->drawerOpen = true;
    }

    public function edit(string $tarfNo): void
    {
        abort_unless($this->canManage() || auth()->user()?->can('training.view'), 403);

        $tarf = TrainingDetail::query()->with('requests')->findOrFail($tarfNo);
        abort_unless((int) $tarf->status === TarfStatuses::PENDING_PETU, 403);

        $this->editingTarfNo = $tarf->tarf_no;
        $this->trainingName = (string) $tarf->training_name;
        $this->trainingVenue = (string) ($tarf->training_venue ?? '');
        $this->sponsor = (string) $tarf->sponsor;
        $this->sponsorType = (int) ($tarf->sponsor_type ?? 1);
        $this->startDate = optional($tarf->start_date)?->toDateString() ?: '';
        $this->endDate = optional($tarf->end_date)?->toDateString() ?: '';
        $this->hrs = (string) ($tarf->hrs ?? '8');
        $this->type = (int) $tarf->type;
        $this->mode = (string) ($tarf->mode ?: 'f2f');
        $this->description = (string) ($tarf->description ?? '');
        $requestor = $tarf->requests->firstWhere('role', 1);
        $this->requestorEmpId = (string) ($requestor?->emp_id ?? '');
        $this->participantEmpIds = $tarf->requests
            ->where('role', '!=', 1)
            ->pluck('emp_id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
        $this->drawerOpen = true;
        $this->resetValidation();
    }

    public function save(TrainingService $trainingService): void
    {
        abort_unless($this->canManage(), 403);

        $data = $this->validate([
            'trainingName' => ['required', 'string', 'max:255'],
            'trainingVenue' => ['nullable', 'string', 'max:100'],
            'sponsor' => ['required', 'string', 'max:100'],
            'sponsorType' => ['required', 'integer', 'in:1,2'],
            'startDate' => ['required', 'date'],
            'endDate' => ['required', 'date', 'after_or_equal:startDate'],
            'hrs' => ['required', 'numeric', 'min:0'],
            'type' => ['required', 'integer', 'exists:hris.tbl_training_types,id'],
            'mode' => ['required', 'string', 'in:f2f,online,hybrid'],
            'description' => ['nullable', 'string', 'max:150'],
            'requestorEmpId' => ['required', 'string', 'exists:hris.tbl_employee,emp_id'],
            'participantEmpIds' => ['array'],
            'participantEmpIds.*' => ['string', 'exists:hris.tbl_employee,emp_id'],
            'supportingFiles' => ['array'],
            'supportingFiles.*' => ['file', 'max:10240'],
        ]);

        $payload = [
            'training_name' => $data['trainingName'],
            'training_venue' => $data['trainingVenue'] ?? '',
            'sponsor' => $data['sponsor'],
            'sponsor_type' => (int) $data['sponsorType'],
            'start_date' => $data['startDate'],
            'end_date' => $data['endDate'],
            'hrs' => $data['hrs'],
            'type' => (int) $data['type'],
            'mode' => $data['mode'],
            'description' => $data['description'] ?? null,
        ];

        if ($this->editingTarfNo) {
            $tarf = TrainingDetail::query()->findOrFail($this->editingTarfNo);
            $trainingService->updatePending($tarf, $payload);
            session()->flash('status', 'TARF / LDI request updated.');
        } else {
            $files = is_array($this->supportingFiles) ? $this->supportingFiles : [];
            $trainingService->createRequest(
                $payload,
                $data['requestorEmpId'],
                $data['participantEmpIds'] ?? [],
                $files
            );
            session()->flash('status', 'TARF / LDI request filed.');
        }

        $this->drawerOpen = false;
        $this->resetForm();
        $this->dispatch('erp-overlay-close', name: 'tarf-request');
    }

    public function cancelRequest(string $tarfNo, TrainingService $trainingService): void
    {
        abort_unless($this->canManage() || auth()->user()?->can('training.approve'), 403);
        $tarf = TrainingDetail::query()->findOrFail($tarfNo);
        $trainingService->cancel($tarf);
        session()->flash('status', 'TARF / LDI request cancelled.');
    }

    public function closeDrawer(): void
    {
        $this->drawerOpen = false;
        $this->resetForm();
    }

    public function toggleParticipant(string $empId): void
    {
        $empId = (string) $empId;
        if (in_array($empId, $this->participantEmpIds, true)) {
            $this->participantEmpIds = array_values(array_filter(
                $this->participantEmpIds,
                fn ($id) => $id !== $empId
            ));
        } else {
            $this->participantEmpIds[] = $empId;
        }
    }

    public function render()
    {
        abort_unless(
            auth()->user()?->can('training.view')
            || auth()->user()?->can('training.manage')
            || auth()->user()?->can('training.approve'),
            403
        );

        $query = TrainingDetail::query()
            ->with(['ldiType', 'requests.employee'])
            ->when($this->search !== '', function ($builder) {
                $search = trim($this->search);
                $builder->where(function ($inner) use ($search) {
                    $inner->where('tarf_no', 'like', "%{$search}%")
                        ->orWhere('training_name', 'like', "%{$search}%")
                        ->orWhere('sponsor', 'like', "%{$search}%")
                        ->orWhereHas('requests', function ($req) use ($search) {
                            $req->where('emp_id', 'like', "%{$search}%")
                                ->orWhereHas('employee', function ($employeeQuery) use ($search) {
                                    $employeeQuery->where('firstname', 'like', "%{$search}%")
                                        ->orWhere('lastname', 'like', "%{$search}%");
                                });
                        });
                });
            })
            ->when($this->statusFilter !== 'all', function ($builder) {
                $ids = TarfStatuses::idsFor($this->statusFilter);
                if ($ids !== []) {
                    $builder->whereIn('status', $ids);
                }
            })
            ->orderByDesc('start_date')
            ->orderByDesc('created_at');

        return view('livewire.training.tarf-requests', [
            'tarfs' => $query->paginate($this->perPage),
            'types' => TrainingTypeLookup::query()->orderBy('id')->get(),
            'employees' => $this->employeeOptions(),
            'canManage' => $this->canManage(),
        ]);
    }

    private function canManage(): bool
    {
        return (bool) auth()->user()?->can('training.manage');
    }

    private function resetForm(): void
    {
        $this->editingTarfNo = null;
        $this->trainingName = '';
        $this->trainingVenue = '';
        $this->sponsor = '';
        $this->sponsorType = 1;
        $this->startDate = CarbonImmutable::today()->toDateString();
        $this->endDate = CarbonImmutable::today()->toDateString();
        $this->hrs = '8';
        $this->type = null;
        $this->mode = 'f2f';
        $this->description = '';
        $this->requestorEmpId = (string) (auth()->user()?->emp_id ?? '');
        $this->participantEmpIds = [];
        $this->employeeSearch = '';
        $this->supportingFiles = [];
        $this->resetValidation();
    }

    private function employeeOptions()
    {
        $search = trim($this->employeeSearch);

        return Employee::query()
            ->select(['emp_id', 'firstname', 'middlename', 'lastname', 'extension', 'department_id', 'is_active'])
            ->with('department')
            ->where('is_active', 'Y')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('emp_id', 'like', "%{$search}%")
                        ->orWhere('firstname', 'like', "%{$search}%")
                        ->orWhere('lastname', 'like', "%{$search}%");
                });
            })
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->limit(40)
            ->get();
    }
}
