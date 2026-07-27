# WP FormVault Task Register

Last updated: 2026-07-27  
Current stage: Quality tooling and CI definitions are complete; database versioning/migration design is next (`DB-001`).

## Purpose

This is the mandatory source of truth for WP FormVault work status. It decomposes the approved [implementation plan](./IMPLEMENTATION_PLAN.md) by module and submodule. Update this file in the same change whenever work starts, scope changes, verification fails, a blocker appears, or a task completes.

## State model

| State | Meaning |
|---|---|
| `PLANNED` | In scope but not yet ready to start. |
| `READY` | Dependencies and acceptance conditions are understood; work may start. |
| `IN_PROGRESS` | Actively being implemented. |
| `BLOCKED` | Work cannot proceed; the blocker and owner must be recorded. |
| `VERIFY` | Implementation is finished but required checks have not all passed. |
| `COMPLETE` | Acceptance evidence exists and all required checks passed. |
| `DEFERRED` | Intentionally postponed with a documented reason. |
| `CANCELLED` | Removed from scope with a documented decision. |
| `ACTIVE` | A continuing governance obligation rather than a finishable build task. |

## Mandatory update rules

1. Move a task to `IN_PROGRESS` before changing implementation files for it.
2. Keep one clear state per task. Split tasks that are partly complete instead of overstating progress.
3. A task becomes `COMPLETE` only when its acceptance conditions, tests, and relevant documentation updates are all satisfied.
4. Code presence alone is not completion. Record reproducible evidence in the task's Notes/Evidence field.
5. Any discovered defect must be added to [BUGS.md](./BUGS.md) and linked from the affected task.
6. Every user-visible, architectural, security, schema, dependency, or operational change must be recorded under `Unreleased` in [CHANGELOGS.md](./CHANGELOGS.md).
7. Update [MEMORY.md](./MEMORY.md) whenever a stable fact, decision, invariant, supported integration, schema rule, or operational instruction changes.
8. Do not silently broaden scope. Add a task or amend an existing task and record the change in the changelog first.
9. When a completed task regresses, move it to `IN_PROGRESS` or `BLOCKED`, add a bug record, and retain the old evidence in history.
10. Dates use `YYYY-MM-DD`; all runtime timestamps use UTC unless a display-time context explicitly requires the WordPress site timezone.
11. After changing task dependencies, run `powershell -File tools/verify-task-graph.ps1`; missing task references and dependency cycles are not allowed.

## Completion evidence standard

Use one or more of these evidence types in Notes/Evidence:

- Test name and passing command/result
- Manual verification steps and environment
- Migration/schema inspection
- Security test or review result
- Generated artifact inspection
- Relevant code path and commit/change identifier

Never use “implemented as planned” as evidence.

## Phase and module map

| Plan phase | Primary task modules |
|---|---|
| Phase 1 — Foundation | `ARCH`, `FND`, `DB`, `SEC`, `QUEUE`, `ADMIN` |
| Phase 2 — CF7 capture/datastore | `ADAPTER`, `SYNC`, `CF7`, `SUB` |
| Phase 3 — Reporting core | `REPORT`, `EXPORT`, `EMAIL` |
| Phase 4 — Scheduling | `SCHED`, `QUEUE` |
| Phase 5 — Upload ZIP delivery | `FILES`, `DOWNLOAD`, `EMAIL`, `CLEANUP` |
| Phase 6 — Workflow | `WORKFLOW`, `BULK` |
| Phase 7 — Editing/audit/report impact | `SUB`, `AUDIT`, `REPORT` |
| Phase 8 — Saved views/unified mapping | `VIEWS`, `REPORT` |
| Phase 9 — Notifications/automation | `NOTIFY`, `AUTO` |
| Phase 10 — Additional integrations | `INTEGRATIONS` |
| Phase 11 — Dashboard/privacy/hardening | `ADMIN`, `PRIVACY`, `MULTISITE`, `HEALTH`, `QA`, `RELEASE` |

## GOV — Project controls and documentation

| ID | Submodule / task | State | Depends on | Notes / evidence |
|---|---|---|---|---|
| GOV-001 | Read and analyze the complete hardened source plan | `COMPLETE` | — | Full 44-section, 1,132-line plan reviewed on 2026-07-27. |
| GOV-002 | Establish WP FormVault identity and rebrand the canonical plan | `COMPLETE` | GOV-001 | `IMPLEMENTATION_PLAN.md` uses display name, slug, bootstrap, namespace, table/capability/hook prefix conventions; root README identity typo corrected. |
| GOV-003 | Create task, changelog, bug, and project-memory controls | `COMPLETE` | GOV-001 | `TASKS.md`, `CHANGELOGS.md`, `BUGS.md`, and `MEMORY.md` created. |
| GOV-004 | Maintain task state on every implementation change | `ACTIVE` | GOV-003 | Standing mandatory rule. |
| GOV-005 | Maintain change history on every project change | `ACTIVE` | GOV-003 | Standing mandatory rule. |
| GOV-006 | Record defects, causes, fixes, and prevention | `ACTIVE` | GOV-003 | Standing mandatory rule. |
| GOV-007 | Keep project memory factual, current, and evidence-linked | `ACTIVE` | GOV-003 | Standing mandatory rule. |
| GOV-008 | Enforce the mandatory control workflow through repository instructions | `COMPLETE` | GOV-003 | Root `AGENTS.md` requires future contributors/agents to read and synchronize all control files. |

## ARCH — Architecture and engineering decisions

| ID | Submodule / task | State | Depends on | Notes / evidence |
|---|---|---|---|---|
| ARCH-001 | Freeze project identifiers: name, slug, namespace, text domain, prefixes | `COMPLETE` | GOV-002 | See `MEMORY.md` Project identity. |
| ARCH-002 | Define supported WordPress/PHP/database compatibility matrix | `COMPLETE` | ARCH-001 | Option A selected by the user on 2026-07-27: WordPress 6.5+, Action Scheduler 3.9.3, PHP 8.1+ on 64-bit, MySQL 5.7+ or MariaDB 10.4+. Verified under PHP 8.1.34 with compatibility/foundation checks and an acyclic 198-task/325-edge graph. The verifier targets stable compatibility facts rather than mutable policy status. `ARCH-005` defines the full CI matrix; executable lanes are implemented under `QA-001`. (`BUG-0005`, `BUG-0011`) |
| ARCH-003 | Define Composer dependency versions and conflict policy | `COMPLETE` | FND-001 | Policy and first minimum-platform lock verified 2026-07-27. PhpSpreadsheet 5.8.1 is the last PHP 8.1 line; Action Scheduler 3.9.3 is the latest WordPress-6.5-compatible line; ZipStream remains 3.0.2. The lock-only build passed strict validation, audit, isolation, and runtime smoke tests. (`BUG-0006`, `BUG-0007`) |
| ARCH-004 | Define service-container composition and module boundaries | `COMPLETE` | FND-001 | Accepted `Core\Plugin` composition root and `Core\ServiceContainer` contract, fail-closed startup sequence, explicit service scopes, 15-module/63-edge inward graph, public-surface rules, and ownership boundaries. Verified 2026-07-27 under PHP 8.1.34 with `php tools/verify-architecture.php`; all module directories/imports passed and the graph is acyclic by strict layer direction. |
| ARCH-005 | Define coding standards, static-analysis level, test layout, and CI matrix | `COMPLETE` | FND-001 | Accepted policy defines 4 PHPCS rulesets, PHPStan level 8/no baseline, PHPUnit 9.6 for the official WordPress 6.5–7.0 harness, 7 test areas, and 9 CI lanes (8 blocking). The installed tool/workflow evidence is recorded under completed `QA-001`; hosted-run evidence remains a release requirement. (`BUG-0014`, `BUG-0015`) |
| ARCH-006 | Record architecture decisions that alter the implementation plan | `ACTIVE` | GOV-007 | Add dated decisions to `MEMORY.md`; update the plan if requirements change. |

## FND — Plugin foundation and lifecycle

| ID | Submodule / task | State | Depends on | Notes / evidence |
|---|---|---|---|---|
| FND-001 | Scaffold `wp-formvault.php`, module directories, autoloading, and constants | `COMPLETE` | ARCH-001 | Verified 2026-07-27 with PHP 8.2.31 container: all 3 PHP files pass `php -l`; `php tools/verify-foundation.php` passes constants, paths, namespace guard, and single autoloader registration; planned directories and legacy-name checks pass; `git diff --check` passes. |
| FND-002 | Add Composer manifest and production dependency strategy | `COMPLETE` | FND-001, ARCH-003 | Verified 2026-07-27 with normal lock-only `tools/run-dependency-build.ps1`: digest-pinned PHP 8.1.34/Composer 2.10.2; strict validate; no audit advisories; all platform requirements pass; seven runtime packages locked; Strauss correction counts Complex=42/Matrix=21/ZipStream=4; Action Scheduler 3.9.3 staged; notices generated; 722 generated PHP files linted; unprefixed-conflict, Complex/Matrix, and real XLSX/ZIP tests pass. Lock SHA-256: `5EAF5929FA2B30EE29FD8A134DA37DA2D79FAB86C05F20D15B6B9C8A06EC3E65`. (`BUG-0004`, `BUG-0007`–`BUG-0010`) |
| FND-003 | Implement plugin bootstrap and service container | `COMPLETE` | FND-001, ARCH-004 | Verified 2026-07-27 under PHP 8.1.34: explicit site-scoped container passes lazy shared/transient/alias/type/freeze and duplicate/missing/circular failures; production dependency and compatibility gates pass and stop at intentional `blocked_schema`; diagnostics expose no paths; injected passing gates construct/register one hook registrar once; independent site graphs do not share services. Clean lock-only dependency build exited 0 in 305.9 seconds with audit/platform/722 generated syntax/isolation/XLSX checks passing; architecture scanned 12 runtime PHP files; task graph and diff checks passed. (`BUG-0012`, `BUG-0013`) |
| FND-004 | Implement activator and per-site capability installation | `PLANNED` | DB-001, SEC-001 | Must support single site and multisite. |
| FND-005 | Implement deactivator: unschedule events and clear locks, preserve data | `PLANNED` | QUEUE-001, QUEUE-004 | Verify no data deletion. |
| FND-006 | Implement guarded uninstall with delete-data setting defaulting OFF | `PLANNED` | DB-001, CLEANUP-001, MULTISITE-003 | Test both preservation and deletion modes. |
| FND-007 | Add internationalization loading and POT generation | `PLANNED` | FND-001 | Text domain `wp-formvault`; include RTL readiness. |
| FND-008 | Create `readme.txt`, licensing notices, and administrator prerequisites | `PLANNED` | ARCH-002, ARCH-003 | Include server-cron recommendation and public-link risks. |

## DB — Database, migrations, and repositories

| ID | Submodule / task | State | Depends on | Notes / evidence |
|---|---|---|---|---|
| DB-001 | Finalize table inventory, columns, data types, and application-level relations | `READY` | FND-001 | Use `$wpdb->prefix . 'wpfv_'`; all runtime timestamps UTC. Sequence after `ARCH-005` unless reprioritized. |
| DB-002 | Implement ordered, idempotent schema migration runner and schema version | `PLANNED` | DB-001 | Add migration lock and upgrade guard. |
| DB-003 | Create forms, fields, submissions, snapshots, and indexed values tables | `PLANNED` | DB-002 | Hybrid canonical JSON + selective EAV model. |
| DB-004 | Create schedules, mappings, filters, recipients, reports, files, and delivery tables | `PLANNED` | DB-002 | Preserve intended period and idempotency key. |
| DB-005 | Create workflow, notes, tags, saved views, notifications, automation, and audit tables | `PLANNED` | DB-002 | Include optimistic `row_version`. |
| DB-006 | Create sync cursors/logs, jobs, locks, access grants, tokens, and download logs | `PLANNED` | DB-002 | Token table stores hashes only. |
| DB-007 | Add required unique constraints and query indexes | `PLANNED` | DB-003, DB-004, DB-005, DB-006 | Include unique source key and report idempotency uniqueness. |
| DB-008 | Implement repositories with prepared SQL and bounded pagination | `PLANNED` | DB-003, SEC-004 | AccessScope must be injected where applicable. |
| DB-009 | Implement batched data-transform migrations and failure recovery | `PLANNED` | DB-002, QUEUE-001 | Report generation blocked while migration is active. |
| DB-010 | Test fresh install, version upgrades, partial failures, and rollback guidance | `PLANNED` | DB-009, QA-001 | Never claim automatic reversibility without a tested path. |

## SEC — Security, authorization, and input/output controls

| ID | Submodule / task | State | Depends on | Notes / evidence |
|---|---|---|---|---|
| SEC-001 | Define roles and `wpfv_*` capability matrix | `READY` | FND-001 | Administrator, Report Manager, Report Viewer, Form Manager. Sequence after `ARCH-005` unless reprioritized. |
| SEC-002 | Implement AccessScope and form/schedule access grants | `PLANNED` | DB-006, SEC-001 | Enforcement must live in repository/query paths. |
| SEC-003 | Implement nonce and capability checks for every write action | `PLANNED` | SEC-001 | Cover admin, AJAX, REST, bulk, run-now, delete. |
| SEC-004 | Implement prepared-query builders and schema-driven validation | `PLANNED` | DB-001 | No dynamic user values concatenated into SQL; dependency direction corrected by BUG-0001. |
| SEC-005 | Implement context-aware sanitization and output escaping | `PLANNED` | FND-001 | Include `wp_kses` policy for HTML templates. |
| SEC-006 | Implement strict REST/AJAX permission callbacks | `PLANNED` | SEC-002, SEC-003 | No unconditional callback except token-only public download route. |
| SEC-007 | Implement guarded legacy metadata decoding with object rejection | `PLANNED` | FND-001 | Never deserialize untrusted objects; adapter registry consumes this primitive (BUG-0001). |
| SEC-008 | Implement media-attachment-only branding validation | `PLANNED` | FND-001 | No arbitrary server-side URL fetch; validation primitive precedes export branding (BUG-0001). |
| SEC-009 | Build security regression suite for CSRF, XSS, SQLi, ACL leakage, SSRF, deserialization, and header injection | `PLANNED` | QA-001, SEC-002–SEC-008 | Link failures to `BUGS.md`. |

## QUEUE — Action Scheduler, jobs, concurrency, and recovery

| ID | Submodule / task | State | Depends on | Notes / evidence |
|---|---|---|---|---|
| QUEUE-001 | Bundle/integrate Action Scheduler and register job groups | `PLANNED` | FND-002 | WP-Cron triggers; server cron remains the reliability backstop. |
| QUEUE-002 | Define typed payload schemas for all planned job types | `PLANNED` | QUEUE-001 | Reject malformed or stale payloads. |
| QUEUE-003 | Implement retry policy with preserved intended period | `PLANNED` | QUEUE-002 | Default attempts: immediate, +15m, +1h, +3h. |
| QUEUE-004 | Implement per-schedule locks, lease timeout, and stuck-job reclaim | `PLANNED` | DB-006, QUEUE-001 | Verify crash recovery and contention behavior. |
| QUEUE-005 | Implement report-run and delivery idempotency | `PLANNED` | DB-004, QUEUE-004 | Key derives from schedule + period boundaries. |
| QUEUE-006 | Enforce maximum simultaneous heavy jobs and bounded batches | `PLANNED` | QUEUE-001 | No full-dataset work in request memory. |
| QUEUE-007 | Add queue administration, retry/reclaim controls, and audit events | `PLANNED` | ADMIN-001, AUDIT-001 | Capability and nonce protected. |

## ADAPTER — Adapter contracts and normalization

| ID | Submodule / task | State | Depends on | Notes / evidence |
|---|---|---|---|---|
| ADAPTER-001 | Define base, datastore, and capture adapter interfaces | `READY` | ARCH-004 | Capabilities are explicit, not inferred; sequence after the foundation bootstrap unless explicitly reprioritized. |
| ADAPTER-002 | Implement adapter registry, detection, version checks, and safe degradation | `PLANNED` | ADAPTER-001, SEC-007 | Unknown versions degrade to read-only with warning. |
| ADAPTER-003 | Implement normalized field vocabulary and stable field identity | `PLANNED` | ADAPTER-001 | Labels never serve as mapping identifiers. |
| ADAPTER-004 | Normalize arrays, repeaters, files, signatures, payments, dates, and system fields | `PLANNED` | ADAPTER-003 | Preserve canonical structured JSON. |
| ADAPTER-005 | Implement schema-drift reconciliation and inactive-field retention | `PLANNED` | ADAPTER-002, DB-003 | Never destroy historical field metadata. |
| ADAPTER-006 | Publish custom adapter developer interface and capability diagnostics | `PLANNED` | ADAPTER-001, FND-008 | Include read-only/custom-table connector guidance. |
| ADAPTER-007 | Create adapter contract test suite | `PLANNED` | ADAPTER-001, QA-001 | Same fixtures/behavior expectations across integrations. |

## SYNC — Indexing, change detection, reconciliation, and capture health

| ID | Submodule / task | State | Depends on | Notes / evidence |
|---|---|---|---|---|
| SYNC-001 | Implement canonical JSON generation and `data_hash` rules | `PLANNED` | ADAPTER-003, DB-003 | Sort keys/arrays; exclude volatile fields; special file hash tuple. |
| SYNC-002 | Implement idempotent real-time submission upsert | `PLANNED` | SYNC-001, DB-007 | Duplicate hooks update rather than duplicate. |
| SYNC-003 | Implement per-source/per-form cursor storage | `PLANNED` | DB-006 | Support source ID and modified-time cursors. |
| SYNC-004 | Implement bounded forward reconciliation for datastore adapters | `PLANNED` | SYNC-002, SYNC-003 | Best-effort catch-up, not a real-time guarantee. |
| SYNC-005 | Implement bounded rolling deletion detection | `PLANNED` | SYNC-004 | Mark `source_deleted`; avoid unbounded scans. |
| SYNC-006 | Implement capture-integrity status and stopped-hook alerts | `PLANNED` | ADAPTER-002, HEALTH-001 | Capture mode never advertises reconciliation. |
| SYNC-007 | Implement rebuild/resync form/date-range recovery tools | `PLANNED` | SYNC-004, QUEUE-006 | Batched, resumable, and audited. |
| SYNC-008 | Redact PII from sync/error diagnostics | `PLANNED` | LOG-001 | Store keys/counts/hashes, not raw values. |

## CF7 — Contact Form 7 initial integrations

| ID | Submodule / task | State | Depends on | Notes / evidence |
|---|---|---|---|---|
| CF7-001 | Verify current CF7 hooks and supported source-version matrix | `PLANNED` | ADAPTER-002 | Confirm at implementation time; do not trust historical hook names blindly. |
| CF7-002 | Implement vanilla CF7 capture-mode adapter | `PLANNED` | CF7-001, SYNC-002 | Clearly label index as the only store. |
| CF7-003 | Implement CF7 capture field/file normalization | `PLANNED` | CF7-002, ADAPTER-004 | Handle missing/inaccessible uploads safely. |
| CF7-004 | Implement CFDB7 datastore adapter | `PLANNED` | CF7-001, SYNC-004 | Declare read/edit/delete/file capabilities by version. |
| CF7-005 | Implement Flamingo datastore adapter | `PLANNED` | CF7-001, SYNC-004 | Use stable source IDs and safe metadata handling. |
| CF7-006 | Evaluate Advanced CF7 DB compatibility and adapter scope | `PLANNED` | CF7-001 | Record product/version decision before coding. |
| CF7-007 | Test downtime gaps: capture-only warning vs datastore reconciliation | `PLANNED` | CF7-002, CF7-004, CF7-005 | Prove the UI does not promise impossible recovery. |

## SUB — Central submission management

| ID | Submodule / task | State | Depends on | Notes / evidence |
|---|---|---|---|---|
| SUB-001 | Implement AccessScope-safe paginated submission list repository | `PLANNED` | DB-008, SEC-002 | Filter/search/sort only authorized records. |
| SUB-002 | Build submission list admin screen and trash view | `PLANNED` | SUB-001, ADMIN-001 | Include source/capture status indicators. |
| SUB-003 | Build submission detail, uploads, workflow, reports, and timeline view | `PLANNED` | SUB-001, AUDIT-001 | Sensitive values capability-gated. |
| SUB-004 | Implement source-aware editing with validation and optimistic locking | `PLANNED` | ADAPTER-002, DB-005 | Datastore write-back where supported; index-only for capture mode. |
| SUB-005 | Implement trash and restore with source capability handling | `PLANNED` | SUB-004 | Audit every operation. |
| SUB-006 | Implement permanent deletion and PII removal | `PLANNED` | PRIVACY-003, SUB-005 | Preserve anonymized audit history. |
| SUB-007 | Mark affected reports outdated after edit/delete/late arrival | `PLANNED` | REPORT-007, SUB-004 | Preserve original report history. |

## REPORT — Report builder, mappings, history, and regeneration

| ID | Submodule / task | State | Depends on | Notes / evidence |
|---|---|---|---|---|
| REPORT-001 | Implement form/field selection keyed by stable field IDs | `PLANNED` | ADAPTER-003, DB-004 | Preview export structure. |
| REPORT-002 | Implement system columns and sensitive-field gating | `PLANNED` | SEC-001, REPORT-001 | IP/user agent default hidden and gated. |
| REPORT-003 | Implement nested filter model and parameterized compiler | `PLANNED` | DB-008, SEC-004 | Target indexed columns only. |
| REPORT-004 | Implement report template/configuration snapshots | `PLANNED` | DB-004, REPORT-001 | Needed for truthful history/regeneration. |
| REPORT-005 | Implement manual date-range and selected-record reports | `PLANNED` | REPORT-003, QUEUE-002 | Heavy exports queued. |
| REPORT-006 | Implement multi-form/unified worksheet mapping and conflict handling | `PLANNED` | REPORT-001, VIEWS-004 | Unmatched fields can route to form sheets. |
| REPORT-007 | Implement report history, report-record snapshots, and outdated reasons | `PLANNED` | DB-004, REPORT-004 | Include late in-period additions. |
| REPORT-008 | Implement reproduce-as-of regeneration | `PLANNED` | REPORT-007, EXPORT-001 | Default expired-file regeneration mode. |
| REPORT-009 | Implement refresh-current regeneration as a new report | `PLANNED` | REPORT-007, EXPORT-001 | Original record/history remains immutable. |

## EXPORT — XLSX, CSV, and export safety

| ID | Submodule / task | State | Depends on | Notes / evidence |
|---|---|---|---|---|
| EXPORT-001 | Integrate PhpSpreadsheet and streaming CSV writer | `PLANNED` | FND-002, QUEUE-001 | Temp-file generation; bounded memory. |
| EXPORT-002 | Implement a single CellSanitizer for XLSX, CSV, and manifests | `PLANNED` | EXPORT-001 | Cover `= + - @ TAB CR`, leading whitespace, and DDE. |
| EXPORT-003 | Force explicit string typing and disable unsafe automatic binding | `PLANNED` | EXPORT-002 | Preserve genuine numeric/date cell types. |
| EXPORT-004 | Implement sheet/filename sanitization, limits, and deduplication | `PLANNED` | EXPORT-001 | No traversal or raw server paths. |
| EXPORT-005 | Implement workbook modes, styles, headers, dates, and print settings | `PLANNED` | EXPORT-001, REPORT-001 | No formulas, charts, pivots, or macros. |
| EXPORT-006 | Implement attachment-ID branding and SSRF-safe image loading | `PLANNED` | SEC-008, EXPORT-005 | Validate local media containment and type. |
| EXPORT-007 | Enforce row/size/resource caps with explicit warnings | `PLANNED` | QUEUE-006, EXPORT-001 | Never silently truncate. |
| EXPORT-008 | Test injection payloads and large exports at 1k/10k/100k rows | `PLANNED` | QA-001, EXPORT-002–EXPORT-007 | Record resource metrics and artifact inspection. |

## SCHED — Schedules, calendar windows, catch-up, and retries

| ID | Submodule / task | State | Depends on | Notes / evidence |
|---|---|---|---|---|
| SCHED-001 | Implement schedule model, forms, fields, mappings, recipients, and states | `PLANNED` | DB-004 | Capture timezone at creation. |
| SCHED-002 | Implement timezone-safe half-open window calculator | `PLANNED` | SCHED-001 | Compute local boundaries, query with UTC instants. |
| SCHED-003 | Implement daily, weekly, monthly, and calendar-quarter windows | `PLANNED` | SCHED-002 | Support fiscal-quarter offset only when explicitly configured. |
| SCHED-004 | Implement alternate-week windows with required anchor date | `PLANNED` | SCHED-002 | No implicit parity. |
| SCHED-005 | Implement one-time and validated custom recurrence | `PLANNED` | SCHED-002 | Store explicit intended period. |
| SCHED-006 | Implement next-run calculation and queue enqueueing | `PLANNED` | QUEUE-001, SCHED-002 | Late cron must not shift reporting windows. |
| SCHED-007 | Implement all-missed-period catch-up service | `PLANNED` | SCHED-006, QUEUE-005 | One idempotent job per intended period. |
| SCHED-008 | Implement schedule duplication with public-risk acknowledgment reset | `PLANNED` | SCHED-001 | Duplicate is Draft/Inactive. |
| SCHED-009 | Implement no-submission policies and manual run-now | `PLANNED` | SCHED-006, EMAIL-001 | Capability/nonce protected. |
| SCHED-010 | Test DST, leap years, Asia/Kolkata, timezone changes, and missed windows | `PLANNED` | QA-001, SCHED-002–SCHED-009 | Include America/New_York DST edges. |

## FILES — Uploaded-file collection and ZIP generation

| ID | Submodule / task | State | Depends on | Notes / evidence |
|---|---|---|---|---|
| FILES-001 | Normalize source upload references without copying originals | `PLANNED` | ADAPTER-004 | Store reference, filename, and MIME metadata. |
| FILES-002 | Implement realpath containment and symlink escape rejection | `PLANNED` | FILES-001 | Allow only WP uploads and explicitly configured source roots. |
| FILES-003 | Integrate ZipStream and collision-safe archive layout | `PLANNED` | FND-002, FILES-002 | Avoid full-archive memory buffering. |
| FILES-004 | Enforce per-file, total-size, and file-count caps | `PLANNED` | FILES-003 | Prevent disk/memory exhaustion. |
| FILES-005 | Generate sanitized manifest with omissions and errors | `PLANNED` | FILES-003, EXPORT-002 | Missing files are reported, not fatal. |
| FILES-006 | Test traversal, symlink, missing, unreadable, duplicate-name, and oversized files | `PLANNED` | QA-001, FILES-002–FILES-005 | Include Windows/Linux path fixtures where feasible. |

## DOWNLOAD — Secure report delivery

| ID | Submodule / task | State | Depends on | Notes / evidence |
|---|---|---|---|---|
| DOWNLOAD-001 | Implement 256-bit token generation and SHA-256 hash-only storage | `PLANNED` | DB-006 | Raw token exists only in the issued URL. |
| DOWNLOAD-002 | Implement authenticated download with capability and AccessScope | `PLANNED` | SEC-002, DOWNLOAD-001 | Default mode. |
| DOWNLOAD-003 | Implement public opt-in mode with explicit risk acknowledgment | `PLANNED` | DOWNLOAD-001 | Never default or inherit acknowledgment on duplicate. |
| DOWNLOAD-004 | Implement optional password hash, IP binding, expiry, and count cap | `PLANNED` | DOWNLOAD-001 | Revoked/expired link returns correct response; revoked is 410. |
| DOWNLOAD-005 | Implement controlled streaming, protected storage, and safe headers | `PLANNED` | FILES-003, DOWNLOAD-002 | No path or internal ID disclosure. |
| DOWNLOAD-006 | Implement per-IP/global throttle and brute-force cooldown | `PLANNED` | DOWNLOAD-001, LOG-001 | Redact attempted tokens. |
| DOWNLOAD-007 | Implement revoke, logging, and download history | `PLANNED` | AUDIT-001, DOWNLOAD-005 | Include count and last-download time. |
| DOWNLOAD-008 | Document Apache/Nginx storage-deny requirements and probe them | `PLANNED` | HEALTH-003, FND-008 | Prefer outside-webroot storage where supported. |

## EMAIL — Email templating, recipients, delivery, and retry

| ID | Submodule / task | State | Depends on | Notes / evidence |
|---|---|---|---|---|
| EMAIL-001 | Implement recipient groups and To/CC/BCC validation | `READY` | FND-003 | Validate every address; dependency-ready but sequenced in the reporting/delivery phase. |
| EMAIL-002 | Implement subject/body templates and documented placeholders | `PLANNED` | EMAIL-001 | Escape values for HTML/plain-text context. |
| EMAIL-003 | Implement CR/LF header-injection guard | `PLANNED` | EMAIL-001 | Cover sender, reply-to, subject, and display names. |
| EMAIL-004 | Implement queued `wp_mail` handoff and honest delivery states | `PLANNED` | QUEUE-002, EMAIL-002 | Do not claim delivery confirmation from boolean handoff. |
| EMAIL-005 | Implement attachment-size secure-link fallback | `PLANNED` | DOWNLOAD-002, EMAIL-004 | Log and notify about fallback. |
| EMAIL-006 | Implement retry/final-failure/manual resend flows | `PLANNED` | QUEUE-003, EMAIL-004 | Prevent duplicate sends with idempotency. |
| EMAIL-007 | Implement preview/test email and SMTP detection | `PLANNED` | ADMIN-001, EMAIL-002 | No sensitive live values in preview without permission. |

## CLEANUP — Generated-file retention and storage lifecycle

| ID | Submodule / task | State | Depends on | Notes / evidence |
|---|---|---|---|---|
| CLEANUP-001 | Create protected private generated-file storage | `PLANNED` | FND-001 | Random internal paths; deny listing/direct serving. |
| CLEANUP-002 | Implement configurable file expiry and cleanup job | `PLANNED` | QUEUE-001, CLEANUP-001 | Default 30 days; preserve report history. |
| CLEANUP-003 | Clean abandoned temp build files and interrupted jobs | `PLANNED` | QUEUE-004, CLEANUP-002 | Avoid deleting active leases. |
| CLEANUP-004 | Implement report state after cleanup and regeneration entry points | `PLANNED` | REPORT-008, REPORT-009, CLEANUP-002 | Show “Expired and cleaned.” |
| CLEANUP-005 | Implement storage quota/usage alerts | `PLANNED` | HEALTH-003, CLEANUP-001 | Respect configured operational limit. |

## WORKFLOW — Status, tags, assignment, notes, priority, follow-up

| ID | Submodule / task | State | Depends on | Notes / evidence |
|---|---|---|---|---|
| WORKFLOW-001 | Implement default/custom statuses and transitions | `PLANNED` | DB-005, SEC-001 | Default: New, In Review, Qualified, Rejected, Follow-up Required, Closed. |
| WORKFLOW-002 | Implement tags, assignee, priority, and follow-up service | `PLANNED` | WORKFLOW-001 | All timestamps UTC; display in site timezone. |
| WORKFLOW-003 | Implement internal notes and user mentions | `PLANNED` | NOTIFY-001, WORKFLOW-001 | Notes never write back to source. |
| WORKFLOW-004 | Implement activity timeline and optimistic conflict handling | `PLANNED` | AUDIT-001, WORKFLOW-001 | Prevent silent concurrent overwrites. |
| WORKFLOW-005 | Apply AccessScope and workflow capabilities to every operation | `PLANNED` | SEC-002, WORKFLOW-001 | Shared features never widen access. |

## BULK — Batched bulk operations

| ID | Submodule / task | State | Depends on | Notes / evidence |
|---|---|---|---|---|
| BULK-001 | Define allowed bulk actions and per-action capabilities | `PLANNED` | SEC-001, WORKFLOW-001 | Destructive actions require confirmation and nonce. |
| BULK-002 | Implement batched, resumable bulk job runner | `PLANNED` | QUEUE-006, BULK-001 | Per-record success/failure and retry. |
| BULK-003 | Implement progress, cancellation-safe state, and final summary | `PLANNED` | BULK-002, ADMIN-001 | Cancellation must not corrupt completed records. |
| BULK-004 | Audit bulk requests and individual material changes | `PLANNED` | AUDIT-001, BULK-002 | Avoid logging raw PII. |

## VIEWS — Saved views and reusable mappings

| ID | Submodule / task | State | Depends on | Notes / evidence |
|---|---|---|---|---|
| VIEWS-001 | Implement saved view schema and filter/column serialization | `PLANNED` | DB-005, SUB-001 | Schema-validate before use. |
| VIEWS-002 | Implement private, user-shared, role-shared, and default visibility | `PLANNED` | SEC-002, VIEWS-001 | A view never widens AccessScope. |
| VIEWS-003 | Build saved-view administration and default selection | `PLANNED` | ADMIN-001, VIEWS-002 | Permission-aware sharing UI. |
| VIEWS-004 | Implement reusable unified mapping templates | `PLANNED` | REPORT-001, DB-004 | Key mappings by stable field IDs. |

## AUDIT / LOG — Audit trail and operational logging

| ID | Submodule / task | State | Depends on | Notes / evidence |
|---|---|---|---|---|
| AUDIT-001 | Implement append-only audit logger and event vocabulary | `PLANNED` | DB-005, FND-003 | Record actor, action, target, result, time, safe old/new metadata. |
| AUDIT-002 | Audit permissions, schedules, submissions, reports, downloads, automation, and privacy events | `PLANNED` | AUDIT-001 | Cover success and failure where material. |
| AUDIT-003 | Implement subject erasure pseudonymization | `PLANNED` | AUDIT-001 | Preserve action history without subject PII; privacy erasure consumes this primitive (BUG-0001). |
| AUDIT-004 | Build capability-gated audit page and filters | `PLANNED` | ADMIN-001, AUDIT-001 | Escape all output. |
| LOG-001 | Implement structured operational logger with PII redaction | `READY` | FND-003 | No raw submission field values, tokens, passwords, or paths; part of the foundation phase after engineering standards. |
| LOG-002 | Implement configurable rotation/retention and diagnostic export | `PLANNED` | LOG-001 | Diagnostic export must remain redacted. |

## NOTIFY — Notifications and preferences

| ID | Submodule / task | State | Depends on | Notes / evidence |
|---|---|---|---|---|
| NOTIFY-001 | Define notification events and criticality | `PLANNED` | AUDIT-001 | Include sync, capture, cron, storage, report, follow-up events. |
| NOTIFY-002 | Implement admin-notice, email, and daily-digest channels | `PLANNED` | EMAIL-004, NOTIFY-001 | Batch digest without access leakage. |
| NOTIFY-003 | Implement user preferences and admin-enforced critical alerts | `PLANNED` | DB-005, NOTIFY-001 | Critical alerts cannot be disabled by users. |
| NOTIFY-004 | Build notification center and preference screens | `PLANNED` | ADMIN-001, NOTIFY-003 | Respect AccessScope for referenced entities. |

## AUTO — Automation rule engine

| ID | Submodule / task | State | Depends on | Notes / evidence |
|---|---|---|---|---|
| AUTO-001 | Define trigger, condition, and action schema | `PLANNED` | DB-005, WORKFLOW-001 | Validate referenced forms/fields/actions. |
| AUTO-002 | Implement parameterized condition evaluator | `PLANNED` | AUTO-001, SEC-004 | No code evaluation. |
| AUTO-003 | Implement queued action execution and per-action logs | `PLANNED` | QUEUE-002, AUTO-001 | Reuse email/report security controls. |
| AUTO-004 | Implement origin flags, recursion cap, and max-actions guard | `PLANNED` | AUTO-003 | Prevent loops and runaway chains. |
| AUTO-005 | Implement dry-run, test mode, priority, enable/disable, and preview | `PLANNED` | AUTO-002, AUTO-003 | Dry-run performs no mutations. |

## INTEGRATIONS — Additional form plugins

| ID | Submodule / task | State | Depends on | Notes / evidence |
|---|---|---|---|---|
| INTEGRATIONS-001 | Implement and test WPForms datastore adapter | `PLANNED` | ADAPTER-007, SYNC-004 | Verify current APIs/version matrix. |
| INTEGRATIONS-002 | Implement and test Gravity Forms datastore adapter | `PLANNED` | ADAPTER-007, SYNC-004 | Prefer Entry API/source change signals. |
| INTEGRATIONS-003 | Implement and test Fluent Forms datastore adapter | `PLANNED` | ADAPTER-007, SYNC-004 | Verify update/file capabilities. |
| INTEGRATIONS-004 | Implement and test Ninja Forms datastore adapter | `PLANNED` | ADAPTER-007, SYNC-004 | Verify current Ninja API. |
| INTEGRATIONS-005 | Implement and test Formidable Forms datastore adapter | `PLANNED` | ADAPTER-007, SYNC-004 | Verify current entries API. |
| INTEGRATIONS-006 | Implement Elementor capture mode without Submissions storage | `PLANNED` | ADAPTER-007, SYNC-006 | Index is the only store. |
| INTEGRATIONS-007 | Implement Elementor datastore mode with Submissions storage | `PLANNED` | ADAPTER-007, SYNC-004 | Runtime feature detection determines type. |
| INTEGRATIONS-008 | Add integration health/version/capability matrix UI | `PLANNED` | ADMIN-001, ADAPTER-002, SYNC-006 | Never overstate unsupported capabilities. |

## ADMIN — Navigation, pages, settings, dashboard, i18n, and accessibility

| ID | Submodule / task | State | Depends on | Notes / evidence |
|---|---|---|---|---|
| ADMIN-001 | Implement WP FormVault admin navigation and base page framework | `PLANNED` | FND-003, SEC-001 | Hide pages/actions the user cannot access. |
| ADMIN-002 | Build 12-step schedule wizard with validation and review | `PLANNED` | SCHED-001, ADMIN-001 | Includes anchor and public-link acknowledgment. |
| ADMIN-003 | Build schedules list/actions/history | `PLANNED` | SCHED-006, ADMIN-001 | Nonce/capability for mutations. |
| ADMIN-004 | Build reports list, logs, regenerate, resend, and revoke actions | `PLANNED` | REPORT-007, ADMIN-001 | Distinguish reproduce vs refresh. |
| ADMIN-005 | Build settings pages for general, email, privacy, downloads, and cleanup | `PLANNED` | ADMIN-001 | Safer defaults and plain-language risk notes. |
| ADMIN-006 | Build permissions and integration pages | `PLANNED` | SEC-002, INTEGRATIONS-008 | Capability-gated. |
| ADMIN-007 | Build KPI/dashboard queries and widgets | `PLANNED` | REPORT-007, SUB-001, HEALTH-001 | Every query AccessScope-safe. |
| ADMIN-008 | Apply keyboard navigation, labels, focus, contrast, and ARIA | `PLANNED` | ADMIN-001 | Verify wizard and dynamic controls. |
| ADMIN-009 | Localize strings, dates, numbers, and RTL styles | `PLANNED` | FND-007, ADMIN-001 | Store UTC; display localized. |

## PRIVACY — Data minimization, export, erasure, and anonymization

| ID | Submodule / task | State | Depends on | Notes / evidence |
|---|---|---|---|---|
| PRIVACY-001 | Implement privacy settings with IP/user-agent capture default OFF | `PLANNED` | ADMIN-005 | Explain purposes and risks. |
| PRIVACY-002 | Register WordPress personal-data exporter | `PLANNED` | DB-008, REPORT-007 | Include submissions, workflow metadata, report references. |
| PRIVACY-003 | Register personal-data eraser across snapshots, values, and report records | `PLANNED` | DB-008, AUDIT-003 | Preserve anonymized audit history. |
| PRIVACY-004 | Implement snapshot retention/pruning as opt-in, audited policy | `PLANNED` | CLEANUP-002, AUDIT-001 | Snapshots contain PII. |
| PRIVACY-005 | Implement optional scheduled anonymization | `PLANNED` | PRIVACY-003, QUEUE-001 | Never enable silently. |
| PRIVACY-006 | Test export/erasure across capture/datastore and historical reports | `PLANNED` | QA-001, PRIVACY-002–PRIVACY-005 | Record what source-plugin data remains out of WP FormVault scope. |

## MULTISITE — Network lifecycle and site isolation

| ID | Submodule / task | State | Depends on | Notes / evidence |
|---|---|---|---|---|
| MULTISITE-001 | Implement per-site tables and capabilities | `PLANNED` | DB-002, SEC-001 | Use each site's `$wpdb->prefix`. |
| MULTISITE-002 | Implement network activation for existing sites | `PLANNED` | FND-004, MULTISITE-001 | Batch if network size requires it. |
| MULTISITE-003 | Provision newly created sites | `PLANNED` | MULTISITE-001 | Hook site initialization. |
| MULTISITE-004 | Implement network defaults and per-site queue/health reporting | `PLANNED` | HEALTH-001, MULTISITE-001 | No cross-site submission leakage. |
| MULTISITE-005 | Implement multisite-aware uninstall in preserve/delete modes | `PLANNED` | FND-006, MULTISITE-001 | Explicit opt-in required for deletion. |

## HEALTH — Observability and operational health

| ID | Submodule / task | State | Depends on | Notes / evidence |
|---|---|---|---|---|
| HEALTH-001 | Add WordPress Site Health integration | `READY` | FND-003 | Cron, storage, queue, adapter/capture checks; dependency-ready but sequenced after core operational services. |
| HEALTH-002 | Implement cron heartbeat and inactivity alert | `PLANNED` | QUEUE-001, NOTIFY-001 | Recommend server cron when unhealthy. |
| HEALTH-003 | Implement writable/protected storage probe | `PLANNED` | CLEANUP-001 | Detect direct web exposure where testable. |
| HEALTH-004 | Implement queue backlog/stuck/failure metrics | `PLANNED` | QUEUE-004, HEALTH-001 | Provide safe reclaim controls. |
| HEALTH-005 | Implement per-integration sync/capture health | `PLANNED` | SYNC-006, HEALTH-001 | Reconciliation shown as N/A for capture mode. |

## QA — Automated and manual verification

| ID | Submodule / task | State | Depends on | Notes / evidence |
|---|---|---|---|---|
| QA-001 | Establish PHPUnit, WordPress integration, static analysis, and coding-standard tooling | `COMPLETE` | ARCH-005, FND-002 | Locked PHPUnit 9.6.35/Polyfills, WPCS 3.4.1 on PHPCS 3.13.5, isolated PHPCompatibilityWP 3.0.0-alpha2 on PHPCS 4.0.1, PHPStan 2.2.6/WordPress 6.5.7 stubs, isolated unit/WordPress bootstraps, and all 9 GitHub Actions lane IDs are implemented. `composer run qa`, strict Composer validation/full-lock audit, policy/tooling verifiers, actionlint 1.7.12, and WordPress 6.5 single-site plus multisite tests on PHP 8.1.34/MySQL 5.7.44 passed. Hosted workflow definitions are not immutable hosted-run evidence. (`BUG-0015`, `BUG-0016`, `BUG-0017`) |
| QA-002 | Unit-test window math, hashes, sanitizer, token, retry, mapping, and filters | `PLANNED` | QA-001, relevant modules | Include DST and half-hour timezone cases. |
| QA-003 | Integration-test adapters, queue, cron, mail, storage, webservers, and object cache | `PLANNED` | QA-001, relevant modules | Pin tested plugin versions. |
| QA-004 | Functional-test schedules, exports, delivery, expiry, regeneration, workflow, and automation | `PLANNED` | QA-001, relevant modules | Verify AccessScope in every path. |
| QA-005 | Run security test matrix and dependency audit | `PLANNED` | SEC-009, QA-001 | Resolve or explicitly accept findings before release. |
| QA-006 | Run 1k/10k/100k data and concurrency performance tests | `PLANNED` | QA-001, relevant modules | Record environment and thresholds. |
| QA-007 | Test install, upgrade, deactivation, uninstall, multisite, i18n, and accessibility lifecycle | `PLANNED` | QA-001, FND-004–FND-008 | Cover delete-data OFF and ON. |
| QA-008 | Trace every acceptance criterion to passing evidence | `PLANNED` | QA-002–QA-007 | No release with unverified mandatory criterion. |

## RELEASE — Packaging and production readiness

| ID | Submodule / task | State | Depends on | Notes / evidence |
|---|---|---|---|---|
| RELEASE-001 | Define semantic versioning and release branch/tag policy | `PLANNED` | ARCH-005 | Record breaking schema/config changes. |
| RELEASE-002 | Produce reproducible production package without development-only files | `PLANNED` | FND-002, QA-008 | Include production dependencies and license notices. |
| RELEASE-003 | Verify clean install and upgrade using the packaged artifact | `PLANNED` | RELEASE-002, QA-007 | Test the artifact, not only the source tree. |
| RELEASE-004 | Complete security/privacy/operational release checklist | `PLANNED` | QA-005, QA-008, HEALTH-001–HEALTH-005 | No unresolved critical/high defect. |
| RELEASE-005 | Finalize changelog, upgrade notes, administrator guide, and support matrix | `PLANNED` | RELEASE-003, FND-008 | Move `Unreleased` entries to the release version. |
| RELEASE-006 | Publish release and record artifact checksum | `PLANNED` | RELEASE-004, RELEASE-005 | Publication requires explicit user authorization. |

## Blockers and open scope decisions

No confirmed implementation blocker remains. The following choices stay within their owning future tasks:

| Decision | Owning task | Current treatment |
|---|---|---|
| Advanced CF7 DB product/version scope | CF7-006 | Evaluate before adapter work. |
| Custom recurrence syntax and validation limits | SCHED-005 | Define before UI/API implementation. |
| Default operational caps beyond the documented examples | Relevant module/settings tasks | Benchmark, document, then choose. |

## Next executable task

`DB-001` is the recommended `READY` task: define schema versioning and the per-site migration-state model before implementing the migration framework. `SEC-001`, `ADAPTER-001`, `LOG-001`, `EMAIL-001`, and `HEALTH-001` are also dependency-ready but remain sequenced after the database foundation unless the user reprioritizes them.
