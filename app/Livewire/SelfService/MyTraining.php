<?php

namespace App\Livewire\SelfService;

use App\Models\Hris\TrainingDetail;
use App\Models\Hris\TrainingRequest;
use App\Models\Hris\TrainingTypeLookup;
use App\Services\Hris\TrainingService;
use App\Support\Hris\TarfStatuses;
use Carbon\CarbonImmutable;
use Livewire\Component;

class MyTraining extends Component
{
    public string $empId = '';

    public bool $showForm = false;

    public string $trainingName = '';

    public string $trainingVenue = '';

    public string $sponsor = '';

    public int $sponsorType = 1;

    public string $startDate = '';

    public string $endDate = '';

    public string $hrs = '8';

    public ?int $type = null;

    public string $mode = 'f2f';

    public function mount(?string $empId = null): void
    {
        abort_unless(
            auth()->user()?->can('self-service.training')
            || auth()->user()?->can('training.manage')
            || auth()->user()?->can('self-service.access'),
            403
        );

        $this->empId = (string) ($empId ?: auth()->user()?->emp_id ?? '');
        abort_unless($this->empId !== '', 404);

        $this->startDate = CarbonImmutable::today()->toDateString();
        $this->endDate = CarbonImmutable::today()->toDateString();
    }

    public function openForm(): void
    {
        abort_unless(auth()->user()?->can('self-service.training') || auth()->user()?->can('training.manage'), 403);
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetValidation();
    }

    public function submit(TrainingService $trainingService): void
    {
        abort_unless(auth()->user()?->can('self-service.training') || auth()->user()?->can('training.manage'), 403);

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
        ]);

        $trainingService->createRequest([
            'training_name' => $data['trainingName'],
            'training_venue' => $data['trainingVenue'] ?? '',
            'sponsor' => $data['sponsor'],
            'sponsor_type' => (int) $data['sponsorType'],
            'start_date' => $data['startDate'],
            'end_date' => $data['endDate'],
            'hrs' => $data['hrs'],
            'type' => (int) $data['type'],
            'mode' => $data['mode'],
        ], $this->empId);

        $this->showForm = false;
        $this->dispatch('erp-overlay-close', name: 'my-training');
        session()->flash('status', 'Training request submitted.');
    }

    public function respondInvite(int $requestId, int $response, TrainingService $trainingService): void
    {
        abort_unless(
            auth()->user()?->can('self-service.training')
            || auth()->user()?->can('training.manage'),
            403
        );

        $trainingService->respondToInvite($requestId, $this->empId, $response);
        session()->flash('status', $response === 1 ? 'Invitation accepted.' : 'Invitation declined.');
    }

    public function render(TrainingService $trainingService)
    {
        $tarfNos = TrainingRequest::query()
            ->where('emp_id', $this->empId)
            ->pluck('tarf_no');

        $tarfs = TrainingDetail::query()
            ->with(['ldiType', 'requests'])
            ->whereIn('tarf_no', $tarfNos)
            ->orderByDesc('start_date')
            ->limit(100)
            ->get();

        return view('livewire.self-service.my-training', [
            'tarfs' => $tarfs,
            'pendingInvites' => $trainingService->pendingInvitesFor($this->empId),
            'types' => TrainingTypeLookup::query()->orderBy('id')->get(),
            'canRequest' => (bool) (
                auth()->user()?->can('self-service.training')
                || auth()->user()?->can('training.manage')
            ),
            'statusLabels' => TarfStatuses::labels(),
        ]);
    }
}
