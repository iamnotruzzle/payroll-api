<?php

namespace App\Services\Hris;

use App\Models\Hris\TrainingDetail;
use App\Models\Hris\TrainingRequest;
use App\Models\Hris\TrainingTypeLookup;
use App\Models\Hris\UploadedFile;
use App\Support\Hris\TarfStatuses;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile as HttpUploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TrainingService
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $participantEmpIds
     * @param  list<HttpUploadedFile>  $supportingFiles
     */
    public function createRequest(array $payload, string $requestorEmpId, array $participantEmpIds = [], array $supportingFiles = []): TrainingDetail
    {
        return DB::connection('hris')->transaction(function () use ($payload, $requestorEmpId, $participantEmpIds, $supportingFiles) {
            $tarfNo = $this->nextTarfNo();

            $typeId = (int) ($payload['type'] ?? 0);
            if ($typeId <= 0 && ! empty($payload['other_type'])) {
                $type = TrainingTypeLookup::query()->firstOrCreate(
                    ['type' => ucwords(strtolower((string) $payload['other_type']))],
                    ['description' => '']
                );
                $typeId = (int) $type->id;
            }

            if ($typeId <= 0) {
                throw ValidationException::withMessages(['type' => 'Training type is required.']);
            }

            $detail = TrainingDetail::query()->create([
                'tarf_no' => $tarfNo,
                'training_name' => (string) $payload['training_name'],
                'training_venue' => (string) ($payload['training_venue'] ?: 'online'),
                'sponsor' => (string) $payload['sponsor'],
                'sponsor_type' => (int) ($payload['sponsor_type'] ?? 1),
                'start_date' => $payload['start_date'],
                'end_date' => $payload['end_date'],
                'hrs' => (float) ($payload['hrs'] ?? 0),
                'type' => $typeId,
                'mode' => (string) ($payload['mode'] ?? 'f2f'),
                'description' => $payload['description'] ?? null,
                'q2' => 0,
                'q3' => 0,
                'q4' => 0,
                'status' => TarfStatuses::PENDING_PETU,
            ]);

            TrainingRequest::query()->create([
                'tarf_no' => $tarfNo,
                'emp_id' => $requestorEmpId,
                'role' => 1,
                'accepted' => 1,
                'ob_ot' => 0,
            ]);

            foreach (array_unique(array_filter($participantEmpIds)) as $empId) {
                if ((string) $empId === (string) $requestorEmpId) {
                    continue;
                }

                TrainingRequest::query()->create([
                    'tarf_no' => $tarfNo,
                    'emp_id' => (string) $empId,
                    'role' => 0,
                    'accepted' => 0,
                    'ob_ot' => 0,
                ]);
            }

            foreach ($supportingFiles as $file) {
                $this->storeUpload($detail, $file, UploadedFile::TYPE_SUPPORTING, $requestorEmpId);
            }

            return $detail->fresh(['requests.employee', 'ldiType', 'uploadedFiles']);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updatePending(TrainingDetail $detail, array $payload): TrainingDetail
    {
        if ((int) $detail->status !== TarfStatuses::PENDING_PETU) {
            throw ValidationException::withMessages(['status' => 'Only pending PETU requests can be edited.']);
        }

        $detail->fill([
            'training_name' => (string) $payload['training_name'],
            'training_venue' => (string) ($payload['training_venue'] ?: 'online'),
            'sponsor' => (string) $payload['sponsor'],
            'sponsor_type' => (int) ($payload['sponsor_type'] ?? $detail->sponsor_type ?? 1),
            'start_date' => $payload['start_date'],
            'end_date' => $payload['end_date'],
            'hrs' => (float) ($payload['hrs'] ?? 0),
            'type' => (int) $payload['type'],
            'mode' => (string) ($payload['mode'] ?? $detail->mode ?? 'f2f'),
            'description' => $payload['description'] ?? $detail->description,
        ])->save();

        return $detail->fresh(['requests.employee', 'ldiType', 'uploadedFiles']);
    }

    public function approvePetu(TrainingDetail $detail, string $actorEmpId, ?string $notes = null): TrainingDetail
    {
        if ((int) $detail->status !== TarfStatuses::PENDING_PETU) {
            throw ValidationException::withMessages(['status' => 'Request is not awaiting PETU approval.']);
        }

        $detail->fill([
            'status' => TarfStatuses::PENDING_MCC,
            'approvedby_petu_id' => $actorEmpId,
            'approvedby_petu' => now(),
            'petu_notes' => $notes,
        ])->save();

        UploadedFile::query()
            ->where('tag', $detail->tarf_no)
            ->where('type', UploadedFile::TYPE_SUPPORTING)
            ->update(['file_stat' => 1]);

        TrainingRequest::query()
            ->where('tarf_no', $detail->tarf_no)
            ->where('accepted', 0)
            ->delete();

        return $detail->fresh();
    }

    public function disapprovePetu(TrainingDetail $detail, string $actorEmpId, ?string $notes = null): TrainingDetail
    {
        if ((int) $detail->status !== TarfStatuses::PENDING_PETU) {
            throw ValidationException::withMessages(['status' => 'Request is not awaiting PETU approval.']);
        }

        $detail->fill([
            'status' => TarfStatuses::DISAPPROVED_PETU,
            'approvedby_petu_id' => $actorEmpId,
            'approvedby_petu' => now(),
            'petu_notes' => $notes,
        ])->save();

        return $detail->fresh();
    }

    public function approveMcc(TrainingDetail $detail, string $actorEmpId, ?string $notes = null, ?string $approverEmpId = null): TrainingDetail
    {
        if ((int) $detail->status !== TarfStatuses::PENDING_MCC) {
            throw ValidationException::withMessages(['status' => 'Request is not awaiting MCC approval.']);
        }

        $detail->fill([
            'status' => TarfStatuses::APPROVED,
            'approved_by' => $approverEmpId ?: $actorEmpId,
            'approvedby_mcc' => now(),
            'mcc_notes' => $notes,
        ])->save();

        return $detail->fresh();
    }

    public function disapproveMcc(TrainingDetail $detail, string $actorEmpId, ?string $notes = null, ?string $approverEmpId = null): TrainingDetail
    {
        if ((int) $detail->status !== TarfStatuses::PENDING_MCC) {
            throw ValidationException::withMessages(['status' => 'Request is not awaiting MCC approval.']);
        }

        $detail->fill([
            'status' => TarfStatuses::DISAPPROVED_MCC,
            'approved_by' => $approverEmpId ?: $actorEmpId,
            'approvedby_mcc' => now(),
            'mcc_notes' => $notes,
        ])->save();

        return $detail->fresh();
    }

    public function cancel(TrainingDetail $detail): TrainingDetail
    {
        if (! in_array((int) $detail->status, [TarfStatuses::PENDING_PETU, TarfStatuses::PENDING_MCC], true)) {
            throw ValidationException::withMessages(['status' => 'Only pending requests can be cancelled.']);
        }

        $detail->fill(['status' => TarfStatuses::CANCELLED])->save();

        return $detail->fresh();
    }

    public function storeUpload(TrainingDetail $detail, HttpUploadedFile $file, int $type, string $uploaderEmpId, ?string $remarks = null): UploadedFile
    {
        $extension = $file->getClientOriginalExtension() ?: 'bin';
        $safeName = preg_replace('/[^A-Za-z0-9\-_]/', '_', $detail->tarf_no) ?: 'tarf';
        $filename = $safeName.'-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(3)).'.'.$extension;

        Storage::disk('local')->putFileAs('training-uploads', $file, $filename);

        $upload = UploadedFile::query()->create([
            'filename' => $filename,
            'tag' => $detail->tarf_no,
            'type' => $type,
            'uploaded_by' => $uploaderEmpId,
            'file_stat' => 0,
            'remarks' => $remarks,
        ]);

        if ($type === UploadedFile::TYPE_REPORT && in_array((int) $detail->status, [TarfStatuses::APPROVED, TarfStatuses::APPROVED_OT], true)) {
            $participantCount = TrainingRequest::query()->where('tarf_no', $detail->tarf_no)->count();
            $reportCount = UploadedFile::query()
                ->where('tag', $detail->tarf_no)
                ->where('type', '!=', UploadedFile::TYPE_SUPPORTING)
                ->count();

            // Legacy rule of thumb: enough reports uploaded → mark completed.
            $needed = max(1, $participantCount);
            if ($reportCount >= $needed) {
                $detail->fill(['status' => TarfStatuses::COMPLETED])->save();
            }
        }

        return $upload;
    }

    public function resolveStoredPath(UploadedFile $upload): ?string
    {
        $local = storage_path('app/training-uploads/'.$upload->filename);
        if (is_file($local)) {
            return $local;
        }

        // Legacy HRIS public/uploads dual-read during cutover.
        $legacy = base_path('reference projects/hris/public/uploads/'.$upload->filename);
        if (is_file($legacy)) {
            return $legacy;
        }

        return null;
    }

    private function nextTarfNo(): string
    {
        $year = CarbonImmutable::now()->format('Y');
        $prefix = 'LDI'.$year.'-PH-';

        $latest = TrainingDetail::query()
            ->where('tarf_no', 'like', $prefix.'%')
            ->orderByDesc('tarf_no')
            ->value('tarf_no');

        $next = 1;
        if (is_string($latest) && preg_match('/(\d+)$/', $latest, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
