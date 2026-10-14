# HRIS payroll API synchronization

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

Salary requests use the payroll month’s `year` and activate only salary grades belonging to the scoped employees. Leaves use the configured `from` and `to` cutoff and are restricted locally to scoped employee IDs. The documented leave endpoint has no employee or department filter; the reference endpoints have no scope filters, so their downloaded records are narrowed locally. Other employees’ canonical records are retained. Basic Pay’s named `type` values are not assumed to equal local payroll-type codes.

The fourth action reads only scoped employees’ previous-month DTR punches from the HRIS database into payroll-owned snapshots. DTR/MRA screens use that local snapshot in standalone mode after successful synchronization. Complete DTR encoding and finalize MRA afterward. The snapshot migration must be installed. CLI full synchronization remains available; for a different payroll cutoff use:

```powershell
php artisan payroll:api-sync --period=2026-10 --from=2026-09-01 --to=2026-09-30
```

## Data handling

For full CLI synchronization, all eight exports must succeed before validation and atomic activation. Wizard steps validate and activate their own scoped exports atomically. Employees are fetched without `active_only` so explicit deactivations are synchronized; salary rates use `year=all` so effective dates remain available. Employee IDs remain strings, preserving leading zeros. Remote employment-status IDs are mapped by status name to the existing payroll categories; unknown categories fail rather than being assigned a guessed category.

Each successful export records a source batch, checksum, operator, counts and leave range. Repeating an unchanged export is idempotent. Failed HTTP requests, warnings, missing documented keys, invalid dates or invalid references do not replace active data. The client rejects mismatched response counts and unexpected pagination and does not follow redirects or print tokens or response bodies.

Bank accounts, responsibility centers, fund types, local accounts and permissions are preserved. Position remarks and position/leave-type active flags are preserved locally because the API does not supply them. Masked statutory identifiers containing `*` preserve the existing local value; for a new employee they remain null. An explicit API employee active flag is authoritative. Records omitted from an export are retained; no deletion contract is assumed.

Leave status and cancellation are stored. Pending and rejected leave is excluded from payroll; cancelled leave remains available to cancellation checks. A non-null `leave_dates` must be a list of valid YYYY-MM-DD dates within the leave range and is used by the leave date helper. Null remains null; the existing date-range expansion remains the fallback. Exact date behavior still needs confirmation with HRIS before live payroll use.

## Timekeeping

The API contract does not define a timekeeping endpoint. In the standalone configuration wizard, **Step 4 — Timekeeping from HRIS database** reads `hris.tbl_employee_dtr` for scoped employees and the calendar month before payroll. It copies punches into `payroll_hris_dtr_snapshots`, records a source batch, and enables payroll's snapshot timekeeping source. It does not import HRIS accounts, roles, employee master data, or leave records through the database.

Install `2026_10_14_000001_create_payroll_hris_dtr_snapshots.php` on the payroll connection and configure `DB_*_HRIS`. A failed or empty download retains the existing snapshot. Successful scoped refreshes preserve other scopes and periods. Payroll DTR encoding, attendance reports, DTR printing, corrections, and MRA use the local snapshot in standalone mode; attendance self-service continues using its existing HRIS model.

Complete DTR encoding and finalize the previous month's MRA for the selected department before payroll finalization. Synchronizing punches does not finalize MRA or manufacture missing attendance. Once copied, payroll can read the punches without a live HRIS database connection.

The existing Super Admin setting under **Settings > Payroll System > Standalone timekeeping source** remains available for direct HRIS database timekeeping or manual inputs. Selecting that setting explicitly switches away from snapshot mode. Manual inputs retain the existing API-generation restriction because no timekeeping API is available.
## Read-only connectivity checks

`payroll:api-check` remains available without changing data:

```powershell
php artisan payroll:api-check employees
php artisan payroll:api-check leaves --from=2026-09-01 --to=2026-09-30
php artisan payroll:api-check salary-rates
```

The existing `basic-pay` POST resource remains available for compatibility; it is not used by the new documented payroll sync. Mapping configuration lives in `config/hris_api.php` and uses payroll field to source JSON field direction.
