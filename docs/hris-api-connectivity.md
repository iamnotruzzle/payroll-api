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

Alternatively, select the period in **Settings > Payroll System**, then choose **Synchronize HRIS API**. The action uses the existing `payroll.system.sync` permission. The default leave range is the selected calendar month. For a different payroll cutoff, enter From and To dates or use:

```powershell
php artisan payroll:api-sync --period=2026-10 --from=2026-09-01 --to=2026-09-30
```

## Data handling

All eight exports must succeed before validation and atomic activation. Employees are fetched without `active_only` so explicit deactivations are synchronized; salary rates use `year=all` so effective dates remain available. Employee IDs remain strings, preserving leading zeros. Remote employment-status IDs are mapped by status name to the existing payroll categories; unknown categories fail rather than being assigned a guessed category.

Each successful export records a source batch, checksum, operator, counts and leave range. Repeating an unchanged export is idempotent. Failed HTTP requests, warnings, missing documented keys, invalid dates or invalid references do not replace active data. The client rejects mismatched response counts and unexpected pagination and does not follow redirects or print tokens or response bodies.

Bank accounts, responsibility centers, fund types, local accounts and permissions are preserved. Position remarks and position/leave-type active flags are preserved locally because the API does not supply them. Masked statutory identifiers containing `*` preserve the existing local value; for a new employee they remain null. An explicit API employee active flag is authoritative. Records omitted from an export are retained; no deletion contract is assumed.

Leave status and cancellation are stored. Pending and rejected leave is excluded from payroll; cancelled leave remains available to cancellation checks. A non-null `leave_dates` must be a list of valid YYYY-MM-DD dates within the leave range and is used by the leave date helper. Null remains null; the existing date-range expansion remains the fallback. Exact date behavior still needs confirmation with HRIS before live payroll use.

## Timekeeping limitation

The supplied PDF does not define a timekeeping endpoint. The default manual setting keeps generation blocked after API activation, including when workbook attendance exists.

To use direct HRIS database access for **timekeeping only**, select **HRIS database for timekeeping only** under **Settings > Payroll System > Standalone timekeeping source** and save. Only a Super Admin can change this setting. Alternatively, set the deployment default:

```dotenv
PAYROLL_STANDALONE_TIMEKEEPING_SOURCE=hris_db
```

A saved selection takes precedence over this default. Keep `PAYROLL_FORCE_MODE=standalone` if desired and configure the existing `DB_*_HRIS` connection. Master data synchronization still uses the API. DTR encoding, MRA and DTR printing read only `hris.tbl_employee_dtr`; employee, position and leave reads use canonical payroll records, and standalone timekeeping does not access scheduler tables. Assign time templates through the local DTR encoding workflow. MRA finalization updates local leave balances, not HRIS balances.

The database and punch table must be available. Complete DTR encoding and finalize the previous month MRA for the selected department before finalizing payroll. Missing or unreachable HRIS timekeeping blocks generation; the selection does not fabricate attendance or bypass MRA readiness. Existing canonical workbook timekeeping summaries are ignored with this selection so two attendance sources do not override one another.

Choose **Manual inputs** to restore the original restriction for API-synchronized payroll until a confirmed timekeeping endpoint is implemented. A connected database sync supersedes the API batch and restores the existing connected workflow.

## Read-only connectivity checks

`payroll:api-check` remains available without changing data:

```powershell
php artisan payroll:api-check employees
php artisan payroll:api-check leaves --from=2026-09-01 --to=2026-09-30
php artisan payroll:api-check salary-rates
```

The existing `basic-pay` POST resource remains available for compatibility; it is not used by the new documented payroll sync. Mapping configuration lives in `config/hris_api.php` and uses payroll field to source JSON field direction.
