<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TARF {{ $tarf->tarf_no }}</title>
    <style>
        body { margin: 0; background: #e2e8f0; color: #0f172a; font-family: Arial, Helvetica, sans-serif; font-size: 13px; }
        .actions { position: sticky; top: 0; display: flex; gap: 8px; justify-content: center; padding: 10px; background: #0f172a; }
        .actions a, .actions button { border: 1px solid #94a3b8; background: #fff; color: #0f172a; padding: 6px 12px; border-radius: 4px; text-decoration: none; cursor: pointer; }
        .sheet { max-width: 900px; margin: 16px auto; background: #fff; padding: 28px; box-shadow: 0 1px 3px rgba(0,0,0,.15); }
        h1 { margin: 0 0 4px; font-size: 20px; }
        .muted { color: #64748b; margin-bottom: 18px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #cbd5e1; padding: 8px 10px; text-align: left; vertical-align: top; }
        th { width: 28%; background: #f8fafc; }
        @media print { .actions { display: none; } body { background: #fff; } .sheet { box-shadow: none; margin: 0; max-width: none; } }
    </style>
</head>
<body>
    <div class="actions">
        <a href="{{ $backUrl }}">Back</a>
        <button type="button" onclick="window.print()">Print</button>
    </div>
    <div class="sheet">
        <h1>Training Authority / Request Form (TARF / LDI)</h1>
        <p class="muted">{{ $tarf->tarf_no }} · Status: {{ $statusName }}</p>
        <table>
            <tr><th>Training name</th><td>{{ $tarf->training_name }}</td></tr>
            <tr><th>Venue</th><td>{{ $tarf->training_venue ?: '—' }}</td></tr>
            <tr><th>Sponsor</th><td>{{ $tarf->sponsor }} ({{ (int) $tarf->sponsor_type === 2 ? 'Internal' : 'External' }})</td></tr>
            <tr><th>Inclusive dates</th><td>{{ optional($tarf->start_date)->format('Y-m-d') }} to {{ optional($tarf->end_date)->format('Y-m-d') }}</td></tr>
            <tr><th>Hours / mode / type</th><td>{{ number_format((float) $tarf->hrs, 2) }} · {{ strtoupper($tarf->mode) }} · {{ $tarf->ldiType?->type ?: $tarf->type }}</td></tr>
            <tr><th>PETU approval</th><td>{{ $tarf->approvedByPetu?->full_name ?: '—' }} · {{ optional($tarf->approvedby_petu)->format('Y-m-d H:i') ?: '—' }} · {{ $tarf->petu_notes ?: '—' }}</td></tr>
            <tr><th>MCC approval</th><td>{{ $tarf->approvedByMcc?->full_name ?: '—' }} · {{ optional($tarf->approvedby_mcc)->format('Y-m-d H:i') ?: '—' }} · {{ $tarf->mcc_notes ?: '—' }}</td></tr>
        </table>

        <h2 style="margin-top: 22px; font-size: 15px;">Participants</h2>
        <table>
            <thead>
                <tr><th style="width:18%">Emp ID</th><th>Name</th><th style="width:22%">Department</th><th style="width:18%">Role</th></tr>
            </thead>
            <tbody>
                @foreach ($tarf->requests as $req)
                    <tr>
                        <td>{{ $req->emp_id }}</td>
                        <td>{{ $req->employee?->full_name ?: '—' }}</td>
                        <td>{{ $req->employee?->department?->department ?? $req->employee?->department?->department_name ?? '—' }}</td>
                        <td>{{ (int) $req->role === 1 ? 'Requestor' : 'Participant' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</body>
</html>
