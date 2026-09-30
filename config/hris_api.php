<?php

// Mapping direction: payroll field => HRIS JSON field (dot notation supported).
// The proposed endpoints are disabled until their contracts are confirmed.
$resource = static fn (string $path, array $fields, array $required, array $sourceFields = []): array => [
    'enabled' => false,
    'path' => $path,
    'method' => 'GET',
    'data_path' => 'data',
    'parameters' => [],
    'fields' => array_replace(array_combine($fields, $fields), $sourceFields),
    'required' => $required,
    // Set pagination to ['page_parameter' => 'page', 'last_page_path' => 'meta.last_page'] when supported.
    'pagination' => null,
];

return [
    'base_url' => env('HRIS_API_BASE_URL', 'http://10.11.12.49:4001'),
    'token' => env('HRIS_API_TOKEN'),
    'timeout' => (int) env('HRIS_API_TIMEOUT', 30),
    'connect_timeout' => 5,
    'max_pages' => 100,
    'resources' => [
        'basic-pay' => [
            'enabled' => true,
            'path' => '/api/external/basic-pay',
            'method' => 'POST',
            'data_path' => 'data',
            'parameters' => ['emp_ids' => 'all'],
            'pagination' => null,
            'fields' => [
                'employee_id' => 'emp_id',
                'first_name' => 'first_name', 'middle_name' => 'middle_name',
                'last_name' => 'last_name', 'suffix' => 'suffix',
                'position_title' => 'position_title', 'plantilla_item' => 'plantilla_item',
                'department_id' => 'department_id', 'department_name' => 'department',
                'division_id' => 'division_id', 'division_name' => 'division',
                'salary_grade' => 'salary_grade', 'step' => 'step',
                'monthly_salary' => 'basic_pay',
                'employment_status' => 'employment_status',
                'employment_status_code' => 'employment_status_code',
                'is_active' => 'is_active', 'separation_date' => 'separation_date',
                'date_hired' => 'date_orig_appo', 'tin' => 'tin_no',
                'gsis' => 'gsis_no', 'sss' => 'sss_no',
                'philhealth' => 'phic_no', 'pagibig' => 'pagibig_no',
            ],
            'required' => ['employee_id', 'first_name', 'last_name', 'monthly_salary'],
        ],
        // Source fields below follow the existing tbl_* HRIS models and database sync.
        'divisions' => $resource('/divisions', ['division_id', 'name'], ['division_id', 'name'], ['name' => 'division']),
        'departments' => $resource('/departments', ['department_id', 'division_id', 'name'], ['department_id', 'division_id', 'name'], ['name' => 'department']),
        'positions' => $resource('/positions', ['position_id', 'title', 'salary_grade', 'remarks'], ['position_id', 'title'], ['title' => 'position_title']),
        'employees' => $resource('/employees', [
            'employee_id', 'first_name', 'middle_name', 'last_name', 'extension', 'suffix',
            'department_id', 'position_id', 'step', 'employment_status_id', 'date_hired',
            'is_active', 'is_external', 'tin', 'gsis', 'philhealth', 'pagibig',
            'vl_balance', 'sl_balance',
        ], ['employee_id', 'first_name', 'last_name'], [
            'employee_id' => 'emp_id', 'first_name' => 'firstname',
            'middle_name' => 'middlename', 'last_name' => 'lastname',
            'employment_status_id' => 'empstat_id', 'tin' => 'tin_no',
            'gsis' => 'gsis_no', 'philhealth' => 'phic_no', 'pagibig' => 'pagibig_no',
            'vl_balance' => 'vacation_leave_credits', 'sl_balance' => 'sick_leave_credits',
        ]),
        'employment-statuses' => $resource('/employment_status', ['employment_status_id', 'status'], ['employment_status_id', 'status'], ['employment_status_id' => 'empstat_id']),
        'salary-rates' => $resource('/salary-rates', ['salary_grade', 'step', 'monthly_salary', 'effective_from'], ['salary_grade', 'step', 'monthly_salary', 'effective_from'], [
            'step' => 'step_increment', 'monthly_salary' => 'salary', 'effective_from' => 'effectivity_date',
        ]),
        'leave-types' => $resource('/leave-types', ['leave_type_id', 'name', 'is_active'], ['leave_type_id', 'name'], ['name' => 'leave_name', 'is_active' => 'to_display']),
        'leaves' => array_replace($resource('/leaves', [
            'leave_id', 'employee_id', 'leave_type_id', 'start_date', 'end_date',
            'days_with_pay', 'days_without_pay', 'status', 'remarks',
        ], ['leave_id', 'employee_id', 'leave_type_id', 'start_date', 'end_date', 'status'], [
            'employee_id' => 'emp_id', 'leave_type_id' => 'leave_type',
            'days_with_pay' => 'days_wpay', 'days_without_pay' => 'days_wopay',
        ]), [
            'parameters' => ['from' => '{from}', 'to' => '{to}'],
        ]),
        'timekeeping' => array_replace($resource('/timekeeping', [
            'period', 'employee_id', 'work_days', 'days_with_dtr', 'regular_hours',
            'undertime_hours', 'tardy_hours', 'mra_hours', 'leave_days_with_pay',
            'leave_days_without_pay', 'absent_days',
        ], ['period', 'employee_id'], ['employee_id' => 'emp_id']), ['parameters' => ['period' => '{period}']]),
    ],
];
