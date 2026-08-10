<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Leave Request #{{ $leave->leave_id }}</title>
    <style>
        body { margin: 0; background: #e2e8f0; color: #0f172a; font-family: Arial, Helvetica, sans-serif; font-size: 13px; }
        .actions { position: sticky; top: 0; display: flex; gap: 8px; justify-content: center; padding: 10px; background: #0f172a; }
        .actions a, .actions button { border: 1px solid #94a3b8; background: #fff; color: #0f172a; padding: 6px 12px; border-radius: 4px; text-decoration: none; cursor: pointer; }
        .sheet { max-width: 800px; margin: 16px auto; background: #fff; padding: 28px; box-shadow: 0 1px 3px rgba(0,0,0,.15); }
        h1 { margin: 0 0 4px; font-size: 20px; }
        .muted { color: #64748b; margin-bottom: 18px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 8px 10px; text-align: left; vertical-align: top; }
        th { width: 32%; background: #f8fafc; }
        @media print { .actions { display: none; } body { background: #fff; } .sheet { box-shadow: none; margin: 0; max-width: none; } }
    </style>
</head>
<body>
    <div class="actions">
        <a href="{{ $backUrl }}">Back</a>
        <button type="button" onclick="window.print()">Print</button>
    </div>
    <div class="sheet">
        <h1>Application for Leave</h1>
        <p class="muted">Leave ID {{ $leave->leave_id }} · Status: {{ $statusName }}</p>
        <table>
            <tr><th>Employee</th><td>{{ $leave->employee?->full_name ?: $leave->emp_id }} ({{ $leave->emp_id }})</td></tr>
            <tr><th>Department / Position</th><td>{{ $leave->employee?->department?->department ?? $leave->employee?->department?->department_name ?? '—' }} / {{ $leave->employee?->position?->position ?? $leave->employee?->position?->position_name ?? '—' }}</td></tr>
            <tr><th>Leave type</th><td>{{ $leave->leave_type_name }}</td></tr>
            <tr><th>Filing date</th><td>{{ optional($leave->filing_date)->format('Y-m-d') ?: '—' }}</td></tr>
            <tr><th>Inclusive dates</th><td>{{ optional($leave->start_date)->format('Y-m-d') ?: '—' }} to {{ optional($leave->end_date)->format('Y-m-d') ?: '—' }}</td></tr>
            <tr><th>Days with / without pay</th><td>{{ number_format((float) $leave->days_wpay, 3) }} / {{ number_format((float) $leave->days_wopay, 3) }}</td></tr>
            <tr><th>Leave location</th><td>{{ $leave->leave_spent ?: '—' }}</td></tr>
            <tr><th>Commutation</th><td>{{ $leave->commutation ?: '—' }}</td></tr>
            <tr><th>Remarks</th><td>{{ $leave->remarks ?: '—' }}</td></tr>
        </table>

        @if ($leave->logs->isNotEmpty())
            <h2 style="margin-top: 22px; font-size: 15px;">Action log</h2>
            <table>
                <thead>
                    <tr><th>When</th><th>Action</th><th>By</th><th>Remarks</th></tr>
                </thead>
                <tbody>
                    @foreach ($leave->logs as $log)
                        <tr>
                            <td>{{ optional($log->created_at)->format('Y-m-d H:i') }}</td>
                            <td>{{ $log->action }}</td>
                            <td>{{ $log->action_by }}</td>
                            <td>{{ $log->remarks }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</body>
</html>
