# HRIS cutover & dual-run pilots

**Schema stance (2026-08-12):** People master / PDS / leave / TARF / IPCR stay on legacy MySQL `hris` (`tbl_*`). Parallel `hris_v2` + ETL + cutover flags were removed. Optional in-place schema ideas: [`docs/hris-schema-enhancements.md`](hris-schema-enhancements.md).

`reference projects/hris` and `reference projects/schedulev2` remain **read-only** comparison codebases (`AGENTS.md`).

---

## Dual-run goal (Phase 9)

Operate **this app** as the day-to-day UI for a pilot department while legacy HRIS / NDOS stay available for comparison only. Shared `hris` data means both UIs see the same people/leave/TARF/IPCR rows.

### Pre-flight

```bash
php artisan hris:pilot-readiness
# Optional department spot-check:
php artisan hris:pilot-readiness --department=3
php artisan db:seed --class=RBACSeeder
```

Confirm routes exist: Employees (incl. **Add employee**), Leave, Self-service profile/leave, Schedule, Training, Performance.

### Pilot A — Employees + Leave + Self-service

1. Pick one department (non-nursing is fine for people ops).
2. In this app: **Employees → Add employee** for a test hire (or use an existing active employee).
3. Confirm auto account (username = emp_id, temporary password flash, `login_attempt = 0`, Employee role).
4. Log in as that account → forced **My Profile** update → save → gate clears (`login_attempt = 1`).
5. Edit PDS sections / documents on the employee profile; print CS Form 212.
6. File leave (self-service or Leave → Requests) with an explicit date mode (pick / weekdays / calendar); approve in Leave → Approvals; check Leave Card / Credits. Confirm `remarks` is a date CSV and credits/LWOP (`days_wopay` + leave log action 7) match expectations.
7. Spot-check the same `emp_id` in legacy HRIS UI (read-only comparison): master fields, leave balances/logs should match.

### Pilot B — Schedule (Nursing / NDOS)

1. Follow the Phase 5c checklist in [`docs/hris-integration-todos.md`](hris-integration-todos.md) (provision profiles, optional NDOS sync, draft → approve → **lock → DTR**).
2. Compare My Schedule / locked month DTR Encoding against NDOS for the pilot ward.
3. Only then stop dual-maintaining that ward in NDOS.

### Exit criteria

- Pilot department HR uses **this app** for employee create/edit, leave filing/approval, and self-service without opening legacy HRIS for those tasks.
- Nursing pilot ward uses this Schedule module through lock→DTR with no NDOS writes for that month.
- Device punches / client sync pointed at this app’s secured APIs (`API_DEVICE_KEYS`) when clocks cut over.

### Intentionally not ported

- Subsidiary HRIS replication
- Appointment / bulletin / committee / GKE / PSS / workforce charts (dormant in legacy; revisit only if HR requests)
