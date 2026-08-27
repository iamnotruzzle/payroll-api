# Stand-alone payroll operations

Payroll generation uses the payroll database as its canonical source. HRIS and scheduler databases are synchronization sources only; they are not required while stand-alone mode is active.

## Initial cutover

1. Back up the payroll and HRIS databases.
2. Configure `PAYROLL_OPERATION_MODES=connected,standalone` and leave `PAYROLL_FORCE_MODE` blank.
3. Run `php artisan migrate --path=database/migrations/2026_08_24_000000_create_payroll_canonical_data_tables.php --database=payroll`.
4. Run `php artisan payroll:canonical-sync` while HRIS is reachable. This atomically copies master data, leave data, compatible password hashes, roles, and permissions.
5. Run `php artisan db:seed --class=RBACSeeder` to add the stand-alone management permissions.
6. Sign in with a copied payroll account and open **Settings → Payroll System**.
7. Import and activate timekeeping for the target period. Confirm readiness, then switch using `SWITCH TO STANDALONE`.

Set `PAYROLL_FORCE_MODE=standalone` only after the imported data and local account recovery flow have been verified. Clear configuration cache after changing environment values.

## Imports

Download either the consolidated template or an individual sheet template from Payroll System Management. Uploads are staged and validated before activation. Activation is atomic and records the file checksum, schema version, operator, effective period, row counts, and full staged payload.

Employee deactivation requires `Confirm Deactivation = Yes`. Passwords imported through the Accounts sheet must already be Laravel-compatible hashes. A Super Admin can instead set a local password from the account recovery section.

## Recovery

- A failed synchronization or invalid workbook never replaces active canonical data.
- Active import batches can be rolled back; when a superseded batch exists it is reactivated.
- Returning to connected mode requires a reconciliation preview and the phrase `SWITCH TO CONNECTED`.
- Every finalized payroll stores its operating mode and active source batch IDs.
