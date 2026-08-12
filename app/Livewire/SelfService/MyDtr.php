<?php

namespace App\Livewire\SelfService;

use App\Models\Hris\Employee;
use App\Services\Payroll\DailyTimeRecordPrintService;
use Carbon\CarbonImmutable;
use Livewire\Component;

class MyDtr extends Component
{
    public string $empId = '';

    public int $month;

    public int $year;

    public function mount(?string $empId = null): void
    {
        abort_unless(
            auth()->user()?->can('self-service.dtr')
            || auth()->user()?->can('self-service.access'),
            403
        );

        $this->empId = (string) ($empId ?: auth()->user()?->emp_id ?? '');
        abort_unless($this->empId !== '', 404);
        abort_unless($this->empId === (string) (auth()->user()?->emp_id ?? ''), 403);

        $today = CarbonImmutable::today();
        $this->month = (int) $today->month;
        $this->year = (int) $today->year;
    }

    public function render(DailyTimeRecordPrintService $dtrPrintService)
    {
        $this->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:1900,2100'],
        ]);

        $employee = Employee::query()
            ->with(['department', 'position'])
            ->where('emp_id', $this->empId)
            ->firstOrFail();

        $payload = $dtrPrintService->buildPrintPayload($this->empId, $this->month, $this->year);
        $rows = collect($payload['rows'])->filter(fn (array $row) => $row['date'] !== null)->values();

        $yearOptions = range((int) CarbonImmutable::today()->year, max(2020, (int) CarbonImmutable::today()->year - 5));

        return view('livewire.self-service.my-dtr', [
            'employee' => $employee,
            'period' => $payload['period'],
            'rows' => $rows,
            'monthOptions' => collect(range(1, 12))
                ->mapWithKeys(fn (int $m) => [$m => CarbonImmutable::createFromDate(2000, $m, 1)->format('F')])
                ->all(),
            'yearOptions' => $yearOptions,
            'printUrl' => route('self-service.dtr.print', [
                'month' => $this->month,
                'year' => $this->year,
            ]),
        ]);
    }
}
