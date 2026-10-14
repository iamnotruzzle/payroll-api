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
            'In Standalone mode, use the HRIS API preparatory step after setting the month and leave cutoff. Review the refreshed scope and prepare timekeeping before proceeding; existing API or workbook data can also be used.',
            'Choose the payroll type, employee types, division or department, and payroll month.',
            'Confirm working days, GSIS days, included leave types, and the inclusive leave cutoff.',
            'For HRIS database timekeeping in standalone mode, select the department whose previous month MRA has been finalized.',
            'Open the generation workflow and confirm the employee list matches this scope.',
        ], 'The payroll month and leave cutoff can differ. For an October payroll, the previous MRA normally covers September.'],
        'system' => ['Payroll System Management', 'Choose the data source and prepare local payroll inputs.', [
            'Connected: select Connected, enter the Super Admin password, and change mode. Preview reconnect conflicts when returning from standalone.',
            'Click Synchronize connected sources to refresh local master data from HRIS. Check payroll input readiness; import and activate a Timekeeping workbook if the target period is missing.',
            'Standalone with API: select Standalone, then open Payroll Configuration. Set the payroll period and leave cutoff and click Synchronize HRIS API in the preparatory step.',
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
                <h3 class="font-semibold">Data source reminder</h3>
                <p class="mt-2">Connected mode refreshes local master data from the HRIS database. Standalone mode uses local records refreshed through the API or workbook imports. The optional HRIS database timekeeping source reads punches only.</p>
                <p class="mt-3 text-slate-600 dark:text-slate-300">Some steps are read-only for your role. Ask the authorized HR or Accounting user to correct those inputs. Save changes before moving between editable steps.</p>
            </section>
        </div>
    </x-setup-form-drawer>
</div>
