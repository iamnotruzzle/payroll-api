# Reference HRIS: Complete Feature-by-Feature Audit

## 1. Scope and method

This document is a static, read-only audit of `reference projects/hris`. It inventories implemented, partial, dormant, and infrastructure features by tracing routes, controllers, models, migrations, jobs, views, configuration, and role/department checks. The reference project was not modified or executed against its configured databases.

Status terms:

- **Active**: routed and supported by a controller and UI or external endpoint.
- **Partial**: implemented but incomplete, inconsistently authorized, or dependent on missing operational setup.
- **Dormant**: code/view exists but navigation or route is commented out, absent, or otherwise disconnected.
- **Utility**: supporting behavior rather than a user-facing module.

## 2. Application architecture

- Laravel 8 / PHP 7.3–8 application with server-rendered Blade and jQuery/AJAX.
- Eloquent models coexist with extensive query-builder and raw SQL usage.
- FPDF, DOMPDF, FPDM, and a custom header class produce printable forms and reports.
- Queue jobs handle DTR writes, leave-credit processing, payroll ingestion, email, and replication.
- The default database connection is `mysql17`; additional connections target the main HRIS, DTR data, and a subsidiary HRIS database.
- Authentication is Laravel session authentication. Four legacy account levels exist: Administrator (1), Section Administrator (2), Point Person (3), and User (4).
- Most web routes share only the `auth` middleware. API routes do not declare authentication middleware.

## 3. Feature catalog

### 3.1 Authentication and account lifecycle — Active, with security gaps

Capabilities:

- Username/password login using `username` rather than email.
- Logout, remember-token support, password reset scaffolding, and login-attempt tracking.
- First-login/annual-update gate: accounts with `login_attempt == 0` are prompted to update employee information before normal use.
- Account directory showing employee, username, role, and account status.
- Add, edit, reset-to-default, and delete employee accounts.
- Administrator can assign levels 1–4; non-administrator account managers are offered levels 3–4.
- New employees automatically receive a Level 4 account with employee number as username and a default password.
- Utility API can create default accounts for employees without accounts.

Role intent:

- Level 1: organization-wide account administration.
- Level 3: visible departmental account coordination.
- Level 2: controller supports departmental account administration, although its menu hides the module.
- Level 4: no intended account administration.

Findings:

- Account mutation routes do not independently enforce role or departmental scope.
- Reset and deletion use GET endpoints.
- Default credentials are predictable.
- The API default-account operation appears unauthenticated.
- The custom `isAdmin` middleware admits all four levels and therefore provides no admin boundary.

### 3.2 Employee directory and employee master — Active

Capabilities:

- Active employee directory with employee number, name, birthdate, position, department, employment status, and fingerprint-registration indicator.
- Search by employee number/name and, for administrators, department.
- Add a complete employee record, validate employee number uniqueness, and select province/town reference data.
- View employees within organizational scope:
  - Level 1: all active employees.
  - Levels 2 and 3: own department.
  - Level 2 in department 42: all departments in division 3.
  - Level 4: self-service profile rather than a directory.
- Update core employment and identity information.
- Upload an employee image to the public images directory.
- Activate or separate an employee; separation captures type and date.
- Track fingerprint registration through two fingerprint fields.
- Replicate newly added/updated employee data to the subsidiary HRIS via a queue job.

Data covered:

- Employee identity and name
- Sex, birth details, civil status, citizenship, religion
- Contact and address information
- Department, division through department, position, plantilla/employment status
- Hire and separation information
- Government identifiers
- Leave balances
- Fingerprint markers
- PDS legal declarations and special-sector flags

Findings:

- Several update endpoints accept an employee ID from request/session without consistent actor-scope enforcement.
- Raw SQL search construction creates injection risk in legacy account and employee searches.
- Employee activation/deactivation uses GET endpoints and UI-only role checks.

### 3.3 Personal Data Sheet (PDS) — Active

Capabilities:

- Basic/personal information.
- Family information and dependents.
- Educational background.
- Civil-service eligibility.
- Work experience.
- Voluntary work.
- Learning and development history.
- Other information, skills, recognitions, and memberships.
- Legal/background questions.
- Character references.
- Government-issued identification.
- PDS preview/printing with normalization of blank values.
- Annual employee-driven data review/update workflow.
- AJAX save and delete operations for repeatable PDS sections.

Findings:

- Both older form-specific controller methods and newer generic AJAX methods coexist, increasing behavioral drift.
- Delete/save AJAX endpoints are authenticated but not comprehensively object-authorized.
- Uploaded photo validation and public storage design should be reviewed before reuse.

### 3.4 Leave application and employee leave self-service — Active

Capabilities:

- Apply for leave for an eligible employee.
- Select displayed leave types; administrators see additional internal types.
- Calculate date ranges, working/pay days, leave with pay and without pay.
- Validate leave balances and type-specific eligibility.
- Track special privilege leave and forced/mandatory leave counts.
- Borrow vacation leave for qualifying sick-leave cases.
- Record undertime.
- Edit, update, cancel, and print leave applications.
- View personal leave applications and leave-card history.
- Administrator can open another employee's leave card.
- Maintain transactional leave logs with before/after vacation and sick balances.

Workflow states observed:

- Pending, approved, disapproved, cancelled, and system-generated credit entries are represented through numeric statuses/actions.
- Some applications require remarks or medical/supporting information before appearing in approval queues.

Findings:

- The legacy leave workflow mixes balance mutation, application state, and ledger creation across `MenuController`, `AdminController`, `LeaveController`, and jobs.
- Approval/disapproval endpoints do not re-check organizational scope of the target leave.
- Some state-changing operations use GET.
- There are duplicate/legacy leave-store paths; one is routed, another remains in the controller.

### 3.5 Leave approval and leave administration — Active

Capabilities:

- Pending leave request queue.
- Organization-wide queue for Level 1; department-filtered queue for other administrative users.
- Approve or disapprove applications.
- Restore deducted credits when disapproving, including borrowed vacation leave reconciliation.
- Browse leave transaction logs.
- Search leave requests and logs by employee/month.
- Manually update employee vacation and sick leave balances.
- Generate department or organization leave reports.

Role intent:

- Level 1: organization-wide leave administrator.
- Level 2: departmental approving officer.
- Other roles can reach routes unless separately prevented by UI flow.

### 3.6 Automated leave-credit accrual — Partial/operational utility

Capabilities:

- Determine employee eligibility from active status, position, employment status, and hire date.
- Create monthly system leave entries.
- Credit standard employees at 1.25 and a special employment class at 0.625.
- Calculate initial prorated credit from days remaining in the hire month.
- Catch up missed monthly accrual periods through queued jobs.
- Adjust accrual for leave without pay.
- Deduct unused forced leave.
- Repair historical leave logs through a maintenance endpoint.

Operational status:

- No scheduler is configured in `Console\Kernel`.
- Accrual is dispatched from application flows rather than a declared scheduled command.
- A public-looking test endpoint and a non-authenticated `leave/fix/leave-logs` web route expose maintenance behavior.

### 3.7 Daily Time Record ingestion — Active, externally integrated

Capabilities:

- Receive a new biometric/DTR punch.
- Classify punches into AM/PM time-in/time-out fields.
- Handle overnight duty through `timeout_nextday`.
- Avoid duplicate writes and adjust an existing field when later punches arrive.
- Queue writes separately for inbound and outbound punch processing.
- Sync batches from a DTR client.
- Sync DTR records to a subsidiary HRIS.
- Replicate individual DTR changes through a queue.
- Manually view, add, update, and delete employee DTR entries in the HRIS UI.

Findings:

- `dtr/new` is outside the authenticated web group.
- DTR sync APIs declare no authentication or device-key check.
- `APIRequestChecker` exists but is not registered/applied in the shown route configuration.
- Manual DTR deletion uses GET.
- The queue worker is operationally required when the queue driver is asynchronous.

### 3.8 DTR printing and Monthly Report of Attendance — Active

Capabilities:

- Generate Civil Service Form 48-style DTR output by department, employee type, month, and year.
- Support regular employees, contract-of-service workers, and interns.
- Generate Monthly Report of Attendance (MRA).
- Select signatories using department head, special-department, division-chief, and regional-director rules.
- Paginate/batch department output.
- Queueable DTR print wrapper exists.

Scope intent:

- Level 1 can select all departments.
- Department 42 administrative users can receive division 3 scope for the main DTR report.
- Other non-users receive their own department in the UI.

Finding: report-generation URLs accept department parameters without consistently revalidating server-side scope.

### 3.9 Employee scheduling — Partial/legacy

Capabilities:

- View department employee schedules and schedule types.
- Add a dated schedule type for an employee.
- Display current-month and future schedules.
- A delete method exists.

Status:

- Schedule navigation is commented out.
- The add and view routes remain active for authenticated users.
- The delete route is commented/absent.
- Scheduling is department-centric and substantially less capable than the separate ScheduleV2 reference project.

### 3.10 Training Authorization and Request Form (TARF) — Active

Capabilities:

- Create training requests with training name, venue, dates, type/mode, objectives, costs, participants, and supporting details.
- Prevent or warn about conflicting/duplicate training requests.
- Browse and search requests.
- View a request with participant and approval details.
- PETU approve/disapprove.
- OMCC/MCC approve/disapprove.
- Reschedule training.
- Cancel or progress requests according to status.
- Invite employees and record invitation responses.
- Upload post-training reports and supporting files.
- Verify uploaded reports/files.
- Calendar UI plus JSON calendar feed.
- Employee list and JSON employee-search feed.
- Generate training reports.
- Email training notifications through a queued mailable.

Printed forms:

- TARF
- Assessment
- Acknowledgement
- Re-entry plan
- TARF/training report

Organizational rules:

- Department 88 acts as PETU/training administration.
- Department 64 acts as OMCC/MCC approval scope.
- Level 4 is hidden from several approval actions.
- Employee `000856` has a hard-coded full training-menu exception.

Findings:

- Approval routes do not have dedicated authorization middleware.
- Department and role logic is duplicated between controllers and Blade.
- Calendar, employee-list, notification, and other APIs appear public.
- Uploaded-file access and validation require hardening before migration.

### 3.11 Employee learning and development history — Active

Separate from TARF workflow, the PDS employee-training module supports:

- Listing historical seminars/training attended.
- Adding and editing entries.
- Capturing inclusive dates, hours, training type, and sponsor/conductor.
- Displaying invitations alongside employee profile history.

This creates two related data domains: employee historical training records and TARF operational requests.

### 3.12 IPCR performance management — Active, weakly authorized

Capabilities:

- Define IPCR periods.
- Define MFO types and MFOs.
- Create department/period MFO sets.
- Add, retrieve, soft-delete, restore, and copy MFOs/targets.
- Assign employee IPCR targets by period and type.
- View employee target sheets.
- Record ratings for strategic, core, and support dimensions.
- Compute averages and descriptive grades.
- Support chief/head-based rating behavior.
- Store calibration sets and calibration values.
- Print employee IPCR forms.
- OPCR and accountable models/tables exist as supporting performance structures.

Findings:

- IPCR routes accept arbitrary employee and record IDs under authentication only.
- AJAX mutations rely on request-supplied employee IDs with little consistent ownership/supervisor enforcement.
- OPCR has models and schema but no routed user interface in this codebase, so it is dormant/supporting.

### 3.13 Payroll ingestion and employee payslips — Active integration

Capabilities:

- Receive generated payroll JSON through an API endpoint.
- Create payroll-generation headers, payroll items/types, and employee payroll rows.
- Process payroll payloads asynchronously through a queue job.
- List an employee's available payslips.
- Render/print a payslip for a payroll date.
- Administrator may print another employee's payslip; other roles are restricted to self in the print controller.

Findings:

- The payroll-consume API does not declare authentication, signature validation, replay protection, or idempotency controls at the route boundary.
- Payslip index authorization should be checked independently; the explicit self/admin check is visible in print, not consistently at every entry point.

### 3.14 Reports and workforce analytics — Active/partial

Capabilities:

- DTR report generation.
- Monthly Report of Attendance.
- Leave reports by type/month/year.
- Leave-report API and AJAX table feed.
- Workforce charts through pie, bar, and line chart builders.
- Chart queries use HR reference and employee data to visualize workforce statistics.
- Report navigation is hidden from Level 4.

Dormant report artifacts:

- Personnel satisfaction survey (`pss`) view/route exists but navigation is commented.
- Some MRA navigation is commented while routes remain active.
- Report print Blade exists alongside direct PDF-generation controllers.

### 3.15 Appointment form generation — Partial/dormant navigation

Capabilities:

- Appointment menu with employee/department/status/position reference lists.
- Retrieve selected employee appointment data.
- Populate a government PDF template using FPDM and generate an appointment form.

Status and dependencies:

- Routes are active under authentication.
- Navigation links are commented out.
- Depends on a bundled PDF template under storage and filesystem write/read behavior.

### 3.16 Bulletin — Partial/dormant navigation

Capabilities:

- List bulletins.
- Create/save a bulletin.
- View bulletin details.
- Administrator-only create button is enforced in the view.

Status:

- Routes are active for all authenticated users.
- Main navigation is commented out.
- Server-side save authorization is not clearly role-enforced.

### 3.17 Committees and designations — Partial/dormant

Capabilities:

- List committee assignments.
- View an employee's committees.
- View a committee set.
- Create a committee assignment.
- Add an employee with a committee role.

Status:

- Routes are active under authentication.
- Navigation links are commented out.
- Mutations do not have dedicated role middleware.

### 3.18 General Knowledge Exam — Active for administrators in navigation

- A report/controller route renders a General Knowledge Exam view.
- Administrator navigation exposes it.
- The route itself is available to any authenticated user.
- No examination engine, scoring, submissions, or persistence was found; this is primarily a printable/display artifact.

### 3.19 Personnel satisfaction survey — Dormant/placeholder

- Controller and view exist for `pss`.
- Route is active under authentication.
- Navigation is commented out.
- No substantial persisted survey workflow was identified.

### 3.20 Reference-data management — Supporting, mostly implicit

Reference tables/models/seeders include:

- Address/barangay, town, province
- Citizenship, civil status, religion
- Division and department
- Position, plantilla, salary grade, employment status
- Eligibility types
- Leave types, leave statuses, day equivalents, leave earned rates
- Schedule types
- Training types and upload-file types
- IPCR types, MFO types, and periods

Most references are consumed through forms and seed data. A comprehensive standalone reference-management UI is not present.

### 3.21 Notifications — Active utility

Capabilities:

- Count pending leave requests by role/scope.
- Count training requests and files awaiting verification.
- Count employees lacking user accounts.
- Fetch training invitations and requests for an employee.

Role behavior:

- Level 1: global leave/account counts and department-specific training rules.
- Level 2: departmental leave counts plus training counts.
- Level 3: training counts.
- Level 4: no administrative counts.

Finding: notification API accepts an employee ID and appears unauthenticated, allowing role/scope enumeration.

### 3.22 Printing and document generation — Active cross-cutting capability

The codebase generates or renders:

- Employee PDS
- Leave applications
- Leave cards/reports
- DTR/CS Form 48
- Monthly Report of Attendance
- Payslips
- IPCR forms
- TARF and training forms
- Appointment forms
- General Knowledge Exam

Multiple PDF libraries and hand-built print layouts are used, so typography, pagination, and signatory rules are distributed across controllers and views.

### 3.23 Subsidiary-HRIS replication — Active infrastructure

Capabilities:

- Queue employee master replication to `mysql_sub_hris`.
- Upsert selected employee fields into the subsidiary database.
- Queue/upsert DTR records into the subsidiary database.
- Dedicated endpoint can synchronize DTR data to the subsidiary HRIS.

Findings:

- Database hosts and credentials are hard-coded in configuration.
- Replication is not transactional with the primary write.
- Fingerprints are intentionally excluded from employee replication.
- Retry/failure reconciliation is not exposed as an operator feature.

### 3.24 System logging — Partial

- A generic system-log model exists.
- Leave has a detailed domain ledger/log.
- Some actions carry `created_by`, `entry_by`, `rating_by`, uploader, or approver identifiers.
- There is no consistent, centralized audit trail covering all sensitive changes.

## 4. Role and scope matrix

Legend: **G** global, **D** department/division scoped, **S** self, **C** conditional by department/position, **—** not intended in UI.

| Feature | L1 Administrator | L2 Section Admin | L3 Point Person | L4 User |
|---|---:|---:|---:|---:|
| Employee directory | G | D | D | S |
| Employee create/edit | G | D implied | D implied | S profile update |
| PDS | G + self | D + self | D + self | S |
| Account management | G | D controller-only | D | — |
| Leave application/card | G + self | S/D | S | S |
| Leave approval/log | G | D | not intended | — |
| DTR manual maintenance | G | D implied | D implied | view self |
| DTR/MRA reports | G | D/C | D | — |
| Payslips | G + any | S | S | S |
| TARF request | G/C | C | C | S/C |
| PETU/MCC approval | C | C | C | — |
| IPCR | G/D | D/C | D/C | S |
| Schedule | route-accessible | route-accessible | route-accessible | route-accessible |
| Workforce charts/reports | G | D/C | D | — |
| Bulletin/committee/appointment | route-accessible | route-accessible | route-accessible | route-accessible |

This matrix describes intended UI behavior. Because route authorization is weak, actual reachable behavior is broader.

## 5. Hidden authorization dimensions

Access is also determined by:

- Department 42: division 3 employee/report expansion.
- Department 64: OMCC/MCC training approval stage.
- Department 88: PETU/LDI administration and file verification.
- Employee `000856`: hard-coded expanded training navigation.
- Employee ownership (`Auth::user()->emp_id` versus requested/selected employee).
- Position IDs 100 and 105: excluded from some leave/payslip eligibility.
- Employment status below 6, with special treatment for statuses 3 and 4.
- Department head, division chief, regional director, and special-department lookups.
- Workflow statuses for leave, TARF, uploads, and IPCR.

These should become explicit permissions and reusable organizational scopes in any replacement system.

## 6. API and machine-to-machine surface

| Endpoint family | Purpose | Observed protection |
|---|---|---|
| `POST dtr/new` | Individual biometric punch | Outside authenticated web group |
| `POST api/dtr/client/sync` | DTR client batch sync | No route middleware declared |
| `POST api/sub-hris/dtr/sync` | Subsidiary HRIS DTR sync | No route middleware declared |
| `POST api/payroll/consume` | Payroll ingestion | No route middleware declared |
| `GET api/training/calendar` | Training calendar feed | No route middleware declared |
| `GET api/training/get/employee-list` | Employee lookup | No route middleware declared |
| `GET api/notifs` | Role-aware notification counts | No route middleware declared |
| `GET api/get/employee/{empID}` | Employee data lookup | No route middleware declared |
| `GET api/reports/leave/...` | Leave report generation | No route middleware declared |
| `GET api/account/blanks/default` | Create default accounts | No route middleware declared |
| `POST api/test/employee/update/leave` | Leave-credit test/update | No route middleware declared |

This is the highest-risk area of the legacy application.

## 7. Background and operational behavior

Queues:

- `dtr_in` / `dtr_out`: punch writes and adjustments.
- `leave`: accrual calculations.
- `sub_hris`: replication.
- Payroll ingestion and training email jobs use the configured queue behavior.

Operational observations:

- Queue default is configuration-driven and falls back to synchronous execution.
- No recurring schedule is declared in `Console\Kernel`.
- Failed-job migrations exist, including duplicate-era migrations.
- No operator dashboard for queue failures, replication drift, or payroll ingestion failures was found.

## 8. Data-domain inventory

Primary domains and tables represented by models/migrations:

- Identity/access: users, user accounts, password resets.
- Organization: divisions, departments, positions, plantilla, salary grades, employment statuses.
- Employee master/PDS: employees, dependents, education, eligibility, work experience, voluntary work, training history, other information, references.
- Attendance: employee DTR, schedules, schedule types.
- Leave: applications, types, statuses, logs, earned-rate/day-equivalent references, special department references.
- Training/TARF: training details, participants/requests, types, uploads, upload types.
- Performance: IPCR periods/types, MFOs/types/sets, employee IPCR, ratings, calibrations; OPCR and accountables.
- Payroll: payroll types, generations, items, employee payrolls.
- Content/utilities: bulletins, committee-related data accessed by controllers, system logs, signatories.

The bundled `hris.sql` is broader than migrations and is likely the most complete legacy-schema snapshot; migrations alone should not be treated as authoritative for reconstruction.

## 9. Dormant, duplicated, and incomplete features

- Schedule, appointment, bulletin, committee, PSS, and some report links are hidden/commented while routes remain active.
- OPCR schema/models exist without a routed UI.
- Duplicate old/new employee and leave update implementations coexist.
- `AdminMiddleware` exists but is ineffective and unused for meaningful separation.
- `APIRequestChecker` exists but is not applied to the enumerated API routes.
- DTR print has both direct controller generation and a queue job wrapper.
- A schedule deletion method exists without an active route.
- Registration scaffolding exists, but normal account creation is employee-admin driven.
- Password reset scaffolding exists; actual mail readiness depends on environment configuration.
- Console scheduler is empty, leaving automated leave accrual without a declared recurring trigger.
- Some routes are explicitly labeled `test` or `fix` but remain exposed.

## 10. Security and correctness findings

### Critical

1. Machine-facing API routes lack declared authentication/authorization.
2. Sensitive account, payroll, DTR, leave-report, employee-data, and maintenance operations are exposed through that API surface.
3. Database credentials and an application key have hard-coded fallbacks in committed configuration.
4. Most sensitive web operations have authentication but no role/object authorization.

### High

1. Leave approval checks list scope but not target scope at mutation time.
2. Employee, account, DTR, and IPCR mutations accept target identifiers without consistent ownership checks.
3. State-changing actions use GET in several modules.
4. Raw SQL is assembled from request values in legacy search functions.
5. UI hiding is frequently the only access restriction.
6. Uploaded files and images need stricter type, path, privacy, and authorization controls.

### Medium

1. Authorization rules are duplicated and depend on magic department, position, status, and employee IDs.
2. Multiple implementations of the same domain workflow can produce inconsistent results.
3. Cross-database replication lacks reconciliation and transactional guarantees.
4. Queue/scheduler requirements are not represented by health checks or operational UI.
5. Several workflows rely on numeric status constants without a centralized state model.
6. Report scope often depends on restricted dropdowns rather than server-side filters.

## 11. Recommended migration decomposition

The replacement application should preserve business capabilities while replacing implicit access rules with explicit permissions:

- Employee: view self/department/global, create, edit master, edit PDS, activate/separate.
- Accounts: view, create, assign role, reset, disable, with department/global scope.
- Leave: apply self/on-behalf, view, calculate, approve, disapprove, adjust balances, report.
- Attendance: ingest device punches, sync client, edit DTR, view self, report.
- Training: request, participate, PETU review, MCC review, upload, verify, report.
- Performance: manage periods/MFOs, assign targets, rate, calibrate, view self, print.
- Payroll: trusted ingest, view self, view global, print.
- Reports: self, department, division, global scopes.
- System operations: integration keys, replication monitoring, queue failures, audit log.

Department IDs 42/64/88, position IDs 100/105, and employee `000856` should be migrated into configurable organizational assignments or permissions, never retained as hard-coded authorization.

## 12. Audit conclusion

The reference HRIS is not only an employee-record application. It combines employee master/PDS, leave accounting and approval, biometric attendance, DTR reporting, limited scheduling, TARF training workflows, IPCR performance management, payroll/payslip consumption, appointment and government-form printing, notifications, and cross-database replication.

Its business feature coverage is substantial, but authorization is primarily navigational and convention-based. The safest migration strategy is to preserve the domain workflows and calculations while rebuilding access control, API trust, auditability, file security, and integration observability as first-class capabilities.
