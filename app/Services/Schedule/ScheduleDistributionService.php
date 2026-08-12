<?php

namespace App\Services\Schedule;

use App\Mail\ScheduleDistributionMail;
use App\Models\Hris\Employee;
use App\Models\Schedule\MonthlySchedule;
use App\Models\Schedule\ScheduleUnit;
use App\Models\Schedule\ScheduleUserUnit;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class ScheduleDistributionService
{
    public function __construct(
        private SchedulePdfService $pdfService,
        private ScheduleScopeService $scopeService,
    ) {}

    /**
     * @return array{binary: string, filename: string, rows: int}
     */
    public function buildPdf(MonthlySchedule $schedule, ?int $unitId = null): array
    {
        if ($unitId !== null) {
            $allowed = $this->scopeService->unitsForDepartment($schedule->department_id)->pluck('id')->all();
            if (! in_array($unitId, $allowed, true)) {
                throw new RuntimeException('Unit is not part of this department.');
            }
        }

        return $this->pdfService->generate($schedule, $unitId);
    }

    /**
     * Resolve recipient emails: explicit list and/or handled-unit supervisors for the department.
     *
     * @param  list<string>  $explicitEmails
     * @return list<string>
     */
    public function resolveRecipients(int $departmentId, array $explicitEmails = [], bool $includeHandledUnitSupervisors = false, ?int $unitId = null): array
    {
        $emails = collect($explicitEmails)
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->values();

        if ($includeHandledUnitSupervisors) {
            $unitIds = ScheduleUnit::query()
                ->where('department_id', $departmentId)
                ->when($unitId, fn ($query) => $query->where('id', $unitId))
                ->where('is_active', true)
                ->pluck('id');

            if ($unitIds->isNotEmpty()) {
                $supervisorEmpIds = ScheduleUserUnit::query()
                    ->whereIn('schedule_unit_id', $unitIds)
                    ->pluck('emp_id')
                    ->unique()
                    ->filter()
                    ->values();

                if ($supervisorEmpIds->isNotEmpty()) {
                    $fromEmployees = Employee::query()
                        ->whereIn('emp_id', $supervisorEmpIds)
                        ->whereNotNull('email')
                        ->pluck('email')
                        ->map(fn ($email) => strtolower(trim((string) $email)))
                        ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL));

                    $emails = $emails->merge($fromEmployees);
                }
            }
        }

        return $emails->unique()->values()->all();
    }

    /**
     * @param  list<string>  $recipients
     * @return array{queued: int, recipients: list<string>}
     */
    public function emailPdf(
        MonthlySchedule $schedule,
        array $recipients,
        ?int $unitId = null,
        ?string $note = null,
    ): array {
        if (! ScheduleMailConfig::isConfigured()) {
            throw new RuntimeException(ScheduleMailConfig::notConfiguredMessage());
        }

        $recipients = array_values(array_unique(array_filter($recipients)));
        if ($recipients === []) {
            throw new RuntimeException('No valid recipient email addresses.');
        }

        $pdf = $this->buildPdf($schedule, $unitId);
        // PDF is regenerated inside the queued mailable (keeps job payload small).

        foreach ($recipients as $email) {
            Mail::to($email)->queue(new ScheduleDistributionMail(
                scheduleId: (int) $schedule->id,
                unitId: $unitId,
                note: $note,
            ));
        }

        return [
            'queued' => count($recipients),
            'recipients' => $recipients,
            'pdf_rows' => $pdf['rows'],
        ];
    }
}
