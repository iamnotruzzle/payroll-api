<?php

return [
    'regular_template_path' => env(
        'PAYROLL_REGULAR_TEMPLATE_PATH',
        resource_path('payroll/templates/mmmhmc_regular_payroll_template.xlsx')
    ),

    'snapshot_template_path' => env(
        'PAYROLL_SNAPSHOT_TEMPLATE_PATH',
        resource_path('payroll/templates/mmmhmc_payroll_snapshot_allied_template.xlsx')
    ),
];
