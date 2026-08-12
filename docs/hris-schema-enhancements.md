# HRIS schema enhancement backlog

**Stance:** Stay on legacy MySQL `hris`. Do **not** recreate a parallel `hris_v2` database. Improve structure in-place when prioritized.

Ideas below come from pain points in `tbl_*` and from what the retired v2 design was trying to fix. None of these (except documents) are implemented unless marked done.

---

## Done

| Item | Notes |
|------|-------|
| Typed `employee_documents` on `hris` | Metadata table keyed by `emp_id` (`disk`, `path`, `mime_type`, category). Livewire upload/download uses this instead of `hris_v2.employee_documents`. |

---

## High value

1. **Employment history** — new table keyed by `emp_id` with `effective_from` / `effective_to` / dept / position / status / step; keep current columns on `tbl_employee` as a cache of the current assignment.
2. **Normalize Y/N flags** — migrate `is_active`, CS Form questions, `is_government`, section-head, etc. from `'Y'/'N'` strings to `TINYINT(1)` / boolean consistently.
3. **Sanitize dates** — ban `0000-00-00`; backfill nulls; enforce STRICT + app `SafeDate` at write time.
4. **Widen VARCHAR/TEXT** — training name/venue/sponsor, addresses, name affixes, license numbers where production data truncates.
5. **Decode coded PDS columns** — other-info type, education level, civil status, work_status → lookups or stable labels; stop ambiguous `0/1/2` storage.
6. **Missing PDS fields** — voluntary org address; character-reference email.
7. **Leave credit ledger** — move VL/SL off `tbl_employee` columns into dated ledger rows; keep running balance as derived/cache for payroll/schedule.

## Medium value

8. **FKs / indexes** — real `emp_id` FKs on section tables; indexes for directory filters (`department_id`, `empstat_id`, `is_active`).
9. **Sensitive columns off master** — move `fingerprint_*` / password-like fields off `tbl_employee`.
10. **Auth cleanup** — retire `user_level` / `pims_role` once Spatie is sole authority (accounts already on `hris`).
11. **External employee rule** — one source of truth (`is_external` **or** external division), not both heuristics.
12. **TARF / IPCR hygiene** — timestamps on `tbl_training_requests`; typed upload FKs instead of `tbl_uploaded_files.tag` polymorphism; document approval column names.

## Optional later

13. **Split `tbl_employee` in-place** — personal / government / contact tables in the same `hris` DB (views or app-layer grouping first if a full split is too risky).

---

## Explicitly out of scope

- Recreating connection `hris_v2` or ETL `hris:migrate-employees`
- Dual-read / freeze-legacy-write feature flags for people master
