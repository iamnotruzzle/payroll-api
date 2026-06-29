<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hris\Employee;
use App\Models\Hris\EmployeeDtr;
use App\Models\Hris\EmployeeLeave;
use App\Models\Hris\LeaveType;
use App\Models\Payroll\PayrollAuditLog;
use App\Models\Payroll\PayrollDtrAdjustment;
use App\Models\Payroll\PayrollDtrLabel;
use App\Models\Payroll\PayrollDtrLabelOption;
use App\Models\Payroll\PayrollDtrScheduleEncoding;
use App\Models\Payroll\PayrollEmployeeDeduction;
use App\Models\Payroll\PayrollEmployeePayrollLine;
use App\Models\Payroll\PayrollEmployeeSnapshot;
use App\Models\Payroll\PayrollHoliday;
use App\Models\Payroll\PayrollLeaveCreditAdjustment;
use App\Models\Payroll\PayrollMraReport;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\PayrollRun;
use App\Models\Payroll\PayrollTimekeepingSummary;
use App\Models\Payroll\PayrollTimeTemplate;
use App\Services\Payroll\SchedulerDtrSyncService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PayrollOperationsController extends Controller
{
    public function periods(Request $request): JsonResponse
    {
        $query = PayrollPeriod::query();
        $sort = $request->get('sort', 'period_start');
        $direction = $this->direction($request);

        $query->orderBy($sort === 'id' ? 'id' : 'period_start', $direction);

        return response()->json($query->paginate(
            (int) $request->get('per_page', 10),
            ['*'],
            'page',
            (int) $request->get('page', 1)
        ));
    }

    public function runs(Request $request): JsonResponse
    {
        $query = PayrollRun::query();

        if ($request->filled('status')) {
            $query->where('status', (int) $request->get('status'));
        }

        if ($request->filled('payroll_type_id')) {
            $query->where('payroll_type_id', (int) $request->get('payroll_type_id'));
        }

        if ($request->filled('q')) {
            $term = trim((string) $request->get('q'));
            $query->where(function (Builder $builder) use ($term) {
                if (is_numeric($term)) {
                    $builder->orWhere('id', (int) $term)
                        ->orWhere('payroll_type_id', (int) $term)
                        ->orWhere('status', (int) $term);
                }

                $builder->orWhere('generated_by', 'like', "%{$term}%");

                if ($this->looksLikeDate($term)) {
                    $builder->orWhereDate('payroll_date', $term);
                }
            });
        }

        $sortMap = [
            'id' => 'id',
            'payroll_type_id' => 'payroll_type_id',
            'status' => 'status',
            'generated_by' => 'generated_by',
            'payroll_date' => 'payroll_date',
        ];

        $sort = $sortMap[$request->get('sort', 'payroll_date')] ?? 'payroll_date';
        $query->orderBy($sort, $this->direction($request));

        if ($sort === 'payroll_date') {
            $query->orderBy('id', $this->direction($request));
        }

        return response()->json($query->paginate(
            (int) $request->get('per_page', 10),
            ['*'],
            'page',
            (int) $request->get('page', 1)
        ));
    }

    public function run(int $id): JsonResponse
    {
        return response()->json(PayrollRun::findOrFail($id));
    }

    public function runLines(Request $request, int $id): JsonResponse
    {
        $query = PayrollEmployeePayrollLine::query()
            ->where('payroll_generate_id', $id);

        if ($request->filled('q')) {
            $term = trim((string) $request->get('q'));
            $query->where(function (Builder $builder) use ($term) {
                $builder->where('emp_id', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%")
                    ->orWhere('remarks', 'like', "%{$term}%");

                if (is_numeric($term)) {
                    $builder->orWhere('amount', (float) $term);
                }
            });
        }

        $sortMap = [
            'emp_id' => 'emp_id',
            'name' => 'name',
            'amount' => 'amount',
        ];

        $sort = $sortMap[$request->get('sort', 'emp_id')] ?? 'emp_id';
        $query->orderBy($sort, $this->direction($request));

        return response()->json($query->paginate(
            (int) $request->get('per_page', 15),
            ['*'],
            'page',
            (int) $request->get('page', 1)
        ));
    }

    public function payslips(Request $request): JsonResponse
    {
        $query = PayrollEmployeePayrollLine::query()
            ->selectRaw('payroll_employee_payroll_lines.payroll_generate_id as run_id')
            ->selectRaw('payroll_generates.payroll_date as payroll_date')
            ->selectRaw('payroll_employee_payroll_lines.emp_id as emp_id')
            ->selectRaw("SUM(CASE WHEN line_group = 'DEDUCTION' THEN -amount ELSE amount END) as total_amount")
            ->leftJoin('payroll_generates', 'payroll_generates.id', '=', 'payroll_employee_payroll_lines.payroll_generate_id')
            ->groupBy('payroll_employee_payroll_lines.payroll_generate_id', 'payroll_generates.payroll_date', 'payroll_employee_payroll_lines.emp_id');

        if ($request->filled('q')) {
            $term = trim((string) $request->get('q'));
            $query->having(function ($builder) use ($term) {
                $builder->having('emp_id', 'like', "%{$term}%");

                if (is_numeric($term)) {
                    $builder->orHaving('run_id', (int) $term)
                        ->orHaving('total_amount', (float) $term);
                }

                if ($this->looksLikeDate($term)) {
                    $builder->orHavingRaw('DATE(payroll_date) = ?', [$term]);
                }
            });
        }

        $sortMap = [
            'run' => 'run_id',
            'date' => 'payroll_date',
            'employee' => 'emp_id',
            'amount' => 'total_amount',
        ];

        $sort = $sortMap[$request->get('sort', 'date')] ?? 'payroll_date';
        $query->orderBy($sort, $this->direction($request));

        return response()->json($query->paginate(
            (int) $request->get('per_page', 15),
            ['*'],
            'page',
            (int) $request->get('page', 1)
        ));
    }

    public function mraReports(Request $request): JsonResponse
    {
        $query = PayrollMraReport::query();
        $this->applyDepartmentPeriodFilter($query, $request);

        return response()->json($query->orderByDesc('generated_at')->get());
    }

    public function saveMraReport(Request $request): JsonResponse
    {
        $data = $request->validate([
            'department_id' => ['required', 'integer'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date'],
            'status' => ['required', 'string', 'max:64'],
            'generated_by' => ['required', 'string', 'max:255'],
            'generated_at' => ['required', 'date'],
            'remarks' => ['nullable', 'string'],
        ]);

        $report = PayrollMraReport::updateOrCreate(
            [
                'department_id' => $data['department_id'],
                'period_start' => $data['period_start'],
                'period_end' => $data['period_end'],
            ],
            $data
        );

        return response()->json($report);
    }

    public function finalizeMraReport(Request $request): JsonResponse
    {
        $data = $request->validate([
            'department_id' => ['required', 'integer'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date'],
            'generated_by' => ['required', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
            'employee_type' => ['nullable', Rule::in(array_keys(Employee::employeeTypeOptions()))],
        ]);

        $report = DB::connection('payroll')->transaction(function () use ($data) {
            $report = PayrollMraReport::updateOrCreate(
                [
                    'department_id' => $data['department_id'],
                    'period_start' => $data['period_start'],
                    'period_end' => $data['period_end'],
                ],
                [
                    'status' => 'Finalized',
                    'generated_by' => $data['generated_by'],
                    'generated_at' => now(),
                    'remarks' => $data['remarks'] ?? null,
                ]
            );

            $previous = PayrollLeaveCreditAdjustment::query()
                ->where('mra_report_id', $report->id)
                ->get();

            if ($previous->isNotEmpty()) {
                $employees = Employee::query()
                    ->whereIn('emp_id', $previous->pluck('emp_id')->all())
                    ->get()
                    ->keyBy('emp_id');

                foreach ($previous as $adjustment) {
                    $employee = $employees->get($adjustment->emp_id);
                    if ($employee && strcasecmp((string) $adjustment->leave_type, 'VL') === 0) {
                        $employee->vacation_leave_credits += (float) $adjustment->adjustment_days;
                        $employee->save();
                    }
                }

                PayrollLeaveCreditAdjustment::query()
                    ->where('mra_report_id', $report->id)
                    ->delete();
            }

            $employees = Employee::query()
                ->where('department_id', $data['department_id'])
                ->where('is_active', 'Y')
                ->employeeType($data['employee_type'] ?? Employee::EMPLOYEE_TYPE_PLANTILLA)
                ->orderBy('lastname')
                ->orderBy('firstname')
                ->get();

            $adjustments = PayrollDtrAdjustment::query()
                ->whereIn('emp_id', $employees->pluck('emp_id')->all())
                ->whereBetween('dtr_date', [$data['period_start'], $data['period_end']])
                ->where('minutes', '>', 0)
                ->whereIn('adjustment_type', ['TARDINESS', 'UNDERTIME'])
                ->get()
                ->groupBy('emp_id');

            foreach ($employees as $employee) {
                $minutes = (int) ($adjustments->get($employee->emp_id)?->sum('minutes') ?? 0);
                if ($minutes <= 0) {
                    continue;
                }

                $days = round($minutes / 480, 3);
                if ($days <= 0) {
                    continue;
                }

                $beginning = (float) $employee->vacation_leave_credits;
                $ending = max(0, $beginning - $days);
                $employee->vacation_leave_credits = $ending;
                $employee->save();

                PayrollLeaveCreditAdjustment::create([
                    'mra_report_id' => $report->id,
                    'emp_id' => $employee->emp_id,
                    'employee_name' => trim("{$employee->lastname}, {$employee->firstname}"),
                    'leave_type' => 'VL',
                    'beginning_balance' => $beginning,
                    'adjustment_days' => $days,
                    'ending_balance' => $ending,
                    'undertime_tardy_minutes' => $minutes,
                    'remarks' => 'MRA '.$data['period_start'].' undertime/tardiness day equivalent.',
                    'created_at' => now(),
                ]);
            }

            return $report->fresh();
        });

        return response()->json($report);
    }

    public function dtrLabels(Request $request): JsonResponse
    {
        $query = PayrollDtrLabel::query();
        $this->applyEmployeeDateFilter($query, $request);

        return response()->json($query->orderBy('dtr_date')->get());
    }

    public function saveDtrLabels(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'labels' => ['required', 'array'],
            'labels.*.emp_id' => ['required', 'string'],
            'labels.*.dtr_date' => ['required', 'date'],
            'labels.*.label' => ['nullable', 'string'],
            'labels.*.remarks' => ['nullable', 'string'],
        ])['labels'];

        DB::connection('payroll')->transaction(function () use ($payload) {
            foreach ($payload as $item) {
                $existing = PayrollDtrLabel::query()
                    ->where('emp_id', $item['emp_id'])
                    ->whereDate('dtr_date', $item['dtr_date'])
                    ->first();

                if (blank($item['label'] ?? null)) {
                    $existing?->delete();

                    continue;
                }

                PayrollDtrLabel::updateOrCreate(
                    [
                        'emp_id' => $item['emp_id'],
                        'dtr_date' => $item['dtr_date'],
                    ],
                    [
                        'label' => $item['label'],
                        'remarks' => $item['remarks'] ?? null,
                    ]
                );
            }
        });

        return response()->json(['message' => 'Saved.']);
    }

    public function dtrAdjustments(Request $request): JsonResponse
    {
        $query = PayrollDtrAdjustment::query();
        $this->applyEmployeeDateFilter($query, $request);

        return response()->json($query->orderBy('dtr_date')->orderBy('adjustment_type')->get());
    }

    public function saveDtrAdjustments(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'adjustments' => ['required', 'array'],
            'adjustments.*.emp_id' => ['required', 'string'],
            'adjustments.*.dtr_date' => ['required', 'date'],
            'adjustments.*.adjustment_type' => ['required', 'string'],
            'adjustments.*.minutes' => ['required', 'integer', 'min:0'],
            'adjustments.*.remarks' => ['nullable', 'string'],
            'adjustments.*.encoded_by' => ['nullable', 'string'],
        ])['adjustments'];

        DB::connection('payroll')->transaction(function () use ($payload) {
            foreach ($payload as $item) {
                $existing = PayrollDtrAdjustment::query()
                    ->where('emp_id', $item['emp_id'])
                    ->whereDate('dtr_date', $item['dtr_date'])
                    ->where('adjustment_type', $item['adjustment_type'])
                    ->first();

                if ((int) $item['minutes'] <= 0) {
                    $existing?->delete();

                    continue;
                }

                PayrollDtrAdjustment::updateOrCreate(
                    [
                        'emp_id' => $item['emp_id'],
                        'dtr_date' => $item['dtr_date'],
                        'adjustment_type' => $item['adjustment_type'],
                    ],
                    [
                        'minutes' => $item['minutes'],
                        'remarks' => $item['remarks'] ?? null,
                        'encoded_by' => $item['encoded_by'] ?? '',
                    ]
                );
            }
        });

        return response()->json(['message' => 'Saved.']);
    }

    public function dtrScheduleEncodings(Request $request): JsonResponse
    {
        $this->syncSchedulerDtrFromRequest($request);

        $query = PayrollDtrScheduleEncoding::query();
        $this->applyEmployeeDateFilter($query, $request);

        return response()->json($query->orderBy('dtr_date')->get());
    }

    public function saveDtrScheduleEncodings(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'encodings' => ['required', 'array'],
            'encodings.*.emp_id' => ['required', 'string'],
            'encodings.*.dtr_date' => ['required', 'date'],
            'encodings.*.payroll_time_template_id' => ['nullable', 'integer'],
            'encodings.*.encoded_by' => ['nullable', 'string'],
        ])['encodings'];

        DB::connection('payroll')->transaction(function () use ($payload) {
            foreach ($payload as $item) {
                $existing = PayrollDtrScheduleEncoding::query()
                    ->where('emp_id', $item['emp_id'])
                    ->whereDate('dtr_date', $item['dtr_date'])
                    ->first();

                if (blank($item['payroll_time_template_id'] ?? null)) {
                    $existing?->delete();

                    continue;
                }

                PayrollDtrScheduleEncoding::updateOrCreate(
                    [
                        'emp_id' => $item['emp_id'],
                        'dtr_date' => $item['dtr_date'],
                    ],
                    [
                        'payroll_time_template_id' => $item['payroll_time_template_id'],
                        'encoded_by' => $item['encoded_by'] ?? '',
                    ]
                );
            }
        });

        return response()->json(['message' => 'Saved.']);
    }

    public function leaveCreditAdjustments(Request $request): JsonResponse
    {
        $query = PayrollLeaveCreditAdjustment::query();

        if ($request->filled('mra_report_id')) {
            $query->where('mra_report_id', (int) $request->get('mra_report_id'));
        }

        return response()->json($query->orderBy('emp_id')->get());
    }

    public function replaceLeaveCreditAdjustments(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mra_report_id' => ['required', 'integer'],
            'adjustments' => ['required', 'array'],
            'adjustments.*.emp_id' => ['required', 'string'],
            'adjustments.*.employee_name' => ['required', 'string'],
            'adjustments.*.leave_type' => ['required', 'string'],
            'adjustments.*.beginning_balance' => ['required', 'numeric'],
            'adjustments.*.adjustment_days' => ['required', 'numeric'],
            'adjustments.*.ending_balance' => ['required', 'numeric'],
            'adjustments.*.undertime_tardy_minutes' => ['required', 'integer'],
            'adjustments.*.remarks' => ['nullable', 'string'],
        ]);

        DB::connection('payroll')->transaction(function () use ($data) {
            PayrollLeaveCreditAdjustment::query()
                ->where('mra_report_id', $data['mra_report_id'])
                ->delete();

            foreach ($data['adjustments'] as $item) {
                PayrollLeaveCreditAdjustment::create([
                    'mra_report_id' => $data['mra_report_id'],
                    ...$item,
                ]);
            }
        });

        return response()->json(['message' => 'Saved.']);
    }

    public function employeeDeductions(Request $request): JsonResponse
    {
        $query = PayrollEmployeeDeduction::query()->where('is_active', true);
        $this->applyEmployeeDateFilter($query, $request, 'effective_start', 'effective_end');

        return response()->json($query->orderByDesc('effective_start')->get());
    }

    public function officeDtrState(Request $request): JsonResponse
    {
        $data = $request->validate([
            'department_id' => ['required', 'integer'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date'],
            'employee_type' => ['nullable', Rule::in(array_keys(Employee::employeeTypeOptions()))],
        ]);

        app(SchedulerDtrSyncService::class)->syncDepartmentPeriod(
            (int) $data['department_id'],
            $data['from'],
            $data['to'],
        );

        $employees = Employee::query()
            ->with('position')
            ->where('department_id', $data['department_id'])
            ->where('is_active', 'Y')
            ->employeeType($data['employee_type'] ?? Employee::EMPLOYEE_TYPE_PLANTILLA)
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->get();
        $empIds = $employees->pluck('emp_id')->all();

        return response()->json([
            'is_locked' => PayrollMraReport::query()
                ->where('department_id', $data['department_id'])
                ->whereDate('period_start', $data['from'])
                ->whereDate('period_end', $data['to'])
                ->exists(),
            'employees' => $employees,
            'dtrs' => EmployeeDtr::query()
                ->whereIn('emp_id', $empIds)
                ->whereBetween('dtr_date', [$data['from'], $data['to']])
                ->get(),
            'leaves' => EmployeeLeave::query()
                ->whereIn('emp_id', $empIds)
                ->whereNotNull('start_date')
                ->whereNotNull('end_date')
                ->whereDate('start_date', '<=', $data['to'])
                ->whereDate('end_date', '>=', $data['from'])
                ->whereDoesntHave('logs', fn ($query) => $query->whereIn('action', [2, 3]))
                ->get(),
            'labels' => PayrollDtrLabel::query()
                ->whereIn('emp_id', $empIds)
                ->whereBetween('dtr_date', [$data['from'], $data['to']])
                ->get(),
            'adjustments' => PayrollDtrAdjustment::query()
                ->whereIn('emp_id', $empIds)
                ->whereBetween('dtr_date', [$data['from'], $data['to']])
                ->get(),
            'schedules' => PayrollDtrScheduleEncoding::query()
                ->whereIn('emp_id', $empIds)
                ->whereBetween('dtr_date', [$data['from'], $data['to']])
                ->get(),
            'holidays' => PayrollHoliday::query()
                ->where('is_active', true)
                ->whereBetween('holiday_date', [$data['from'], $data['to']])
                ->get(),
            'time_templates' => PayrollTimeTemplate::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'label_options' => PayrollDtrLabelOption::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function mraPreviewState(Request $request): JsonResponse
    {
        $data = $request->validate([
            'department_id' => ['required', 'integer'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'employee_type' => ['nullable', Rule::in(array_keys(Employee::employeeTypeOptions()))],
        ]);

        app(SchedulerDtrSyncService::class)->syncDepartmentPeriod(
            (int) $data['department_id'],
            $data['from'],
            $data['to'],
        );

        $page = (int) ($data['page'] ?? 1);
        $perPage = (int) ($data['per_page'] ?? 10);
        $employeesPage = Employee::query()
            ->with('position')
            ->where('department_id', $data['department_id'])
            ->where('is_active', 'Y')
            ->employeeType($data['employee_type'] ?? Employee::EMPLOYEE_TYPE_PLANTILLA)
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->paginate($perPage, ['*'], 'page', $page);

        $employees = $employeesPage->getCollection()->values();
        $empIds = $employees->pluck('emp_id')->all();
        $leaves = EmployeeLeave::query()
            ->whereIn('emp_id', $empIds)
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->whereDate('start_date', '<=', $data['to'])
            ->whereDate('end_date', '>=', $data['from'])
            ->whereDoesntHave('logs', fn ($query) => $query->whereIn('action', [2, 3]))
            ->get();
        $leaveNames = LeaveType::query()
            ->whereIn('leave_type_id', $leaves->pluck('leave_type')->filter()->unique()->all())
            ->pluck('leave_name', 'leave_type_id');
        $leaves->each(function ($leave) use ($leaveNames) {
            $leave->leave_name = $leaveNames[$leave->leave_type] ?? "Leave #{$leave->leave_type}";
        });

        return response()->json([
            'total_employees' => $employeesPage->total(),
            'employees' => $employees,
            'dtrs' => EmployeeDtr::query()
                ->whereIn('emp_id', $empIds)
                ->whereBetween('dtr_date', [$data['from'], $data['to']])
                ->get(),
            'leaves' => $leaves,
            'labels' => PayrollDtrLabel::query()
                ->whereIn('emp_id', $empIds)
                ->whereBetween('dtr_date', [$data['from'], $data['to']])
                ->get(),
            'adjustments' => PayrollDtrAdjustment::query()
                ->whereIn('emp_id', $empIds)
                ->whereBetween('dtr_date', [$data['from'], $data['to']])
                ->get(),
            'schedules' => PayrollDtrScheduleEncoding::query()
                ->whereIn('emp_id', $empIds)
                ->whereBetween('dtr_date', [$data['from'], $data['to']])
                ->get(),
            'holidays' => PayrollHoliday::query()
                ->where('is_active', true)
                ->whereBetween('holiday_date', [$data['from'], $data['to']])
                ->get(),
            'time_templates' => PayrollTimeTemplate::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'label_options' => PayrollDtrLabelOption::query()
                ->get(),
        ]);
    }

    public function generateRun(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period.period_start' => ['required', 'date'],
            'period.period_end' => ['required', 'date'],
            'period.period_type' => ['required', 'string'],
            'run.payroll_date' => ['required', 'date'],
            'run.payroll_type_id' => ['required', 'integer'],
            'run.department_id' => ['nullable', 'integer'],
            'run.department_name' => ['nullable', 'string'],
            'run.status' => ['required', 'integer'],
            'run.generated_by' => ['required', 'string'],
            'run.gross_pay' => ['required', 'numeric'],
            'run.total_additions' => ['required', 'numeric'],
            'run.total_deductions' => ['required', 'numeric'],
            'run.net_pay' => ['required', 'numeric'],
            'snapshots' => ['required', 'array'],
            'summaries' => ['required', 'array'],
            'lines' => ['required', 'array'],
            'audit.action' => ['required', 'string'],
            'audit.performed_by' => ['required', 'string'],
            'audit.remarks' => ['nullable', 'string'],
        ]);

        $run = DB::connection('payroll')->transaction(function () use ($data) {
            $period = PayrollPeriod::create([
                ...$data['period'],
                'is_locked' => true,
                'locked_at' => now(),
            ]);

            $run = PayrollRun::create([
                ...$data['run'],
                'payroll_period_id' => $period->id,
            ]);

            foreach ($data['snapshots'] as $snapshot) {
                PayrollEmployeeSnapshot::create([
                    ...$snapshot,
                    'payroll_generate_id' => $run->id,
                    'created_at' => now(),
                ]);
            }

            foreach ($data['summaries'] as $summary) {
                PayrollTimekeepingSummary::create([
                    ...$summary,
                    'payroll_generate_id' => $run->id,
                    'created_at' => now(),
                ]);
            }

            foreach ($data['lines'] as $line) {
                PayrollEmployeePayrollLine::create([
                    ...$line,
                    'payroll_generate_id' => $run->id,
                ]);
            }

            PayrollAuditLog::create([
                ...$data['audit'],
                'payroll_generate_id' => $run->id,
                'created_at' => now(),
            ]);

            return $run->fresh();
        });

        return response()->json($run);
    }

    private function applyEmployeeDateFilter(Builder $query, Request $request, string $fromColumn = 'dtr_date', string $toColumn = 'dtr_date'): void
    {
        if ($request->filled('emp_id')) {
            $query->where('emp_id', $request->get('emp_id'));
        }

        if ($request->filled('emp_ids')) {
            $ids = collect(explode(',', (string) $request->get('emp_ids')))
                ->map(fn ($id) => trim($id))
                ->filter()
                ->values()
                ->all();
            $query->whereIn('emp_id', $ids);
        }

        if ($fromColumn === $toColumn) {
            if ($request->filled('from')) {
                $query->whereDate($fromColumn, '>=', $request->get('from'));
            }

            if ($request->filled('to')) {
                $query->whereDate($toColumn, '<=', $request->get('to'));
            }

            return;
        }

        if ($request->filled('from')) {
            $query->where(function (Builder $builder) use ($request, $toColumn) {
                $builder->whereNull($toColumn)
                    ->orWhereDate($toColumn, '>=', $request->get('from'));
            });
        }

        if ($request->filled('to')) {
            $query->where(function (Builder $builder) use ($request, $fromColumn) {
                $builder->whereNull($fromColumn)
                    ->orWhereDate($fromColumn, '<=', $request->get('to'));
            });
        }
    }

    private function applyDepartmentPeriodFilter(Builder $query, Request $request): void
    {
        if ($request->filled('department_id')) {
            $query->where('department_id', (int) $request->get('department_id'));
        }

        if ($request->filled('period_start')) {
            $query->whereDate('period_start', $request->get('period_start'));
        }

        if ($request->filled('period_end')) {
            $query->whereDate('period_end', $request->get('period_end'));
        }
    }

    private function syncSchedulerDtrFromRequest(Request $request): void
    {
        if (! $request->filled('from') || ! $request->filled('to')) {
            return;
        }

        $sync = app(SchedulerDtrSyncService::class);

        if ($request->filled('department_id')) {
            $sync->syncDepartmentPeriod((int) $request->get('department_id'), $request->get('from'), $request->get('to'));

            return;
        }

        if ($request->filled('emp_id')) {
            $sync->syncEmployeesPeriod([$request->get('emp_id')], $request->get('from'), $request->get('to'));

            return;
        }

        if ($request->filled('emp_ids')) {
            $ids = collect(explode(',', (string) $request->get('emp_ids')))
                ->map(fn ($id) => trim($id))
                ->filter()
                ->values()
                ->all();

            $sync->syncEmployeesPeriod($ids, $request->get('from'), $request->get('to'));
        }
    }

    private function direction(Request $request): string
    {
        return strtolower((string) $request->get('direction', 'desc')) === 'asc'
            ? 'asc'
            : 'desc';
    }

    private function looksLikeDate(string $value): bool
    {
        return (bool) strtotime($value);
    }
}
