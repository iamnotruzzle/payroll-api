<?php

use App\Enums\PayrollOperatingMode;

return [
    'allowed_modes' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('PAYROLL_OPERATION_MODES', 'connected,standalone'))
    ))),
    'forced_mode' => env('PAYROLL_FORCE_MODE') ?: null,
    'default_mode' => env('PAYROLL_DEFAULT_MODE', PayrollOperatingMode::Connected->value),
    'super_admin_switch_password_hash' => env('SUPER_ADMIN_SWITCH_PASSWORD_HASH'),
    'workbook_version' => '1.0',
];
