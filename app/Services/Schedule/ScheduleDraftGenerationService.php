<?php

namespace App\Services\Schedule;

use App\Models\Hris\Employee;
use App\Models\Schedule\EmployeeScheduleSetting;
use App\Models\Schedule\MonthlySchedule;
use App\Models\Schedule\ScheduleAssignment;
use App\Models\Schedule\ScheduleTemplate;
use App\Models\Schedule\ShiftCode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ScheduleDraftGenerationService
{
    public function __construct(
        private AuditLogService $auditLogService,
        private ScheduleConflictValidator $validator,
    ) {}

    public function generate(int $year, int $month, ?int $departmentId = null, ?int $templateId = null, ?string $performedBy = null): array
    {
        $template = $templateId ? ScheduleTemplate::with('days.shiftCode')->findOrFail($templateId) : null;
        $restShift = ShiftCode::where('code', 'R')->first();
        $defaultWorkShift = ShiftCode::where('is_work_shift', true)->orderBy('code')->firstOrFail();

        $schedule = DB::connection('payroll_scheduler')->transaction(function () use ($year, $month, $departmentId, $template, $restShift, $defaultWorkShift, $performedBy) {
            $schedule = MonthlySchedule::query()->updateOrCreate(
                ['department_id' => $departmentId, 'year' => $year, 'month' => $month],
                [
                    'status' => MonthlySchedule::STATUS_DRAFT,
                    'generated_by' => $performedBy,
                    'generated_at' => now(),
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'approved_by' => null,
                    'approved_at' => null,
                    'locked_by' => null,
                    'locked_at' => null,
                ]
            );

            $schedule->assignments()->delete();

            $employees = Employee::query()
                ->when($departmentId, fn($query) => $query->where('department_id', $departmentId))
                ->where('is_active', 'Y')
                ->orderBy('lastname')
                ->get(['emp_id', 'department_id', 'firstname', 'lastname']);

            $settings = EmployeeScheduleSetting::query()
                ->whereIn('employee_id', $employees->pluck('emp_id'))
                ->get()
                ->keyBy('employee_id');

            $date = CarbonImmutable::create($year, $month, 1);
            $lastDate = $date->endOfMonth();

            while ($date <= $lastDate) {
                foreach ($employees as $index => $employee) {
                    $setting = $settings->get($employee->emp_id);
                    $shift = $this->resolveShift($date, $index, $setting, $template, $restShift, $defaultWorkShift);

                    ScheduleAssignment::create([
                        'monthly_schedule_id' => $schedule->id,
                        'employee_id' => $employee->emp_id,
                        'schedule_date' => $date->toDateString(),
                        'shift_code_id' => $shift->id,
                        'source' => $setting?->can_rotate_shift ? 'generated_rotation' : 'default_schedule',
                    ]);
                }

                $date = $date->addDay();
            }

            $this->auditLogService->record('schedule.generated', $schedule, null, $schedule->fresh()->toArray(), $performedBy);

            return $schedule->fresh('assignments.shiftCode');
        });

        return [
            'schedule' => $schedule,
            'conflicts' => $this->validator->validate($schedule),
        ];
    }

    private function resolveShift(CarbonImmutable $date, int $employeeIndex, ?EmployeeScheduleSetting $setting, ?ScheduleTemplate $template, ?ShiftCode $restShift, ShiftCode $defaultWorkShift): ShiftCode
    {
        if ($setting && ! $setting->can_rotate_shift && $setting->defaultShiftCode) {
            return $setting->defaultShiftCode;
        }

        if ($date->isWeekend() && $restShift) {
            return $restShift;
        }

        if (! $template || $template->days->isEmpty()) {
            return $setting?->defaultShiftCode ?? $defaultWorkShift;
        }

        $days = $template->days->values();
        $offset = ($date->day - 1 + $employeeIndex) % $days->count();

        return $days[$offset]->shiftCode ?? $defaultWorkShift;
    }
}
