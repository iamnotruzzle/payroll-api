<?php

// Mapping direction: payroll field => HRIS JSON field (dot notation supported).
// Payroll resources follow the HRIS Payroll API Endpoints document.
$resource = static fn (string $path, array $fields, array $required, array $sourceFields = []): array => [
    'enabled' => true,
    'path' => '/api/external/payroll'.$path,
    'method' => 'GET',
    'data_path' => 'data',
    'parameters' => [],
    'fields' => array_replace(array_combine($fields, $fields), $sourceFields),
    'required' => $required,
    'required_keys' => $fields,
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
        'divisions' => $resource('/divisions', ['division_id', 'name', 'is_active'], ['division_id', 'name', 'is_active']),
        'departments' => $resource('/departments', ['department_id', 'division_id', 'name', 'is_active'], ['department_id', 'name', 'is_active']),
        'positions' => $resource('/positions', ['position_id', 'title', 'salary_grade'], ['position_id', 'title']),
        'employees' => $resource('/employees', [
            'employee_id', 'first_name', 'middle_name', 'last_name', 'extension', 'suffix',
            'department_id', 'position_id', 'step', 'employment_status_id', 'date_hired',
            'is_active', 'is_external', 'tin', 'gsis', 'philhealth', 'pagibig', 'vl_balance', 'sl_balance',
        ], ['employee_id', 'first_name', 'last_name', 'employment_status_id', 'is_active', 'is_external']),
        'employment-statuses' => $resource('/employment_status', ['employment_status_id', 'status', 'code'], ['employment_status_id', 'status']),
        'salary-rates' => array_replace($resource('/salary-rates', ['salary_grade', 'step', 'monthly_salary', 'effective_from', 'effective_to'], ['salary_grade', 'step', 'monthly_salary', 'effective_from']), ['parameters' => ['year' => 'all']]),
        'leave-types' => $resource('/leave-types', ['leave_type_id', 'name'], ['leave_type_id', 'name']),
        'leaves' => array_replace($resource('/leaves', [
            'leave_id', 'employee_id', 'leave_type_id', 'start_date', 'end_date',
            'days_with_pay', 'days_without_pay', 'status_id', 'status', 'cancelled', 'leave_dates',
        ], ['leave_id', 'employee_id', 'leave_type_id', 'start_date', 'end_date', 'days_with_pay', 'days_without_pay', 'status', 'cancelled']), [
            'parameters' => ['from' => '{from}', 'to' => '{to}'],
        ]),
        // Not present in the supplied HRIS contract. Do not call an invented endpoint.
        'timekeeping' => ['enabled' => false],
    ],
];
