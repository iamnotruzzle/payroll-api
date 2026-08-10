<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Leave Card — {{ $employee->emp_id }}</title>
    <style>
        body { margin: 0; background: #e2e8f0; color: #0f172a; font-family: Arial, Helvetica, sans-serif; font-size: 12px; }
        .actions { position: sticky; top: 0; display: flex; gap: 8px; justify-content: center; padding: 10px; background: #0f172a; }
        .actions a, .actions button { border: 1px solid #94a3b8; background: #fff; color: #0f172a; padding: 6px 12px; border-radius: 4px; text-decoration: none; cursor: pointer; }
        .sheet { max-width: 960px; margin: 16px auto; background: #fff; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,.15); }
        h1 { margin: 0 0 4px; font-size: 18px; }
        .meta { margin-bottom: 14px; color: #475569; }
        .credits { display: flex; gap: 24px; margin: 12px 0 18px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
        th { background: #f8fafc; font-size: 11px; text-transform: uppercase; }
        @media print { .actions { display: none; } body { background: #fff; } .sheet { box-shadow: none; margin: 0; max-width: none; } }
    </style>
</head>
<body>
    <div class="actions">
        <a href="{{ $backUrl }}">Back</a>
        <button type="button" onclick="window.print()">Print</button>
    </div>
    <div class="sheet">
        <h1>Employee Leave Card</h1>
        <p class="meta">
            {{ $employee->full_name }} ({{ $employee->emp_id }}) ·
            {{ $employee->department?->department ?? $employee->department?->department_name ?? '—' }} ·
            {{ $employee->position?->position ?? $employee->position?->position_name ?? '—' }}
        </p>
        <div class="credits">
            <div><strong>Vacation leave:</strong> {{ number_format((float) $employee->vacation_leave_credits, 3) }}</div>
            <div><strong>Sick leave:</strong> {{ number_format((float) $employee->sick_leave_credits, 3) }}</div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Filing</th>
                    <th>Type</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>WP</th>
                    <th>WOP</th>
                    <th>Status</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($leaves as $leave)
                    <tr>
                        <td>{{ optional($leave->filing_date)->format('Y-m-d') }}</td>
                        <td>{{ $leave->leave_type_name }}</td>
                        <td>{{ optional($leave->start_date)->format('Y-m-d') }}</td>
                        <td>{{ optional($leave->end_date)->format('Y-m-d') }}</td>
                        <td>{{ number_format((float) $leave->days_wpay, 2) }}</td>
                        <td>{{ number_format((float) $leave->days_wopay, 2) }}</td>
                        <td>{{ $leave->status_name ?: \App\Support\Hris\LeaveStatuses::nameFor($leave->status !== null ? (int) $leave->status : null) }}</td>
                        <td>{{ $leave->remarks }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8">No leave records.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</body>
</html>
