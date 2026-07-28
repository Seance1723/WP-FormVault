# WP FormVault Project Memory

Last updated: 2026-07-28
Memory status: Current for the verified foundation, dependency baseline, base container/bootstrap, enforced module architecture, engineering-quality/CI toolchain, accepted database contract, and implemented target-zero schema coordinator.

## How to use this memory

This file is a concise, maintained map of the project. It is not proof that a feature exists. Every statement is labeled by context:

- **Current** — verified in the workspace now.
- **Required** — specified by the approved plan/user but not necessarily implemented.
- **Planned** — intended work recorded in `TASKS.md`.
- **Open** — a decision or fact still needs evidence.

When status is unclear, treat it as unimplemented and inspect the code/tests before answering.

## Sources of truth and conflict rules

| Question | Authoritative source |
|---|---|
| What the product must do | The user's latest explicit instruction, then [IMPLEMENTATION_PLAN.md](./IMPLEMENTATION_PLAN.md) |
| What work is currently started/blocked/complete | [TASKS.md](./TASKS.md) |
| What is actually implemented | Workspace code, migrations, installed dependency manifests, and reproducible tests |
| What changed over time | [CHANGELOGS.md](./CHANGELOGS.md) |
| What failed, why, and how recurrence is prevented | [BUGS.md](./BUGS.md) |
| Concise project orientation | This file, derived from the sources above |

If documents disagree:

1. Do not merge the conflicting claims into a guess.
2. For requirements, use the latest explicit user decision and update the plan/task/memory records.
3. For implementation status, code plus reproducible evidence wins; the task register must be corrected.
4. Record material corrections in the changelog.
5. If the discrepancy caused a defect, add it to the bug register.

## Anti-hallucination protocol

Before claiming a feature is implemented, supported, secure, tested, or production-ready:

1. Find the owning task and check its state.
2. Inspect the relevant code/configuration/migration.
3. Find passing evidence for the exact claim.
4. Verify the evidence applies to the current source state and environment.
5. State limitations or missing evidence plainly.

Automated documentation checks must assert stable product, platform, and dependency contracts. They must not depend on mutable task states or lifecycle prose unless that lifecycle state is the contract being tested (`BUG-0011`).

Never:

- Treat a plan item as implemented.
- Infer adapter support from a hook name listed in the plan; verify current source-plugin APIs and tested versions.
- Claim email delivery from `wp_mail()` returning true; that only confirms handoff.
- Claim public downloads are “secure” without naming their access mode and controls.
- Claim capture-mode sources can recover submissions missed while WP FormVault was inactive.
- Claim an expired report can always be reproduced if its report-record snapshot has been pruned.
- Put secrets, raw tokens, passwords, personal form values, or unredacted production data in project-control documents.

## Current project state

**Current:** The workspace contains the canonical plan/control documents, verified WordPress foundation, isolated Composer dependency build, explicit base service container, fail-closed composition root, enforceable module-boundary contract, installed engineering-quality/test toolchain with GitHub Actions definitions, accepted machine-readable database design, and implemented target-zero per-site schema coordinator. Bootstrap loads the isolated dependency tree, registers Action Scheduler without early API calls, verifies runtime compatibility, then installs/verifies the two migration control tables and runs the fenced readiness gate. A healthy current site reaches schema version zero `ready`; missing, invalid, active, failed, background, or newer state blocks product startup. The other 32 catalog tables and numbered migrations 1-4 remain unimplemented, as do capability/site provisioning, product hook registrars, queue integration, production ZIP, and release. Workflow files are locally verified, but no immutable hosted GitHub Actions run is recorded.

**Current completed controls:**

- Full hardened source plan reviewed.
- Product identity changed to WP FormVault.
- Canonical plan stored as `IMPLEMENTATION_PLAN.md`.
- Task, changelog, bug, and memory maintenance rules established.
- Root `AGENTS.md` instructs future contributors/agents to follow and synchronize these controls.
- `wp-formvault.php` provides the guarded WordPress entry boundary and foundation constants.
- `includes/Autoloader.php` provides validated, idempotent `WPFormVault\*` class resolution.
- `docs/architecture/dependency-policy.md` records verified upstream constraints, namespace isolation, Action Scheduler coexistence, and reproducible production packaging requirements.
- `composer.json` and `composer.lock` define the PHP 8.1-compatible runtime graph and build-only Strauss tooling.
- `tools/run-dependency-build.ps1` builds through the digest-pinned PHP 8.1.34 / Composer 2.10.2 image, without modifying the shared local WordPress container.
- The lock-only dependency build validates/audits the lock, verifies platform requirements, generates the isolated tree, stages Action Scheduler, generates notices, lints 722 PHP files, and passes conflict plus XLSX/ZIP/Complex/Matrix runtime tests.
- `docs/architecture/service-container-and-module-boundaries.md` defines the composition root, fail-closed startup gates, container scope, public module surfaces, interaction rules, and ownership boundaries.
- `docs/architecture/module-boundaries.json` records the 15-module/63-edge inward dependency graph; `tools/verify-architecture.php` validates that graph and current PHP imports.
- `includes/Core/ServiceContainer.php` implements explicit values, lazy shared factories, transient factories, aliases, type checks, circular/missing/duplicate detection, site identity, and immutable freeze.
- `includes/Core/Plugin.php` implements the idempotent composition root and ordered dependency, compatibility, and schema gates; `tools/verify-bootstrap.php` exercises its positive and negative paths under PHP 8.1.
- `docs/architecture/engineering-quality-and-ci-policy.md` and `quality-policy.json` define the implemented coding rules, static-analysis floor, test taxonomy, and nine-lane CI matrix.
- The root lock contains PHPUnit 9.6.35, PHPUnit Polyfills 3.1.2, WPCS 3.4.1 on PHPCS 3.13.5, PHPStan 2.2.6, and WordPress 6.5.7 stubs. PHPCompatibilityWP 3.0.0-alpha2 is isolated with PHPCS 4.0.1 under `tools/phpcompatibility/` because its current PHPCS requirement conflicts with stable WPCS.
- Unit and WordPress-backed PHPUnit bootstraps are separate. The WordPress runner downloads an exact/latest/trunk runtime into a validated temporary directory, installs the matching `wp-phpunit` harness, uses an ephemeral environment-configured database, logs resolved versions, and supports explicit single-site/multisite suites.
- `.github/workflows/quality.yml`, `forward-compatibility.yml`, and `release-candidate-performance.yml` implement the nine policy lane IDs. `tools/verify-quality-policy.php` validates the stable contract; `tools/verify-qa-tooling.php` cross-checks the locked tools, configurations, suite names, Docker prerequisites, and workflow job identities.
- `docs/architecture/database-schema-and-migration-state.md` and `database-schema-policy.json` freeze 34 per-site suffixes, 402 typed columns, 55 application relations, 21 unique candidate keys, schema stages 0–4, and the fail-closed per-site state/lease model; `tools/verify-database-schema-policy.php` enforces them.
- Local QA evidence on 2026-07-27: WPCS and PHPCompatibilityWP passed 37 first-party PHP files; PHPStan level 8 reported no errors; PHPUnit unit tests passed; actionlint 1.7.12 accepted all workflows; WordPress 6.5 single-site integration/security and multisite integration/functional harnesses passed under PHP 8.1.34 and MySQL 5.7.44. This local evidence does not substitute for hosted lane evidence.
- `includes/Core/SchemaGate.php` composes the reviewed wpdb boundary, exact control-plane installer, contiguous migration registry, optimistic state store, 120-second lease manager, and bounded coordinator. Activation and ordinary `plugins_loaded` checks share the same runner; target zero performs no numbered domain migration.
- Local DB-002 evidence on 2026-07-28: aggregate QA passed 59 first-party PHP files, PHPStan level 8 reported no errors, and 13 unit tests/29 assertions passed. WordPress 6.5 database suites passed on MySQL 5.7.44 (single-site integration/security 11 tests/48 assertions; multisite integration/functional 11 tests/47 assertions) and MariaDB 10.4.34 (integration 10 tests/46 assertions). This local evidence does not substitute for hosted lane evidence.
- `tools/verify-foundation.php` passes in the repository-owned PHP 8.1 container.
- `tools/verify-task-graph.ps1` validates all 198 tasks and 325 dependency edges with no missing references or cycles.

**Current blockers:** None confirmed for `DB-003`. Only the two DB-002 control tables are runtime-installed; no numbered domain table exists yet.

**Recommended next task:** `DB-003` - add migration version 1 for forms, fields, submissions, canonical snapshots, and indexed values using the completed coordinator.

## Project identity

| Item | Value | Status |
|---|---|---|
| Display name | WP FormVault | Required/frozen |
| Plugin slug | `wp-formvault` | Required/frozen |
| Bootstrap file | `wp-formvault.php` | Required/frozen |
| Text domain | `wp-formvault` | Required/frozen |
| PHP namespace root | `WPFormVault` | Required/frozen |
| Technical prefix | `wpfv` | Required/frozen |
| Database table suffix prefix | `wpfv_` after the current site's `$wpdb->prefix` | Required/frozen |
| Capability prefix | `wpfv_` | Required/frozen |
| Hooks/filters prefix | `wpfv_` | Required/frozen |
| Public download query key | `wpfv_download` | Required/frozen |
| Current development version | `0.0.0-dev` | Current; unreleased scaffold only |

The example table `wp_wpfv_submissions` means `$wpdb->prefix . 'wpfv_submissions'`; never hard-code `wp_`, especially on multisite.

## Supported platform baseline

| Component | Minimum | Status |
|---|---:|---|
| WordPress | 6.5 | Required/frozen by user decision on 2026-07-27 |
| PHP | 8.1, 64-bit | Required/frozen; local PHP 8.1 QA passed and PHP 8.2–8.5 hosted lanes are configured |
| MySQL | 5.7 | Required/frozen; WordPress 6.5 local minimum lane passed on MySQL 5.7.44 |
| MariaDB | 10.4 | Required/frozen; WordPress 6.5 local DB-002 integration passed on MariaDB 10.4.34; immutable hosted evidence pending |
| Action Scheduler | 3.9.3 | Required dependency baseline |

The WordPress minimum intentionally changed from the original 6.2 plan baseline so the bundled Action Scheduler 3.9.3 dependency is supported.

## Current foundation implementation

- `wp-formvault.php` is the only WordPress plugin entry file.
- Runtime PHP files stop when `ABSPATH` is not defined.
- The entry file declares the WordPress/PHP minimums and identity/path constants, then registers the internal autoloader.
- The entry file delegates once to `WPFormVault\Core\Plugin`, which creates the request/site graph and runs fail-closed bootstrap gates.
- `WPFormVault\Autoloader` maps namespace-relative class names into `/includes`, validates every namespace segment, ignores unrelated/unsafe names, and registers only once.
- The bootstrap loads the isolated runtime autoloader and registers the unprefixed Action Scheduler arbitration loader early. It does not call Action Scheduler APIs; runtime queue integration remains `QUEUE-001`.
- The real schema gate resolves table names from the active `$wpdb->prefix`, idempotently bootstraps `wpfv_schema_version` and `wpfv_locks`, and refuses product startup unless target/version/state/lease/postconditions are ready.
- Module directories mirror the hardened plan: Core, Adapters, Sync, Submissions, Workflow, Reports, Scheduling, Email, Downloads, Privacy, Notifications, Audit, Rest, Health, and Admin, plus assets, languages, and templates.
- The standalone verifier uses only non-production WordPress stubs and must not be loaded by WordPress.

## Service-container and module-boundary baseline

**Current base implementation and architecture contract:**

- `WPFormVault\Core\Plugin` is the sole application composition root and the only production class allowed to import concrete implementations from every module for wiring.
- `WPFormVault\Core\ServiceContainer` is a small project-owned container: explicit factories/values, no reflection auto-wiring, lazy request/site-scoped shared services, controlled transient factories, aliases, type enforcement, circular/missing/duplicate detection, and freeze before hook registration.
- Feature services never receive the container, a generic resolver, or `Core\Plugin`; they receive exact constructor dependencies.
- Boot is fail closed in this order: entry/autoload guards, dependency availability, early Action Scheduler registration without early API calls, platform compatibility, per-site schema/migration gate, definition validation/freeze, then idempotent product-hook registration.
- Cross-module imports are limited to the provider's `Contracts`, `DTO`, `Events`, and `Value` namespaces. Concrete cross-module wiring is restricted to the composition root.
- `Core` is the innermost module. Dependencies point to strictly lower layers. `Admin` and `Rest` are terminal inbound modules and no feature module may depend on them.
- AccessScope remains a repository/query-layer concern; presentation and transport modules cannot construct SQL or bypass scope.
- Container sharing is confined to one PHP request and current site. Multisite blog switches require a target-site graph and guaranteed restoration.
- Module graph changes must update the architecture document and machine-readable graph and pass `php tools/verify-architecture.php`.
- `tools/verify-bootstrap.php` proves gate order, dependency loading, sanitized diagnostics, constructor-injected substitutes, hook idempotency, container failure modes, and independent site graphs. Its production-like stub deliberately lacks `wpdb` and therefore must stop at `blocked_schema`; real WordPress database behavior is proven separately by integration tests.

The accepted graph contains 15 modules and 63 explicitly allowed dependencies. Omitted edges are forbidden; being acyclic alone does not authorize a dependency.

## Database schema and migration-state baseline

**Current DB-001 contract with DB-002 control-plane runtime implemented; domain schema remains planned under DB-003 through DB-007:**

- The authoritative catalog is `docs/architecture/database-schema-policy.json`; the human contract is `docs/architecture/database-schema-and-migration-state.md`.
- Exactly 34 site-local tables resolve from `$wpdb->prefix . 'wpfv_' . $suffix`. Runtime code never hard-codes `wp_`, and rows do not duplicate a `site_id`.
- The catalog contains 402 typed columns, 55 application-enforced relations, and 21 unique candidate keys. Database foreign keys are not part of the portable contract.
- UTF-8 JSON uses application-validated `LONGTEXT`; runtime `DATETIME` values are UTC; nullable timestamps use SQL `NULL`; SHA-256 digests use `BINARY(32)`.
- The two idempotently bootstrapped control tables are `wpfv_schema_version` and `wpfv_locks`. DB-002 installs/verifies them through `dbDelta()` and exact metadata postconditions; they do not advance the numbered domain schema.
- Planned numbered stages are version 1 submission index (`DB-003`), version 2 schedules/reports (`DB-004`), version 3 workflow/automation (`DB-005`), and version 4 operations/access (`DB-006`).
- Schema versions are contiguous non-negative integers independent of plugin SemVer. Fresh install and upgrade use the same chain; the installed version advances only after postconditions pass; automatic downgrade is forbidden.
- Per-site states are `uninitialized`, `pending`, `running`, `awaiting_background`, `failed`, `ready`, and `blocked_newer`. Schema-dependent work runs only when installed equals target, state is ready, and no active migration lease exists.
- The `schema_migration` lease stores only a SHA-256 owner-token hash. Production duration is 120 seconds; acquisition is atomic, state writes require the unexpired owner hash/fence plus optimistic row version, and orderly release retains an expired row so subsequent fences remain monotonic.
- Raw download tokens are never stored. Generated-file records contain opaque relative storage keys. IP/user-agent fields are nullable/privacy-gated, and report/audit relations declare anonymization behavior.
- Critical uniqueness includes source submission identity, binary report/delivery/job idempotency keys, binary token hashes, lock keys, source cursors, and natural join/configuration keys.
- `DB-007` owns final physical indexes and query-plan proof. DB-001 candidate keys are required inputs, not proof that indexes exist.
- `tools/verify-database-schema-policy.php` currently passes with 34 tables, 402 columns, 55 relations, 21 unique keys, and contiguous stages 0–4.

## Engineering quality and CI baseline

**Current implemented policy and QA-001 tooling:**

- PHP_CodeSniffer applies `WordPress-Core`, `WordPress-Docs`, `WordPress-Extra`, and `PHPCompatibilityWP` with the PHP range `8.1-` to first-party PHP.
- PHPStan analyzes `wp-formvault.php` and `includes/` at level 8 with WordPress/Action Scheduler stubs, unmatched ignores reported, and no generated baseline.
- The test tree separates Unit, Integration, Functional, Security, Performance, Support, and synthetic/redacted Fixtures. Unit tests do not load WordPress; integration tests use dedicated ephemeral databases.
- The GitHub Actions matrix has nine lane IDs, eight blocking: minimum/latest quality, exact WordPress 6.5.0 + PHP 8.1 on MySQL 5.7 and MariaDB 10.4, current WordPress across the upstream-supported PHP band, current MariaDB multisite, minimum dependency build, nightly WordPress trunk, and release-candidate performance.
- Rolling labels resolve at job start and the exact WordPress/PHP/database/tool versions must appear in durable logs. Missing, skipped, cancelled, or unresolved blocking lanes count as failures.
- The 2026-07-27 upstream snapshot records WordPress 7.0.2 as latest stable, PHP 8.2–8.5 as supported, PHP 8.1 as end-of-life legacy product coverage, and PHPUnit 9 as the common runner required by the official WordPress 6.5–7.0 test-suite matrix (`BUG-0015`).
- `tools/verify-quality-policy.php` verifies the stable contract and `tools/verify-qa-tooling.php` verifies installed locks/configurations/workflow identities. Local tools and minimum WordPress/MySQL harnesses have passing evidence; do not claim hosted CI passed until immutable GitHub Actions runs exist.

See `docs/architecture/engineering-quality-and-ci-policy.md` for rationale and `docs/architecture/quality-policy.json` for the authoritative machine-readable matrix.

## Dependency and packaging baseline

**Current implemented dependency baseline:**

- PhpSpreadsheet uses root constraint `~5.8.1`; 5.8.1 is the last upstream release line supporting the PHP 8.1 minimum.
- ZipStream uses root constraint `~3.0.2` while PHP 8.1 is supported because newer 3.x minors raise the PHP minimum.
- Action Scheduler is fixed at 3.9.3 and sets the WordPress minimum to 6.5.
- Action Scheduler 4.0.0 is the current upstream release but requires WordPress 6.8 and contains breaking behavior changes; it is intentionally excluded from the selected profile.
- Composer resolves against platform PHP 8.1.0, the lock file is committed, stable packages are required, and production never updates or downloads dependencies.
- Generic Composer packages and their production transitive closure are rewritten under `WPFormVault\Vendor\` into `vendor-prefixed/` using the build-only Strauss 0.28.1 tool.
- The current Strauss/lock combination requires a count-locked post-prefix correction for `Complex`, `Matrix`, and `ZipStream` return types whose class names equal their root namespaces; real runtime smoke tests guard the correction (`BUG-0010`).
- Action Scheduler is deliberately not prefixed. It is packaged under `libraries/action-scheduler/`, loaded before `plugins_loaded` priority 0, and called only after its initialization boundary.
- Production artifacts include the isolated runtime tree, the selected Action Scheduler library, and dependency/license notices; they exclude raw generic `vendor/`, development packages, and the prefixing tool.
- PHP must be 64-bit and provide `ctype`, `dom`, `fileinfo`, `filter`, `gd`, `iconv`, `libxml`, `mbstring`, `simplexml`, `xml`, `xmlreader`, `xmlwriter`, `zip`, and `zlib`.

**Current locked runtime versions:** Composer PCRE 3.4.0, ZipStream 3.0.2, MarkBaker Complex 3.0.2, MarkBaker Matrix 3.0.1, PhpSpreadsheet 5.8.1, PSR Simple Cache 2.0.0, and Action Scheduler 3.9.3. Strauss 0.28.1 and its closure are build-only.

**Current local evidence:** The shared `localdev_php_apache` container still lacks `gd` and Composer, but it is no longer a build blocker. The Windows host Composer/PHP path is also non-authoritative: its global `COMPOSER` override is directory-valued and its PHP lacks required extensions (`BUG-0018`). The repository-owned image provides 64-bit PHP 8.1.34, Composer 2.10.2, and every required extension. Generated `vendor/`, `vendor-prefixed/`, and staged Action Scheduler directories are ignored and must be regenerated from the committed lock; they are not release artifacts.

See `docs/architecture/dependency-policy.md` for the complete policy and evidence links.

Manifest constraints select reviewed compatible lines (`~5.8.1`, `~3.0.2`, and `~3.9.3`). `composer.lock` and build verification define the exact shipped versions; no production installation performs dependency updates.

The measured fully clean Docker Desktop/Windows bind-mount dependency build is approximately 306 seconds. Callers use a minimum 420-second limit and inspect for a still-running project build container after interruption before retrying (`BUG-0013`).

## Product concept

**Required:** WP FormVault is a centralized reporting, submission-management, workflow, scheduling, export, and delivery layer for existing WordPress form plugins. It does not replace form builders.

It must:

- Detect supported forms through adapters.
- Normalize submissions into a cross-plugin index.
- Manage and search submissions subject to query-layer access control.
- Generate manual and scheduled XLSX/CSV reports.
- Package referenced uploads into streamed ZIP files.
- Deliver attachments or controlled download links.
- Support workflow metadata, saved views, audit history, notifications, and automation.
- Preserve report history while cleaning temporary generated files.
- Handle privacy export/erasure, schema upgrades, uninstall, multisite, and health diagnostics.

## Core architectural distinction

There are two adapter types:

1. **Datastore adapter:** The form plugin persists entries. That source remains authoritative for original fields. WP FormVault indexes them, receives real-time hooks, and performs bounded cursor-based reconciliation. Edit/delete write-back exists only when the adapter declares and implements the capability.
2. **Capture-mode adapter:** The source does not expose a persisted entry store. WP FormVault captures the submission at submit time, and its index is the only copy. Historical reconciliation is impossible. The UI must warn that submissions received while WP FormVault was inactive could not be captured.

Do not blur these two modes in UI, logs, documentation, or support claims.

## Required initial and later integrations

| Source | Required adapter treatment | Delivery phase |
|---|---|---|
| Contact Form 7 without storage | Capture mode | Initial/MVP |
| CFDB7 | Datastore | Initial/MVP |
| Flamingo | Datastore | Initial/MVP |
| Advanced CF7 DB | Open pending product/version evaluation | Initial scope decision |
| WPForms | Datastore | Later integration phase |
| Gravity Forms | Datastore | Later integration phase |
| Fluent Forms | Datastore | Later integration phase |
| Ninja Forms | Datastore | Later integration phase |
| Formidable Forms | Datastore | Later integration phase |
| Elementor without Submissions | Capture mode | Later integration phase |
| Elementor with Submissions | Datastore, selected by runtime feature detection | Later integration phase |

Hook names in the plan are implementation leads, not current-version proof. Verify APIs and pin the versions exercised by tests.

## Normalized data model

**Required:** Custom per-site tables provide a hybrid model:

- A submission row contains stable source identity and operational metadata.
- A canonical JSON snapshot contains the full normalized record and supports report reproducibility.
- Selective EAV rows exist only for administrator-marked filterable/sortable fields.
- Optional indexed generated columns may optimize hot JSON fields where the database supports them.
- Workflow, report, schedule, access, audit, download, sync, notification, and automation data live in dedicated tables.

Critical identity invariant:

```text
UNIQUE (source_plugin, source_form_id, source_submission_id)
```

This makes repeated real-time hooks and reconciliation upserts idempotent.

Canonical change hash:

```text
sha256(canonical_json(normalized_fields))
```

Canonicalization sorts keys, normalizes allowed values, excludes volatile/system fields, and handles multi-values deterministically. File fields hash attachment/reference identity plus filename and size as defined by the plan.

## Time and scheduling invariants

- Store runtime timestamps in UTC.
- Capture each schedule's site timezone when it is created.
- Compute human calendar boundaries in that captured timezone and convert them to UTC for queries.
- Use half-open periods `[start, end)` everywhere.
- Period membership is based on `submitted_at`, not edit time.
- Alternate-week schedules require a fixed anchor date.
- Quarterly means calendar quarters unless an explicit fiscal offset is configured.
- Action Scheduler is the primary queue; WP-Cron is a trigger; server cron is the recommended reliability backstop.
- A heartbeat enumerates every missed intended window and enqueues each with its original boundaries.
- Retries preserve the intended period.
- Overlapping work is controlled by leases/locks and idempotency.

Report run idempotency is derived from schedule identity plus period start/end. Retried jobs must not generate or email duplicates.

## Reporting and export invariants

- Reports select fields by stable source field ID, never labels.
- Filters compile to parameterized queries against indexed paths.
- Supported outputs include XLSX, streaming CSV, separate files, workbook tabs, unified mapped sheets, ZIP bundles, and manual selected/date-range exports.
- XLSX generation uses PhpSpreadsheet; CSV generation streams.
- Large jobs run in the queue and use temp files/batches.
- Exports intentionally exclude formulas, charts, pivots, macros, and analytical worksheets.
- Report history and configuration/data-set snapshots are distinct from temporary generated files.
- Expired reports offer:
  - **Reproduce:** rebuild from the stored report-record snapshot.
  - **Refresh:** build a new report from current data while preserving old history.

## Spreadsheet safety invariant

A single CellSanitizer must cover XLSX text cells, CSV cells, and ZIP manifests. After accounting for leading whitespace, neutralize text beginning with:

```text
=  +  -  @  TAB  CR
```

This includes DDE-style payloads. Write text with explicit string typing and disable unsafe automatic type inference. Preserve genuine numeric/date types. Sanitize sheet names and filenames, enforce Excel's 31-character sheet limit, deduplicate names, and reject unsafe hyperlink schemes.

## Uploaded-file and ZIP invariants

- Store references to original uploads; do not duplicate them during indexing.
- Resolve the real path before reading.
- Permit only paths within allow-listed upload roots.
- Reject traversal and symlink escapes.
- Validate existence/readability before inclusion.
- Stream ZIP output.
- Enforce per-file size, cumulative size, and file-count caps.
- Missing/rejected files are logged and included as sanitized manifest omissions rather than crashing the entire report.

## Download and storage invariants

- Authenticated download is the default and requires capability plus AccessScope.
- Public token mode is opt-in per schedule with explicit risk acknowledgment.
- Duplicating a schedule never copies that acknowledgment.
- Tokens contain 256 random bits. Persist only `sha256(raw_token)`.
- Raw tokens must not appear in the database or logs.
- Support optional password hash, IP binding, download cap, expiry, revoke, throttling, and brute-force cooldown.
- A revoked route returns 410 Gone.
- Serve files only through a controlled endpoint with safe headers and no internal path/ID disclosure.
- Generated XLSX/CSV/ZIP/temp files default to 30-day retention.
- Report/delivery/audit history remains after generated files are cleaned.
- Store generated files outside the webroot when possible; otherwise enforce and document Apache and Nginx denial controls.

## Email invariants

- Validate every To/CC/BCC/failure recipient.
- Strip/reject CR/LF in sender name, reply-to, subject, and other headers.
- Escape placeholder values for their HTML/plain-text context.
- Send via queued jobs.
- When an attachment exceeds the configured conservative limit, preserve the report and use a secure-link fallback with an audit/log entry.
- `wp_mail()` success means handoff, not confirmed recipient delivery.
- Retry and manual resend must honor idempotency and record every attempt.

## Authorization invariants

Planned roles:

- Administrator — full control.
- Report Manager — schedules, reports, delivery, assigned forms.
- Report Viewer — view/download allowed records; no edit/delete.
- Form Manager — assigned forms and related submissions/schedules/reports.

The AccessScope service must constrain every non-admin data path at the repository/query layer:

- Lists and details
- Search/filter/sort
- Saved views
- Dashboard metrics
- Exports
- Bulk operations
- Reports
- Authenticated downloads

UI hiding is not authorization. Shared views and templates never widen access. Sensitive fields also require `wpfv_view_sensitive_fields`.

## Security invariants

- Nonce plus capability on every mutation.
- Real permission callbacks on all REST/AJAX endpoints.
- Prepared SQL only.
- Schema-validate and sanitize input; context-escape output.
- No unsafe deserialization; legacy serialized source metadata must reject objects.
- Logos/branding are selected by WordPress media attachment ID; no arbitrary server-side URL fetch.
- Redact PII from operational logs.
- Never log raw tokens/passwords.
- Audit security-relevant permission, privacy, download, report, and destructive actions.

## Privacy and lifecycle invariants

- IP address and user-agent storage default OFF.
- WordPress personal-data exporter covers indexed submissions, workflow metadata, and report references.
- Erasure removes/anonymizes subject data from snapshots, indexed values, and report records.
- Audit history survives erasure only with subject identifiers replaced by a stable pseudonym.
- Snapshot pruning is separate, opt-in, and audited because snapshots hold PII.
- Deactivation preserves data, unschedules events, and clears locks.
- Uninstall deletes data only when the administrator previously enabled “Delete all plugin data on uninstall”; default is OFF.
- On multisite, data and capabilities are per site and uninstall honors the setting across sites.

## Observability invariants

WP Site Health and the admin UI must expose:

- Scheduler/cron heartbeat health
- Server-cron recommendation when traffic-based WP-Cron is unreliable
- Queue backlog, failed/stuck jobs, and safe reclaim
- Writable/protected storage status
- Per-integration adapter type, validated version status, capabilities, last sync, and capture/reconciliation health
- Per-schedule last run, next run, retries, failures, and storage usage

Capture mode shows capture-integrity health; reconciliation must display as not applicable.

## Major modules

```text
Core/Foundation
├─ Database and migrations
├─ Capabilities, AccessScope, REST/AJAX security
├─ Queue, locks, idempotency, logging
Adapters and Sync
├─ Typed adapter registry and normalization
├─ Real-time indexing
├─ Datastore reconciliation
└─ Capture-integrity monitoring
Product Services
├─ Submissions and workflow
├─ Reports, XLSX/CSV, mappings, snapshots
├─ Scheduling and catch-up
├─ ZIP/files, downloads, email
├─ Saved views, bulk actions
├─ Notifications and automation
└─ Audit, privacy, health
Admin
└─ Dashboard, pages, wizard, settings, integration/permission screens
```

## Delivery order

The hardened plan contains eleven phases:

1. Foundation, schema, security scaffolding, queue, locks, logging, AccessScope.
2. CF7 capture plus CFDB7/Flamingo datastore, indexing, initial submission UI.
3. Manual reporting, filtering, XLSX/CSV safety, email, report snapshots/history.
4. Calendar-safe scheduling, catch-up, retry, no-data behavior.
5. Secure upload ZIP, download modes, throttle, cleanup, regeneration.
6. Workflow and batched bulk actions.
7. Source-aware editing/deletion, audit, report impact, reproduce/refresh.
8. Saved views and unified mappings.
9. Notifications and safe automation.
10. Additional adapters with version/capability detection.
11. Dashboard, privacy, multisite, health, performance, security, release hardening.

`TASKS.md` is more granular and controls the actual state.

## Non-goals

- Replacing form builders.
- Recovering missed submissions from capture-only sources that never stored them.
- Excel formulas, charts, pivots, macros, or analytical worksheets.
- Directly serving private generated files from predictable public paths.
- Treating email handoff as guaranteed delivery.
- Making public unauthenticated download links the default.
- Copying source-upload files into the normalized index.
- Allowing shared views/templates to expand authorization scope.

## Open decisions

These are deliberately not guessed:

- Advanced CF7 DB versions/products included in the initial adapter scope.
- Exact custom recurrence grammar and safety limits.
- Final operational defaults for batch sizes, row caps, ZIP caps, queue concurrency, log retention, and brute-force thresholds after benchmarking.

## Decision log

### 2026-07-27 — Project identity

The product name is **WP FormVault**. The canonical technical identifiers are `wp-formvault`, `WPFormVault`, and the `wpfv` prefix. The plan and control documents were updated before implementation began, preventing a later identifier migration.

### 2026-07-27 — Mandatory project-control records

`TASKS.md`, `CHANGELOGS.md`, `BUGS.md`, and `MEMORY.md` are mandatory throughout implementation. A task cannot be complete without relevant tests/evidence and synchronized documentation.

### 2026-07-27 — Current-state honesty

The implementation plan describes required behavior, not existing behavior. Until code and passing verification exist, features remain planned.

### 2026-07-27 — Pre-Composer foundation

The unreleased scaffold uses version `0.0.0-dev` and a small internal `WPFormVault` autoloader so module classes can be added safely before dependency packaging is defined. Product service startup remains separate in `FND-003`.

### 2026-07-27 — Task dependency graph is enforced

After circular prerequisites were found, task dependency changes now require `tools/verify-task-graph.ps1`. The verifier rejects duplicate task IDs, missing dependency references, and cycles.

### 2026-07-27 — Composer dependencies are isolated at build time

Generic runtime dependencies will be generated under `WPFormVault\Vendor\` and shipped from `vendor-prefixed/`. Composer's lock and a PHP 8.1 platform constraint control resolution. No runtime dependency download or update is permitted.

Action Scheduler remains unprefixed because it intentionally arbitrates one active version across the WordPress site. WP FormVault must load its copy early, defer API calls until initialization, feature-detect optional APIs, and tolerate another compatible registered version winning.

### 2026-07-27 — WordPress 6.5 and Action Scheduler 3.9.3 selected

The user selected the approved compatibility profile: WordPress 6.5+ and Action Scheduler 3.9.3. This replaces the original WordPress 6.2 baseline. PHP remains 8.1+ on 64-bit architecture, with MySQL 5.7+ or MariaDB 10.4+.

Action Scheduler 4.0.0 was subsequently confirmed as the current upstream release, requiring WordPress 6.8 and adding breaking uniqueness/retention behavior. Version 3.9.3 remains intentionally pinned as the latest line compatible with the user-selected WordPress 6.5 floor. It must not be described as the current upstream release.

### 2026-07-27 — Repository-owned dependency build

WP FormVault resolves and verifies dependencies in a digest-pinned PHP 8.1.34 / Composer 2.10.2 image rather than changing the shared WordPress container. Normal builds install only from the committed lock; lock updates require the explicit `-UpdateLock` switch.

The current lock has seven runtime packages. Generic libraries are isolated under `WPFormVault\Vendor`; Action Scheduler 3.9.3 is staged unprefixed. The build fails on missing extensions, invalid/stale locks, advisories, platform mismatches, generated syntax errors, unprefixed conflicts, wrong package versions, ambiguous homonym return types, or invalid XLSX output.

### 2026-07-27 — Explicit composition root and enforceable module graph

`WPFormVault\Core\Plugin` is the sole composition root and `WPFormVault\Core\ServiceContainer` is the explicit, frozen, request/site-scoped container. Feature services use constructor injection and cannot resolve arbitrary services. The accepted 15-module graph permits only documented inward dependencies and public `Contracts`, `DTO`, `Events`, or `Value` surfaces; `Admin` and `Rest` remain terminal inbound modules. A machine-readable graph and source verifier enforce this architecture.

### 2026-07-27 — Base runtime boot was schema-gated during FND-003

At FND-003 completion, the entry file delegated to the idempotent composition root and intentionally stopped at `blocked_schema` through `PendingSchemaGate`. DB-002 subsequently superseded that temporary implementation with the real coordinator recorded below. This entry remains historical evidence and must not be used to describe current startup.

### 2026-07-27 — Quality toolchain and GitHub Actions matrix implemented

QA uses PHPUnit 9.6 for both pure unit and official WordPress 6.5–7.0 harness compatibility, WPCS/PHPCS 3 for WordPress standards, a separate PHPCS 4 installation for the current alpha PHPCompatibilityWP stack, and PHPStan level 8 with minimum-WordPress stubs and no baseline. GitHub Actions is the selected hosted CI provider; all nine accepted lane IDs are defined across blocking, nightly informational, and release-candidate workflows. Workflow presence is not a passing hosted run, so release evidence must still link immutable successful GitHub Actions executions.

### 2026-07-27 — Portable per-site schema and fenced migration state

DB-001 froze a 34-table, 402-column site-local catalog using the active `$wpdb->prefix`, application-enforced relations, portable `LONGTEXT` JSON, UTC runtime timestamps, raw binary hashes, and privacy-aware nullable/anonymization fields. The machine policy allocates domain schema stages 1–4 to DB-003 through DB-006 while reserving DB-002 for idempotent control-plane bootstrap and the runner.

Schema versions are integers independent of plugin releases. Every site owns its singleton state and `schema_migration` lease; no network-global version can mask an unprovisioned site. The gate is ready only when committed and target versions match, state is `ready`, and no migration lease is active. Owner tokens remain hashed and acquisitions are fenced. Failed, active, background, and newer-than-code states all block schema-dependent work. At DB-001 completion this was design-only; DB-002 now implements the target-zero control-plane subset.

### 2026-07-28 — Target-zero schema coordinator implemented

DB-002 replaces `PendingSchemaGate` with a real current-site coordinator. It bootstraps and verifies the exact control tables before locking, derives its target from a contiguous registered chain, re-reads state after atomic lease acquisition, advances versions only after postconditions, refuses downgrades, and emits stable sanitized gate failures. Activation and ordinary `plugins_loaded` checks use the same bounded runner. The current empty registry targets version zero; DB-003 must register the first numbered migration.

Production schema leases last 120 seconds. Raw owner tokens never leave memory. Orderly release retains the row as immediately expired history, so the next acquisition advances the fence and stale owners fail both hash/fence and optimistic-row checks. Failed-run retry expressions are evaluated before mutating state. Local disposable database verification uses private container networking rather than assuming an available host port (`BUG-0020`, `BUG-0021`, `BUG-0024`).

## Memory maintenance checklist

Update this file when any of the following changes:

- Project identifiers or supported platform versions
- Architecture/module boundaries
- Data ownership or schema invariants
- Supported adapters or validated source-plugin versions
- Security, authorization, token, export, file, or email rules
- Schedule/window semantics
- Privacy, retention, uninstall, or multisite behavior
- Operational defaults or production procedures
- Known non-goals or unresolved decisions

After editing, verify the matching tasks and changelog are updated, and ensure no sensitive data entered the document.
