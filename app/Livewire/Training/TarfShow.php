<?php

namespace App\Livewire\Training;

use App\Models\Hris\TrainingDetail;
use App\Models\Hris\UploadedFile;
use App\Services\Hris\TrainingService;
use App\Support\Hris\TarfStatuses;
use Livewire\Component;
use Livewire\WithFileUploads;

class TarfShow extends Component
{
    use WithFileUploads;

    public string $tarfNo = '';

    public string $uploadRemarks = '';

    /** @var mixed */
    public $reportFiles = [];

    public bool $showReschedule = false;

    public string $rescheduleStart = '';

    public string $rescheduleEnd = '';

    public string $rescheduleHrs = '';

    public string $rescheduleNotes = '';

    public function mount(string $tarfNo): void
    {
        $this->tarfNo = $tarfNo;
    }

    public function openReschedule(): void
    {
        abort_unless(auth()->user()?->can('training.manage') || auth()->user()?->can('training.approve'), 403);
        $tarf = TrainingDetail::query()->findOrFail($this->tarfNo);
        $this->rescheduleStart = optional($tarf->start_date)->format('Y-m-d') ?: '';
        $this->rescheduleEnd = optional($tarf->end_date)->format('Y-m-d') ?: '';
        $this->rescheduleHrs = $tarf->hrs !== null ? (string) $tarf->hrs : '';
        $this->rescheduleNotes = '';
        $this->showReschedule = true;
    }

    public function cancelReschedule(): void
    {
        $this->showReschedule = false;
        $this->resetValidation();
    }

    public function saveReschedule(TrainingService $trainingService): void
    {
        abort_unless(auth()->user()?->can('training.manage') || auth()->user()?->can('training.approve'), 403);

        $data = $this->validate([
            'rescheduleStart' => ['required', 'date'],
            'rescheduleEnd' => ['required', 'date', 'after_or_equal:rescheduleStart'],
            'rescheduleHrs' => ['nullable', 'numeric', 'min:0'],
            'rescheduleNotes' => ['nullable', 'string', 'max:1000'],
        ]);

        $tarf = TrainingDetail::query()->findOrFail($this->tarfNo);
        $trainingService->reschedule($tarf, [
            'start_date' => $data['rescheduleStart'],
            'end_date' => $data['rescheduleEnd'],
            'hrs' => $data['rescheduleHrs'] ?? null,
            'notes' => $data['rescheduleNotes'] ?? null,
        ]);

        $this->showReschedule = false;
        session()->flash('status', 'TARF rescheduled.');
    }

    public function uploadReports(TrainingService $trainingService): void
    {
        abort_unless(
            auth()->user()?->can('training.manage')
            || auth()->user()?->can('self-service.training'),
            403
        );

        $this->validate([
            'reportFiles' => ['required', 'array', 'min:1'],
            'reportFiles.*' => ['file', 'max:10240'],
            'uploadRemarks' => ['nullable', 'string', 'max:255'],
        ]);

        $tarf = TrainingDetail::query()->with('requests')->findOrFail($this->tarfNo);
        $user = auth()->user();
        $actor = (string) ($user?->emp_id ?? $user?->username ?? 'system');

        if ($user?->can('self-service.training')
            && ! $user?->can('training.manage')
            && ! $user?->can('training.view')
        ) {
            $isParticipant = $tarf->requests->contains(fn ($r) => (string) $r->emp_id === (string) $user->emp_id);
            abort_unless($isParticipant, 403);
        }

        foreach ($this->reportFiles as $file) {
            $trainingService->storeUpload(
                $tarf,
                $file,
                UploadedFile::TYPE_REPORT,
                $actor,
                $this->uploadRemarks ?: null
            );
        }

        $this->reportFiles = [];
        $this->uploadRemarks = '';
        session()->flash('status', 'Training report uploaded.');
    }

    public function render()
    {
        $tarf = TrainingDetail::query()
            ->with(['requests.employee.department', 'ldiType', 'uploadedFiles.uploader', 'approvedByPetu', 'approvedByMcc'])
            ->findOrFail($this->tarfNo);

        $user = auth()->user();
        abort_unless(
            $user?->can('training.view')
            || $user?->can('training.manage')
            || $user?->can('training.approve')
            || (
                $user?->can('self-service.training')
                && $tarf->requests->contains(fn ($r) => (string) $r->emp_id === (string) $user->emp_id)
            ),
            403
        );

        $canReschedule = (bool) (
            ($user?->can('training.manage') || $user?->can('training.approve'))
            && in_array((int) $tarf->status, [
                TarfStatuses::PENDING_PETU,
                TarfStatuses::PENDING_MCC,
                TarfStatuses::APPROVED,
                TarfStatuses::APPROVED_OT,
            ], true)
        );

        return view('livewire.training.tarf-show', [
            'tarf' => $tarf,
            'statusName' => TarfStatuses::nameFor($tarf->status !== null ? (int) $tarf->status : null),
            'statusKey' => TarfStatuses::keyFor($tarf->status !== null ? (int) $tarf->status : null),
            'canUpload' => (bool) (
                $user?->can('training.manage')
                || $user?->can('self-service.training')
            ),
            'canReschedule' => $canReschedule,
        ]);
    }
}
