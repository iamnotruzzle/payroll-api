<?php

namespace App\Services\Hris;

use App\Models\Hris\Employee;
use App\Models\Hris\EmployeeLeave;
use App\Models\Hris\EmployeeLeaveLog;
use App\Models\Hris\SalaryGrade;
use App\Support\Hris\LeaveDates;
use Carbon\CarbonImmutable;
use FPDM;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Fills legacy CSC Form 6 PDF (NEW_LEAVE_FORM.pdf) — same output as HRIS leave/print/{id}/{emp}.
 */
class LeaveApplicationPrintService
{
    public function __construct(
        private readonly LeaveSignatoryResolver $signatories,
    ) {}

    public function stream(EmployeeLeave $leave): SymfonyResponse
    {
        $content = $this->binary($leave);

        // Avoid Content-Disposition filenames ending in .pdf and prefer octet-stream —
        // Internet Download Manager hooks .pdf / application/pdf navigations.
        return response($content, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="leave-form-'.$leave->leave_id.'"',
            'Content-Transfer-Encoding' => 'binary',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function binary(EmployeeLeave $leave): string
    {
        $fields = $this->buildFields($leave);
        $template = $this->templatePath();

        $pdf = new FPDM($template);
        $pdf->useCheckboxParser = true;
        $pdf->Load($fields, true);
        $pdf->Merge();

        return $pdf->Output('S');
    }

    /**
     * @return array<string, mixed>
     */
    public function buildFields(EmployeeLeave $leave): array
    {
        $leave->loadMissing(['employee.department', 'employee.position', 'leaveType']);

        $employee = $leave->employee;
        abort_unless($employee instanceof Employee, 404);

        $name = $this->signatories->familyNameFirst($employee);
        $position = (string) ($employee->position?->position_title ?? '');
        $effectivityDate = CarbonImmutable::parse($leave->created_at ?? $leave->filing_date ?? now())
            ->startOfYear()
            ->toDateString();

        $salaryRow = SalaryGrade::query()
            ->where('salary_grade', $employee->position?->salary_grade)
            ->where('step_increment', $employee->step)
            ->whereDate('effectivity_date', '<=', $effectivityDate)
            ->orderByDesc('effectivity_date')
            ->first();

        $salaryAmount = (float) ($salaryRow?->salary ?? 0);
        $daysWpay = (float) ($leave->days_wpay ?? 0);
        $daysWopay = (float) ($leave->days_wopay ?? 0);
        $numdays = $daysWpay + $daysWopay;

        if ((int) $employee->empstat_id === Employee::EMPSTAT_PART_TIME) {
            $position .= ' (PT)';
            $salaryAmount = $salaryAmount / 2;
            $numdays = $numdays * 2;
        }

        $commutation = ((string) $leave->commutation === 'N') ? 'not_requested' : 'requested';

        $fields = [
            'name' => $name,
            'department' => (string) ($employee->department?->department ?? ''),
            'position' => $position,
            'salary' => 'Php '.number_format($salaryAmount, 2),
            'num_days' => $numdays.' day/s',
            'days_wpay' => $daysWpay,
            'days_wopay' => $daysWopay,
            'filing_date' => optional($leave->filing_date)
                ? CarbonImmutable::parse($leave->filing_date)->format('M j, Y')
                : '',
            $commutation => true,
        ];

        $leaveTypeId = (int) $leave->leave_type;
        $leavebox = 'leave_'.$leaveTypeId;
        if ($leaveTypeId > 18) {
            if ($leaveTypeId === 20) {
                $leavebox = 'is_monet';
            } else {
                $leavebox = 'leave_18';
                $fields['leave_others'] = $leave->leave_spent;
            }
        }
        $fields[$leavebox] = true;

        $remarks = 'Applied for '.($leave->leaveType?->leave_name ?: 'leave');
        $fields = array_merge($fields, $this->leaveTypeSpecificFields($leave, $remarks));

        // Legacy uses LOG::where(leave_id)->first() with no ORDER BY. In practice the HRIS
        // print for this leave matched the latest log (e.g. cancel balances + that log date).
        $creditLog = EmployeeLeaveLog::query()
            ->where('leave_id', $leave->leave_id)
            ->orderByDesc('log_id')
            ->first();

        $fields['date_as_of'] = CarbonImmutable::parse(
            $creditLog?->created_at ?? $leave->updated_at ?? $leave->created_at ?? now()
        )->format('M j, Y');

        [$totalVl, $totalSl, $minusVl, $minusSl, $vlBal, $slBal] = $this->creditSnapshotFromLog($leave, $remarks, $creditLog);
        [$supervisorName, $supervisorPos, $signatoryName, $signatoryPos] = $this->resolveSignatories($employee, $numdays, $leaveTypeId);

        $fields = array_merge($fields, [
            'leave_dates' => $this->formatInclusiveDates($leave, $numdays),
            'total_vl' => $totalVl,
            'total_sl' => $totalSl,
            'vl_minus' => $minusVl,
            'sl_minus' => $minusSl,
            'vl_bal' => $vlBal,
            'sl_bal' => $slBal,
            'supervisor_name' => $supervisorName,
            'supervisor_position' => $supervisorPos,
            'signatory_name' => $signatoryName,
            'signatory_position' => $signatoryPos,
        ]);

        $status = (int) ($leave->status ?? -1);
        if ($status === 1) {
            $fields['is_approved_1'] = true;
        } elseif ($status === 2) {
            $fields['is_disapproved_1'] = true;
            $fields['disapproved_1'] = (string) (EmployeeLeaveLog::query()
                ->where('leave_id', $leave->leave_id)
                ->where('action', LeaveService::ACTION_DISAPPROVED)
                ->value('remarks') ?? '');
        }

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    private function leaveTypeSpecificFields(EmployeeLeave $leave, string &$remarks): array
    {
        $fields = [];
        $type = (int) $leave->leave_type;

        switch ($type) {
            case 1:
            case 3:
            case 4:
                if ($leave->leave_spent === 'Philippines') {
                    $fields['is_ph'] = true;
                    $fields['vl_ph'] = $leave->leave_spent_to;
                } else {
                    $fields['is_abroad'] = true;
                    $fields['vl_abroad'] = $leave->leave_spent_to;
                }
                $remarks = match ($type) {
                    1 => 'Applied for VL',
                    3 => 'Applied for FL',
                    default => 'Applied for SPL',
                };
                break;

            case 2:
                if ($leave->leave_spent === 'Hospital') {
                    $fields['is_hospital'] = true;
                    $fields['sl_hospital'] = $leave->leave_spent_to;
                } else {
                    $fields['is_opd'] = true;
                    $fields['sl_opd'] = $leave->leave_spent_to;
                }
                $remarks = 'Applied for SL';
                break;

            case 10:
                $fields['magna_carta'] = $leave->leave_spent_to;
                $remarks = 'Applied for Magna Carta for Women';
                break;

            case 11:
                $fields[$leave->leave_spent === 'BOARD' ? 'is_study_exam' : 'is_study_master'] = true;
                $remarks = 'Applied for Study Leave';
                break;

            default:
                if (in_array($type, [18, 21], true)) {
                    $fields['leave_18'] = $leave->leave_spent;
                }
                $remarks = 'Applied for '.($leave->leaveType?->leave_name ?: 'leave');
                break;
        }

        return $fields;
    }

    /**
     * @return array{0: float|int|string, 1: float|int|string, 2: float|int|string, 3: float|int|string, 4: float|int|string, 5: float|int|string}
     */
    private function creditSnapshotFromLog(EmployeeLeave $leave, string $remarks, ?EmployeeLeaveLog $log): array
    {
        $totalVl = (float) ($log?->vlc ?? $leave->employee?->vacation_leave_credits ?? 0);
        $totalSl = (float) ($log?->slc ?? $leave->employee?->sick_leave_credits ?? 0);
        $minusVl = 0.0;
        $minusSl = 0.0;
        $vlBal = $totalVl;
        $slBal = $totalSl;

        if (! $log) {
            return [$totalVl, $totalSl, $minusVl, $minusSl, $vlBal, $slBal];
        }

        $type = (int) $leave->leave_type;
        if (in_array($type, [1, 3, 11], true)) {
            $minusVl = (float) $log->credits;
            $totalVl = $vlBal + $minusVl;
        } elseif ($remarks === 'Applied for SL') {
            $minusSl = (float) $log->credits;
            $totalSl = $slBal + $minusSl;
        } elseif ($type === 20) {
            $minusVl = (float) $log->credits - (float) $log->vlc;
            $minusSl = (float) $log->credits - (float) $log->slc;
            $totalVl = $vlBal + (float) $log->credits;
            $totalSl = $slBal + (float) $log->credits;
        }

        return [$totalVl, $totalSl, $minusVl, $minusSl, $vlBal, $slBal];
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    private function resolveSignatories(Employee $employee, float $numdays, int $leaveTypeId): array
    {
        $supersign = 'N/A';
        $supervisor = '';
        $signatory = '';
        $signatoryPos = '';

        $divisionId = $employee->department?->division_id;
        $departmentId = $employee->department_id;
        $empId = (string) $employee->emp_id;

        if ((int) $employee->position_id === 50) {
            $director = $this->signatories->regionalDirector($empId);
            $supersign = 'N/A';
            $supervisor = '';
            $signatory = $director['name'];
            $signatoryPos = $director['pos'];
        } elseif ($numdays >= 30 && $leaveTypeId !== 20) {
            if ($this->signatories->isChief($empId)) {
                $supersign = 'N/A';
                $supervisor = '';
            } elseif ($employee->is_section_head === 'Y') {
                $head = $this->signatories->divisionChief($divisionId);
                $supersign = $head['name'];
                $supervisor = $head['pos'];
            } else {
                $head = $this->signatories->departmentHead($departmentId);
                if ($head['response'] === 1) {
                    $supersign = $head['name'];
                    $supervisor = $head['pos'];
                } else {
                    $head = $this->signatories->divisionChief($divisionId);
                    $supersign = $head['name'];
                    $supervisor = $head['pos'];
                }
            }
            $chief = $this->signatories->divisionChief(1);
            $signatory = $chief['name'];
            $signatoryPos = $chief['pos'];
        } elseif ($this->signatories->isChief($empId)) {
            $supersign = 'N/A';
            $supervisor = '';
            $chief = $this->signatories->divisionChief(1);
            $signatory = $chief['name'];
            $signatoryPos = $chief['pos'];
        } elseif ($this->signatories->isHead($empId)) {
            $supersign = 'N/A';
            $supervisor = '';
            $chief = $this->signatories->divisionChief($divisionId);
            $signatory = $chief['name'];
            $signatoryPos = $chief['pos'];
        } elseif ($this->signatories->isSpecialDepartment($departmentId)) {
            $chief = $this->signatories->specialDepartmentSignatory($departmentId);
            $signatory = $chief['name'];
            $signatoryPos = $chief['pos'];
            $supersign = '';
            $supervisor = 'Immediate Supervisor/Section Head';
        } elseif ((int) $divisionId === 1) {
            $supersign = '';
            $supervisor = 'Immediate Supervisor/Section Head';
            if (in_array((int) $departmentId, [64, 62], true)) {
                $chief = $this->signatories->divisionChief(1);
                $signatory = $chief['name'];
                $signatoryPos = $chief['pos'];
                $head = $this->signatories->departmentHead($departmentId);
                if ($head['response'] === 1) {
                    $supersign = $head['name'];
                    $supervisor = $head['pos'];
                }
            } else {
                $head = $this->signatories->departmentHead($departmentId);
                $signatory = $head['name'];
                $signatoryPos = $head['pos'];
            }
        } elseif ((int) $divisionId === 3) {
            $supersign = '';
            $supervisor = 'Immediate Supervisor/Section Head';
            $head = $this->signatories->divisionChief($divisionId);
            $signatory = $head['name'];
            $signatoryPos = $head['pos'];
        } else {
            $head = $this->signatories->departmentHead($departmentId);
            if ($head['response'] === 1) {
                $supersign = $head['name'];
                $supervisor = $head['pos'];
            }
            $chief = $this->signatories->divisionChief($divisionId);
            $signatory = $chief['name'];
            $signatoryPos = $chief['pos'];
        }

        return [$supersign, $supervisor, $signatory, $signatoryPos];
    }

    private function formatInclusiveDates(EmployeeLeave $leave, float $numdays): string
    {
        $dates = LeaveDates::for($leave);
        if ($dates === [] && $leave->start_date) {
            $dates = [CarbonImmutable::parse($leave->start_date)->toDateString()];
        }

        if ($numdays <= 1 || count($dates) <= 1) {
            $date = $dates[0] ?? optional($leave->start_date)?->toDateString();

            return $date ? CarbonImmutable::parse($date)->format('M j, Y') : '';
        }

        if ($numdays > 7) {
            return CarbonImmutable::parse($dates[0])->format('M j')
                .' to '
                .CarbonImmutable::parse($dates[array_key_last($dates)])->format('M j, Y');
        }

        $datestring = '';
        $mdate = null;
        $count = count($dates);
        foreach ($dates as $i => $raw) {
            $parsed = CarbonImmutable::parse($raw);
            $thismdate = $parsed->month;

            if ($i === 0) {
                $mdate = $thismdate;
                $datestring .= $parsed->format('M j');
                continue;
            }

            if ($mdate === $thismdate) {
                $datestring .= $i === ($count - 1)
                    ? ' & '.$parsed->format('j, Y')
                    : ' ,'.$parsed->format('j');
            } elseif ($i === ($count - 1)) {
                $datestring .= ' & '.$parsed->format('M j, Y');
            } else {
                $mdate = $thismdate;
                $datestring .= ' ,'.$parsed->format('M j');
            }
        }

        return $datestring;
    }

    private function templatePath(): string
    {
        $configured = (string) config('hris.leave_form_pdf', storage_path('app/forms/NEW_LEAVE_FORM.pdf'));
        if (is_file($configured)) {
            return $configured;
        }

        $fallback = base_path('reference projects/hris/public/fillables/NEW_LEAVE_FORM.pdf');
        abort_unless(is_file($fallback), 500, 'Leave form PDF template is missing.');

        return $fallback;
    }
}
