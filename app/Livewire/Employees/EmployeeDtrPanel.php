<?php

namespace App\Livewire\Employees;

use App\Models\Hris\EmployeeDtr;
use Carbon\CarbonImmutable;
use Livewire\Component;

class EmployeeDtrPanel extends Component
{
    public string $empId;

    /** @var array<int, array{timein_am:?string,timeout_am:?string,timein_pm:?string,timeout_pm:?string}> */
    public array $punches = [];

    public function mount(string $empId): void
    {
        abort_unless(
            auth()->user()?->can('payroll.view') || auth()->user()?->can('self-service.dtr'),
            403
        );
        $this->empId = $empId;
    }

    public function savePunch(int $dtrId): void
    {
        abort_unless(auth()->user()?->can('payroll.view') || auth()->user()?->can('payroll.generate'), 403);

        $row = EmployeeDtr::query()
            ->where('emp_id', $this->empId)
            ->where('dtr_id', $dtrId)
            ->firstOrFail();

        $data = $this->validate([
            "punches.{$dtrId}.timein_am" => ['nullable', 'string', 'max:20'],
            "punches.{$dtrId}.timeout_am" => ['nullable', 'string', 'max:20'],
            "punches.{$dtrId}.timein_pm" => ['nullable', 'string', 'max:20'],
            "punches.{$dtrId}.timeout_pm" => ['nullable', 'string', 'max:20'],
        ])['punches'][$dtrId];

        $row->fill([
            'timein_am' => $this->blankToNull($data['timein_am'] ?? null),
            'timeout_am' => $this->blankToNull($data['timeout_am'] ?? null),
            'timein_pm' => $this->blankToNull($data['timein_pm'] ?? null),
            'timeout_pm' => $this->blankToNull($data['timeout_pm'] ?? null),
        ])->save();

        session()->flash('status', 'DTR punch updated for '.optional($row->dtr_date)->format('Y-m-d').'.');
    }

    public function render()
    {
        $from = CarbonImmutable::today()->subDays(30)->toDateString();

        $rows = EmployeeDtr::query()
            ->where('emp_id', $this->empId)
            ->whereDate('dtr_date', '>=', $from)
            ->orderByDesc('dtr_date')
            ->limit(31)
            ->get();

        foreach ($rows as $row) {
            $this->punches[$row->dtr_id] ??= [
                'timein_am' => (string) ($row->timein_am ?? ''),
                'timeout_am' => (string) ($row->timeout_am ?? ''),
                'timein_pm' => (string) ($row->timein_pm ?? ''),
                'timeout_pm' => (string) ($row->timeout_pm ?? ''),
            ];
        }

        return view('livewire.employees.employee-dtr-panel', [
            'rows' => $rows,
            'from' => $from,
            'canManage' => (bool) (auth()->user()?->can('payroll.view') || auth()->user()?->can('payroll.generate')),
        ]);
    }

    private function blankToNull(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
