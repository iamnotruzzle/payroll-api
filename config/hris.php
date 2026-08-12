<?php

use App\Models\Hris\Employee;

return [

    /*
    |--------------------------------------------------------------------------
    | Connections
    |--------------------------------------------------------------------------
    |
    | People master, PDS, leave, TARF, and IPCR use the legacy `hris`
    | connection (`tbl_*`). Schema enhancements stay on that same database;
    | see docs/hris-schema-enhancements.md.
    |
    */

    'connections' => [
        'legacy' => 'hris',
    ],

    /*
    |--------------------------------------------------------------------------
    | Leave credit computation (legacy hris tables)
    |--------------------------------------------------------------------------
    |
    | Rules mirror legacy LeaveCreditsUpdater / MenuController entitlement
    | helpers. Do not invent Philippine law beyond leave_type descriptions and
    | these configured gates.
    |
    | Hire-month math: first month uses tbl_leave_earned prorata for days
    | remaining in the hire month (legacy day_id lookup, capped at 30). Each
    | subsequent month through the as-of month earns the full monthly rate.
    | Part-time (empstat 3) uses half rate. Monthly accrual skips employees
    | who have not yet reached their hire date.
    |
    */

    'leave_credits' => [

        'monthly_vl' => 1.25,

        'monthly_sl' => 1.25,

        'part_time_monthly_rate' => 0.625,

        'part_time_empstat_id' => Employee::EMPSTAT_PART_TIME,

        /*
         * Legacy LeaveCreditsUpdater: empstat_id < 6 && empstat_id != 4
         * → Permanent, Casual, Part Time, Temporary.
         */
        'accrual_empstat_ids' => [
            Employee::EMPSTAT_PERMANENT,
            Employee::EMPSTAT_CASUAL,
            Employee::EMPSTAT_PART_TIME,
            Employee::EMPSTAT_TEMPORARY,
        ],

        'excluded_position_ids' => [
            Employee::CONTRACT_OF_SERVICE_POSITION_ID,
            105, // Technical Assistant (legacy exclusion)
        ],

        /** Leave types whose approved days reduce VL balance. */
        'vl_deduct_leave_type_ids' => [1, 3, 11], // VL, Forced Leave, Study Leave

        /** Leave types whose approved days reduce SL balance. */
        'sl_deduct_leave_type_ids' => [2, 18], // Sick Leave, Others (SL)

        'undertime_leave_type_id' => 15,

        'gain_leave_type_id' => 14,

        /** Extended maternity — entire application is days_wopay. */
        'extended_maternity_leave_type_ids' => [17],

        /** Quota / special leaves that must not auto-split into LWOP. */
        'reject_if_insufficient_type_ids' => [4, 5, 6, 7],

        'married_civil_stat_ids' => [1], // tbl_civilstat: Married

        /*
         * Entitlement period for leave types with max_value > 0.
         * annual = calendar year usage; lifetime = all approved usage vs max.
         */
        'entitlement_period' => [
            3 => 'annual',   // Forced Leave
            4 => 'annual',   // Special Privileged Leave
            5 => 'lifetime', // Maternity
            6 => 'lifetime', // Paternity
            7 => 'annual',   // Solo Parent
            8 => 'lifetime', // Rehabilitation
            9 => 'lifetime', // VAWC
            10 => 'lifetime', // Magna Carta for Women
            11 => 'lifetime', // Study Leave
            17 => 'lifetime', // Extended Maternity
            22 => 'annual',  // Wellness
        ],

        /*
         * Eligibility gates keyed by leave_type_id. Missing employee fields
         * mark the type ineligible without inventing a balance.
         */
        'eligibility' => [
            5 => [ // Maternity — female + ≥2 years service
                'gender' => ['F', 'Female', 'female'],
                'min_service_years' => 2,
            ],
            6 => [ // Paternity — married male
                'gender' => ['M', 'Male', 'male'],
                'requires_married' => true,
            ],
            7 => [ // Solo Parent — is_soloparent + ≥1 year
                'requires_solo_parent' => true,
                'min_service_years' => 1,
                'any_employment_status' => true,
            ],
            9 => [ // VAWC — female, any employment status
                'gender' => ['F', 'Female', 'female'],
                'any_employment_status' => true,
            ],
            10 => [ // Magna Carta — female + ≥6 months
                'gender' => ['F', 'Female', 'female'],
                'min_service_months' => 6,
            ],
            17 => [ // Extended Maternity — female
                'gender' => ['F', 'Female', 'female'],
            ],
        ],

        /** Leave type ids that are ledger/system rows, never shown as entitlements. */
        'hidden_leave_type_ids' => [12, 14, 15, 16, 20],
    ],

    /*
    |--------------------------------------------------------------------------
    | CSC Form 6 leave application PDF (legacy NEW_LEAVE_FORM.pdf via FPDM)
    |--------------------------------------------------------------------------
    */
    'leave_form_pdf' => env('HRIS_LEAVE_FORM_PDF', storage_path('app/forms/NEW_LEAVE_FORM.pdf')),

];
