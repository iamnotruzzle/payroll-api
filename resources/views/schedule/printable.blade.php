<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Schedule Print - {{ $department?->department ?? 'Department' }}</title>
    <style>
        @page {
            size: 13in 8.5in;
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #0f172a;
            font-family: Arial, sans-serif;
            background: #f8fafc;
        }

        .toolbar {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 16px;
        }

        .toolbar button {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: #fff;
            color: #0f172a;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            padding: 10px 14px;
        }

        .sheet {
            position: relative;
            width: min(100%, 13in);
            aspect-ratio: 13 / 8.5;
            background: #fff;
            border: 1px solid #cbd5e1;
            margin: 0 auto 24px;
            overflow: hidden;
            padding: 0;
        }

        .print-logo {
            position: absolute;
            z-index: 2;
        }

        .print-logo img {
            display: block;
            width: 100%;
            height: auto;
            max-width: 100%;
            object-fit: contain;
        }

        .header {
            position: absolute;
            inset-inline: 0;
            top: 5%;
            display: grid;
            grid-template-columns: 120px 1fr 120px;
            gap: 12px;
            align-items: center;
        }

        .logo-slot {
            min-height: 76px;
            display: flex;
            align-items: center;
        }

        .logo-slot.center {
            justify-content: center;
        }

        .logo-slot.right {
            justify-content: flex-end;
        }

        .logo-slot img {
            max-height: 76px;
            object-fit: contain;
        }

        .heading {
            text-align: center;
        }

        .heading .org {
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0;
        }

        .heading .dept {
            font-size: 14px;
            font-weight: 700;
            margin-top: 2px;
        }

        .heading .title {
            font-size: 15px;
            font-weight: 700;
            margin-top: 6px;
        }

        .meta {
            position: absolute;
            left: 1.6%;
            right: 1.6%;
            top: 13%;
            display: flex;
            justify-content: space-between;
            gap: 16px;
            font-size: 11px;
            font-weight: 700;
        }

        .schedule-table {
            position: absolute;
            left: 1.6%;
            right: 1.6%;
            top: 24%;
            width: 96.8%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 7.8px;
        }

        .schedule-table th,
        .schedule-table td {
            border: 1px solid #334155;
            padding: 2px 3px;
            text-align: center;
            vertical-align: middle;
            line-height: 1.05;
        }

        .schedule-table th.employee-col,
        .schedule-table td.employee-col {
            width: 17%;
            text-align: left;
            padding-left: 4px;
            font-weight: 700;
            white-space: nowrap;
        }

        .schedule-table th.position-col,
        .schedule-table td.position-col {
            width: 5%;
            text-align: left;
            padding-left: 3px;
            white-space: nowrap;
        }

        .schedule-table th.day-col {
            width: 2.25%;
        }

        .schedule-table th.remarks-col,
        .schedule-table td.remarks-col {
            width: 5%;
        }

        .schedule-table .day-date {
            display: block;
            font-size: 7.5px;
            font-weight: 700;
        }

        .schedule-table .day-name {
            display: block;
            font-size: 7px;
            font-weight: 700;
        }

        .legend {
            position: absolute;
            left: 1.6%;
            right: 1.6%;
            top: 71%;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 4px 14px;
            font-size: 8px;
        }

        .legend-item {
            display: flex;
            gap: 4px;
        }

        .legend-item strong {
            min-width: 28px;
        }

        .signatures {
            position: absolute;
            left: 1.6%;
            right: 1.6%;
            top: 84%;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 24px;
        }

        .signature-block {
            min-height: 74px;
            font-size: 10px;
        }

        .signature-purpose {
            font-weight: 700;
            text-transform: uppercase;
        }

        .signature-space {
            height: 26px;
        }

        .signature-name {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .signature-designation {
            margin-top: 2px;
        }

        @media print {
            body {
                background: #fff;
            }

            .toolbar {
                display: none;
            }

            .sheet {
                border: 0;
                margin: 0;
                width: 13in;
                height: 8.5in;
                max-width: none;
                aspect-ratio: auto;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
    </div>

    <main class="sheet">
        @php
            $organizationName = $settings?->organization_name ?: 'MARIANO MARCOS MEMORIAL HOSPITAL AND MEDICAL CENTER';
            $scheduleHeading = $settings?->schedule_heading ?: 'MONTHLY SCHEDULE OF DUTIES';
            $areaLabel = $settings?->area_label ?: 'AREA';
        @endphp

        @foreach ($logos as $logo)
            <div class="print-logo" style="left: {{ $logo->x_position }}%; top: {{ $logo->y_position }}%; width: {{ $logo->width }}px;">
                <img src="{{ asset('storage/'.$logo->path) }}" alt="{{ $logo->label }}">
            </div>
        @endforeach

        <section class="header">
            <div class="logo-slot"></div>

            <div class="heading">
                <div class="org">{{ $organizationName }}</div>
                <div class="dept">{{ strtoupper($department?->department ?? 'DEPARTMENT') }}</div>
                <div class="title">{{ strtoupper($schedule->year.' '.\Carbon\CarbonImmutable::create($schedule->year, $schedule->month, 1)->format('F').' '.$scheduleHeading) }}</div>
            </div>

            <div class="logo-slot right"></div>
        </section>

        <section class="meta">
            <div>{{ strtoupper($areaLabel) }}: {{ $department?->department ?? 'Department' }}</div>
            <div>DATE: {{ \Carbon\CarbonImmutable::create($schedule->year, $schedule->month, 1)->format('F j') }}-{{ \Carbon\CarbonImmutable::create($schedule->year, $schedule->month, 1)->endOfMonth()->format('j, Y') }}</div>
        </section>

        <table class="schedule-table">
            <thead>
                <tr>
                    <th class="employee-col">Employee</th>
                    <th class="position-col">Position</th>
                    @foreach ($days as $day)
                        <th class="day-col">
                            <span class="day-name">{{ ['Sun' => 'Su', 'Mon' => 'Mo', 'Tue' => 'Tu', 'Wed' => 'We', 'Thu' => 'Th', 'Fri' => 'Fr', 'Sat' => 'Sa'][$day->format('D')] }}</span>
                            <span class="day-date">{{ $day->format('d') }}</span>
                        </th>
                    @endforeach
                    <th class="remarks-col">Remarks</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td class="employee-col">{{ $row['employee_name'] }}</td>
                        <td class="position-col">{{ $row['position'] ?: '-' }}</td>
                        @foreach ($days as $day)
                            <td>{{ $row['assignments'][$day->toDateString()] ?? '-' }}</td>
                        @endforeach
                        <td class="remarks-col"></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $days->count() + 3 }}">No assignments available for this schedule.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <section class="legend">
            @foreach ($legend as $shift)
                <div class="legend-item">
                    <strong>{{ $shift->code }}</strong>
                    <span>{{ $shift->name }}{{ $shift->start_time && $shift->end_time ? ' - '.$shift->start_time.' to '.$shift->end_time : '' }}</span>
                </div>
            @endforeach
        </section>

        <section class="signatures">
            @foreach ($signatories as $signatory)
                <div class="signature-block">
                    <div class="signature-purpose">{{ $signatory->purpose }}:</div>
                    <div class="signature-space"></div>
                    <div class="signature-name">{{ $signatory->person_name }}</div>
                    <div class="signature-designation">{{ $signatory->designation }}</div>
                </div>
            @endforeach
        </section>
    </main>
</body>
</html>
