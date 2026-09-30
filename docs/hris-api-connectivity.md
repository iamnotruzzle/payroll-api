# HRIS API connectivity

The HTTP client and mapping check work in connected or standalone mode without accessing HRIS or payroll databases. They do not activate imports, change salaries, or modify payroll records. Existing database sync is unchanged.

Set `HRIS_API_BASE_URL`, `HRIS_API_TOKEN`, and optionally `HRIS_API_TIMEOUT` in the deployment environment. Keep the token out of version control. Run `php artisan config:clear` after changing configuration (or rebuild the deployment configuration cache).

```powershell
php artisan payroll:api-check basic-pay --employee=000001
php artisan payroll:api-check basic-pay
```

The command prints counts and missing optional field names, never employee values or credentials. Source warnings cause a nonzero exit code. An empty response verifies connectivity only, not mappings.

## Changing the contract

Edit `config/hris_api.php`. Each resource specifies `enabled`, `path`, `method` (GET or POST), `parameters`, `data_path`, `fields`, `required`, and `pagination`.

Mappings are **payroll field => source JSON path**. For example:

```php
'employee_id' => 'employee.number',
'monthly_salary' => 'compensation.basic_pay',
```

`data_path` selects the list in the response (for example `data` or `result.employees`); an empty string selects a root JSON list. Only mapped fields are returned. `basic_pay` maps to `monthly_salary`; `payroll_lagged` and `account_status` are intentionally absent. Employee IDs must be strings; the client does not guess missing leading zeros.

Only basic-pay is enabled initially. Other paths are proposed defaults from the API document; source mappings use the existing HRIS database column names. For example, employee names use `firstname`/`middlename`/`lastname`, status IDs use `empstat_id`, salary rates use `salary`/`step_increment`/`effectivity_date`, and leave quantities use `days_wpay`/`days_wopay`. Confirm paths, methods, mappings and required fields, then enable resources individually. Parameters support `{period}`, `{from}`, and `{to}` placeholders. Basic-pay keeps its verified response names and supports the employee filter shown above.

Previews preserve source values: legacy `is_active` can be Y/N, leave status is a legacy code, and `remarks` can contain date CSV or free text. These need explicit semantic normalization before activation. Cancellation currently depends on HRIS leave logs, so no invented cancellation column is mapped. Reference tables do not all have active flags; salary rows do not have a confirmed end date. Timekeeping totals are an API summary contract, not claimed raw HRIS columns; only `emp_id` is mapped to the existing employee identifier. Balance timestamps and synchronization metadata must still be agreed with HRIS.

```powershell
php artisan payroll:api-check employees
php artisan payroll:api-check leaves --from=2026-09-01 --to=2026-09-30
php artisan payroll:api-check timekeeping --period=2026-09
```

For numbered pagination, configure `pagination` as `['page_parameter' => 'page', 'last_page_path' => 'meta.last_page']`. The client requests all pages up to `max_pages`; invalid, changing or excessive page metadata aborts the check. Known pagination indicators with pagination disabled are rejected rather than returning a knowingly partial export. Cursor pagination, snapshot validation and incremental synchronization require the final HRIS contract. Redirects are disabled so authentication is not forwarded to another destination.

## Before activating API imports

This is the connectivity foundation, not automatic payroll synchronization. Confirm salary authority/effective dates, employment-status mapping, approval and cancellation meanings, exact leave dates, timekeeping units and overlap rules, nullability, and deletion handling. Then wire validated exports into staging and activation while preserving payroll-owned fields and accounts. Compare against an existing payroll period before live activation. Missing optional fields currently remain null in previews; they must not be treated as instructions to clear payroll data.
