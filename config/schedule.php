<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CNO / Nursing division
    |--------------------------------------------------------------------------
    |
    | HRIS tbl_department.division_id for Chief Nurse Office / Nursing Service.
    | Departments in this division get NDOS-parity profile defaults
    | (units, floaters, on-call, swaps, census). Other divisions stay on a
    | simple department roster; they may enable uses_units alone for multi-area.
    |
    */
    'cno_division_id' => (int) env('SCHEDULE_CNO_DIVISION_ID', 3),

    'cno_profile_defaults' => [
        'uses_units' => true,
        'uses_floaters' => true,
        'uses_on_call' => true,
        'uses_swaps' => true,
        'uses_census' => true,
    ],

    'simple_profile_defaults' => [
        'uses_units' => false,
        'uses_floaters' => false,
        'uses_on_call' => false,
        'uses_swaps' => false,
        'uses_census' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | NDOS sync (connection key: schedulev2)
    |--------------------------------------------------------------------------
    |
    | Read-only import from NDOS (Nursing Division Online Scheduling) into
    | payroll_scheduler. Connection settings live in config/database.php
    | under the "schedulev2" connection (DB_*_SCHEDULEV2 env vars).
    |
    | Import never calls ScheduleLockService / DTR sync. Only approved (A)
    | employee_schedules rows are pulled; months land locked without payroll
    | side effects. Re-runs always re-compare and update mapped rows in place.
    |
    | Full reference backfill (schedule:backfill-schedulev2) clears schedule-owned
    | tables then imports shifts/locations/groups/pools/settings. Requires
    | --apply --force. Optional --with-assignments reuses the sync above.
    |
    */
    'schedulev2' => [
        'connection' => env('SCHEDULEV2_DB_CONNECTION', 'schedulev2'),
        'default_months_back' => (int) env('SCHEDULEV2_SYNC_MONTHS_BACK', 1),
        'default_months_ahead' => (int) env('SCHEDULEV2_SYNC_MONTHS_AHEAD', 1),
        // NDOS statuses: P pending, S submitted, C checked, R recommended, A approved, D deleted.
        'approved_statuses' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('SCHEDULEV2_APPROVED_STATUSES', 'A'))
        ))),
        // Skip on-call-only shift labels (NDOS uses "OC"; resolve by label, never hard-coded shift IDs).
        'skip_shift_labels' => array_filter(array_map('trim', explode(',', (string) env('SCHEDULEV2_SKIP_SHIFT_LABELS', 'OC')))),
        // When true, create missing ShiftCode rows from NDOS shift_label during sync.
        'create_missing_shift_codes' => (bool) env('SCHEDULEV2_CREATE_MISSING_SHIFTS', true),
        // When true, create ScheduleUnit rows from NDOS location names under the
        // duty-location-resolved schedule department (fallback: employee HRIS home department).
        'create_missing_units' => (bool) env('SCHEDULEV2_CREATE_MISSING_UNITS', true),
        // Full backfill: when a location cannot be matched to an HRIS department,
        // place the unit here (department_id). Null = use first CNO division dept when
        // backfill_unmatched_locations_to_cno is true; otherwise skip (units_no_department).
        'backfill_fallback_department_id' => filled(env('SCHEDULEV2_BACKFILL_FALLBACK_DEPARTMENT_ID'))
            ? (int) env('SCHEDULEV2_BACKFILL_FALLBACK_DEPARTMENT_ID')
            : null,
        'backfill_unmatched_locations_to_cno' => (bool) env('SCHEDULEV2_BACKFILL_UNMATCHED_TO_CNO', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF / email distribution
    |--------------------------------------------------------------------------
    |
    | Download PDF always works. Email uses queueable ScheduleDistributionMail
    | and requires a real MAIL_FROM_ADDRESS (not hello@example.com) plus a
    | non-array mailer. Local smtp 127.0.0.1 is rejected unless allow_local_smtp.
    |
    */
    'distribution' => [
        'allow_local_smtp' => (bool) env('SCHEDULE_ALLOW_LOCAL_SMTP', false),
    ],
];
