<?php

namespace App\Services\Hris;

use App\Mail\TrainingStatusMail;
use App\Models\Hris\TrainingDetail;
use App\Models\Hris\TrainingRequest;
use App\Models\Hris\TrainingTypeLookup;
use App\Models\Hris\UploadedFile;
use App\Services\Schedule\ScheduleMailConfig;
use App\Support\Hris\TarfStatuses;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile as HttpUploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TrainingService
{
    public const MAIL_ASSESSED = 1;

    public const MAIL_APPROVED = 2;

    public const MAIL_DISAPPROVED = 3;

    public const MAIL_RESCHEDULED = 7;

    public const MAIL_INVITE = 8;

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $participantEmpIds
     * @param  list<HttpUploadedFile>  $supportingFiles
     */
    public function createRequest(array $payload, string $requestorEmpId, array $participantEmpIds = [], array $supportingFiles = []): TrainingDetail
    {
        $detail = DB::connection('hris')->transaction(function () use ($payload, $requestorEmpId, $participantEmpIds, $supportingFiles) {
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

        $this->notify(self::MAIL_INVITE, $detail->tarf_no);

        return $detail;
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

    public function respondToInvite(int $requestId, string $empId, int $response): TrainingRequest
    {
        $request = TrainingRequest::query()->with('trainingDetail')->findOrFail($requestId);

        if ((string) $request->emp_id !== (string) $empId) {
            throw ValidationException::withMessages(['invite' => 'This invitation is not for your account.']);
        }

        if ((int) $request->role !== 0 || (int) $request->accepted !== 0) {
            throw ValidationException::withMessages(['invite' => 'Invitation is no longer pending.']);
        }

        $status = (int) ($request->trainingDetail?->status ?? -1);
        if ($status !== TarfStatuses::PENDING_PETU) {
            throw ValidationException::withMessages(['invite' => 'Invitations can only be answered while the TARF is pending PETU.']);
        }

        if ($response === 1) {
            $request->accepted = 1;
            $request->save();

            return $request->fresh(['trainingDetail', 'employee']);
        }

        if ($response === 2) {
            $request->delete();

            return $request;
        }

        throw ValidationException::withMessages(['invite' => 'Invalid invitation response.']);
    }

    /**
     * @param  array{start_date:string,end_date:string,hrs?:float|int|string|null,notes?:?string}  $payload
     */
    public function reschedule(TrainingDetail $detail, array $payload): TrainingDetail
    {
        if (! in_array((int) $detail->status, [
            TarfStatuses::PENDING_PETU,
            TarfStatuses::PENDING_MCC,
            TarfStatuses::APPROVED,
            TarfStatuses::APPROVED_OT,
        ], true)) {
            throw ValidationException::withMessages(['status' => 'This TARF cannot be rescheduled in its current status.']);
        }

        $notes = trim((string) ($payload['notes'] ?? ''));
        $description = (string) ($detail->description ?? '');
        if ($notes !== '') {
            $stamp = now()->format('Y-m-d H:i');
            $description = trim($description."\n[Reschedule {$stamp}] {$notes}");
        }

        $detail->fill([
            'start_date' => $payload['start_date'],
            'end_date' => $payload['end_date'],
            'hrs' => isset($payload['hrs']) && $payload['hrs'] !== '' && $payload['hrs'] !== null
                ? (float) $payload['hrs']
                : $detail->hrs,
            'description' => $description !== '' ? $description : $detail->description,
        ])->save();

        $this->notify(self::MAIL_RESCHEDULED, $detail->tarf_no);

        return $detail->fresh(['requests.employee', 'ldiType', 'uploadedFiles']);
    }

    /**
     * @param  array<string, int>  $obOtByEmpId
     */
    public function approveMcc(
        TrainingDetail $detail,
        string $actorEmpId,
        ?string $notes = null,
        ?string $approverEmpId = null,
        array $obOtByEmpId = [],
        bool $asOt = false
    ): TrainingDetail {
        if ((int) $detail->status !== TarfStatuses::PENDING_MCC) {
            throw ValidationException::withMessages(['status' => 'Request is not awaiting MCC approval.']);
        }

        foreach ($obOtByEmpId as $empId => $obOt) {
            TrainingRequest::query()
                ->where('tarf_no', $detail->tarf_no)
                ->where('emp_id', (string) $empId)
                ->update(['ob_ot' => (int) $obOt]);
        }

        $detail->fill([
            'status' => $asOt ? TarfStatuses::APPROVED_OT : TarfStatuses::APPROVED,
            'approved_by' => $approverEmpId ?: $actorEmpId,
            'approvedby_mcc' => now(),
            'mcc_notes' => $notes,
        ])->save();

        $this->notify(self::MAIL_APPROVED, $detail->tarf_no);

        return $detail->fresh();
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

        $this->notify(self::MAIL_ASSESSED, $detail->tarf_no);

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

        $this->notify(self::MAIL_DISAPPROVED, $detail->tarf_no);

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

        $this->notify(self::MAIL_DISAPPROVED, $detail->tarf_no);

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

        $legacy = base_path('reference projects/hris/public/uploads/'.$upload->filename);
        if (is_file($legacy)) {
            return $legacy;
        }

        return null;
    }

    public function notify(int $code, string $tarfNo): void
    {
        if (! ScheduleMailConfig::isConfigured()) {
            return;
        }

        $detail = TrainingDetail::query()->with(['requests.employee'])->find($tarfNo);
        if (! $detail) {
            return;
        }

        $emails = $detail->requests
            ->map(fn (TrainingRequest $request) => $request->employee?->email)
            ->filter(fn ($email) => is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();

        foreach ($emails as $email) {
            Mail::to($email)->queue(new TrainingStatusMail($tarfNo, $code));
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, TrainingRequest>
     */
    public function pendingInvitesFor(string $empId)
    {
        return TrainingRequest::query()
            ->with(['trainingDetail.ldiType'])
            ->where('emp_id', $empId)
            ->where('role', 0)
            ->where('accepted', 0)
            ->whereHas('trainingDetail', fn ($q) => $q->where('status', TarfStatuses::PENDING_PETU))
            ->orderByDesc('id')
            ->get();
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
