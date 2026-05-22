<?php

return [
    'regular_template_path' => env(
        'PAYROLL_REGULAR_TEMPLATE_PATH',
        resource_path('payroll/templates/mmmhmc_regular_payroll_template.xlsx')
    ),
];
