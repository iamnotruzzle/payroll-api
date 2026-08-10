<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CS Form 212 — {{ $surname }}, {{ $firstname }}</title>
    <style>
        :root {
            --ink: #000;
            --thick: #000;
            --label: #e7e7e7;
            --sec: #9b9b9b;
            --red: #c00000;
            --thin: 0.5pt solid #000;
            --med: 1.5pt solid var(--thick);
        }
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body {
            margin: 0;
            background: #94a3b8;
            color: var(--ink);
            font-family: "Arial Narrow", Arial, Helvetica, sans-serif;
            font-size: 6.8pt;
            line-height: 1.1;
        }
        .actions {
            position: relative; z-index: 40;
            display: flex; gap: 8px; justify-content: center; align-items: center;
            padding: 10px; background: #0f172a; color: #fff; font-family: system-ui, sans-serif; font-size: 13px;
        }
        .actions a, .actions button {
            border: 1px solid #94a3b8; background: #fff; color: #0f172a;
            padding: 6px 12px; border-radius: 4px; text-decoration: none; cursor: pointer; font-size: 13px;
        }
        .sheet {
            width: 215.9mm;
            height: 279.4mm;
            margin: 8px auto;
            background: #fff;
            padding: 7mm 20mm 4mm;
            overflow: hidden;
            page-break-after: always;
            position: relative;
        }
        .sheet:last-of-type { page-break-after: auto; }
        .form {
            height: 100%;
            border: var(--med);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: #fff;
        }
        .wm {
            position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
            display: none;
        }
        .content { position: relative; z-index: 1; height: 100%; display: flex; flex-direction: column; }
        .hdr {
            flex: 0 0 auto;
            display: grid;
            grid-template-columns: 28mm 1fr 22mm;
            gap: 2mm;
            padding: 1.2mm 2mm 0.8mm;
            border-bottom: none;
        }
        .hdr .left { font-size: 8pt; font-weight: 700; line-height: 1.15; }
        .hdr .left small { font-size: 7pt; font-weight: 700; }
        .hdr .center { text-align: center; }
        .hdr .center h1 { margin: 0; font-size: 16pt; letter-spacing: .02em; font-weight: 800; }
        .warn {
            flex: 0 0 auto;
            padding: 0 2mm 1.2mm;
            font-size: 5.8pt;
            font-style: italic;
            line-height: 1.2;
        }
        .sec {
            flex: 0 0 auto;
            background: var(--sec);
            color: #fff;
            font-style: italic;
            font-weight: 700;
            padding: 1px 4px;
            font-size: 8pt;
            border-top: var(--med);
            border-bottom: var(--med);
        }
        table.g {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        table.g td, table.g th {
            border: var(--thin);
            padding: 0 1.2px;
            vertical-align: middle;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: clip;
            height: 100%;
        }
        table.g td.wrap, table.g th.wrap {
            white-space: normal;
            line-height: 1.05;
        }
        .lbl { background: var(--label); font-weight: 400; font-size: 5.7pt; }
        .num { background: var(--label); font-weight: 400; text-align: center; width: 5mm; font-size: 5.8pt; }
        .val { font-weight: 400; font-size: 6.3pt; }
        .chk {
            display: inline-block; width: 8px; height: 8px; border: 1px solid #000;
            margin: 0 2px 0 0; vertical-align: -1px; position: relative; background: #fff;
        }
        .chk.on::after {
            content: "✓"; position: absolute; top: -4px; left: -1px;
            font-size: 10px; font-weight: 700; line-height: 1;
        }
        .red { color: var(--red); font-style: italic; font-size: 6pt; font-weight: 700; }
        .note-row td { border-left: var(--thin); border-right: var(--thin); border-top: none; border-bottom: none; height: 3.2mm !important; }
        .sig-row td { height: 7.5mm !important; }
        .pg {
            flex: 0 0 auto;
            text-align: right;
            font-size: 6.5pt;
            padding: 0.8mm 1mm 0;
        }
        .grow { flex: 1 1 auto; min-height: 0; display: flex; flex-direction: column; }
        .fill { flex: 1 1 auto; min-height: 0; }
        .fill > table { height: 100%; }
        .two { display: grid; grid-template-columns: 1fr 1fr; height: 100%; }
        .two > div { min-height: 0; overflow: hidden; }
        .two > div > table { height: 100%; }
        /* Header/label rows keep modest fixed heights; data rows share leftover space. */
        .h-name { height: 5.6mm; }
        .h-field { height: 5mm; }
        .h-civil { height: 6.8mm; }
        .h-addr { height: 4.4mm; }
        .h-child-h, .h-edu-h, .h-elig-h, .h-work-h, .h-train-h, .h-vol-h, .h-other-h { height: 7mm; }
        .h-child { height: 4.4mm; }
        .h-edu { height: 5.5mm; }
        .h-elig { height: 8.1mm; }
        .h-work { height: 6.6mm; }
        .h-train { height: 6.25mm; }
        .h-vol { height: 5.95mm; }
        .h-other { height: 5.1mm; }
        .h-ref { height: 6.8mm; }
        .outer-l { border-left: var(--med) !important; }
        .outer-r { border-right: var(--med) !important; }
        .thick-t { border-top: var(--med) !important; }
        .thick-b { border-bottom: var(--med) !important; }
        .no-b { border: none !important; }
        .split-v { border-right: var(--med) !important; }
        .cont-title {
            background: var(--sec); color: #fff; font-style: italic; font-weight: 700;
            padding: 2px 5px; font-size: 9pt; border-bottom: var(--med);
        }
        .photo {
            width: 35mm; height: 45mm; border: var(--thin); margin: 2mm auto;
            display: flex; align-items: center; justify-content: center;
            text-align: center; font-size: 7pt; background: #f8fafc;
        }
        .questions { width: 100%; height: 149mm; border-collapse: collapse; table-layout: fixed; }
        .questions td { border: var(--thin); white-space: normal; vertical-align: top; padding: 1mm 1.5mm; line-height: 1.18; font-size: 6.1pt; }
        .questions .prompt { width: 62%; background: var(--label); }
        .questions .answer { width: 38%; vertical-align: middle; background:#fff; }
        .answer-line { display: block; border-bottom: var(--thin); min-height: 4mm; margin: .5mm 1mm 0; }
        .q34 { height: 26mm; } .q35 { height: 30mm; } .q36 { height: 15mm; }
        .q37 { height: 16mm; } .q38 { height: 21mm; } .q39 { height: 13mm; } .q40 { height: 28mm; }
        .c4-bottom { width:100%; border-collapse:collapse; table-layout:fixed; }
        .c4-bottom > tbody > tr > td { border:var(--thin); padding:0; vertical-align:top; }
        .c4-left { width:74%; } .c4-right { width:26%; text-align:center; }
        .c4-ref-title { height:6mm; padding:1mm 1.5mm; font-weight:700; background:var(--label); }
        .c4-declaration { height:22mm; padding:1.5mm; font-size:6pt; line-height:1.18; background:var(--label); }
        .c4-photo { width:35mm; height:45mm; margin:6mm auto 1mm; border:var(--thin); display:flex; align-items:center; justify-content:center; font-size:5.8pt; line-height:1.15; }
        .c4-thumb { width:38mm; height:30mm; margin:2mm auto 0; border:var(--thin); display:flex; align-items:flex-end; justify-content:center; padding-bottom:1mm; }
        .cont-footer td { height:7mm !important; font-weight:700; text-align:center; }
        .c4-old { display:none !important; }
        .cont-title + .fill .h-child { height:5mm; }
        .cont-title + .fill .h-edu { height:6.5mm; }
        .cont-title + .fill .h-other { height:6.2mm; }
        @media print {
            body { background: #fff; }
            .actions { display: none !important; }
            .sheet {
                width: 215.9mm; height: 279.4mm; margin: 0; padding: 7mm 20mm 4mm;
                page-break-after: always; box-shadow: none;
            }
            .sheet:last-of-type { page-break-after: auto; }
            @page { size: Letter portrait; margin: 0; }
        }
    </style>
</head>
<body>
@php
    $cf = $civilFlags;
    $cell = function ($row, string $key = '') {
        if (! is_array($row)) {
            return is_string($row) ? $row : '';
        }
        return $key === '' ? '' : (string) ($row[$key] ?? '');
    };
    $padRows = fn (array $rows, int $slots) => array_pad(array_values($rows), max($slots, count($rows)), null);
@endphp

<div class="actions">
    <button type="button" onclick="window.print()">Print CS Form 212</button>
    <a href="{{ $backRoute ?? route('employees.show', $agencyEmployeeNo) }}">Back</a>
    <span style="opacity:.85">Enable “Background graphics” in print settings</span>
</div>

{{-- ===================== C1 / PAGE 1 ===================== --}}
<section class="sheet">
    <div class="wm">Page 1</div>
    <div class="form">
        <div class="content">
            <div class="hdr">
                <div class="left">CS Form No. 212<br><small>Revised 2026</small></div>
                <div class="center"><h1>PERSONAL DATA SHEET</h1></div>
                <div></div>
            </div>
            <div class="warn">
                <strong>WARNING:</strong> Any misrepresentation made in the Personal Data Sheet and the Work Experience Sheet shall cause the filing of administrative/criminal case/s against the person concerned.<br>
                READ THE ATTACHED GUIDE TO FILLING OUT THE PERSONAL DATA SHEET (PDS) BEFORE ACCOMPLISHING THE PDS FORM.<br>
                Tick appropriate boxes and use separate sheet if necessary. Indicate <strong>N/A</strong> if not applicable. <strong>DO NOT ABBREVIATE.</strong> Dates: <strong>dd/mm/yyyy</strong>.
            </div>

            <div class="sec">I. PERSONAL INFORMATION</div>
            <div class="grow" style="flex:0 0 auto">
                <table class="g">
                    <tr class="h-name">
                        <td class="num outer-l" style="width:5mm">1.</td>
                        <td class="lbl" style="width:22mm">SURNAME</td>
                        <td class="val outer-r" colspan="6">{{ $surname }}</td>
                    </tr>
                    <tr class="h-name">
                        <td class="num outer-l">2.</td>
                        <td class="lbl">FIRST NAME</td>
                        <td class="val" colspan="3">{{ $firstname }}</td>
                        <td class="lbl wrap" colspan="2">NAME EXTENSION (JR., SR)</td>
                        <td class="val outer-r">{{ $nameExtension }}</td>
                    </tr>
                    <tr class="h-name">
                        <td class="num outer-l"></td>
                        <td class="lbl">MIDDLE NAME</td>
                        <td class="val outer-r" colspan="6">{{ $middlename }}</td>
                    </tr>
                </table>

                <div class="two" style="height:78mm">
                    <div style="border-right:var(--med)">
                        <table class="g">
                            <tr class="h-field"><td class="num outer-l">3.</td><td class="lbl wrap" style="width:28mm">DATE OF BIRTH<br><span style="font-weight:400;font-size:5.5pt">(dd/mm/yyyy)</span></td><td class="val">{{ $birthdate }}</td></tr>
                            <tr class="h-field"><td class="num outer-l">4.</td><td class="lbl">PLACE OF BIRTH</td><td class="val">{{ $birthplace }}</td></tr>
                            <tr class="h-field">
                                <td class="num outer-l">5.</td><td class="lbl">SEX AT BIRTH</td>
                                <td class="val"><span class="chk {{ $sexMale ? 'on' : '' }}"></span>Male &nbsp;<span class="chk {{ $sexFemale ? 'on' : '' }}"></span>Female</td>
                            </tr>
                            <tr class="h-civil">
                                <td class="num outer-l">6.</td><td class="lbl">CIVIL STATUS</td>
                                <td class="val wrap" style="white-space:normal">
                                    <span class="chk {{ $cf['single'] ? 'on' : '' }}"></span>Single
                                    <span class="chk {{ $cf['married'] ? 'on' : '' }}"></span>Married
                                    <span class="chk {{ $cf['widowed'] ? 'on' : '' }}"></span>Widowed<br>
                                    <span class="chk {{ $cf['separated'] ? 'on' : '' }}"></span>Separated
                                    <span class="chk {{ $cf['other'] ? 'on' : '' }}"></span>Other/s: {{ $cf['otherText'] }}
                                </td>
                            </tr>
                            <tr class="h-field"><td class="num outer-l">7.</td><td class="lbl">HEIGHT (m)</td><td class="val">{{ $height }}</td></tr>
                            <tr class="h-field"><td class="num outer-l">8.</td><td class="lbl">WEIGHT (kg)</td><td class="val">{{ $weight }}</td></tr>
                            <tr class="h-field"><td class="num outer-l">9.</td><td class="lbl">BLOOD TYPE</td><td class="val">{{ $bloodType }}</td></tr>
                            <tr class="h-field"><td class="num outer-l">10.</td><td class="lbl">UMID ID NO.</td><td class="val">{{ $umid }}</td></tr>
                            <tr class="h-field"><td class="num outer-l">11.</td><td class="lbl">PAG-IBIG ID NO.</td><td class="val">{{ $pagibig }}</td></tr>
                            <tr class="h-field"><td class="num outer-l">12.</td><td class="lbl">PHILHEALTH NO.</td><td class="val">{{ $philhealth }}</td></tr>
                            <tr class="h-field"><td class="num outer-l">13.</td><td class="lbl wrap">PhilSys Card Number (PCN)</td><td class="val">{{ $philsys }}</td></tr>
                            <tr class="h-field"><td class="num outer-l">14.</td><td class="lbl">TIN NO.</td><td class="val">{{ $tin }}</td></tr>
                            <tr class="h-field"><td class="num outer-l thick-b">15.</td><td class="lbl thick-b">AGENCY EMPLOYEE NO.</td><td class="val thick-b">{{ $agencyEmployeeNo }}</td></tr>
                        </table>
                    </div>
                    <div>
                        <table class="g">
                            <tr class="h-field">
                                <td class="num">16.</td>
                                <td class="lbl wrap" colspan="3">CITIZENSHIP</td>
                            </tr>
                            <tr style="height:9mm">
                                <td class="val wrap" colspan="4" style="white-space:normal;font-size:6.5pt">
                                    <span class="chk {{ $isFilipino ? 'on' : '' }}"></span>Filipino
                                    &nbsp;<span class="chk {{ ! $isFilipino && $citizenship !== 'N/A' ? 'on' : '' }}"></span>Dual Citizenship
                                    &nbsp;<span class="chk"></span>by birth
                                    &nbsp;<span class="chk"></span>by naturalization<br>
                                    Pls. indicate country: <strong>{{ $isFilipino ? 'N/A' : $citizenship }}</strong>
                                </td>
                            </tr>
                            <tr class="h-addr"><td class="num">17.</td><td class="lbl" colspan="3">RESIDENTIAL ADDRESS</td></tr>
                            <tr class="h-addr"><td class="lbl" colspan="1">House/Block/Lot No.</td><td class="val">{{ $residential['house'] }}</td><td class="lbl">Street</td><td class="val outer-r">{{ $residential['street'] }}</td></tr>
                            <tr class="h-addr"><td class="lbl">Subdivision/Village</td><td class="val">{{ $residential['subdivision'] }}</td><td class="lbl">Barangay</td><td class="val outer-r">{{ $residential['barangay'] }}</td></tr>
                            <tr class="h-addr"><td class="lbl">City/Municipality</td><td class="val">{{ $residential['city'] }}</td><td class="lbl">Province</td><td class="val outer-r">{{ $residential['province'] }}</td></tr>
                            <tr class="h-addr"><td class="lbl">ZIP CODE</td><td class="val" colspan="3">{{ $residential['zip'] }}</td></tr>
                            <tr class="h-addr"><td class="num">18.</td><td class="lbl" colspan="3">PERMANENT ADDRESS</td></tr>
                            <tr class="h-addr"><td class="lbl">House/Block/Lot No.</td><td class="val">{{ $permanent['house'] }}</td><td class="lbl">Street</td><td class="val outer-r">{{ $permanent['street'] }}</td></tr>
                            <tr class="h-addr"><td class="lbl">Subdivision/Village</td><td class="val">{{ $permanent['subdivision'] }}</td><td class="lbl">Barangay</td><td class="val outer-r">{{ $permanent['barangay'] }}</td></tr>
                            <tr class="h-addr"><td class="lbl">City/Municipality</td><td class="val">{{ $permanent['city'] }}</td><td class="lbl">Province</td><td class="val outer-r">{{ $permanent['province'] }}</td></tr>
                            <tr class="h-addr"><td class="lbl">ZIP CODE</td><td class="val" colspan="3">{{ $permanent['zip'] }}</td></tr>
                            <tr class="h-field"><td class="num">19.</td><td class="lbl">TELEPHONE NO.</td><td class="val" colspan="2">{{ $telephone }}</td></tr>
                            <tr class="h-field"><td class="num">20.</td><td class="lbl">MOBILE NO.</td><td class="val" colspan="2">{{ $mobile }}</td></tr>
                            <tr class="h-field"><td class="num thick-b">21.</td><td class="lbl thick-b">E-MAIL ADDRESS (if any)</td><td class="val thick-b" colspan="2">{{ $email }}</td></tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="sec">II. FAMILY BACKGROUND</div>
            <div class="two" style="height:68mm;flex:0 0 auto">
                <div style="border-right:var(--med)">
                    <table class="g">
                        <tr class="h-field"><td class="num outer-l">22.</td><td class="lbl" style="width:30mm">SPOUSE'S SURNAME</td><td class="val" colspan="3">{{ $spouse['surname'] }}</td></tr>
                        <tr class="h-field"><td class="num outer-l"></td><td class="lbl">FIRST NAME</td><td class="val">{{ $spouse['firstname'] }}</td><td class="lbl wrap" style="width:18mm">NAME EXTENSION</td><td class="val">{{ $spouse['extension'] }}</td></tr>
                        <tr class="h-field"><td class="num outer-l"></td><td class="lbl">MIDDLE NAME</td><td class="val" colspan="3">{{ $spouse['middlename'] }}</td></tr>
                        <tr class="h-field"><td class="num outer-l"></td><td class="lbl">OCCUPATION</td><td class="val" colspan="3">{{ $spouse['occupation'] }}</td></tr>
                        <tr class="h-field"><td class="num outer-l"></td><td class="lbl wrap">EMPLOYER/BUSINESS NAME</td><td class="val" colspan="3">{{ $spouse['employer'] }}</td></tr>
                        <tr class="h-field"><td class="num outer-l"></td><td class="lbl">BUSINESS ADDRESS</td><td class="val" colspan="3">{{ $spouse['address'] }}</td></tr>
                        <tr class="h-field"><td class="num outer-l"></td><td class="lbl">TELEPHONE NO.</td><td class="val" colspan="3">{{ $spouse['telephone'] }}</td></tr>
                        <tr class="note-row"><td class="outer-l" colspan="5"><span class="red">(Continue on sheet C7 if necessary)</span></td></tr>
                        <tr class="h-field"><td class="num outer-l">24.</td><td class="lbl">FATHER'S SURNAME</td><td class="val" colspan="3">{{ $father['surname'] }}</td></tr>
                        <tr class="h-field"><td class="num outer-l"></td><td class="lbl">FIRST NAME</td><td class="val">{{ $father['firstname'] }}</td><td class="lbl wrap">NAME EXTENSION</td><td class="val">{{ $father['extension'] }}</td></tr>
                        <tr class="h-field"><td class="num outer-l"></td><td class="lbl">MIDDLE NAME</td><td class="val" colspan="3">{{ $father['middlename'] }}</td></tr>
                        <tr class="h-field"><td class="num outer-l">25.</td><td class="lbl wrap" colspan="4">MOTHER'S MAIDEN NAME</td></tr>
                        <tr class="h-field"><td class="num outer-l"></td><td class="lbl">SURNAME</td><td class="val" colspan="3">{{ $mother['surname'] }}</td></tr>
                        <tr class="h-field"><td class="num outer-l"></td><td class="lbl">FIRST NAME</td><td class="val" colspan="3">{{ $mother['firstname'] }}</td></tr>
                        <tr class="h-field"><td class="num outer-l"> </td><td class="lbl">MIDDLE NAME</td><td class="val" colspan="3">{{ $mother['middlename'] }}</td></tr>
                    </table>
                </div>
                <div>
                    <table class="g">
                        <tr class="h-child-h">
                            <td class="num">23.</td>
                            <td class="lbl wrap">NAME of CHILDREN (Write full name and list all)</td>
                            <td class="lbl wrap outer-r" style="width:28mm">DATE OF BIRTH<br><span style="font-weight:400;font-size:5.5pt">(dd/mm/yyyy)</span></td>
                        </tr>
                        @foreach ($children as $child)
                            <tr class="h-child">
                                <td class="num"></td>
                                <td class="val">{{ $cell($child, 'name') }}</td>
                                <td class="val outer-r">{{ $cell($child, 'birthdate') }}</td>
                            </tr>
                        @endforeach
                        <tr class="note-row"><td colspan="3" class="outer-r"><span class="red">(Continue on sheet C7 if necessary)</span></td></tr>
                    </table>
                </div>
            </div>

            <div class="sec">III. EDUCATIONAL BACKGROUND</div>
            <div class="fill" style="flex:1 1 auto">
                <table class="g" style="height:100%">
                    <tr class="h-edu-h">
                        <th class="lbl wrap outer-l" style="width:22mm">26. LEVEL</th>
                        <th class="lbl wrap">NAME OF SCHOOL<br><span style="font-weight:400">(Write in full)</span></th>
                        <th class="lbl wrap">BASIC EDUCATION/DEGREE/COURSE<br><span style="font-weight:400">(Write in full)</span></th>
                        <th class="lbl wrap" colspan="2">PERIOD OF ATTENDANCE</th>
                        <th class="lbl wrap" style="width:14mm">HIGHEST LEVEL/ UNITS EARNED</th>
                        <th class="lbl wrap" style="width:12mm">YEAR GRADUATED</th>
                        <th class="lbl wrap outer-r">SCHOLARSHIP/ ACADEMIC HONORS RECEIVED</th>
                    </tr>
                    <tr class="h-edu-h">
                        <th class="lbl outer-l"></th><th class="lbl"></th><th class="lbl"></th>
                        <th class="lbl" style="width:10mm">From</th><th class="lbl" style="width:10mm">To</th>
                        <th class="lbl"></th><th class="lbl"></th><th class="lbl outer-r"></th>
                    </tr>
                    @foreach ($educations as $edu)
                        <tr class="h-edu">
                            <td class="lbl wrap outer-l">{{ $edu['level'] === 'VOCATIONAL' ? 'VOCATIONAL / TRADE COURSE' : $edu['level'] }}</td>
                            <td class="val">{{ $edu['school'] }}</td>
                            <td class="val">{{ $edu['course'] }}</td>
                            <td class="val">{{ $edu['from'] }}</td>
                            <td class="val">{{ $edu['to'] }}</td>
                            <td class="val">{{ $edu['units'] }}</td>
                            <td class="val">{{ $edu['year'] }}</td>
                            <td class="val outer-r">{{ $edu['honors'] }}</td>
                        </tr>
                    @endforeach
                    <tr class="note-row"><td class="outer-l" colspan="8"><span class="red">(Continue on sheet C8 if necessary)</span></td></tr>
                    <tr class="sig-row">
                        <td class="lbl outer-l thick-t" colspan="2">SIGNATURE</td>
                        <td class="red thick-t" colspan="3">(e-signature/digital certificate)</td>
                        <td class="lbl thick-t">DATE</td>
                        <td class="val outer-r thick-t" colspan="2">{{ $dateAccomplished }}</td>
                    </tr>
                </table>
            </div>
            <div class="pg">CS FORM 212 (Revised 2026), Page 1 of 4</div>
        </div>
    </div>
</section>

{{-- ===================== C2 / PAGE 2 ===================== --}}
<section class="sheet">
    <div class="wm">Page 2</div>
    <div class="form">
        <div class="content">
            <div class="sec" style="border-top:none">IV. CIVIL SERVICE ELIGIBILITY</div>
            <div style="flex:0 0 auto;height:68mm">
                <table class="g" style="height:100%">
                    <tr class="h-elig-h">
                        <th class="lbl wrap outer-l" style="width:42%">27. CAREER SERVICE/ RA 1080 (BOARD/ BAR) UNDER SPECIAL LAWS/ CES/ CSEE / BARANGAY ELIGIBILITY / DRIVER'S LICENSE</th>
                        <th class="lbl wrap" style="width:10%">RATING<br>(If Applicable)</th>
                        <th class="lbl wrap" style="width:14%">DATE OF EXAMINATION/ CONFERMENT</th>
                        <th class="lbl wrap">PLACE OF EXAMINATION/ CONFERMENT</th>
                        <th class="lbl wrap" style="width:12%">LICENSE NO.</th>
                        <th class="lbl wrap outer-r" style="width:12%">Date of Validity</th>
                    </tr>
                    @foreach ($eligibilities as $row)
                        <tr class="h-elig">
                            <td class="val outer-l">{{ $cell($row, 'title') }}</td>
                            <td class="val">{{ $cell($row, 'rating') }}</td>
                            <td class="val">{{ $cell($row, 'confer_date') }}</td>
                            <td class="val">{{ $cell($row, 'confer_place') }}</td>
                            <td class="val">{{ $cell($row, 'license_no') }}</td>
                            <td class="val outer-r">{{ $cell($row, 'exp_date') }}</td>
                        </tr>
                    @endforeach
                    <tr class="note-row"><td class="outer-l" colspan="6"><span class="red">(Continue on sheet C9 if necessary)</span></td></tr>
                </table>
            </div>

            <div class="sec">V. WORK EXPERIENCE</div>
            <div style="font-size:6pt;padding:1px 4px;border-bottom:var(--thin)">(Include private employment. Start from your recent work) Description of duties should be indicated in the attached Work Experience Sheet.</div>
            <div class="fill">
                <table class="g" style="height:100%">
                    <tr class="h-work-h">
                        <th class="lbl wrap outer-l" colspan="2">28. INCLUSIVE DATES<br>(dd/mm/yyyy)</th>
                        <th class="lbl wrap" rowspan="2">POSITION TITLE<br>(Write in full/Do not abbreviate)</th>
                        <th class="lbl wrap" rowspan="2">DEPARTMENT / AGENCY / OFFICE / COMPANY<br>(Write in full/Do not abbreviate)</th>
                        <th class="lbl wrap" rowspan="2" style="width:12mm">MONTHLY SALARY</th>
                        <th class="lbl wrap" rowspan="2" style="width:16mm">SALARY/JOB/PAY GRADE &amp; STEP INCREMENT<br>(Format "00-0")</th>
                        <th class="lbl wrap" rowspan="2" style="width:14mm">STATUS OF APPOINTMENT</th>
                        <th class="lbl wrap outer-r" rowspan="2" style="width:10mm">GOV'T SERVICE<br>(Y/N)</th>
                    </tr>
                    <tr class="h-work-h">
                        <th class="lbl outer-l" style="width:12mm">From</th>
                        <th class="lbl" style="width:12mm">To</th>
                    </tr>
                    @foreach ($workExperiences as $row)
                        <tr class="h-work">
                            <td class="val outer-l">{{ $cell($row, 'from') }}</td>
                            <td class="val">{{ $cell($row, 'to') }}</td>
                            <td class="val">{{ $cell($row, 'position') }}</td>
                            <td class="val">{{ $cell($row, 'company') }}</td>
                            <td class="val">{{ $cell($row, 'salary') }}</td>
                            <td class="val">{{ $cell($row, 'grade_step') }}</td>
                            <td class="val">{{ $cell($row, 'status') }}</td>
                            <td class="val outer-r">{{ $cell($row, 'govt') }}</td>
                        </tr>
                    @endforeach
                    <tr class="note-row"><td class="outer-l" colspan="8"><span class="red">(Continue on sheet C6 if necessary)</span></td></tr>
                    <tr class="sig-row">
                        <td class="lbl outer-l thick-t" colspan="2">SIGNATURE</td>
                        <td class="red thick-t" colspan="3">(e-signature/digital certificate)</td>
                        <td class="lbl thick-t">DATE</td>
                        <td class="val outer-r thick-t" colspan="2">{{ $dateAccomplished }}</td>
                    </tr>
                </table>
            </div>
            <div class="pg">CS FORM 212 (Revised 2026), Page 2 of 4</div>
        </div>
    </div>
</section>

{{-- ===================== C3 / PAGE 3 ===================== --}}
<section class="sheet">
    <div class="wm">Page 3</div>
    <div class="form">
        <div class="content">
            <div class="sec" style="border-top:none">VI. LEARNING AND DEVELOPMENT (L&amp;D) INTERVENTIONS/TRAINING PROGRAMS ATTENDED</div>
            <div style="flex:0 0 auto;height:128mm">
                <table class="g" style="height:100%">
                    <tr class="h-train-h">
                        <th class="lbl wrap outer-l" rowspan="2">29. TITLE OF LEARNING AND DEVELOPMENT INTERVENTIONS/TRAINING PROGRAMS<br>(Write in full)</th>
                        <th class="lbl wrap" colspan="2">INCLUSIVE DATES OF ATTENDANCE<br>(dd/mm/yyyy)</th>
                        <th class="lbl wrap" rowspan="2" style="width:14mm">NUMBER OF HOURS</th>
                        <th class="lbl wrap" rowspan="2" style="width:18mm">Type of L&amp;D<br>(Managerial/ Supervisory/ Technical/etc)</th>
                        <th class="lbl wrap outer-r" rowspan="2">CONDUCTED/ SPONSORED BY<br>(Write in full)</th>
                    </tr>
                    <tr class="h-train-h">
                        <th class="lbl" style="width:12mm">From</th>
                        <th class="lbl" style="width:12mm">To</th>
                    </tr>
                    @foreach ($trainings as $row)
                        <tr class="h-train">
                            <td class="val outer-l">{{ $cell($row, 'title') }}</td>
                            <td class="val">{{ $cell($row, 'from') }}</td>
                            <td class="val">{{ $cell($row, 'to') }}</td>
                            <td class="val">{{ $cell($row, 'hours') }}</td>
                            <td class="val">{{ $cell($row, 'type') }}</td>
                            <td class="val outer-r">{{ $cell($row, 'sponsor') }}</td>
                        </tr>
                    @endforeach
                    <tr class="note-row"><td class="outer-l" colspan="6"><span class="red">(Continue on sheet C5 if necessary)</span></td></tr>
                </table>
            </div>

            <div class="sec">VII. VOLUNTARY WORK OR INVOLVEMENT IN CIVIC / NON-GOVERNMENT / PEOPLE / VOLUNTARY ORGANIZATION/S</div>
            <div style="flex:0 0 auto;height:72mm">
                <table class="g" style="height:100%">
                    <tr class="h-vol-h">
                        <th class="lbl wrap outer-l" rowspan="2">30. NAME &amp; ADDRESS OF ORGANIZATION<br>(Write in full)</th>
                        <th class="lbl wrap" colspan="2">INCLUSIVE DATES<br>(dd/mm/yyyy)</th>
                        <th class="lbl wrap" rowspan="2" style="width:16mm">NUMBER OF HOURS</th>
                        <th class="lbl wrap outer-r" rowspan="2">POSITION / NATURE OF WORK</th>
                    </tr>
                    <tr class="h-vol-h">
                        <th class="lbl" style="width:14mm">From</th>
                        <th class="lbl" style="width:14mm">To</th>
                    </tr>
                    @foreach ($voluntaryWorks as $row)
                        <tr class="h-vol">
                            <td class="val outer-l">{{ $cell($row, 'org') }}</td>
                            <td class="val">{{ $cell($row, 'from') }}</td>
                            <td class="val">{{ $cell($row, 'to') }}</td>
                            <td class="val">{{ $cell($row, 'hours') }}</td>
                            <td class="val outer-r">{{ $cell($row, 'position') }}</td>
                        </tr>
                    @endforeach
                    <tr class="note-row"><td class="outer-l" colspan="5"><span class="red">(Continue on sheet C10 if necessary)</span></td></tr>
                </table>
            </div>

            <div class="sec">VIII. OTHER INFORMATION</div>
            <div class="fill">
                <table class="g" style="height:100%">
                    <tr class="h-other-h">
                        <th class="lbl wrap outer-l" style="width:33%">31. SPECIAL SKILLS and HOBBIES</th>
                        <th class="lbl wrap" style="width:34%">32. NON-ACADEMIC DISTINCTIONS / RECOGNITION<br>(Write in full)</th>
                        <th class="lbl wrap outer-r">33. MEMBERSHIP IN ASSOCIATION/ORGANIZATION<br>(Write in full)</th>
                    </tr>
                    @for ($i = 0; $i < 7; $i++)
                        <tr class="h-other">
                            <td class="val outer-l">{{ is_string($skills[$i] ?? null) ? $skills[$i] : '' }}</td>
                            <td class="val">{{ is_string($recognitions[$i] ?? null) ? $recognitions[$i] : '' }}</td>
                            <td class="val outer-r">{{ is_string($memberships[$i] ?? null) ? $memberships[$i] : '' }}</td>
                        </tr>
                    @endfor
                    <tr class="note-row"><td class="outer-l" colspan="3"><span class="red">(Continue on sheet C11 if necessary)</span></td></tr>
                    <tr class="sig-row">
                        <td class="lbl outer-l thick-t">SIGNATURE</td>
                        <td class="red thick-t">(e-signature/digital certificate)</td>
                        <td class="val outer-r thick-t"><span class="lbl" style="display:inline-block;padding:0 4px;margin-right:4px">DATE</span>{{ $dateAccomplished }}</td>
                    </tr>
                </table>
            </div>
            <div class="pg">CS FORM 212 (Revised 2026), Page 3 of 4</div>
        </div>
    </div>
</section>

{{-- ===================== C4 / PAGE 4 ===================== --}}
<section class="sheet c4-old">
    <div class="wm">Page 4</div>
    <div class="form">
        <div class="content">
            <div class="grow" style="flex:1 1 auto;min-height:0">
                <table class="g" style="height:58%">
                    <tr>
                        <td class="outer-l wrap" style="width:68%;padding:2mm;vertical-align:top;white-space:normal;font-size:6.8pt;line-height:1.25">
                            <div style="margin-bottom:2mm"><strong>34.</strong> Are you related by consanguinity or affinity to the appointing or recommending authority, or to the chief of bureau or office, or to the person who has immediate supervision over you in the Office, Bureau or Department where you will be appointed,<br>
                            a. within the third degree? &nbsp; <span class="chk"></span> YES &nbsp; <span class="chk on"></span> NO<br>
                            b. within the fourth degree (for Local Government Unit - Career Employees)? &nbsp; <span class="chk"></span> YES &nbsp; <span class="chk on"></span> NO<br>
                            <span style="font-size:6pt">If YES, give details: ________________________________</span></div>
                            <div style="margin-bottom:2mm"><strong>35.</strong> a. Have you ever been found guilty of any administrative offense? &nbsp; <span class="chk"></span> YES &nbsp; <span class="chk on"></span> NO<br>
                            <span style="font-size:6pt">If YES, give details: ________________________________</span><br>
                            b. Have you been criminally charged before any court? &nbsp; <span class="chk"></span> YES &nbsp; <span class="chk on"></span> NO<br>
                            <span style="font-size:6pt">Date Filed: ____________ &nbsp; Status of Case/s: ____________</span></div>
                            <div style="margin-bottom:2mm"><strong>36.</strong> Have you ever been convicted of any crime or violation of any law, decree, ordinance or regulation before any court? &nbsp; <span class="chk"></span> YES &nbsp; <span class="chk on"></span> NO<br>
                            <span style="font-size:6pt">If YES, give details: ________________________________</span></div>
                            <div style="margin-bottom:2mm"><strong>37.</strong> Have you ever been separated from the service in any of the following modes: resignation, retirement, dropped from the rolls, dismissal, termination, end of term, finished contract, AWOL or phase out, in the public or private sector? &nbsp; <span class="chk"></span> YES &nbsp; <span class="chk on"></span> NO</div>
                            <div style="margin-bottom:2mm"><strong>38.</strong> a. Have you ever been a candidate in a national or local election held within the last year (except Barangay election)? &nbsp; <span class="chk"></span> YES &nbsp; <span class="chk on"></span> NO<br>
                            b. Have you resigned from the government service during the three (3)-month period before the last election to partisan-political candidacy? &nbsp; <span class="chk"></span> YES &nbsp; <span class="chk on"></span> NO</div>
                            <div style="margin-bottom:2mm"><strong>39.</strong> Have you acquired the status of an immigrant or permanent resident of another country? &nbsp; <span class="chk"></span> YES &nbsp; <span class="chk on"></span> NO</div>
                            <div><strong>40.</strong> Pursuant to: (a) Indigenous Peoples' Act (RA 8371); (b) Magna Carta for Disabled Persons (RA 7277), as amended; and (c) Expanded Solo Parents Welfare Act (RA 11861), please answer the following:<br>
                            a. Are you a member of any indigenous group? &nbsp; <span class="chk"></span> YES &nbsp; <span class="chk on"></span> NO<br>
                            b. Are you a person with disability? &nbsp; <span class="chk"></span> YES &nbsp; <span class="chk on"></span> NO<br>
                            c. Are you a solo parent? &nbsp; <span class="chk"></span> YES &nbsp; <span class="chk on"></span> NO</div>
                        </td>
                        <td class="outer-r" style="width:32%;vertical-align:top;text-align:center">
                            <div class="photo">ID picture taken within<br>the last 6 months<br>3.5 cm × 4.5 cm<br>(passport size)</div>
                        </td>
                    </tr>
                </table>

                <div class="sec">41. REFERENCES <span style="font-weight:400;font-size:6.5pt">(Person not related by consanguinity or affinity to applicant/appointee)</span></div>
                <table class="g">
                    <tr class="h-ref">
                        <th class="lbl outer-l" style="width:34%">NAME</th>
                        <th class="lbl">OFFICE / RESIDENTIAL ADDRESS</th>
                        <th class="lbl outer-r" style="width:28%">CONTACT NO. AND/OR EMAIL</th>
                    </tr>
                    @foreach ($references as $row)
                        <tr class="h-ref">
                            <td class="val outer-l">{{ $cell($row, 'name') }}</td>
                            <td class="val">{{ $cell($row, 'address') }}</td>
                            <td class="val outer-r">{{ $cell($row, 'contact') }}</td>
                        </tr>
                    @endforeach
                </table>

                <div class="sec">42.</div>
                <div style="border-bottom:var(--thin);padding:2mm;font-size:6.5pt;line-height:1.25">
                    I declare under oath that I have personally accomplished this Personal Data Sheet which is a true, correct and complete statement pursuant to the provisions of pertinent laws, rules and regulations of the Republic of the Philippines. I also authorize the agency head/authorized representative to verify/validate the contents stated herein. I agree that any misrepresentation made in this document and its attachments shall cause the filing of administrative/criminal case/s against me.
                </div>

                <table class="g" style="margin-top:0">
                    <tr style="height:28mm">
                        <td class="outer-l wrap" style="width:42%;padding:2mm;vertical-align:top;white-space:normal">
                            <strong>Government Issued ID</strong> (i.e., Passport, GSIS, SSS, PRC, Driver's License, etc.)<br><br>
                            Government Issued ID: ___________________________<br><br>
                            ID/License/Passport No.: ________________________<br><br>
                            Date/Place of Issuance: _________________________
                        </td>
                        <td style="width:30%;padding:2mm;vertical-align:top;text-align:center">
                            <div style="border:var(--thin);height:16mm;margin-bottom:2mm">
                                <div class="red" style="padding:1px">(e-signature/digital certificate)</div>
                                <div style="margin-top:10mm;border-top:var(--thin);font-size:6pt">Signature (Sign inside the box)</div>
                            </div>
                            Date Accomplished<br><strong>{{ $dateAccomplished }}</strong>
                        </td>
                        <td class="outer-r" style="width:28%;padding:2mm;vertical-align:top;text-align:center">
                            <div style="border:var(--thin);height:22mm;display:flex;align-items:flex-end;justify-content:center;padding:2mm;font-size:7pt">
                                Right Thumbmark
                            </div>
                        </td>
                    </tr>
                </table>

                <div style="border-top:var(--med);padding:2mm;font-size:6.5pt;flex:1 1 auto">
                    SUBSCRIBED AND SWORN to before me this ________ day of ________________, 20____, affiant exhibiting his/her validly issued government ID as indicated above.
                    <div style="text-align:center;margin-top:8mm">
                        _________________________________________<br>
                        <span class="red">(e-signature/digital certificate except for notary public)</span><br>
                        Person Administering Oath
                    </div>
                </div>
            </div>
            <div class="pg">CS FORM 212 (Revised 2026), Page 4 of 4</div>
        </div>
    </div>
</section>

<section class="sheet c4-official">
    <div class="form"><div class="content">
        <table class="questions">
            <tr class="q34"><td class="prompt"><strong>34.</strong> Are you related by consanguinity or affinity to the appointing or recommending authority, or to the chief of bureau or office, or to the person who has immediate supervision over you in the Office, Bureau or Department where you will be appointed,<br><br>a. within the third degree?<br><br>b. within the fourth degree (for Local Government Unit - Career Employees)?</td><td class="answer"><span class="chk"></span> YES &nbsp;&nbsp;&nbsp; <span class="chk on"></span> NO<br>If YES, give details:<span class="answer-line"></span><br><span class="chk"></span> YES &nbsp;&nbsp;&nbsp; <span class="chk on"></span> NO<br>If YES, give details:<span class="answer-line"></span></td></tr>
            <tr class="q35"><td class="prompt"><strong>35.</strong>&nbsp;&nbsp; a. Have you ever been found guilty of any administrative offense?<br><br><br><br>b. Have you been criminally charged before any court?</td><td class="answer"><span class="chk"></span> YES &nbsp;&nbsp;&nbsp; <span class="chk on"></span> NO<br>If YES, give details:<span class="answer-line"></span><br><span class="chk"></span> YES &nbsp;&nbsp;&nbsp; <span class="chk on"></span> NO<br>If YES, give details:<span class="answer-line"></span>Date Filed: <span style="display:inline-block;width:22mm;border-bottom:var(--thin)">&nbsp;</span><br>Status of Case/s: <span style="display:inline-block;width:18mm;border-bottom:var(--thin)">&nbsp;</span></td></tr>
            <tr class="q36"><td class="prompt"><strong>36.</strong> Have you ever been convicted of any crime or violation of any law, decree, ordinance or regulation by any court or tribunal?</td><td class="answer"><span class="chk"></span> YES &nbsp;&nbsp;&nbsp; <span class="chk on"></span> NO<br>If YES, give details:<span class="answer-line"></span></td></tr>
            <tr class="q37"><td class="prompt"><strong>37.</strong> Have you ever been separated from the service in any of the following modes: resignation, retirement, dropped from the rolls, dismissal, termination, end of term, finished contract or phased out (abolition), in the public or private sector?</td><td class="answer"><span class="chk"></span> YES &nbsp;&nbsp;&nbsp; <span class="chk on"></span> NO<br>If YES, give details:<span class="answer-line"></span></td></tr>
            <tr class="q38"><td class="prompt"><strong>38.</strong>&nbsp;&nbsp; a. Have you ever been a candidate in a national or local election held within the last year (except Barangay election)?<br><br>b. Have you resigned from the government service during the three (3)-month period before the last election to promote/actively campaign for a national or local candidate?</td><td class="answer"><span class="chk"></span> YES &nbsp;&nbsp;&nbsp; <span class="chk on"></span> NO<br>If YES, give details:<span class="answer-line"></span><span class="chk"></span> YES &nbsp;&nbsp;&nbsp; <span class="chk on"></span> NO<br>If YES, give details:<span class="answer-line"></span></td></tr>
            <tr class="q39"><td class="prompt"><strong>39.</strong> Have you acquired the status of an immigrant or permanent resident of another country?</td><td class="answer"><span class="chk"></span> YES &nbsp;&nbsp;&nbsp; <span class="chk on"></span> NO<br>If YES, give details (country):<span class="answer-line"></span></td></tr>
            <tr class="q40"><td class="prompt"><strong>40.</strong> Pursuant to: (a) Indigenous Peoples' Act (RA 8371); (b) Magna Carta for Disabled Persons (RA 7277), as amended; and (c) Expanded Solo Parents Welfare Act (RA 11861), please answer the following:<br><br>a.&nbsp;&nbsp; Are you a member of any indigenous group?<br><br>b.&nbsp;&nbsp; Are you a person with disability?<br><br>c.&nbsp;&nbsp; Are you a solo parent?</td><td class="answer"><span class="chk"></span> YES &nbsp;&nbsp;&nbsp; <span class="chk on"></span> NO<br>If YES, please specify:<span class="answer-line"></span><span class="chk"></span> YES &nbsp;&nbsp;&nbsp; <span class="chk on"></span> NO<br>If YES, please specify ID No:<span class="answer-line"></span><span class="chk"></span> YES &nbsp;&nbsp;&nbsp; <span class="chk on"></span> NO<br>If YES, please specify ID No:<span class="answer-line"></span></td></tr>
        </table>
        <table class="c4-bottom">
            <tr><td class="c4-left"><div class="c4-ref-title">41.&nbsp;&nbsp; REFERENCES <span style="font-size:5.5pt">(Person not related by consanguinity or affinity to applicant/appointee)</span></div><table class="g"><tr style="height:5mm"><th class="lbl" style="width:44%">NAME</th><th class="lbl">OFFICE / RESIDENTIAL ADDRESS</th><th class="lbl" style="width:20%">CONTACT NO. AND/OR EMAIL</th></tr>@foreach ($references as $row)<tr style="height:6.5mm"><td class="val">{{ $cell($row, 'name') }}</td><td class="val">{{ $cell($row, 'address') }}</td><td class="val">{{ $cell($row, 'contact') }}</td></tr>@endforeach</table><div class="c4-declaration"><strong>42.</strong>&nbsp;&nbsp; I declare under oath that I have personally accomplished this Personal Data Sheet which is a true, correct and complete statement pursuant to the provisions of pertinent laws, rules, and regulations of the Republic of the Philippines. I authorize the agency head/authorized representative to verify/validate the contents stated herein. I agree that any misrepresentation made in this document and its attachments shall cause the filing of administrative/criminal case/s against me.</div></td><td class="c4-right"><div class="c4-photo">Passport-sized unfiltered digital picture taken within the last 6 months<br>4.5 cm x 3.5 cm</div><div style="font-size:5.5pt;color:#777">PHOTO</div></td></tr>
            <tr style="height:31mm"><td class="c4-left"><table class="g" style="height:100%"><tr><td class="wrap" style="width:46%;padding:1mm;vertical-align:top;white-space:normal"><strong>Government Issued ID</strong> (i.e., Passport, GSIS, SSS, PRC, Driver's License, etc.)<br><em>PLEASE INDICATE ID Number and Date of Issuance</em><br><br>Government Issued ID:<br><br>ID/License/Passport No.:<br><br>Date/Place of Issuance:</td><td style="width:54%;padding:2mm;text-align:center;vertical-align:top"><div style="height:17mm;border:var(--thin);padding-top:7mm"><span class="red">(e-signature/digital certificate)</span></div><div style="border:var(--thin);font-size:5.5pt">Signature (Sign inside the box)</div><div style="height:5mm;padding-top:1mm">{{ $dateAccomplished }}</div><div style="border:var(--thin);font-size:5.5pt">Date Accomplished</div></td></tr></table></td><td class="c4-right"><div class="c4-thumb">Right Thumbmark</div></td></tr>
        </table>
        <div style="height:27mm;border-top:var(--med);padding:2mm 10mm;font-size:5.8pt">SUBSCRIBED AND SWORN to before me this <span style="display:inline-block;width:30mm;border-bottom:var(--thin)">&nbsp;</span>, affiant exhibiting his/her validly issued government ID as indicated above.<div style="width:70mm;height:19mm;border:var(--thin);margin:4mm auto 0;text-align:center;padding-top:6mm"><span class="red">(e-signature/digital certificate except for notary public)</span><div style="border-top:var(--thin);margin-top:5mm;padding-top:1mm">Person Administering Oath</div></div></div>
        <div class="pg">CS FORM 212 (Revised 2026), Page 4 of 4</div>
    </div></div>
</section>

@if ($showC5)
<section class="sheet">
    <div class="form"><div class="content">
        <div class="cont-title">LEARNING AND DEVELOPMENT (L&amp;D) INTERVENTIONS/TRAINING PROGRAMS ATTENDED (Continuation)</div>
        <div class="fill"><table class="g" style="height:100%">
            <tr class="h-train-h">
                <th class="lbl wrap outer-l" rowspan="2">TITLE OF LEARNING AND DEVELOPMENT INTERVENTIONS/TRAINING PROGRAMS<br>(Write in full)</th>
                <th class="lbl wrap" colspan="2">INCLUSIVE DATES OF ATTENDANCE<br>(dd/mm/yyyy)</th>
                <th class="lbl" rowspan="2" style="width:14mm">NUMBER OF HOURS</th><th class="lbl wrap" rowspan="2" style="width:18mm">Type of L&amp;D<br>(Managerial/Supervisory/Technical/etc)</th>
                <th class="lbl wrap outer-r" rowspan="2">CONDUCTED/ SPONSORED BY<br>(Write in full)</th>
            </tr>
            <tr class="h-train-h"><th class="lbl" style="width:12mm">From</th><th class="lbl" style="width:12mm">To</th></tr>
            @foreach ($padRows($trainingsCont, 38) as $row)
                <tr class="h-train">
                    <td class="val outer-l">{{ $cell($row, 'title') }}</td>
                    <td class="val">{{ $cell($row, 'from') }}</td>
                    <td class="val">{{ $cell($row, 'to') }}</td>
                    <td class="val">{{ $cell($row, 'hours') }}</td>
                    <td class="val">{{ $cell($row, 'type') }}</td>
                    <td class="val outer-r">{{ $cell($row, 'sponsor') }}</td>
                </tr>
            @endforeach
        </table></div>
        <table class="g cont-footer"><tr><td style="width:24%">SIGNATURE</td><td class="red">(e-signature/digital certificate)</td><td style="width:14%">DATE</td><td style="width:24%">{{ $dateAccomplished }}</td></tr></table>
        <div class="pg">CS FORM 212 (Revised 2026), Continuation C5</div>
    </div></div>
</section>
@endif

@if ($showC6)
<section class="sheet">
    <div class="form"><div class="content">
        <div class="cont-title">WORK EXPERIENCE (Continuation)</div>
        <div class="fill"><table class="g" style="height:100%">
            <tr class="h-work-h">
                <th class="lbl wrap outer-l" colspan="2">INCLUSIVE DATES<br>(dd/mm/yyyy)</th>
                <th class="lbl wrap" rowspan="2">POSITION TITLE<br>(Write in full/Do not abbreviate)</th><th class="lbl wrap" rowspan="2">DEPARTMENT / AGENCY / OFFICE / COMPANY<br>(Write in full/Do not abbreviate)</th>
                <th class="lbl" rowspan="2" style="width:12mm">MONTHLY SALARY</th><th class="lbl wrap" rowspan="2" style="width:14mm">SALARY/JOB/PAY GRADE &amp; STEP INCREMENT</th>
                <th class="lbl wrap" rowspan="2" style="width:14mm">STATUS OF APPOINTMENT</th><th class="lbl wrap outer-r" rowspan="2" style="width:10mm">GOV'T SERVICE<br>(Y/N)</th>
            </tr>
            <tr class="h-work-h"><th class="lbl outer-l" style="width:12mm">From</th><th class="lbl" style="width:12mm">To</th></tr>
            @foreach ($padRows($workExperiencesCont, 34) as $row)
                <tr class="h-work">
                    <td class="val outer-l">{{ $cell($row, 'from') }}</td>
                    <td class="val">{{ $cell($row, 'to') }}</td>
                    <td class="val">{{ $cell($row, 'position') }}</td>
                    <td class="val">{{ $cell($row, 'company') }}</td>
                    <td class="val">{{ $cell($row, 'salary') }}</td>
                    <td class="val">{{ $cell($row, 'grade_step') }}</td>
                    <td class="val">{{ $cell($row, 'status') }}</td>
                    <td class="val outer-r">{{ $cell($row, 'govt') }}</td>
                </tr>
            @endforeach
        </table></div>
        <table class="g cont-footer"><tr><td style="width:18%">SIGNATURE</td><td class="red">(e-signature/digital certificate)</td><td style="width:12%">DATE</td><td style="width:24%">{{ $dateAccomplished }}</td></tr></table>
        <div class="pg">CS FORM 212 (Revised 2026), Continuation C6</div>
    </div></div>
</section>
@endif

@if ($showC7)
<section class="sheet">
    <div class="form"><div class="content">
        <div class="cont-title">FAMILY BACKGROUND (Continuation)</div>
        <div class="fill"><table class="g" style="height:100%">
            <tr class="h-child-h">
                <th class="lbl wrap outer-l">SPOUSE'S SURNAME</th><th class="lbl">MIDDLE NAME</th><th class="lbl">FIRST NAME</th><th class="lbl wrap">NAME EXTENSION<br>(JR., SR)</th><th class="lbl">OCCUPATION</th><th class="lbl wrap">EMPLOYER/BUSINESS NAME</th><th class="lbl">BUSINESS ADDRESS</th><th class="lbl">TELEPHONE NO.</th><th class="lbl wrap">NAME of CHILDREN (Write full name and list all)</th><th class="lbl wrap outer-r" style="width:27mm">DATE OF BIRTH<br>(dd/mm/yyyy)</th>
            </tr>
            @foreach ($padRows($childrenCont, 22) as $child)
                <tr class="h-child">
                    <td class="val outer-l"></td><td class="val"></td><td class="val"></td><td class="val"></td><td class="val"></td><td class="val"></td><td class="val"></td><td class="val"></td><td class="val">{{ $cell($child, 'name') }}</td><td class="val outer-r">{{ $cell($child, 'birthdate') }}</td>
                </tr>
            @endforeach
        </table></div><table class="g cont-footer"><tr><td style="width:20%">SIGNATURE</td><td class="red">(e-signature/digital certificate)</td><td style="width:18%">DATE</td><td style="width:20%">{{ $dateAccomplished }}</td></tr></table>
        <div class="pg">CS FORM 212 (Revised 2026), Continuation C7</div>
    </div></div>
</section>
@endif

@if ($showC8)
<section class="sheet">
    <div class="form"><div class="content">
        <div class="cont-title">EDUCATIONAL BACKGROUND (Continuation)</div>
        <div class="fill"><table class="g" style="height:100%">
            <tr class="h-edu-h"><th class="lbl outer-l" rowspan="2">LEVEL</th><th class="lbl wrap" rowspan="2">NAME OF SCHOOL<br>(Write in full)</th><th class="lbl wrap" rowspan="2">BASIC EDUCATION/DEGREE/COURSE<br>(Write in full)</th><th class="lbl" colspan="2">PERIOD OF ATTENDANCE</th><th class="lbl wrap" rowspan="2">HIGHEST LEVEL/UNITS EARNED</th><th class="lbl" rowspan="2">YEAR GRADUATED</th><th class="lbl wrap outer-r" rowspan="2">SCHOLARSHIP/ACADEMIC HONORS RECEIVED</th></tr>
            <tr class="h-edu-h"><th class="lbl">From</th><th class="lbl">To</th></tr>
            @foreach ($padRows($educationsCont, 36) as $edu)
                <tr class="h-edu">
                    <td class="val outer-l">{{ $cell($edu, 'level') }}</td>
                    <td class="val">{{ $cell($edu, 'school') }}</td>
                    <td class="val">{{ $cell($edu, 'course') }}</td>
                    <td class="val">{{ $cell($edu, 'from') }}</td>
                    <td class="val">{{ $cell($edu, 'to') }}</td>
                    <td class="val">{{ $cell($edu, 'units') }}</td>
                    <td class="val">{{ $cell($edu, 'year') }}</td>
                    <td class="val outer-r">{{ $cell($edu, 'honors') }}</td>
                </tr>
            @endforeach
        </table></div><table class="g cont-footer"><tr><td style="width:20%">SIGNATURE</td><td class="red">(e-signature/digital certificate)</td><td style="width:18%">DATE</td><td style="width:20%">{{ $dateAccomplished }}</td></tr></table>
        <div class="pg">CS FORM 212 (Revised 2026), Continuation C8</div>
    </div></div>
</section>
@endif

@if ($showC9)
<section class="sheet">
    <div class="form"><div class="content">
        <div class="cont-title">CIVIL SERVICE ELIGIBILITY (Continuation)</div>
        <div class="fill"><table class="g" style="height:100%">
            <tr class="h-elig-h"><th class="lbl wrap outer-l" rowspan="2">CES/CSEE/CAREER SERVICE/RA 1080 (BOARD/BAR)/UNDER SPECIAL LAWS/CATEGORY II/IV/ELIGIBILITY</th><th class="lbl" rowspan="2">RATING<br>(If Applicable)</th><th class="lbl wrap" rowspan="2">DATE OF EXAMINATION/CONFERMENT</th><th class="lbl wrap" rowspan="2">PLACE OF EXAMINATION/CONFERMENT</th><th class="lbl outer-r" colspan="2">LICENSE (if applicable)</th></tr>
            <tr class="h-elig-h"><th class="lbl">NUMBER</th><th class="lbl outer-r">Valid Until</th></tr>
            @foreach ($padRows($eligibilitiesCont, 30) as $row)
                <tr class="h-elig">
                    <td class="val outer-l">{{ $cell($row, 'title') }}</td>
                    <td class="val">{{ $cell($row, 'rating') }}</td>
                    <td class="val">{{ $cell($row, 'confer_date') }}</td>
                    <td class="val">{{ $cell($row, 'confer_place') }}</td>
                    <td class="val">{{ $cell($row, 'license_no') }}</td>
                    <td class="val outer-r">{{ $cell($row, 'exp_date') }}</td>
                </tr>
            @endforeach
        </table></div><table class="g cont-footer"><tr><td style="width:20%">SIGNATURE</td><td class="red">(e-signature/digital certificate)</td><td style="width:18%">DATE</td><td style="width:20%">{{ $dateAccomplished }}</td></tr></table>
        <div class="pg">CS FORM 212 (Revised 2026), Continuation C9</div>
    </div></div>
</section>
@endif

@if ($showC10)
<section class="sheet">
    <div class="form"><div class="content">
        <div class="cont-title">VOLUNTARY WORK OR INVOLVEMENT IN CIVIC / NON-GOVERNMENT / PEOPLE / VOLUNTARY ORGANIZATION/S (Continuation)</div>
        <div class="fill"><table class="g" style="height:100%">
            <tr class="h-vol-h"><th class="lbl wrap outer-l" rowspan="2">NAME &amp; ADDRESS OF ORGANIZATION<br>(Write in full)</th><th class="lbl" colspan="2">INCLUSIVE DATES<br>(dd/mm/yyyy)</th><th class="lbl" rowspan="2" style="width:16mm">NUMBER OF HOURS</th><th class="lbl wrap outer-r" rowspan="2">POSITION / NATURE OF WORK</th></tr>
            <tr class="h-vol-h"><th class="lbl" style="width:14mm">From</th><th class="lbl" style="width:14mm">To</th></tr>
            @foreach ($padRows($voluntaryWorksCont, 38) as $row)
                <tr class="h-vol">
                    <td class="val outer-l">{{ $cell($row, 'org') }}</td>
                    <td class="val">{{ $cell($row, 'from') }}</td>
                    <td class="val">{{ $cell($row, 'to') }}</td>
                    <td class="val">{{ $cell($row, 'hours') }}</td>
                    <td class="val outer-r">{{ $cell($row, 'position') }}</td>
                </tr>
            @endforeach
        </table></div><table class="g cont-footer"><tr><td style="width:25%">SIGNATURE</td><td class="red">(e-signature/digital certificate)</td><td style="width:18%">DATE</td><td style="width:20%">{{ $dateAccomplished }}</td></tr></table>
        <div class="pg">CS FORM 212 (Revised 2026), Continuation C10</div>
    </div></div>
</section>
@endif

@if ($showC11)
<section class="sheet">
    <div class="form"><div class="content">
        <div class="cont-title">OTHER INFORMATION (Continuation)</div>
        <div class="fill"><table class="g" style="height:100%">
            <tr class="h-other-h">
                <th class="lbl wrap outer-l">SPECIAL SKILLS and HOBBIES</th>
                <th class="lbl wrap">NON-ACADEMIC DISTINCTIONS / RECOGNITION</th>
                <th class="lbl wrap outer-r">MEMBERSHIP IN ASSOCIATION/ORGANIZATION</th>
            </tr>
            @php
                $c11 = max(count($skillsCont), count($recognitionsCont), count($membershipsCont), 36);
            @endphp
            @for ($i = 0; $i < $c11; $i++)
                <tr class="h-other">
                    <td class="val outer-l">{{ $skillsCont[$i] ?? '' }}</td>
                    <td class="val">{{ $recognitionsCont[$i] ?? '' }}</td>
                    <td class="val outer-r">{{ $membershipsCont[$i] ?? '' }}</td>
                </tr>
            @endfor
        </table></div><table class="g cont-footer"><tr><td style="width:25%">SIGNATURE</td><td class="red">(e-signature/digital certificate)</td><td style="width:18%">DATE</td><td style="width:20%">{{ $dateAccomplished }}</td></tr></table>
        <div class="pg">CS FORM 212 (Revised 2026), Continuation C11</div>
    </div></div>
</section>
@endif

</body>
</html>
