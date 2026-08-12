# HRIS Foundation

**Decision updated:** 2026-08-12  
**Schema strategy:** **A** — UI and services use legacy MySQL `hris` (`tbl_*`). The parallel `hris_v2` database and ETL path were retired. Future normalization stays on the same `hris` DB — see [`docs/hris-schema-enhancements.md`](hris-schema-enhancements.md).

---

## Ownership

| Domain | System of record | Connection / store |
|--------|------------------|--------------------|
| People master, PDS, leave, TARF, IPCR, DTR punches | Legacy HRIS DB | `hris` |
| Employee documents (metadata) | This app → `employee_documents` | `hris` |
| Spatie roles/permissions | This app | `hris` |
| Scheduling | This app | `payroll_scheduler` |
| Payroll ops / DTR encodings | This app | `payroll` |
| Reference projects | Read-only | `reference projects/*` |

---

## ID policy

| Key | Rule |
|-----|------|
| `tbl_employee.emp_id` | **Public business key** for people, payroll, schedule, accounts |
| Payroll / schedule / accounts | Continue to key off `emp_id` (no mass rewrite of historical rows) |

Do **not** invent new employee numbers casually. New hires follow the agreed numbering rules already used on legacy HRIS.

---

## People / PDS tables (legacy)

Core people data remains on:

- `tbl_employee` — identity, org FKs, personal, gov IDs, leave balances, CS Form flags
- `tbl_employee_dependents`, `tbl_employee_education`, `tbl_employee_eligibility`, `tbl_employee_work_exp`, `tbl_employee_training`, `tbl_employee_volwork`, `tbl_employee_otherinfo`, `tbl_employee_ref`
- Org lookups: `tbl_department`, `tbl_division`, `tbl_position`, `tbl_employmentstat`, `tbl_salary_grade`
- Documents: `employee_documents` (typed metadata + storage path; keyed by `emp_id`)

Enhancement backlog (in-place on `hris`, not a second DB): [`docs/hris-schema-enhancements.md`](hris-schema-enhancements.md).

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
| `API_REQUIRE_AUTH` | Lock down `/api/*` (clocks use device keys) |
| `API_DEVICE_KEYS` | Comma-separated device API keys for biometric / sync clients |

`HRIS_USE_V2`, `DB_*_HRIS_V2`, `HRIS_CUTOVER_*`, and `HRIS_FREEZE_LEGACY_WRITES` were **removed** (2026-08-12). See [`docs/hris-cutover.md`](hris-cutover.md) for retired cutover notes.

---

## Leave data note

Leave filing/approvals/credits write **legacy** `hris` tables (`tbl_employee_leave`, `tbl_leave_log`, VL/SL on `tbl_employee`) so schedule (`ScheduleAvailabilityService`) and payroll (MRA / generation) keep reading the same `emp_id`-keyed rows.

`reference projects/hris` and `reference projects/schedulev2` remain **historical / read-only** for comparison (`AGENTS.md`).
