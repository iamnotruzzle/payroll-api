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
- `schedulev2` is reference-only.
- Department **schedule profiles** (`uses_units`, `uses_floaters`, `uses_on_call`, `uses_swaps`) gate nursing-style features.
- Never bypass approve → lock → DTR sync.

---

## API auth plan

| Consumer | Auth |
|----------|------|
| Browser / Livewire | Session (`web` guard) |
| First-party SPA / mobile (future) | Laravel Sanctum tokens |
| Biometric clocks / batch sync | API keys (table + middleware), not open routes |
| External payroll consumers | Sanctum or signed API keys per client |

Phase 6 implements enforcement; Phase 1 locks the standard so new endpoints do not ship open.

---

## Legacy `user_level` mapping (target)

| Legacy level | Meaning | Target roles (initial) |
|--------------|---------|------------------------|
| 1 | Administrator | `admin` (+ domain roles as needed) |
| 2 | Section Administrator | `admin` or dept-scoped manager roles (future) |
| 3 | Point Person | limited `employees.view` / leave approve (future) |
| 4 | User | `employee` + `self-service.*` |

Exact dept gates from legacy Training (hard-coded dept ids) are **not** copied; replace with Spatie permissions + schedule handled-units later.

---

## Feature flags

| Env | Purpose |
|-----|---------|
| `HRIS_USE_V2` | When `true`, Employees module reads `hris_v2` (Phase 3 Leave still uses legacy `hris` leave tables) |
| `DB_*_HRIS_V2` | Connection for new schema |

Default: `HRIS_USE_V2=true` after ETL validation (set `false` only for dual-read rollback).

---

## Phase 3 leave data note

Leave filing/approvals/credits write **legacy** `hris` tables (`tbl_employee_leave`, `tbl_leave_log`, VL/SL on `tbl_employee`) so schedule (`ScheduleAvailabilityService`) and payroll (MRA / generation) keep reading the same `emp_id`-keyed rows. Normalized leave on `hris_v2` is deferred until a leave ETL is ready.
