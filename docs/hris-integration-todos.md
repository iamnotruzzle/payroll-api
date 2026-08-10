# HRIS Integration Todo List

Track legacy HRIS (`reference projects/hris`) integration into this app (payroll-api → HRIS & Payroll).

**Rules**

- Treat `reference projects/` as **read-only** reference (see `AGENTS.md`).
- Implement all work in this repository.
- Update checkboxes here when items complete so progress survives across chats and tools.

**Recommended build order:** Phase 0 → Employees → Leave → Self-service → biometric APIs → Training / IPCR → cutover.

---

## Context (gap summary)

| Domain | Legacy HRIS | This project today |
|--------|-------------|--------------------|
| Employees / PDS | Full CRUD + print | Mostly read API |
| Leave | Apply / approve / credits / reports | Consume only |
| Training (TARF) | Full LDI workflow | Profile training read only |
| IPCR / OPCR | Full module | Absent |
| Self-service | Profile, leave, DTR, payslip | Time punch only |
| Biometrics sync | `dtr/new`, client sync, sub-HRIS | Punch UI only |
| Scheduling | Limited schedules | Full module |
| Timekeeping ops | Manual DTR + reports | Encoding, corrections, MRA, holidays |
| Payroll | Consume API + payslip viewer | Generation engine (Medicare still thin) |
| Auth / RBAC | `user_level` 1–4 + dept gates | Spatie roles/permissions |

**Data stance:** Keep shared **HRIS MySQL** as people/leave/raw DTR source of truth unless explicitly decided otherwise. Own scheduling on `payroll_scheduler` and payroll ops on `payroll`.

---

## Phase 0 — Foundation

- [ ] Document legacy→new module map, data ownership (HRIS DB vs payroll/scheduler), and cutover order; freeze what stays read-only vs becomes owned UI
- [ ] Expand Spatie permissions/roles to cover HRIS domains (employee, leave, training, IPCR, reports, self-service) and map legacy `user_level` 1–4 + dept gates
- [ ] Restructure app shell nav for HRIS & Payroll (Employees, Leave, Training, Performance, Self-Service) alongside existing Schedule / Timekeeping / Payroll

---

## Employees

- [ ] Build Livewire employee directory (search / dept / active) replacing `AdminController@viewAdminMenu`
- [ ] Port PDS profile CRUD (basic, address, family, education, eligibility, work exp, L&D, other info, refs, gov IDs) with Spatie-gated edit
- [ ] Port dependents management, activate/deactivate (resignation flows), and file uploads
- [ ] Port PDS view/print (DomPDF/FPDF equivalent) for admin and self-service

---

## Leave

- [ ] Port leave apply / edit / cancel / print + leave card UI writing `tbl_employee_leave` / leave logs
- [ ] Port approval queue (approve / disapprove / request / log) with role-based approvers replacing `user_level` checks
- [ ] Port leave credits maintenance, undertime, and queued `LeaveCreditsUpdater` jobs
- [ ] Port leave reports (monthly / type APIs + UI) into References / Reports

---

## Attendance / DTR bridge

- [ ] Port biometric punch API (`POST dtr/new`) and client/batch sync (`api/dtr/client/sync`) into this app with auth
- [ ] Evaluate / port sub-HRIS DTR replication (`mysql_sub_hris` + `ReplicateToSubHRIS`) or replace with a modern sync strategy
- [ ] Surface fingerprint registration status and manual DTR admin parity where not covered by DTR Encoding

---

## Training

- [ ] Port TARF / LDI module (requests, PETU/OMCC approvals, calendar, employee list, reschedule, invites)
- [ ] Port TARF PDFs (assessment, re-entry, acknowledgement) and uploaded training reports

---

## Performance

- [ ] Port IPCR / OPCR / MFO module (periods, targets, ratings, calibration, print)

---

## Self-service

- [ ] Employee hub — My Profile (read/edit), My Leave, My DTR, My IPCR, My Payslip (beyond Time Punch)

---

## Payroll bridge

- [ ] Port employee payslip index / print from `tbl_employee_payrolls` / payroll consume snapshots
- [ ] Decide fate of legacy `POST payroll/consume` — keep for external systems or reverse so this app is source of payslips
- [ ] Complete Medicare generation beyond `placeholderRows` for full payroll module parity

---

## Reports & admin

- [ ] Port legacy report hub pieces still needed (DTR bulk print, statistics/charts, appointment/PSS/exam if still used)
- [ ] Ensure User Accounts covers legacy `AccountController` flows (create / reset / delete) without regressing Spatie assignments

---

## Platform

- [ ] Lock down `/api/*` with Sanctum (or API keys) matching legacy intent of `APIRequestChecker`; secure biometric endpoints

---

## Cutover

- [ ] Pilot one department on new HRIS modules while reading the same HRIS DB; dual-run leave/DTR against legacy
- [ ] Feature-flag retire legacy HRIS routes once Employees + Leave + Self-service reach parity; archive reference project usage

---

## Progress log

| Date | Notes |
|------|-------|
| 2026-08-10 | Initial todo list created from legacy vs payroll-api gap analysis. |
