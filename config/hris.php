<?php

use App\Models\Hris\Employee;

return [

    /*
    |--------------------------------------------------------------------------
    | HRIS schema mode
    |--------------------------------------------------------------------------
    |
    | When false, people data continues to be read from the legacy `hris`
    | connection (`tbl_*`). When true, Employees/Leave modules prefer `hris_v2`.
    |
    */

    'use_v2' => (bool) env('HRIS_USE_V2', false),

    'connections' => [
        'legacy' => 'hris',
        'v2' => 'hris_v2',
    ],

    /*
    |--------------------------------------------------------------------------
    | Phase 9 cutover — canonical app flags
    |--------------------------------------------------------------------------
    |
    | When true for a module, this app is the operational system of record for
    | that module (legacy HRIS UI retired for that scope). Defaults stay false
    | until ops completes dual-run and flips env. See docs/hris-cutover.md.
    |
    | These flags drive the Cutover status page and optional ERP banner; they
    | do not auto-redirect legacy apps.
    |
    */

    'cutover' => [
        'employees' => (bool) env('HRIS_CUTOVER_EMPLOYEES', false),
        'leave' => (bool) env('HRIS_CUTOVER_LEAVE', false),
        'self_service' => (bool) env('HRIS_CUTOVER_SELF_SERVICE', false),
        'schedule' => (bool) env('HRIS_CUTOVER_SCHEDULE', false),
        'training' => (bool) env('HRIS_CUTOVER_TRAINING', false),
        'performance' => (bool) env('HRIS_CUTOVER_PERFORMANCE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Freeze legacy employee-master writes
    |--------------------------------------------------------------------------
    |
    | When true (and typically only with HRIS_USE_V2=true), block writes to
    | legacy tbl_employee / PDS section tables via Employees UI services.
    | Does NOT freeze leave credits, TARF, IPCR, or DTR — those still use
    | intentional legacy tables. See LegacyEmployeeMasterWriteGuard.
    |
    */

    'freeze_legacy_writes' => (bool) env('HRIS_FREEZE_LEGACY_WRITES', false),

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

];
