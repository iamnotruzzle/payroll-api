<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\Hris\TrainingDetail;
use App\Models\Hris\UploadedFile;
use App\Services\Hris\TrainingService;
use App\Support\Hris\TarfStatuses;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TrainingPageController extends Controller
{
    public function requests(): View
    {
        return view('training.requests');
    }

    public function approvals(): View
    {
        return view('training.approvals');
    }

    public function calendar(): View
    {
        return view('training.calendar');
    }

    public function show(string $tarfNo): View
    {
        $tarf = TrainingDetail::query()
            ->with(['requests.employee.department', 'requests.employee.position', 'ldiType', 'uploadedFiles.uploader', 'approvedByPetu', 'approvedByMcc'])
            ->findOrFail($tarfNo);

        $user = auth()->user();
        if ($user?->can('self-service.training')
            && ! $user?->can('training.view')
            && ! $user?->can('training.manage')
            && ! $user?->can('training.approve')
        ) {
            $isParticipant = $tarf->requests->contains(fn ($r) => (string) $r->emp_id === (string) $user->emp_id);
            abort_unless($isParticipant, 403);
        }

        return view('training.show', [
            'tarf' => $tarf,
            'statusName' => TarfStatuses::nameFor($tarf->status !== null ? (int) $tarf->status : null),
            'statusKey' => TarfStatuses::keyFor($tarf->status !== null ? (int) $tarf->status : null),
            'canApprove' => (bool) $user?->can('training.approve'),
            'canManage' => (bool) $user?->can('training.manage'),
        ]);
    }

    public function print(string $tarfNo): View
    {
        $tarf = TrainingDetail::query()
            ->with(['requests.employee.department', 'requests.employee.position', 'ldiType', 'approvedByPetu', 'approvedByMcc'])
            ->findOrFail($tarfNo);

        $user = auth()->user();
        if ($user?->can('self-service.training')
            && ! $user?->can('training.view')
            && ! $user?->can('training.manage')
            && ! $user?->can('training.approve')
        ) {
            $isParticipant = $tarf->requests->contains(fn ($r) => (string) $r->emp_id === (string) $user->emp_id);
            abort_unless($isParticipant, 403);
        }

        return view('training.print', [
            'tarf' => $tarf,
            'statusName' => TarfStatuses::nameFor($tarf->status !== null ? (int) $tarf->status : null),
            'backUrl' => $user?->can('training.view') || $user?->can('training.manage') || $user?->can('training.approve')
                ? route('training.requests')
                : route('self-service.training'),
        ]);
    }

    public function download(string $tarfNo, int $fileId, TrainingService $trainingService): BinaryFileResponse|Response
    {
        $upload = UploadedFile::query()
            ->where('tag', $tarfNo)
            ->whereKey($fileId)
            ->firstOrFail();

        $user = auth()->user();
        $tarf = TrainingDetail::query()->with('requests')->findOrFail($tarfNo);

        if ($user?->can('self-service.training')
            && ! $user?->can('training.view')
            && ! $user?->can('training.manage')
            && ! $user?->can('training.approve')
        ) {
            $isParticipant = $tarf->requests->contains(fn ($r) => (string) $r->emp_id === (string) $user->emp_id);
            abort_unless($isParticipant || (string) $upload->uploaded_by === (string) $user->emp_id, 403);
        }

        $path = $trainingService->resolveStoredPath($upload);
        abort_unless($path !== null, 404, 'File not found on disk.');

        return response()->download($path, $upload->filename);
    }
}
