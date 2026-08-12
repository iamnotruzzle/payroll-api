<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>IPCR {{ $employee->emp_id }} · {{ $period->label }}</title>
    <style>
        body { margin: 0; background: #e2e8f0; color: #0f172a; font-family: Arial, Helvetica, sans-serif; font-size: 12px; }
        .actions { position: sticky; top: 0; display: flex; gap: 8px; justify-content: center; padding: 10px; background: #0f172a; }
        .actions a, .actions button { border: 1px solid #94a3b8; background: #fff; color: #0f172a; padding: 6px 12px; border-radius: 4px; text-decoration: none; cursor: pointer; }
        .sheet { max-width: 1100px; margin: 16px auto; background: #fff; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,.15); }
        h1 { margin: 0 0 4px; font-size: 18px; }
        h2 { margin: 18px 0 8px; font-size: 14px; }
        .muted { color: #64748b; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #f8fafc; }
        @media print { .actions { display: none; } body { background: #fff; } .sheet { box-shadow: none; margin: 0; max-width: none; } }
    </style>
</head>
<body>
    <div class="actions">
        <a href="{{ $backUrl }}">Back</a>
        <button type="button" onclick="window.print()">Print</button>
    </div>
    <div class="sheet">
        <h1>Individual Performance Commitment and Review (IPCR)</h1>
        <p class="muted">
            {{ $employee->full_name }} ({{ $employee->emp_id }})
            · {{ $employee->department?->department ?? $employee->department?->department_name ?? '—' }}
            · {{ $employee->position?->position ?? $employee->position?->position_name ?? '—' }}
            · {{ $period->label }}
        </p>
        <p class="muted">
            Weighted average: <strong>{{ $summary['average'] ?? '—' }}</strong>
            · Grade: <strong>{{ $summary['grade'] ?? '—' }}</strong>
            · Strategic {{ $summary['by_function']['strategic'] ?? '—' }}
            · Core {{ $summary['by_function']['core'] ?? '—' }}
            · Support {{ $summary['by_function']['support'] ?? '—' }}
        </p>

        @foreach ($grouped as $functionLabel => $rows)
            @continue(count($rows) === 0)
            <h2>{{ $functionLabel }}</h2>
            <table>
                <thead>
                    <tr>
                        <th style="width:28%">MFO / Success indicator</th>
                        <th style="width:28%">Target</th>
                        <th style="width:24%">Accomplishment</th>
                        <th>Ratings (Q/E/T)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>{{ $row->mfoSet?->mfo?->mfo ?: '—' }}</td>
                            <td>{{ $row->target }}</td>
                            <td>{{ $row->accomplishment ?: '—' }}</td>
                            <td>
                                @forelse ($row->ratings as $rating)
                                    {{ $rating->quality }}/{{ $rating->effectiveness }}/{{ $rating->timeliness }}
                                    (avg {{ $rating->average ?? '—' }})
                                    <br>
                                @empty
                                    —
                                @endforelse
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach
    </div>
</body>
</html>
