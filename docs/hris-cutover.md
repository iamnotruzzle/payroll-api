# HRIS & Schedule Cutover Runbook (Phase 9)

**Audience:** ops / HRIS admins executing dual-run and go-live.  
**Tooling status page:** `/admin/cutover` (permission `admin.cutover.view`; `admin` + `super-admin` roles).  
**Do not** mark live pilots complete in `docs/hris-integration-todos.md` without dual-run evidence.

---

## Reference projects are historical only

`reference projects/hris` and `reference projects/schedulev2` are **read-only comparison codebases**. Do not modify, deploy, or commit under `reference projects/` (see `AGENTS.md`). Production cutover means retiring **usage** of legacy HRIS UI and NDOS (Nursing Division Online Scheduling; formerly referred to as schedulev2) apps — not deleting DB connections or this repo’s dual-read paths until freeze is signed off.

---

## Env flags (defaults OFF)

| Env | Default | Purpose |
|-----|---------|---------|
| `HRIS_USE_V2` | (set after ETL) | Employees UI reads/writes `hris_v2` |
| `HRIS_CUTOVER_EMPLOYEES` | `false` | This app is canonical for Employees |
| `HRIS_CUTOVER_LEAVE` | `false` | Canonical for Leave (still on legacy leave tables) |
| `HRIS_CUTOVER_SELF_SERVICE` | `false` | Canonical for Self-service hub |
| `HRIS_CUTOVER_SCHEDULE` | `false` | Canonical for Schedule; NDOS archived |
| `HRIS_CUTOVER_TRAINING` | `false` | Canonical for TARF / Training |
| `HRIS_CUTOVER_PERFORMANCE` | `false` | Canonical for IPCR |
| `HRIS_FREEZE_LEGACY_WRITES` | `false` | Block legacy **employee master / PDS** writes via Employees services |
| `API_REQUIRE_AUTH` | `true` | Lock down `/api/*` (clocks use device keys) |

**Freeze scope:** only `tbl_employee` core + PDS section tables through `EmployeeProfileWriteService` / `EmployeePdsSectionService`. **Does not** freeze leave requests/credits, TARF, IPCR, or DTR.

After flipping env: `php artisan config:clear` (or restart php-fpm / `php artisan config:cache` in prod).

---

## Ordered cutover steps

### A. Dual-run — Employees + Leave + Self-service

1. Confirm `HRIS_USE_V2=true` and employee ETL validated (`hris:migrate-employees`).
2. Pick **one pilot department**. Instruct that dept to use **this app only** for directory/PDS, leave filing/approvals, and self-service (profile / leave / DTR / payslip).
3. Keep legacy HRIS UI available for rollback; **do not** flip cutover flags yet.
4. Daily spot-check for 1–2 weeks:
   - Employee edits appear in this app (`hris_v2`).
   - Leave rows still on legacy `tbl_employee_leave` / credits (expected).
   - Payslips from local snapshots; Time Punch / DTR as today.
5. Signoff → set `HRIS_CUTOVER_EMPLOYEES=true`, `HRIS_CUTOVER_LEAVE=true`, `HRIS_CUTOVER_SELF_SERVICE=true`. ERP shell shows the cutover banner for those modules.
6. Communicate: stop day-to-day employee/leave/self-service work in legacy HRIS UI for that scope (org-wide when ready).

### B. Schedule — CNO sync → lock → DTR pilot

1. Configure `DB_*_SCHEDULEV2` (read-only). Confirm status page shows connection reachable.
2. `php artisan schedule:provision-department-profiles --apply` (or open Department Profile once for CNO depts).
3. Dry-run then apply: `php artisan schedule:sync-schedulev2 --dry-run --division=3` then `--apply` for a target month (or UI: **Schedule → Import / Pull**).
4. In this app for the pilot ward/dept: draft → review → approve → **lock**; confirm `ScheduleLockService` wrote payroll DTR encodings.
5. Spot-check DTR Encoding / My Schedule vs NDOS.
6. Repeat for one **non-CNO** simple-profile department.
7. Only with evidence: set `HRIS_CUTOVER_SCHEDULE=true` and **archive NDOS usage** (stop dual-maintaining; keep DB connection `schedulev2` for historical pull if needed).

### C. Point clocks at Phase 6 APIs

1. Set `API_DEVICE_KEYS` and keep `API_REQUIRE_AUTH=true`.
2. Point biometric clocks / offline sync clients to this app:
   - `POST /api/dtr/new` (and legacy-compatible `POST /dtr/new`)
   - `POST /api/dtr/client/sync`
3. Verify with sample curls in `docs/hris-integration-todos.md` Phase 6.
4. Retire legacy HRIS punch endpoints for those devices.

### D. Training + Performance (optional same wave)

1. Dual-run TARF / IPCR on this app (legacy `hris` tables intentionally).
2. After signoff: `HRIS_CUTOVER_TRAINING=true`, `HRIS_CUTOVER_PERFORMANCE=true`.

### E. Schema B — freeze legacy employee-master writes

1. Preconditions: `HRIS_USE_V2=true`, Employees cutover flag on, no remaining dependence on legacy PDS UI.
2. Set `HRIS_FREEZE_LEGACY_WRITES=true`.
3. Verify: attempting Employees save while forced onto legacy path fails with freeze message; leave/TARF/IPCR still writable.
4. Decommission dual-read only when ops agrees (set and keep `HRIS_USE_V2=true`; do not delete `hris` connection — leave/DTR/training still need it).

### F. Go-live signoff checklist

- [ ] Pilot dept dual-run evidence attached (dates, dept id, owners).
- [ ] Cutover flags flipped for completed modules; status page reviewed.
- [ ] Nursing (+ optional non-CNO) schedule pilot: lock → DTR verified; NDOS usage archived.
- [ ] Clocks on Phase 6 APIs with device keys.
- [ ] `HRIS_FREEZE_LEGACY_WRITES` on only after Employees on v2.
- [ ] Manuals / staff notice updated; reference projects treated as historical.
- [ ] Rollback plan: turn flags off / freeze off; restore legacy UI access if needed (connections remain).

---

## Where to look in the app

| Item | Location |
|------|----------|
| Cutover status | `/admin/cutover` |
| NDOS pull | `/schedule/schedulev2-sync` |
| Department profiles | `/schedule/department-profile` |
| Foundation decisions | `docs/hris-foundation.md` |
| Phase checklist | `docs/hris-integration-todos.md` |
