<?php

namespace App\Livewire\Employees;

use App\Models\Hris\TrainingDetail;
use App\Models\Hris\TrainingRequest;
use App\Services\Hris\TrainingService;
use Livewire\Component;

class EmployeeTrainingPanel extends Component
{
    public string $empId;

    public function mount(string $empId): void
    {
        abort_unless(
            auth()->user()?->can('training.view')
            || auth()->user()?->can('training.manage')
            || auth()->user()?->can('training.approve'),
            403
        );
        $this->empId = $empId;
    }

    public function approvePetu(string $tarfNo, TrainingService $trainingService): void
    {
        abort_unless(auth()->user()?->can('training.approve'), 403);
        $detail = $this->detailForEmployee($tarfNo);
        $trainingService->approvePetu($detail, $this->actor());
        session()->flash('status', 'TARF forwarded to MCC.');
    }

    public function disapprovePetu(string $tarfNo, TrainingService $trainingService): void
    {
        abort_unless(auth()->user()?->can('training.approve'), 403);
        $detail = $this->detailForEmployee($tarfNo);
        $trainingService->disapprovePetu($detail, $this->actor());
        session()->flash('status', 'TARF disapproved by PETU.');
    }

    public function approveMcc(string $tarfNo, TrainingService $trainingService): void
    {
        abort_unless(auth()->user()?->can('training.approve'), 403);
        $detail = $this->detailForEmployee($tarfNo)->load('requests');
        $trainingService->approveMcc($detail, $this->actor());
        session()->flash('status', 'TARF approved by MCC.');
    }

    public function disapproveMcc(string $tarfNo, TrainingService $trainingService): void
    {
        abort_unless(auth()->user()?->can('training.approve'), 403);
        $detail = $this->detailForEmployee($tarfNo);
        $trainingService->disapproveMcc($detail, $this->actor());
        session()->flash('status', 'TARF disapproved by MCC.');
    }

    public function cancel(string $tarfNo, TrainingService $trainingService): void
    {
        abort_unless(
            auth()->user()?->can('training.manage') || auth()->user()?->can('training.approve'),
            403
        );
        $detail = $this->detailForEmployee($tarfNo);
        $trainingService->cancel($detail);
        session()->flash('status', 'TARF cancelled.');
    }

    public function render()
    {
        $requests = TrainingRequest::query()
            ->with('trainingDetail')
            ->where('emp_id', $this->empId)
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        return view('livewire.employees.employee-training-panel', [
            'requests' => $requests,
            'canApprove' => (bool) auth()->user()?->can('training.approve'),
            'canCancel' => (bool) (auth()->user()?->can('training.manage') || auth()->user()?->can('training.approve')),
        ]);
    }

    private function detailForEmployee(string $tarfNo): TrainingDetail
    {
        $belongs = TrainingRequest::query()
            ->where('emp_id', $this->empId)
            ->where('tarf_no', $tarfNo)
            ->exists();

        abort_unless($belongs, 404);

        return TrainingDetail::query()->findOrFail($tarfNo);
    }

    private function actor(): string
    {
        return (string) (auth()->user()?->emp_id ?? auth()->user()?->username ?? 'system');
    }
}
