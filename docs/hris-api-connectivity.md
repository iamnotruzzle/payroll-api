# HRIS payroll API synchronization

## Admin endpoint diagnostics

Admins and super-admins can open **Settings > Access & Security > HRIS API Tests** (`/admin/hris-api-diagnostics`). Choose one documented endpoint, supply supported filters, and run a read-only test. Results show HTTP status, elapsed time, record count, preview contract issues, and bounded JSON. CoS Positions and Basic Pay are included. The page uses configured credentials server-side, redacts credential fields, sends no redirects, and performs no canonical-data import. Access is checked again on every test action; it is available in both operating modes.

The documented eight GET resources under `/api/external/payroll` now synchronize payroll-owned canonical data in **standalone** mode. Connected mode retains direct HRIS database synchronization. API synchronization never opens the HRIS or scheduler database connection, and does not switch modes automatically.

## Configure API-only operation

Set the HRIS origin (without the endpoint path) and bearer token in the deployment environment:

```dotenv
PAYROLL_OPERATION_MODES=connected,standalone
PAYROLL_DEFAULT_MODE=standalone
PAYROLL_FORCE_MODE=standalone
HRIS_API_BASE_URL=https://hris.example.org
HRIS_API_TOKEN=your-deployment-token
HRIS_API_TIMEOUT=30
```

`PAYROLL_FORCE_MODE=standalone` locks the Super Admin selector. Leave it blank to permit the existing selector; `PAYROLL_DEFAULT_MODE` is only the default when no saved mode exists. Existing local payroll accounts are required. This integration does not import accounts, passwords or roles, because the supplied API has no account endpoints.

Run the additive migration and refresh configuration:

```powershell
php artisan migrate --database=payroll --path=database/migrations/2026_10_13_000001_support_hris_api_canonical_inputs.php
php artisan config:clear
php artisan payroll:api-sync --period=2026-09
```

The base canonical migration must already be installed. The new migration allows unassigned department divisions and stores leave approval status and exact dates.

In **Payroll Configuration**, complete **Run details**, then **Payroll scope**. Standalone mode adds **Prepare data**, followed by **Review**. Payroll generation users can run the four preparation actions themselves. Each action records its counts and batch, and later actions unlock in order. Starting preparation requires completing all four actions before continuing; changing the scope, employee types, month, or cutoff invalidates those completion results.

Scoped master-data synchronization first calls `POST /api/external/basic-pay` with selected `department_ids` (when present), otherwise `division_ids`, and `include_inactive=true`. Its employee IDs are then sent to `GET /api/external/payroll/employees` as comma-separated `emp_ids` in batches of 100. Organization and employee-type checks also run locally before activation. Inactive employee records remain eligible for synchronization so deactivations can be captured.

Salary requests use the payroll month’s `year` and activate only salary grades belonging to the scoped employees. Leaves use the configured `from` and `to` cutoff and are restricted locally to scoped employee IDs. The leave endpoint has no employee-ID filter and is requested with department IDs; the reference endpoints have no scope filters, so their downloaded records are narrowed locally. Other employees’ canonical records are retained. Basic Pay’s named `type` values are not assumed to equal local payroll-type codes.

The fourth action defaults to scoped HRIS API summaries for the previous attendance month. The database-punch alternative still requires the snapshot migration, DTR encoding, and MRA finalization. CLI full synchronization remains available; for a different payroll cutoff use:

```powershell
php artisan payroll:api-sync --period=2026-10 --from=2026-09-01 --to=2026-09-30
```

## Data handling

For full CLI synchronization, all eight exports must succeed before validation and atomic activation. Wizard steps validate and activate their own scoped exports atomically. Employees are fetched without `active_only` so explicit deactivations are synchronized; salary rates use `year=all` so effective dates remain available. Employee IDs remain strings, preserving leading zeros. Remote employment-status IDs are mapped by status name to the existing payroll categories; unknown categories fail rather than being assigned a guessed category.

Each successful export records a source batch, checksum, operator, counts and leave range. Repeating an unchanged export is idempotent. Failed HTTP requests, warnings, missing documented keys, invalid dates or invalid references do not replace active data. The client rejects mismatched response counts and unexpected pagination and does not follow redirects or print tokens or response bodies.

Bank accounts, responsibility centers, fund types, local accounts and permissions are preserved. Position remarks and position/leave-type active flags are preserved locally because the API does not supply them. Masked statutory identifiers containing `*` preserve the existing local value; for a new employee they remain null. An explicit API employee active flag is authoritative. Records omitted from an export are retained; no deletion contract is assumed.

Leave status and cancellation are stored. Pending and rejected leave is excluded from payroll; cancelled leave remains available to cancellation checks. A non-null `leave_dates` must be a list of valid YYYY-MM-DD dates within the leave range and is used by the leave date helper. Null remains null; the existing date-range expansion remains the fallback. Exact date behavior still needs confirmation with HRIS before live payroll use.

## Timekeeping

The updated contract provides `GET /api/external/payroll/timekeeping`. In Standalone Payroll Configuration, the fourth preparation action defaults to **HRIS API attendance summaries**. It requests the calendar month before payroll using `period=YYYY-MM` and scoped comma-separated `emp_ids` in batches of 100. October payroll therefore imports September attendance; the leave cutoff is independent.

The fields are `period`, `employee_id`, `work_days`, `days_with_dtr`, `regular_hours`, `tardy_hours`, `undertime_hours`, `leave_days_with_pay`, `leave_days_without_pay`, and `absent_days`. `mra_hours` is not requested or required. The legacy storage column remains zero for API imports. The latest contract supplies calendar holidays separately; a legacy `holidays` count is optional and defaults to zero.

If `days_with_dtr` is explicitly null, payroll counts distinct DTR dates in `hris.tbl_employee_dtr` for that employee and attendance month. Duplicate rows on the same date count once. Explicit zero is preserved; a missing field remains a contract error. Only null counts trigger HRIS database reads. A failed fallback leaves existing attendance intact. Source batches record which counts came from the database.

Every scoped employee must have exactly one row for the requested month. Missing employees, duplicate or unexpected rows, incorrect periods, warnings, malformed responses, and negative or non-finite metrics prevent activation and preserve existing attendance. Activation and the switch to API timekeeping happen in one payroll transaction. Repeating identical data reuses the batch.

Payroll reads API attendance from the previous month and validates coverage for all scoped employees. Unauthorized absence days default from `absent_days` unless explicitly overridden. Leave processing remains separate to avoid adding leave deductions twice. Tardiness and undertime remain separate in finalized summaries; `mra_hours` is not an additional deduction. Review Step 1 before finalization. API summaries require HRIS database access only for null DTR-day counts, and do not require a local MRA report generated from raw punches.

The API client also supports documented `from` + `to` ranges for connectivity checks. The wizard intentionally uses calendar months because canonical periods are stored as `YYYY-MM`.

The alternate **HRIS database DTR punches** source remains available. It copies scoped previous-month punches into local snapshots and still requires local DTR encoding and MRA finalization. That option requires `DB_*_HRIS` and the snapshot migration. Switching sources invalidates preparation results.

The updated employee endpoint rejects `active_only`. Scoped requests use `emp_ids`, which includes named inactive employees. Group codes are not assumed to equal local employment-category codes. Leaves now receive documented comma-separated `department_ids` plus cutoff dates and are then filtered locally to scoped employees.
## Read-only connectivity checks

`payroll:api-check` remains available without changing data:

```powershell
php artisan payroll:api-check employees
php artisan payroll:api-check leaves --from=2026-09-01 --to=2026-09-30
php artisan payroll:api-check salary-rates
php artisan payroll:api-check timekeeping --period=2026-09
```

The existing `basic-pay` POST resource remains available for compatibility; it is not used by the new documented payroll sync. Mapping configuration lives in `config/hris_api.php` and uses payroll field to source JSON field direction.


## Standalone holiday calendar

Run `php artisan migrate --force` on deployment to add the HRIS calendar ID. In Payroll Configuration > Run details, authorized payroll HR/calendar users can select **Sync holidays from HRIS**. This calls `GET /api/external/payroll/holidays?period=YYYY-MM&kinds=holiday,suspension` for the selected payroll month. Calendar closures are shared and have no division/department filter in the documented API.

Full-day and standard half-day closures are mapped to the local calendar; OD-only/non-closing records are skipped. Unsupported partial hours and duplicate-date closures reject the import before any writes. Manual date conflicts are skipped. Imports are repeatable by HRIS holiday ID, preserve locally edited paid status, and retain existing entries absent from subsequent responses. New entries default to paid because the endpoint has no paid flag; review them before continuing. Holidays are also available in admin HRIS API Tests with month or date-range filtering.
