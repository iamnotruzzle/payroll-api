<?php

namespace App\Livewire\Training;

use App\Models\Hris\TrainingDetail;
use App\Services\Hris\TrainingService;
use App\Support\Hris\TarfStatuses;
use Livewire\Component;
use Livewire\WithPagination;

class TarfApprovals extends Component
{
    use WithPagination;

    public string $search = '';

    public int $perPage = 20;

    /** @var array<string, string> */
    public array $notes = [];

    /** @var array<string, array<string, int|string>> */
    public array $obOt = [];

    public bool $approveAsOt = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function approvePetu(string $tarfNo, TrainingService $trainingService): void
    {
        abort_unless(auth()->user()?->can('training.approve'), 403);
        $tarf = TrainingDetail::query()->findOrFail($tarfNo);
        $actor = (string) (auth()->user()?->emp_id ?? auth()->user()?->username ?? 'system');
        $trainingService->approvePetu($tarf, $actor, $this->notes[$tarfNo] ?? null);
        session()->flash('status', 'TARF forwarded to MCC.');
    }

    public function disapprovePetu(string $tarfNo, TrainingService $trainingService): void
    {
        abort_unless(auth()->user()?->can('training.approve'), 403);
        $tarf = TrainingDetail::query()->findOrFail($tarfNo);
        $actor = (string) (auth()->user()?->emp_id ?? auth()->user()?->username ?? 'system');
        $trainingService->disapprovePetu($tarf, $actor, $this->notes[$tarfNo] ?? null);
        session()->flash('status', 'TARF disapproved by PETU.');
    }

    public function approveMcc(string $tarfNo, TrainingService $trainingService): void
    {
        abort_unless(auth()->user()?->can('training.approve'), 403);
        $tarf = TrainingDetail::query()->with('requests')->findOrFail($tarfNo);
        $actor = (string) (auth()->user()?->emp_id ?? auth()->user()?->username ?? 'system');

        $obOtByEmpId = [];
        foreach ($tarf->requests as $request) {
            $value = $this->obOt[$tarfNo][$request->emp_id] ?? 0;
            $obOtByEmpId[(string) $request->emp_id] = (int) $value;
        }

        $trainingService->approveMcc(
            $tarf,
            $actor,
            $this->notes[$tarfNo] ?? null,
            null,
            $obOtByEmpId,
            $this->approveAsOt
        );
        session()->flash('status', $this->approveAsOt ? 'TARF approved with OT.' : 'TARF approved by MCC.');
    }

    public function disapproveMcc(string $tarfNo, TrainingService $trainingService): void
    {
        abort_unless(auth()->user()?->can('training.approve'), 403);
        $tarf = TrainingDetail::query()->findOrFail($tarfNo);
        $actor = (string) (auth()->user()?->emp_id ?? auth()->user()?->username ?? 'system');
        $trainingService->disapproveMcc($tarf, $actor, $this->notes[$tarfNo] ?? null);
        session()->flash('status', 'TARF disapproved by MCC.');
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('training.approve') || auth()->user()?->can('training.view'), 403);

        $tarfs = TrainingDetail::query()
            ->with(['ldiType', 'requests.employee'])
            ->whereIn('status', TarfStatuses::approvalQueueIds())
            ->when($this->search !== '', function ($builder) {
                $search = trim($this->search);
                $builder->where(function ($inner) use ($search) {
                    $inner->where('tarf_no', 'like', "%{$search}%")
                        ->orWhere('training_name', 'like', "%{$search}%")
                        ->orWhereHas('requests.employee', function ($employeeQuery) use ($search) {
                            $employeeQuery->where('firstname', 'like', "%{$search}%")
                                ->orWhere('lastname', 'like', "%{$search}%")
                                ->orWhere('emp_id', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('status')
            ->orderBy('start_date')
            ->paginate($this->perPage);

        foreach ($tarfs as $tarf) {
            $this->notes[$tarf->tarf_no] ??= '';
            foreach ($tarf->requests as $request) {
                $this->obOt[$tarf->tarf_no][$request->emp_id] ??= (int) ($request->ob_ot ?? 0);
            }
        }

        return view('livewire.training.tarf-approvals', [
            'tarfs' => $tarfs,
            'canApprove' => (bool) auth()->user()?->can('training.approve'),
        ]);
    }
}
