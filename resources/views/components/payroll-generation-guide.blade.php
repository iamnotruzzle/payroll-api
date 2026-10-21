@props(['section' => 'configuration'])

@php
    $guides = [
        'hazard-1' => ['Step 1 — Hazard Computation', 'Review hazard pay eligibility and computation.', [
            'Confirm the payroll period, employee scope, and working days.',
            'Review each employee’s hazard pay basis and calculated amount.',
            'Resolve missing or incorrect inputs before continuing to Tax Calculation.',
        ], 'Use the approved hazard pay rules and verify adjustments against supporting records.'],
        'hazard-2' => ['Step 2 — Tax Calculation', 'Review tax on the hazard payroll.', [
            'Check taxable amounts and withholding for each employee.',
            'Review unusual results and correct the underlying inputs.',
            'Continue to Review after checking the calculations.',
        ], 'Confirm that withholding follows the configured payroll tax treatment.'],
        'hazard-3' => ['Step 3 — Review', 'Verify the hazard payroll before finalizing.', [
            'Check employee coverage, hazard pay, tax, net pay, and totals.',
            'Resolve any validation messages before accepting the results.',
            'Finalize using the action on this page and verify the saved run in Payroll History.',
        ], 'Review the payroll against your supporting records before finalization.'],
        'medicare-1' => ['Step 1 — Professional Fees', 'Check doctors’ professional fees and their applicable period.', [
            'Confirm the payroll scope and the professional fee period displayed on this page.',
            'Review the professional fee records, employee matches, and amounts.',
            'Check the configured tax treatment and withholding before continuing to Review.',
        ], 'The professional fee period can differ from the payroll month. Use the period displayed on the page.'],
        'medicare-2' => ['Step 2 — Review', 'Verify the Medicare payroll before finalizing.', [
            'Review doctor coverage, professional fees, withholding, net amounts, and totals.',
            'Resolve missing records or validation messages.',
            'Finalize using the action on this page and verify the saved run in Payroll History.',
        ], 'Confirm fee amounts and tax treatment against the approved supporting records.'],
        'configuration' => ['Payroll Configuration', 'Set the scope before opening the payroll wizard.', [
            'In Standalone mode, set the month and leave cutoff, then sync Employee master data, Salary rates, Leaves, and Timekeeping in order. The fourth step copies the previous MRA month’s DTR punches from the HRIS database. Once started, complete all four before proceeding; existing prepared data can also be used without starting a new sync.',
            'Choose the payroll type, employee types, division or department, and payroll month.',
            'Confirm working days, GSIS days, included leave types, and the inclusive leave cutoff.',
            'For HRIS database timekeeping in standalone mode, select the department whose previous month MRA has been finalized.',
            'Open the generation workflow and confirm the employee list matches this scope.',
        ], 'The payroll month and leave cutoff can differ. For an October payroll, the previous MRA normally covers September.'],
        'system' => ['Payroll System Management', 'Choose the data source and prepare local payroll inputs.', [
            'Connected: select Connected, enter the Super Admin password, and change mode. Preview reconnect conflicts when returning from standalone.',
            'Click Synchronize connected sources to refresh local master data from HRIS. Check payroll input readiness; import and activate a Timekeeping workbook if the target period is missing.',
            'Standalone: open Payroll Configuration to synchronize Employee master data, Salary rates, and Leaves through the API, then copy Timekeeping from the HRIS database. Complete local DTR encoding and finalize MRA afterward.',
            'To read attendance from HRIS, select HRIS database for timekeeping only and save. Complete local DTR encoding and finalize the previous month MRA for the selected department.',
            'For workbook imports, download the template, fill it, stage and validate, resolve errors, then activate the batch.',
        ], 'A forced operating mode is locked. API-synchronized payroll with Manual inputs is blocked because the API has no timekeeping endpoint. HRIS database timekeeping requires a reachable database; it does not switch master data away from the API.'],
        'step-1' => ['Step 1 — MRA Validation', 'Confirm attendance and leave deductions before reviewing pay.', [
            'Confirm the employee scope, payroll period, working days, and previous month MRA.',
            'Review the inclusive leave cutoff and apply any changes. Check paid leave, leave without pay, and previously processed dates.',
            'Review each employee’s DTR/MRA deduction days and logbook leave without pay. Enter zero when a manual input has no deduction.',
            'For offline workbook payroll, enter values or download and import the completed DTR/MRA template, review the preview, and apply valid rows.',
            'Save Step 1 before continuing to Gross Compensation.',
        ], 'With standalone HRIS database timekeeping, finalize the previous month MRA for the selected department first. Manual attendance inputs do not bypass the missing timekeeping restriction on an active API batch.'],
        'step-2' => ['Step 2 — Gross Compensation', 'Verify salary and earnings for each employee.', [
            'Check position, salary grade, step, and the salary rate effective for the payroll period.',
            'Review basic salary, payable days, and the effect of leave and attendance deductions.',
            'Review allowances and compensation adjustments. Include remarks explaining any adjustment.',
            'Save changes before continuing to Mandatory Deductions.',
        ], 'Resolve missing or incorrect salary rates in the source data before finalizing payroll.'],
        'step-3' => ['Step 3 — Mandatory Deductions', 'Review applicable statutory deductions.', [
            'Check which mandatory deduction programs apply to the employee scope.',
            'Review the calculated employee amounts and configured bases or rates.',
            'Check employee eligibility and identifiers where needed for the deduction.',
            'Save changes before continuing to Loan Deductions.',
        ], 'Use the approved deduction configuration. Masked API identifiers do not replace existing local identifiers.'],
        'step-4' => ['Step 4 — Loan Deductions', 'Verify the loans charged in this payroll period.', [
            'Import or enter the loan deductions required for this payroll.',
            'Resolve unmatched employees and review loan references, deduction amounts, and the applicable month.',
            'Check for duplicate entries and review any loan refunds.',
            'Save changes before continuing to Other Deductions.',
        ], 'Check imported loan records against the employee list and the source statement before accepting amounts.'],
        'step-5' => ['Step 5 — Other Deductions', 'Review deductions outside statutory programs and loans.', [
            'Select the applicable deduction programs and employee rosters.',
            'Check individual amounts and any additional premiums or adjustments shown on this step.',
            'Confirm each deduction belongs to this employee and payroll period.',
            'Save changes before continuing to Tax Calculation.',
        ], 'Avoid charging a deduction again if it is already included in another program or import.'],
        'step-6' => ['Step 6 — Tax Calculation', 'Check tax inputs before accepting the calculated withholding.', [
            'Review taxable earnings and the tax inputs used for each employee.',
            'Check prior payroll or annualization amounts, including imported tax inputs when applicable.',
            'Review unusual or missing withholding amounts and correct the underlying inputs.',
            'Save changes before continuing to Review.',
        ], 'Review tax calculations using your approved payroll rules and records.'],
        'step-7' => ['Step 7 — Review', 'Check the complete payroll before export or finalization.', [
            'Verify employee coverage, salary, allowances, deductions, gross pay, net pay, and totals.',
            'Resolve missing adjustment remarks and any validation messages.',
            'Click Export Regular Payroll and review the Excel output against the inputs.',
            'After checking the results, click Finalize Payroll Run and verify the saved run in Payroll History.',
        ], 'Exporting is separate from finalization. Do not finalize until payroll inputs and totals have been reviewed.'],
    ];

    $standalone = app(\App\Services\Payroll\PayrollOperatingModeService::class)->current() === \App\Enums\PayrollOperatingMode::Standalone;
    $modeLabel = $standalone ? 'Standalone' : 'Connected';
    $guides['configuration'] = ['Payroll Configuration', 'Complete the configuration steps in order.', [
        'Set the payroll month, days paid, holidays or work suspensions, and leave cutoff in Run details.',
        'Select the payroll type, employee types, divisions, departments, and included leave types in Payroll scope. Departments are filtered by the selected divisions.',
        ...($standalone ? ['Prepare scoped employee master data, salary rates, leaves, and timekeeping in order. Choose API summaries or database punches for timekeeping. If you start syncing, finish all four actions.'] : []),
        'Use Review & Continue at each step. Check the modal, including fields marked No entry, before confirming.',
        'Review the full configuration and confirm before opening payroll generation.',
    ], 'The leave cutoff can differ from the payroll month. The previous MRA normally covers the calendar month before payroll.'];
    $guides['configuration-details'] = ['Run details', 'Set the dates and calendar for this payroll.', [
        'Choose the payroll month and days paid. GSIS days are calculated from the month.',
        'Review holidays and work suspensions. Add or edit shared calendar entries if your role permits it.',
        'Set the inclusive leave cutoff. It can cover a different month from payroll.',
        'Click Review & Continue. Check the dates, day counts, and calendar entries in the modal; No entries means no calendar entries are recorded.',
    ], 'Confirm & Continue validates these fields before opening Payroll scope. Back to edit closes the modal without advancing.'];
    $guides['configuration-scope'] = ['Payroll scope', 'Select who and what this payroll includes.', [
        'Choose the payroll type and employee types.',
        'Choose one or more divisions, then select departments if needed. Removing a division clears its selected departments.',
        'Leave departments empty to include all departments in the selected divisions. Review the checked leave types.',
        'Click Review & Continue and confirm the scope in the modal.',
    ], $standalone ? 'The selected organization, employee types, month, and cutoff control the preparation syncs.' : 'After confirming the scope, continue to the full configuration review.'];
    if ($standalone) {
        $guides['configuration-preparation'] = ['Prepare data', 'Refresh the selected payroll scope before generation.', [
            'Sync Employee master data. Basic Pay discovers scoped IDs; employee records are fetched by those IDs and filtered by employee type.',
            'Sync Salary rates for the payroll year. Only grades used by scoped employees are activated.',
            'Sync Leaves for the cutoff. The endpoint filters by dates and departments; payroll also restricts records to scoped employees.',
            'For Timekeeping, choose HRIS API attendance summaries or HRIS database DTR punches. Both use scoped employees and the calendar month before payroll.',
            'Check each batch and record count, then Review & Continue. Finish all four actions once started; changing the scope or dates requires preparation again.',
        ], 'API summaries read the HRIS database only when days_with_dtr is null; raw DTR encoding is not required. Database punches still require local DTR encoding and MRA finalization. Previously prepared data can be used without starting a new sync.'];
        $guides['system'] = ['Payroll System Management', 'Manage local payroll inputs and source history.', [
            'Open Payroll Configuration to run the four scoped preparation actions.',
            'Review source history and payroll input readiness. A successful API timekeeping sync stores local attendance summaries; database sync stores DTR snapshots.',
            'For workbook inputs, download a template, stage and validate the workbook, resolve errors, and activate it.',
            'With database punches, complete local DTR encoding and finalize the previous month MRA. With API summaries, verify every scoped employee has attendance for the previous month.',
        ], 'An operating mode forced by the environment cannot be changed here. Changing the timekeeping source explicitly can switch away from snapshot reads.'];
        $guides['step-1'][2] = [
            'Standalone mode does not calculate or apply vacation-leave credit adjustments. Imported leave balances remain unchanged.',
            'Confirm the scope, payroll month, and previous month MRA.',
            'Review the cutoff, paid leave, leave without pay, and previously processed dates using local canonical records.',
            'With HRIS API summaries, review the imported absence, tardiness, and undertime values. Every scoped employee must have attendance for the previous month; mra_hours is not required.',
            'For synchronized HRIS punches, complete local DTR encoding and finalize the previous month MRA for the selected department.',
            'For manual workbook attendance, review or import DTR/MRA deduction days and logbook leave without pay. Enter zero when no deduction applies.',
            'Save Step 1 before continuing to Gross Compensation.',
        ];
        $guides['step-1'][3] = 'API summaries and database punches are separate attendance sources. Tardiness and undertime remain separate hours; mra_hours is not an extra deduction. Database punches still require MRA finalization.';
    } else {
        $guides['system'] = ['Payroll System Management', 'Refresh payroll data from the configured databases.', [
            'Click Synchronize connected sources to refresh canonical master data and accounts from HRIS.',
            'Check HRIS and scheduler connectivity and payroll input readiness.',
            'Prepare DTR encoding and finalize the previous month MRA before payroll finalization.',
            'Review source history and resolve missing source records before generation.',
        ], 'An operating mode forced by the environment cannot be changed here.'];
        $guides['step-1'][2] = [
            'Confirm the employee scope, payroll month, days paid, and previous month MRA.',
            'Review the inclusive cutoff, paid leave, leave without pay, and previously processed dates from the configured sources.',
            'Review DTR/MRA deduction days and logbook leave without pay. Resolve incorrect attendance before continuing.',
            'Save Step 1 before continuing to Gross Compensation.',
        ];
        $guides['step-1'][3] = 'Verify attendance and the previous month MRA against the configured HRIS and payroll records.';
        $guides['step-3'][3] = 'Use approved deduction configuration and verify employee identifiers against the configured source records.';
    }
    $guides['configuration-review'] = ['Review configuration', 'Confirm the complete setup before opening generation.', [
        'Check payroll month, type, employee types, divisions, departments, days paid, and GSIS days.',
        'Review the cutoff and included leave types. Empty departments include all departments in the selected divisions.',
        ...($standalone ? ['Check all preparation results, or verify that the existing local data is ready.'] : []),
        'Open the final review modal and confirm. If an existing payroll is found, review the overlap notice before choosing Proceed Anyway.',
    ], 'Opening generation does not finalize payroll. Attendance, MRA, and finalization validations still apply.'];
@endphp

<div x-data class="inline-flex">
    <button
        type="button"
        class="payroll-generation-utility inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm font-medium focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2"
        x-on:click="window.dispatchEvent(new CustomEvent('payroll-guide-section', { detail: { section: @js($section) } })); window.erpOverlay.open($wire, 'payroll-generation-guide', {}, false)"
        aria-haspopup="dialog"
    >
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 4h6a3 3 0 0 1 3 3v14a4 4 0 0 0-4-2H4V4Zm16 0h-4a3 3 0 0 0-3 3v14a4 4 0 0 1 4-2h3V4Z" /></svg>
        Payroll Generation Guide
    </button>

    <x-setup-form-drawer name="payroll-generation-guide" title="Payroll Generation Guide" size="lg" description="Help for the current page. Opening this guide does not change payroll data.">
        <div
            x-data="{ section: @js($section), returnFocus: null }"
            x-on:payroll-guide-section.window="section = $event.detail.section; returnFocus = document.activeElement"
            x-on:erp-overlay-open.window="if ($event.detail.name === 'payroll-generation-guide') $nextTick(() => $el.closest('[role=dialog]').querySelector('.erp-overlay-close')?.focus())"
            x-on:erp-overlay-close.window="if (!$event.detail.name || $event.detail.name === 'payroll-generation-guide') $nextTick(() => returnFocus?.isConnected && returnFocus.focus())"
            x-on:keydown.tab.window="
                if (!open) return;
                const controls = Array.from($el.closest('.erp-overlay-panel').querySelectorAll('button, a[href], input, select, textarea, [tabindex]')).filter(node => node.tabIndex >= 0 && node.getClientRects().length);
                if (!controls.length) return;
                const first = controls[0], last = controls[controls.length - 1];
                if ($event.shiftKey && document.activeElement === first) { $event.preventDefault(); last.focus(); }
                else if (!$event.shiftKey && document.activeElement === last) { $event.preventDefault(); first.focus(); }
            "
            class="space-y-6 text-sm leading-relaxed"
        >
            @foreach ($guides as $key => [$heading, $intro, $instructions, $note])
                <section x-show="section === @js($key)" x-cloak>
                    <h3 class="text-lg font-semibold">{{ $heading }}</h3>
                    <p class="mt-2 text-slate-600 dark:text-slate-300">{{ $intro }}</p>
                    <ol class="mt-5 list-decimal space-y-3 pl-5">
                        @foreach ($instructions as $instruction)
                            <li class="pl-1">{{ $instruction }}</li>
                        @endforeach
                    </ol>
                    <p class="mt-5 text-slate-600 dark:text-slate-300">{{ $note }}</p>
                </section>
            @endforeach
            <section class="border-t border-slate-200 pt-5 dark:border-slate-700">
                <h3 class="font-semibold">{{ $modeLabel }} mode · Data source reminder</h3>
                <p class="mt-2">{{ $standalone ? 'Payroll uses local records refreshed through scoped API syncs or workbook imports. Timekeeping can use API attendance summaries or database DTR snapshots. Only the database-punch workflow requires local DTR encoding and MRA finalization.' : 'Payroll uses the configured HRIS and payroll database sources. Refresh master data through Payroll System Management and verify attendance and MRA before finalization.' }}</p>
                <p class="mt-3 text-slate-600 dark:text-slate-300">Some steps are read-only for your role. Ask the authorized HR or Accounting user to correct those inputs. Save changes before moving between editable steps.</p>
            </section>
        </div>
    </x-setup-form-drawer>
</div>
