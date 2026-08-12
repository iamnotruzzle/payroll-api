# HRIS Foundation (Phase 1)

**Decision date:** 2026-08-10  
**Schema strategy:** **B** — new normalized HRIS schema (`hris_v2`) + migrate existing employee records from legacy `tbl_*`.

---

## Ownership

| Domain | System of record | Connection / store |
|--------|------------------|--------------------|
| People master, PDS, leave (new) | This app → `hris_v2` | `hris_v2` |
| Legacy people/leave/DTR (until cutover) | Legacy HRIS DB (read / dual-write period) | `hris` |
| Spatie roles/permissions | This app | `hris` (existing) |
| Scheduling | This app | `payroll_scheduler` |
| Payroll ops / DTR encodings | This app | `payroll` |
| Reference projects | Read-only | `reference projects/*` |

**Cutover order:** Foundation → Employees (v2 + ETL) → Leave → Self-service → Schedule generalization → Training/IPCR → freeze legacy writes.

---

## ID policy

| Key | Rule |
|-----|------|
| `employees.id` | Surrogate bigint PK inside `hris_v2` |
| `employees.emp_id` | **Preserved forever** as the public business key (same string as legacy `tbl_employee.emp_id`) |
| Payroll / schedule / accounts | Continue to key off `emp_id` (no mass rewrite of historical payroll/schedule rows) |
| Optional map | `legacy_record_maps` stores source table + source PK + checksum for ETL audit |

Do **not** invent new employee numbers during migration. New hires after cutover still receive `emp_id` values from the agreed numbering rules.

---

## Target schema (initial slice)

Core Phase 1 / 2 tables (migrations under `hris_v2`):

- `employees` — identity + status (`emp_id`, names, active, hire/separation)
- `employee_personals` — birth, sex, civil status, citizenship, religion, addresses
- `employee_government_ids` — TIN, GSIS, Pag-IBIG, PhilHealth, SSS, etc.
- `employee_contacts` — email, mobile, emergency
- `employment_assignments` — dated dept/position/status/step (replaces flat org columns)
- `employee_dependents`, `employee_educations`, `employee_eligibilities`, `employee_work_experiences`, `employee_trainings`, `employee_voluntary_works`, `employee_other_infos`, `employee_character_references`
- `legacy_record_maps` — ETL lineage
- `hris_migration_runs` — batch metadata / row counts / checksums

Org lookups (`departments`, `divisions`, `positions`, salary grades) may stay on legacy `hris` initially and be copied later; employment assignments store legacy FK ids until org is ported.

---

## ETL plan (high level)

1. **Dry-run** count + sample checksum of `tbl_employee` and section tables.
2. **Load employees** 1:1 on `emp_id` into `hris_v2.employees` (+ personals / gov ids / contacts).
3. **Load section tables** keyed by `emp_id`.
4. **Validate** row counts, null `emp_id` orphans, name checksum samples.
5. **Dual-read:** app feature flag `HRIS_USE_V2=false` until validation; then read v2 for Employees UI while payroll/schedule still resolve via `emp_id`.
6. **Dual-write** (optional short window) then freeze legacy writes for migrated modules.

Command: `php artisan hris:migrate-employees {--dry-run} {--apply}`.

---

## Schedule strategy (confirmed)

- One Schedule module in this app for all departments.
- NDOS (Nursing Division Online Scheduling; connection/`reference projects/schedulev2`) is reference-only.
- Department **schedule profiles** (`uses_units`, `uses_floaters`, `uses_on_call`, `uses_swaps`, `uses_census`) gate nursing-style features via `/schedule/department-profile` and Schedule nav.
- **CNO / Nursing** (`config('schedule.cno_division_id')`, default HRIS division_id **3** / Nursing Service): nursing flags on by default; other divisions stay simple and may enable `uses_units` alone for multi-area (Areas UI).
- Optional **schedule units** + handled-unit scheduler scope (`schedule_user_units` by `emp_id`) when `uses_units` is on.
- **Pattern fill** on the monthly dashboard: preview then apply template/rotation patterns to selected employees or a date range (locked months blocked).
- **PDF / email distribution** extends Print Settings (download always; email when `MAIL_*` is configured). Does not replace HTML Print / Export.
- Never bypass approve → lock → DTR sync.

---

## API auth plan

| Consumer | Auth |
|----------|------|
| Browser / Livewire | Session (`web` guard) |
| First-party SPA / mobile (future) | Laravel Sanctum tokens / stateful session |
| Biometric clocks / batch sync | **Device API keys** (`API_DEVICE_KEYS`) via `X-API-Key` or `Authorization: Bearer` |
| External payroll / schedule consumers | Session/Sanctum when `API_REQUIRE_AUTH=true`, or device keys |

### Phase 6 enforcement (implemented)

- Middleware `api.device` — required on punch/sync endpoints; rejects missing/invalid keys.
- Middleware `api.access` — when `API_REQUIRE_AUTH=true` (default), all `/api/*` except `auth/login` need a device key **or** authenticated web/Sanctum user. Sanctum `statefulApi()` enables same-origin session cookies.
- Compatible device routes:
  - `POST /api/dtr/new` and legacy-compatible `POST /dtr/new` (CSRF exempt; still requires device key)
  - `POST /api/dtr/client/sync`
- Schedule read APIs (Phase 5b; same `api.access` auth):
  - `GET /api/schedule/week?from=&to=&department_id=` (optional `unit_id`, `emp_id`, `statuses[]`)
  - `GET /api/schedule/attendance?from=&to=&…` — **approved/locked schedule presence only** (not biometric DTR)
- Writes go to legacy `hris.tbl_employee_dtr` (same as Time Punch / DTR Encoding). Preserve `emp_id`.
- **Sub-HRIS replication:** **drop/replace** — do not port `POST api/sub-hris/dtr/sync` / `ReplicateToSubHRIS`. Point satellites at this app’s secured APIs instead.

Env: see `.env.example` (`API_DEVICE_KEYS`, `API_REQUIRE_AUTH`, `SANCTUM_STATEFUL_DOMAINS`, schedule mail notes).

---

## Payslip source vs legacy consume (Phase 4 / 8)

**Decision (2026-08-11, finalized Phase 8):**

| Surface | Stance |
|---------|--------|
| Employee **My Payslip** (`/self-service/payslip`) | **Local only** — reads `payroll_batch_records.snapshot_json` (+ batch period/type) produced by this app’s payroll finalize. |
| Admin payslip view | Same snapshots via **Payroll History** (`/payroll/history`) + print route `/payroll/history/payslip/{recordId}/print`. |
| Legacy `POST payroll/consume` | Lives only in legacy HRIS (`reference projects/hris`). **Keep there** for any external/legacy consumers still posting into legacy payslip tables. **Do not port** into this repo unless a live external dependency is proven and cannot read shared DB / call a new authenticated API. |
| This app’s `/api/payslips` | Operational list over local payroll lines — not a consume ingest. |

No consume ingest endpoint exists in payroll-api today. Employee UI must never depend on legacy consume.

---

## Legacy `user_level` / `pims_role` mapping (implemented)

Source of truth: `App\Support\Rbac\LegacyHrisRoleMapper` + `php artisan rbac:backfill-legacy-accounts`.

| Legacy `user_level` | Target Spatie roles |
|---------------------|---------------------|
| 1 | `super-admin` |
| 2 | `admin` |
| 3 | `scheduler` + `schedule-approver` |
| 4 | `scheduler` |
| 5 | `employee` |

| Legacy `pims_role` | Target Spatie roles |
|--------------------|---------------------|
| 1 | `super-admin` |
| 2 | `admin` |
| 3 | `payroll-approver` |
| 4 | `payroll-processor` + `timekeeper` |
| 5 | `timekeeper` |

Combined roles are unioned (except `super-admin` / `admin`, which win alone). Unknown values fall back to `employee`. Accounts with **no** Spatie roles still need the backfill (or an explicit assign) — `RBACSeeder` alone only seeds role definitions + a few designated users.

### Home launcher SOON badge

`SOON` means the **module is unfinished** (Training, Performance). Built modules (Employees, Leave, Schedule, Payroll, Timekeeping, Settings) are **hidden** when the user lacks permission — they must not use the coming-soon route as a stand-in for “no access”.

Exact dept gates from legacy Training (hard-coded dept ids) are **not** copied; replace with Spatie permissions + schedule handled-units later.

---

## Feature flags

| Env | Purpose |
|-----|---------|
| `HRIS_USE_V2` | When `true`, Employees module reads `hris_v2` (Phase 3 Leave still uses legacy `hris` leave tables) |
| `DB_*_HRIS_V2` | Connection for new schema |
| `HRIS_CUTOVER_EMPLOYEES` / `_LEAVE` / `_SELF_SERVICE` / `_SCHEDULE` / `_TRAINING` / `_PERFORMANCE` | Phase 9: this app is canonical for that module (default `false`; status page + optional shell banner) |
| `HRIS_FREEZE_LEGACY_WRITES` | Phase 9: block legacy employee-master / PDS writes via Employees services (default `false`; does not freeze leave/TARF/IPCR/DTR) |

Default: `HRIS_USE_V2=true` after ETL validation (set `false` only for dual-read rollback). Cutover / freeze flags stay `false` until dual-run signoff — see [`docs/hris-cutover.md`](hris-cutover.md).

---

## Phase 9 cutover

Ops dual-run and freeze steps live in **[`docs/hris-cutover.md`](hris-cutover.md)**. Admin status: `/admin/cutover`.

`reference projects/hris` and `reference projects/schedulev2` remain **historical / read-only** for comparison (`AGENTS.md`). Do not modify them during cutover; retire production **usage** via flags + process, not by deleting DB connections.

---

## Phase 3 leave data note

Leave filing/approvals/credits write **legacy** `hris` tables (`tbl_employee_leave`, `tbl_leave_log`, VL/SL on `tbl_employee`) so schedule (`ScheduleAvailabilityService`) and payroll (MRA / generation) keep reading the same `emp_id`-keyed rows. Normalized leave on `hris_v2` is deferred until a leave ETL is ready.
