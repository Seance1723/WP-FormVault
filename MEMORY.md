# WP FormVault Project Memory

Last updated: 2026-07-27  
Memory status: Current for the documentation-only project baseline.

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

Never:

- Treat a plan item as implemented.
- Infer adapter support from a hook name listed in the plan; verify current source-plugin APIs and tested versions.
- Claim email delivery from `wp_mail()` returning true; that only confirms handoff.
- Claim public downloads are “secure” without naming their access mode and controls.
- Claim capture-mode sources can recover submissions missed while WP FormVault was inactive.
- Claim an expired report can always be reproduced if its report-record snapshot has been pruned.
- Put secrets, raw tokens, passwords, personal form values, or unredacted production data in project-control documents.

## Current project state

**Current:** The workspace contains the canonical implementation plan and four mandatory project-control documents. There is no WordPress plugin implementation, Composer manifest, database migration, automated test suite, build artifact, or release yet.

**Current completed controls:**

- Full hardened source plan reviewed.
- Product identity changed to WP FormVault.
- Canonical plan stored as `IMPLEMENTATION_PLAN.md`.
- Task, changelog, bug, and memory maintenance rules established.
- Root `AGENTS.md` instructs future contributors/agents to follow and synchronize these controls.

**Next ready task:** `FND-001` — scaffold the plugin foundation. See `TASKS.md`.

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

The example table `wp_wpfv_submissions` means `$wpdb->prefix . 'wpfv_submissions'`; never hard-code `wp_`, especially on multisite.

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

- Exact dependency versions and production packaging approach.
- CI provider and precise WordPress/PHP/database test matrix beyond minimum compatibility.
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
