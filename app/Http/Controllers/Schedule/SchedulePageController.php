<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Models\Hris\Department;
use App\Models\Schedule\MonthlySchedule;
use App\Models\Schedule\SchedulePrintLogo;
use App\Models\Schedule\SchedulePrintSetting;
use App\Models\Schedule\ScheduleSignatory;
use App\Models\Schedule\ShiftCode;
use Carbon\CarbonImmutable;
use Database\Seeders\RBACSeeder;

class SchedulePageController extends Controller
{
    public function shiftCodes()
    {
        return view('schedule.shift-codes');
    }

    public function dashboard()
    {
        return view('schedule.dashboard');
    }

    public function employees()
    {
        return view('schedule.employees');
    }

    public function employeeReferences()
    {
        return view('schedule.employee-references');
    }

    public function rotationGroups()
    {
        return view('schedule.rotation-groups');
    }

    public function staffingRequirements()
    {
        return view('schedule.staffing-requirements');
    }

    public function scheduleTemplates()
    {
        return view('schedule.schedule-templates');
    }

    public function userManual()
    {
        return view('schedule.user-manual');
    }

    public function rolesPermissionsManual()
    {
        return view('references.roles-permissions-manual', [
            'permissionGroups' => RBACSeeder::groupedPermissions(),
            'roleDefinitions' => RBACSeeder::roleDefinitions(),
            'permissionPages' => $this->permissionPages(),
        ]);
    }

    public function printSettings()
    {
        return view('schedule.print-settings');
    }

    public function printable(MonthlySchedule $schedule)
    {
        abort_unless($schedule->department_id === auth()->user()?->employee?->department_id, 404);

        $schedule->load('assignments.employee.position', 'assignments.shiftCode');

        $department = Department::find($schedule->department_id);
        $settings = SchedulePrintSetting::where('department_id', $schedule->department_id)->first();
        $logos = SchedulePrintLogo::where('department_id', $schedule->department_id)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
        $signatories = ScheduleSignatory::where('department_id', $schedule->department_id)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('purpose')
            ->get();

        $startDate = CarbonImmutable::create($schedule->year, $schedule->month, 1);
        $days = collect(range(1, $startDate->daysInMonth))
            ->map(fn (int $day) => $startDate->setDay($day))
            ->values();

        $rows = $schedule->assignments
            ->groupBy('employee_id')
            ->map(function ($assignments) {
                $first = $assignments->first();

                return [
                    'employee_name' => $this->printEmployeeName($first->employee),
                    'position' => $this->abbreviatePosition($first->employee?->position?->position_title),
                    'assignments' => $assignments
                        ->keyBy(fn ($assignment) => $assignment->schedule_date->toDateString())
                        ->map(fn ($assignment) => $assignment->shiftCode?->code ?: '-')
                        ->all(),
                ];
            })
            ->sortBy('employee_name')
            ->values();

        $legend = ShiftCode::where(function ($query) use ($schedule) {
            $query->whereNull('department_id')->orWhere('department_id', $schedule->department_id);
        })
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return view('schedule.printable', [
            'schedule' => $schedule,
            'department' => $department,
            'settings' => $settings,
            'logos' => $logos,
            'signatories' => $signatories,
            'days' => $days,
            'rows' => $rows,
            'legend' => $legend,
        ]);
    }

    private function printEmployeeName($employee): string
    {
        if (! $employee) {
            return 'Unknown employee';
        }

        return implode(' ', array_filter([
            $employee->lastname.',',
            $employee->firstname,
            $employee->middlename,
        ]));
    }

    private function abbreviatePosition(?string $position): string
    {
        if (! $position) {
            return '';
        }

        $stopWords = ['of', 'and', 'the', 'for'];

        return collect(preg_split('/\s+/', trim($position)))
            ->filter()
            ->reject(fn (string $word) => in_array(mb_strtolower($word), $stopWords, true))
            ->map(function (string $word) {
                $clean = preg_replace('/[^A-Za-z0-9]/', '', $word);

                if ($clean === '') {
                    return null;
                }

                if (preg_match('/^(I|II|III|IV|V|VI|VII|VIII|IX|X)$/i', $clean)) {
                    return mb_strtoupper($clean);
                }

                return mb_strtoupper(mb_substr($clean, 0, 1));
            })
            ->filter()
            ->reduce(function (string $carry, string $part) {
                return preg_match('/^(I|II|III|IV|V|VI|VII|VIII|IX|X)$/', $part)
                    ? trim($carry.' '.$part)
                    : $carry.$part;
            }, '');
    }

    private function permissionPages(): array
    {
        return [
            'admin.users.view' => [
                ['label' => 'User Accounts', 'route' => 'admin.user-accounts'],
            ],
            'admin.roles.view' => [
                ['label' => 'Roles and Permissions', 'route' => 'admin.roles-permissions'],
            ],
            'schedule.view' => [
                ['label' => 'Schedule Dashboard', 'route' => 'schedule.dashboard'],
                ['label' => 'Shift Codes', 'route' => 'schedule.shift-codes'],
                ['label' => 'Employee Schedule Settings', 'route' => 'schedule.employees'],
                ['label' => 'Rotation Groups', 'route' => 'schedule.rotation-groups'],
                ['label' => 'Staffing Requirements', 'route' => 'schedule.staffing-requirements'],
                ['label' => 'Schedule Templates', 'route' => 'schedule.templates'],
                ['label' => 'Print and Export Settings', 'route' => 'schedule.print-settings'],
            ],
            'references.view' => [
                ['label' => 'Employee References', 'route' => 'schedule.employee-references'],
                ['label' => 'Schedule Management User Manual', 'route' => 'schedule.user-manual'],
                ['label' => 'Payroll Operations Manual', 'route' => 'payroll.user-manual'],
                ['label' => 'Roles and Permissions Manual', 'route' => 'references.roles-permissions-manual'],
                ['label' => 'Employees API', 'href' => '/api/employees'],
            ],
            'timekeeping.view' => [
                ['label' => 'Daily Attendance', 'route' => 'payroll.daily-attendance'],
                ['label' => 'Attendance Report', 'route' => 'payroll.attendance-report'],
                ['label' => 'DTR Encoding', 'route' => 'payroll.dtr-encoding'],
                ['label' => 'DTR Corrections', 'route' => 'payroll.dtr-correction-requests'],
                ['label' => 'DTR Approvers', 'route' => 'payroll.dtr-correction-approvers'],
                ['label' => 'MRA', 'route' => 'payroll.mra'],
                ['label' => 'Holidays', 'route' => 'payroll.holidays'],
            ],
            'payroll.view' => [
                ['label' => 'Payroll Generation', 'route' => 'payroll.generation.configuration'],
                ['label' => 'Payroll History', 'route' => 'payroll.history'],
                ['label' => 'Loan Due Imports', 'route' => 'payroll.loan-imports'],
                ['label' => 'Loan References', 'route' => 'payroll.loan-references'],
                ['label' => 'Deduction Programs', 'route' => 'payroll.deduction-programs'],
                ['label' => 'Statutory Contributions', 'route' => 'payroll.statutory-contributions'],
                ['label' => 'Compensation Rules', 'route' => 'payroll.compensations'],
                ['label' => 'Adjustment Types', 'route' => 'payroll.adjustment-types'],
            ],
        ];
    }
}
