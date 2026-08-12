# HRIS cutover notes (retired)

**Status:** Retired 2026-08-12.

Schema strategy **B** (`hris_v2` + employee ETL + `HRIS_USE_V2` / cutover / freeze flags) was reverted. People master, PDS, leave, TARF, IPCR, and DTR stay on legacy MySQL `hris`. The `/admin/cutover` status page, cutover env flags, and `hris:migrate-employees` command were removed.

For ops going forward:

- Use this app’s modules directly against `hris` (`tbl_*`).
- Optional in-place schema work is tracked in [`docs/hris-schema-enhancements.md`](hris-schema-enhancements.md).
- Schedule / NDOS import tooling (`schedule:sync-schedulev2`, Department Profiles) is unchanged and unrelated to `hris_v2`.
- Device API hardening remains as documented in [`docs/hris-foundation.md`](hris-foundation.md).

`reference projects/hris` and `reference projects/schedulev2` stay read-only comparison codebases (`AGENTS.md`).
