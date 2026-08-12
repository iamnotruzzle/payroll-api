<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payslip · {{ $batch?->payroll_period ?? $record->id }}</title>
    <style>
        body { margin: 0; background: #e2e8f0; color: #0f172a; font-family: Arial, Helvetica, sans-serif; font-size: 13px; }
        .actions { position: sticky; top: 0; display: flex; gap: 8px; justify-content: center; padding: 10px; background: #0f172a; }
        .actions a, .actions button { border: 1px solid #94a3b8; background: #fff; color: #0f172a; padding: 6px 12px; border-radius: 4px; text-decoration: none; cursor: pointer; }
        .sheet { max-width: 860px; margin: 16px auto; background: #fff; padding: 28px; box-shadow: 0 1px 3px rgba(0,0,0,.15); }
        h1 { margin: 0 0 4px; font-size: 20px; }
        h2 { margin: 22px 0 8px; font-size: 14px; text-transform: uppercase; letter-spacing: .04em; color: #475569; }
        .muted { color: #64748b; margin-bottom: 18px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 18px; }
        .meta dt { color: #64748b; font-size: 11px; text-transform: uppercase; }
        .meta dd { margin: 2px 0 0; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 7px 10px; text-align: left; vertical-align: top; }
        th { background: #f8fafc; width: 55%; }
        td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
        .totals td { font-weight: 700; }
        .split { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 16px; }
        .split .card { border: 1px solid #cbd5e1; border-radius: 4px; padding: 12px; background: #f8fafc; }
        .split .label { color: #64748b; font-size: 11px; text-transform: uppercase; }
        .split .value { margin-top: 4px; font-size: 20px; font-weight: 700; }
        @media print {
            .actions { display: none; }
            body { background: #fff; }
            .sheet { box-shadow: none; margin: 0; max-width: none; }
        }
        @media (max-width: 640px) {
            .grid, .split { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="actions">
        <a href="{{ $backUrl }}">Back</a>
        <button type="button" onclick="window.print()">Print</button>
    </div>

    <div class="sheet">
        <h1>Employee Payslip</h1>
        <p class="muted">
            Period {{ $batch?->payroll_period ?? '—' }}
            · {{ $batch?->payroll_type ?? 'Payroll' }}
            @if ($batch?->snapshot_created_at)
                · Snapshot {{ $batch->snapshot_created_at->format('M d, Y g:i A') }}
            @endif
        </p>

        <dl class="grid meta">
            <div>
                <dt>Employee</dt>
                <dd>{{ $employee['employee_name'] ?? $record->emp_id }} ({{ $record->emp_id }})</dd>
            </div>
            <div>
                <dt>Department / Position</dt>
                <dd>{{ $employee['department'] ?? '—' }} / {{ $employee['position'] ?? '—' }}</dd>
            </div>
            <div>
                <dt>Salary grade / Step</dt>
                <dd>{{ $employee['sg_step'] ?? (($employee['salary_grade'] ?? '—').' / '.($employee['step'] ?? '—')) }}</dd>
            </div>
            <div>
                <dt>Snapshot ID</dt>
                <dd>#{{ $record->id }}</dd>
            </div>
        </dl>

        <div class="split">
            <div class="card">
                <div class="label">15th release</div>
                <div class="value">{{ number_format((float) ($totals['fifteenth'] ?? $record->fifteenth ?? 0), 2) }}</div>
            </div>
            <div class="card">
                <div class="label">30th release</div>
                <div class="value">{{ number_format((float) ($totals['thirtieth'] ?? $record->thirtieth ?? 0), 2) }}</div>
            </div>
        </div>

        <h2>Earnings</h2>
        <table>
            <tr>
                <th>Basic salary</th>
                <td class="num">{{ number_format((float) ($earnings['basic_salary'] ?? 0), 2) }}</td>
            </tr>
            @foreach (($earnings['compensations'] ?? []) as $compensation)
                <tr>
                    <th>{{ $compensation['name'] ?? 'Compensation' }}</th>
                    <td class="num">{{ number_format((float) ($compensation['amount'] ?? 0), 2) }}</td>
                </tr>
            @endforeach
            <tr class="totals">
                <th>Gross</th>
                <td class="num">{{ number_format((float) ($totals['gross'] ?? $earnings['gross'] ?? $record->gross ?? 0), 2) }}</td>
            </tr>
            <tr class="totals">
                <th>Net compensation</th>
                <td class="num">{{ number_format((float) ($totals['net_compensation'] ?? $earnings['net_compensation'] ?? 0), 2) }}</td>
            </tr>
        </table>

        @if (! empty($statutory) || ! empty($tax))
            <h2>Mandatory / tax</h2>
            <table>
                @foreach ($statutory as $key => $amount)
                    @if (is_numeric($amount))
                        <tr>
                            <th>{{ strtoupper(str_replace('_', ' ', (string) $key)) }}</th>
                            <td class="num">{{ number_format((float) $amount, 2) }}</td>
                        </tr>
                    @endif
                @endforeach
                @if (isset($tax['withholding_tax']) || isset($tax['monthly_tax_due']))
                    <tr>
                        <th>Withholding tax</th>
                        <td class="num">{{ number_format((float) ($tax['withholding_tax'] ?? $tax['monthly_tax_due'] ?? 0), 2) }}</td>
                    </tr>
                @endif
                <tr class="totals">
                    <th>Total mandatory deductions</th>
                    <td class="num">{{ number_format((float) ($totals['total_mandatory_deductions'] ?? 0), 2) }}</td>
                </tr>
            </table>
        @endif

        @php
            $programItems = collect($programs);
            if ($programItems->isNotEmpty() && ! $programItems->keys()->every(fn ($k) => is_int($k))) {
                $programItems = $programItems->map(fn ($amount, $key) => [
                    'name' => is_string($key) ? strtoupper(str_replace('_', ' ', $key)) : 'Program deduction',
                    'amount' => $amount,
                ])->values();
            }
            $premiumItems = collect($premiums);
            if ($premiumItems->isNotEmpty() && ! $premiumItems->keys()->every(fn ($k) => is_int($k))) {
                $premiumItems = $premiumItems->map(fn ($amount, $key) => [
                    'name' => is_string($key) ? strtoupper(str_replace('_', ' ', $key)) : 'Premium',
                    'amount' => $amount,
                ])->values();
            }
            $loanColumns = $loans['columns'] ?? $loans;
        @endphp

        @if ($programItems->isNotEmpty() || $premiumItems->isNotEmpty() || ! empty($loanColumns))
            <h2>Other deductions</h2>
            <table>
                @foreach ($programItems as $item)
                    @php $amount = is_array($item) ? ($item['amount'] ?? 0) : $item; @endphp
                    @if (is_numeric($amount))
                        <tr>
                            <th>{{ is_array($item) ? ($item['name'] ?? 'Program deduction') : 'Program deduction' }}</th>
                            <td class="num">{{ number_format((float) $amount, 2) }}</td>
                        </tr>
                    @endif
                @endforeach
                @foreach ($premiumItems as $item)
                    @php $amount = is_array($item) ? ($item['amount'] ?? 0) : $item; @endphp
                    @if (is_numeric($amount))
                        <tr>
                            <th>{{ is_array($item) ? ($item['name'] ?? 'Premium') : 'Premium' }}</th>
                            <td class="num">{{ number_format((float) $amount, 2) }}</td>
                        </tr>
                    @endif
                @endforeach
                @foreach ((array) $loanColumns as $key => $amount)
                    @if (is_numeric($amount) && (float) $amount != 0.0)
                        <tr>
                            <th>{{ strtoupper(str_replace('_', ' ', (string) $key)) }}</th>
                            <td class="num">{{ number_format((float) $amount, 2) }}</td>
                        </tr>
                    @endif
                @endforeach
            </table>
        @endif

        <h2>Net pay</h2>
        <table>
            <tr class="totals">
                <th>Net after loans</th>
                <td class="num">{{ number_format((float) ($totals['net_after_loan_deductions'] ?? $record->net ?? 0), 2) }}</td>
            </tr>
        </table>
    </div>
</body>
</html>
