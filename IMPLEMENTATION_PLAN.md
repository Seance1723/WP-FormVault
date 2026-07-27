# WP FormVault
## Upgraded Implementation Plan (v2 — Hardened)

**Document type:** Technical implementation plan
**Target platform:** WordPress 6.5+ / PHP 8.1+ (64-bit) / MySQL 5.7+ or MariaDB 10.4+
**Primary goal:** WP FormVault is a universal form-submission management, reporting, scheduling, workflow, and Excel-export layer that sits on top of existing WordPress form plugins without replacing them.

> This version supersedes the original plan. It preserves the original scope and intent, but re-analyzes the core logic, hardens security, resolves internal contradictions, and fills structural gaps. Every materially changed or added area is marked **[HARDENED]**, **[FIXED]**, or **[NEW]** so the deltas are auditable.

**Project identity:** display name `WP FormVault`; plugin slug and text domain `wp-formvault`; bootstrap file `wp-formvault.php`; PHP namespace root `WPFormVault`; database, capability, hook, and route identifier prefix `wpfv`.

---

## 0. Revision Notes — What Changed and Why

This section is the audit trail of the review. It exists so reviewers can see the reasoning, not just the result.

### 0.1 Critical logic corrections

| # | Original assumption | Problem | Resolution |
|---|---|---|---|
| C1 | "The original form plugin is always the source of truth." | **Contact Form 7 stores nothing.** CF7 only sends mail. Elementor Forms (and several others) only store submissions when a paid/optional feature is enabled. For these, there is *no* historical source to read or reconcile against. | Introduce **capture-mode adapters** vs **datastore adapters** (§3.4). For capture-mode sources, the plugin's own index is the *only* store, and reconciliation is disabled/replaced with a capture-integrity check. |
| C2 | "Run scheduled reconciliation jobs to detect missed records." | Undefined mechanism; impossible for capture-mode sources; full table scans are O(n) and break at 100k+ entries. | Define **cursor-based incremental reconciliation** with per-adapter cursors, batch windows, and a deletion-detection pass (§5.4). Reconciliation is a *capability*, not a guarantee, and is surfaced per integration. |
| C3 | Calendar windows ("Mon 00:00–Sun 23:59:59") with no timezone anchoring. | DST transitions create non-existent or duplicated local times; `submitted_at` storage unit was unspecified; alternate-week had no anchor epoch; quarter definition undefined. | Store all timestamps in **UTC**; compute window boundaries in the **site timezone** then convert to UTC for querying (§8). Add a fixed **alternate-week anchor date** and explicit **calendar-quarter** definition. |
| C4 | WP-Cron drives schedules; retries cover "failure." | WP-Cron only fires on traffic. On a low-traffic site a weekly report can silently never run. Retries handle *failures*, not *missed windows*. | Add **missed-window catch-up**: on every heartbeat, compute all *intended* periods since last successful run and enqueue any that were skipped, each using its own intended window (§8.4, §9). Recommend **Action Scheduler as primary**, WP-Cron as trigger, server cron as the reliability backstop. |
| C5 | "Anyone with the secure random link can download the ZIP. No login or password." | This is a **public, unauthenticated URL to a bundle of personal data** (resumes, IDs, etc.). Links leak via forwarding, mail logs, proxies, referrers. 30-day life is long. Direct GDPR exposure. | Public mode is retained but is **opt-in per schedule with an explicit risk acknowledgment**, **not the default**. Add password protection, authenticated-download mode, download-count caps, optional IP binding, tokens **stored hashed**, and short-lived signed variants (§14). |
| C6 | Formula-injection escaping covers `= + - @`. | Incomplete: misses leading **tab (0x09)**, **carriage return (0x0D)**, DDE payloads (`=cmd\|...`), and leading whitespace before a trigger char. Prefix-only escaping can corrupt legitimate data. | Full trigger set + **explicit string cell typing** in PhpSpreadsheet, applied to text cells only, with a documented binder strategy (§11.2). manifest.csv and email placeholders are covered too. |
| C7 | EAV table `wp_wpfv_submission_values` (row per field) drives all filtering/sorting. | At 100k submissions × N fields this is millions of rows; filtering/sorting on EAV is slow and hard to index. | Hybrid storage: **canonical JSON snapshot** per submission + **indexed EAV for filterable fields only** + optional generated columns (§6.5). Concrete indexing strategy added. |

### 0.2 Security hardening summary

- Tokens stored as **hashes** (SHA-256), compared with `hash_equals()`; raw token never persisted. (§14)
- REST/AJAX endpoints: **no `permission_callback` may return true unconditionally**; nonce + capability on every write; token-only on the single public download route. (§28.5)
- **Unique index** on `(source_plugin, source_form_id, source_submission_id)` to make real-time indexing idempotent. (§6.6)
- **Never `unserialize()` untrusted source data** — object-injection guard for adapters that read postmeta. (§3.6, §28.6)
- **SSRF guard**: logos/branding by **media attachment ID**, never arbitrary URL fetch. (§11.4, §28.6)
- **Email-header injection** guard: strip CR/LF from names/subjects, validate every recipient. (§13.6)
- **Path-traversal / symlink** guard: `realpath()` containment check against an allow-list of upload roots before any file is read or zipped. (§12.2)
- **Per-schedule run lock** + **idempotency keys** to prevent double generation / double send under overlapping cron. (§9.4)
- **Token brute-force lockout** + rate limiting on the download endpoint. (§14.4)
- **Log redaction**: field values are not written to sync/error logs in the clear. (§30.3)
- **Form/schedule-level access enforced at the query layer** for every non-admin request, not just in the UI. (§23.3)

### 0.3 Gaps filled (new sections)

Multisite strategy (§39), Schema migration/versioning (§40), Uninstall & data lifecycle (§41), GDPR/privacy engineering (§42), Internationalization & accessibility (§43), Observability & health (§44), Field-type normalization spec (§3.5), Concurrency & locking (§9.4), Idempotency (§9.5), and an expanded testing matrix (§35).

---

## 1. Product Overview

The plugin provides a centralized system for collecting, indexing, viewing, editing, organizing, filtering, exporting, scheduling, and emailing form submissions from multiple WordPress form plugins.

It does **not** replace form plugins. Where a form plugin stores its own submissions, that plugin remains the source of truth. Where a form plugin stores nothing (capture-mode sources — see §3.4), the plugin's normalized index becomes the *de facto* store, and this is made explicit to the admin.

The plugin operates as a reporting and workflow layer that:

- Detects supported forms automatically.
- Connects to form-plugin data through typed adapters (datastore or capture-mode).
- Maintains a normalized internal submission index.
- Generates scheduled and manual Excel/CSV reports.
- Sends Excel files by email with graceful large-attachment fallback.
- Bundles uploaded files into downloadable ZIP archives.
- Supports multiple recurring and one-time schedules with calendar-accurate, timezone-safe windows and missed-run catch-up.
- Provides centralized records management with query-level access control.
- Adds internal workflow tools: status, notes, tags, assignees, priority, follow-up dates.
- Maintains permanent report history and audit logs, with GDPR-aware anonymization.
- Removes generated files automatically after a configurable retention period (default 30 days) without deleting historical records.

---

## 2. Confirmed Scope

### 2.1 Supported report types

- One-time scheduled reports
- Immediate manual reports
- Daily, Weekly, Alternate-week, Monthly, Quarterly reports
- Custom recurrence patterns

### 2.2 Export modes

Each schedule independently uses one of:

1. One workbook, one worksheet per form
2. One workbook, unified mapped worksheets
3. Separate Excel files per form
4. Separate Excel files bundled into a ZIP
5. Excel report plus uploaded-files ZIP
6. Manual export of selected records
7. Manual export by custom filters and date range

### 2.3 Delivery rules **[HARDENED]**

- Excel files are attached to the report email **when under the configured size limit**; otherwise the plugin auto-falls-back to a secure download link and logs the change.
- Uploaded files are bundled into a ZIP when records include uploads.
- The ZIP download link is placed in the email body.
- **Default download mode is authenticated** (logged-in user with capability). **Public token mode is opt-in per schedule and requires an explicit risk acknowledgment** (§14).
- Optional password protection and download-count caps are available in any mode.
- Generated Excel/CSV/ZIP files are deleted automatically after the retention period (default 30 days, configurable).
- Report history and delivery logs remain permanent.
- Expired reports can be regenerated (from snapshot to reproduce the original, or from current data to produce an updated report — the admin chooses; see §15.3).

---

## 3. Compatibility Strategy

Adapter-based architecture with two adapter *types* (§3.4).

### 3.1 Initial supported integrations

Phase 1 priority:

- Contact Form 7 *(capture-mode; requires a DB companion for history — see below)*
- Advanced CF7 DB / CFDB7 / Flamingo *(datastore for CF7)*
- WPForms
- Gravity Forms
- Fluent Forms
- Ninja Forms
- Formidable Forms
- Elementor Forms *(capture-mode unless Submissions storage is enabled)*

### 3.2 Adapter responsibilities

Each adapter provides a standard interface for:

- Detecting installed forms and reading field schema
- Fetching submissions (paged, cursor-based) and single submissions
- Reading uploaded-file references
- Updating / trashing / deleting a submission **when the source supports it**
- Detecting changes (via source-native change signals where available, else content hash)
- Reporting **capabilities** (read / edit / delete / file-access / real-time / reconcilable)
- Returning normalized field data per the normalization spec (§3.5)
- Declaring its **adapter type** and **source-version compatibility** (§3.6)

### 3.3 Unsupported form plugins

Provide: a documented adapter interface, developer hooks/filters, an optional custom-table connector, a read-only fallback connector, and explicit capability indicators (read / edit / delete / file / real-time / reconcilable).

### 3.4 Adapter types **[FIXED — resolves C1]**

Every adapter declares one of two types:

**Datastore adapter** — the source plugin persists submissions (Gravity, WPForms, Fluent, Ninja, Formidable, CFDB7/Flamingo for CF7, Elementor with storage enabled). Supports fetch, reconciliation, and (where the source allows) edit/delete write-back.

**Capture-mode adapter** — the source plugin does **not** persist submissions in a readable store (vanilla CF7, Elementor without storage). The plugin captures each submission **at submit time** via the source's hook and writes it to the normalized index. For these sources:

- The plugin's index is the **only** copy; "source of truth" language does not apply.
- **Reconciliation is not possible** (nothing to reconcile against). Instead the integration exposes a **capture-integrity indicator**: hook registration status, last capture time, and a warning if the hook stops firing.
- Edit/delete write-back is not applicable; edits stay in the index and are audited.
- The Integrations page clearly labels the source as *capture-only* and warns that submissions received while the plugin was inactive were not captured.

**Real-time hooks used per source** (grounds the design; verify per version at build time):

| Source | Capture hook | Type |
|---|---|---|
| Contact Form 7 | `wpcf7_mail_sent` / `wpcf7_before_send_mail` | capture-mode |
| CFDB7 / Flamingo / Advanced CF7 DB | native tables/CPT (`flamingo_inbound`) | datastore |
| WPForms | `wpforms_process_complete` + entries table | datastore |
| Gravity Forms | `gform_after_submission` + Entry API | datastore |
| Fluent Forms | `fluentform/submission_inserted` + submissions table | datastore |
| Ninja Forms | `ninja_forms_after_submission` + Ninja API | datastore |
| Formidable | `frm_after_create_entry` + entries table | datastore |
| Elementor Forms | `elementor_pro/forms/new_record` (+ Submissions if enabled) | capture-mode / datastore |

### 3.5 Field-type normalization spec **[NEW]**

Adapters normalize source fields to a fixed type vocabulary so reporting is consistent:

- `text`, `textarea`, `email`, `url`, `number`, `date`, `time`, `datetime`, `select`, `multiselect`, `checkbox`, `radio`, `file`, `repeater`, `matrix`, `signature`, `payment`, `hidden`, `system`.
- **Multi-value fields** (checkbox/multiselect) normalize to a JSON array in the snapshot and to a delimiter-joined string in exports (delimiter configurable; default `; `).
- **Repeaters / lists** (Gravity list, Fluent repeater) normalize to a JSON array of row objects. Export options: flatten to indexed columns (`Item 1 - Name`, `Item 2 - Name`, …) up to a cap, or serialize to one cell. Chosen per report.
- **File fields** store a *reference* (attachment ID or URL + relative path), never a copy, plus original filename and mime.
- **Payment/signature** fields export as text summaries; signatures reference the image if present.
- Field identity keys on the **stable source field ID/key**, never the label. Labels are stored separately and may change without breaking mapping (§10.4, schema-drift handling in §3.6).

### 3.6 Adapter versioning & safety **[NEW]**

- Each adapter records the **source plugin version** it was validated against and performs feature detection at runtime; on an unrecognized version it degrades to read-only and raises an integration warning rather than assuming schema.
- **Schema drift**: when a source form gains/loses/reorders fields, the adapter reconciles field metadata by stable key; new fields appear as available, removed fields are marked `inactive` (not deleted, to preserve historical reports).
- **No `unserialize()` on untrusted data.** Adapters that must read serialized postmeta use a safe reader (`maybe_unserialize` only where the data is plugin-controlled; otherwise JSON) and reject objects. This closes PHP object-injection. (§28.6)

---

## 4. Core Architecture

```text
WordPress Forms
   ├─ Datastore sources (Gravity, WPForms, Fluent, Ninja, Formidable, CFDB7/Flamingo, Elementor+storage)
   └─ Capture-mode sources (CF7, Elementor no-storage)
        │
Adapter Layer  (typed: datastore | capture-mode; declares capabilities + source version)
        │
Normalized Submission Index  (UTC timestamps, canonical JSON snapshot + indexed filter columns)
        │
   ├─ Submission Manager        ├─ Schedule Engine (calendar-safe, catch-up)
   ├─ Workflow Engine           ├─ Excel / CSV / ZIP Generators
   ├─ Saved Views               ├─ Email Engine (queued, size-aware)
   ├─ Reporting Engine          ├─ Secure Download Controller
   ├─ Automation Engine         ├─ Notifications
   ├─ Audit Logs                └─ Dashboard Analytics
        │
   Job Queue (Action Scheduler primary) + Locking + Idempotency
```

### 4.1 Recommended plugin structure **[HARDENED]**

```text
wp-formvault/
├── wp-formvault.php
├── uninstall.php
├── composer.json
├── readme.txt
├── languages/                      # [NEW] i18n .pot/.po/.mo
├── assets/ { css/ js/ images/ }
├── includes/
│   ├── Core/ { Plugin, Activator, Deactivator, Capabilities,
│   │           Database, Migrations, ServiceContainer, Logger,
│   │           Lock, Rng }                    # [NEW] Migrations, Logger, Lock, Rng
│   ├── Adapters/ { AdapterInterface, DatastoreAdapterInterface,
│   │               CaptureAdapterInterface, AbstractAdapter,
│   │               AdapterRegistry, CapabilityDescriptor,
│   │               <one file per source> }     # [FIXED] typed interfaces
│   ├── Sync/ { SubmissionIndexer, ReconciliationService,
│   │           CaptureIntegrityService, ChangeDetector,
│   │           CursorStore, SyncQueue }         # [NEW] CaptureIntegrity, CursorStore
│   ├── Submissions/ { Repository, Service, Editor, Trash,
│   │                  BulkActionService, SavedViewService,
│   │                  AccessScope }             # [NEW] AccessScope (query-level ACL)
│   ├── Workflow/ { WorkflowService, StatusService, TagService,
│   │               AssignmentService, NotesService, FollowUpService,
│   │               AutomationRuleEngine }
│   ├── Reports/ { ReportService, ReportBuilder, DataMapper,
│   │              UnifiedSheetMapper, ExcelGenerator, CsvGenerator,
│   │              ZipGenerator, CellSanitizer,   # [NEW] CellSanitizer
│   │              ReportTemplateService, ReportCleanupService,
│   │              SnapshotService }              # [NEW] SnapshotService
│   ├── Scheduling/ { ScheduleService, ReportingWindowCalculator,
│   │                 CronManager, CatchUpService, RetryManager,
│   │                 OneTimeScheduleService }    # [NEW] CatchUpService
│   ├── Email/ { EmailService, TemplateParser, RecipientValidator,
│   │            HeaderGuard, DeliveryLogger }     # [NEW] HeaderGuard
│   ├── Downloads/ { SecureDownloadController, TokenService,
│   │                DownloadThrottle, DownloadLogger } # [NEW] Throttle
│   ├── Privacy/ { PrivacyExporter, PrivacyEraser,
│   │              Anonymizer }                    # [NEW] whole module
│   ├── Notifications/ { NotificationService, AdminNotification,
│   │                    EmailNotification, DigestService }
│   ├── Audit/ { AuditLogger, ReportImpactService }
│   ├── Rest/ { RestController, Permissions }      # [NEW] explicit REST perms
│   ├── Health/ { SiteHealth, CronHeartbeat, StorageProbe } # [NEW]
│   └── Admin/ { AdminMenu, DashboardPage, SubmissionsPage,
│                SchedulesPage, ReportsPage, TemplatesPage,
│                NotificationsPage, IntegrationsPage,
│                PermissionsPage, AuditPage, SettingsPage }
├── templates/ { admin/ email/ }
└── vendor/ { phpoffice/phpspreadsheet, maennchen/zipstream-php,
              woocommerce/action-scheduler }       # [NEW] zipstream, action-scheduler
```

### 4.2 Service composition and module dependency contract **[HARDENED]**

`WPFormVault\Core\Plugin` is the sole application composition root. It wires concrete implementations into the small project-owned `WPFormVault\Core\ServiceContainer`; feature services receive exact constructor dependencies and never receive the container or a generic resolver.

Startup is fail closed: entry/autoload guards, packaged-dependency availability, early Action Scheduler registration without early API use, platform compatibility, and the per-site schema/migration gate all pass before the container is frozen and product hooks are registered. Constructors and service-definition factories do not perform database writes, queue work, email, filesystem mutation, or remote calls.

Modules depend only on explicitly approved inward layers and import another module only through its reviewed `Contracts`, `DTO`, `Events`, or `Value` namespaces. `Admin` and `Rest` are terminal inbound modules; repositories and application services contain business/persistence logic and query-layer AccessScope enforcement.

The complete ownership rules, lifecycle, service scopes, allowed dependency edges, and implementation acceptance criteria are defined in `docs/architecture/service-container-and-module-boundaries.md`. The authoritative machine-readable graph is `docs/architecture/module-boundaries.json` and is enforced by `tools/verify-architecture.php`.

---

## 5. Hybrid Data Model

The datastore source remains source of truth. Capture-mode sources (§3.4) have no external store; the index is authoritative for them.

The normalized index supports fast search, cross-form reporting, unified filtering, dashboard metrics, workflow metadata, saved views, scheduling, report generation, change tracking, and audit history.

### 5.1 Data sync behavior **[HARDENED]**

- Index new submissions in real time via source hooks (both adapter types).
- **Datastore sources**: run cursor-based incremental reconciliation to catch records missed while the plugin/hook was down (§5.4).
- **Capture-mode sources**: run capture-integrity checks (§3.4) — reconciliation is not possible.
- Update indexed records when datastore originals change (change signal or content hash).
- Mark indexed records `source_deleted` when datastore originals disappear.
- Never duplicate uploaded files — store references only.
- Store `source_plugin`, `source_form_id`, `source_submission_id`, and a source reference, under a **unique constraint** for idempotency (§6.6).
- Store a **canonical field snapshot** for audit and report reproducibility, plus a `data_hash` for change detection (§5.3).

### 5.2 Data ownership

| Data type | Source of truth |
|---|---|
| Original fields (datastore source) | Original form plugin |
| Original fields (capture-mode source) | **This plugin's index** *(only copy)* |
| Workflow status, tags, notes, assignee, priority, follow-up | This plugin |
| Report / audit / download / schedule history | This plugin |
| Generated files | This plugin (temporary) |
| Uploaded files | Original plugin or WP media/upload storage (referenced, not copied) |

### 5.3 Change detection & `data_hash` **[HARDENED — resolves Z]**

- `data_hash = sha256( canonical_json(normalized_fields) )`, where `canonical_json` sorts keys, normalizes whitespace, excludes volatile/system fields (e.g. `updated_at`), and represents multi-value fields as sorted arrays.
- File fields hash on `attachment_id + original_filename + filesize`, so a replaced file is detected even if the reference path is stable.
- Prefer source-native change signals (Gravity/Fluent modification timestamps) when present; fall back to `data_hash` comparison.

### 5.4 Reconciliation engine (datastore only) **[FIXED — resolves C2]**

- **CursorStore** keeps a per-(source, form) cursor: last seen source ID and/or last seen source modified-time.
- **Forward pass** (new/updated): fetch source records with ID/mtime greater than the cursor, in bounded batches; upsert into the index; advance the cursor. Bounded batch size prevents timeouts and memory blow-up.
- **Deletion pass** (bounded): periodically sample/enumerate a source-ID window and mark index rows whose source IDs no longer exist as `source_deleted`. Full-table deletion scans are avoided on large sources; deletion detection runs on a rolling window and on-demand for a form.
- Reconciliation frequency, batch size, and deletion-scan window are configurable and surfaced per integration. Reconciliation is presented as **best-effort catch-up**, not a real-time guarantee.

---

## 6. Database Design

Custom tables (not post-meta) for scale. All timestamps are stored in **UTC** (`DATETIME`), displayed in the site timezone.

### 6.1 Core tables

```text
wp_wpfv_forms                 wp_wpfv_schedules
wp_wpfv_form_fields           wp_wpfv_schedule_forms
wp_wpfv_submissions           wp_wpfv_schedule_fields
wp_wpfv_submission_values     wp_wpfv_schedule_filters
wp_wpfv_submission_snapshot   wp_wpfv_schedule_recipients   [NEW: snapshot table]
wp_wpfv_submission_workflow   wp_wpfv_schedule_mappings
wp_wpfv_submission_notes      wp_wpfv_report_templates
wp_wpfv_submission_tags       wp_wpfv_reports
wp_wpfv_tags                  wp_wpfv_report_files
wp_wpfv_saved_views           wp_wpfv_report_records
wp_wpfv_download_tokens       wp_wpfv_report_deliveries
wp_wpfv_download_logs         wp_wpfv_notifications
wp_wpfv_notification_prefs    wp_wpfv_audit_logs
wp_wpfv_sync_logs             wp_wpfv_jobs
wp_wpfv_automation_rules      wp_wpfv_automation_actions
wp_wpfv_sync_cursors          wp_wpfv_access_grants          [NEW: cursors, ACL]
wp_wpfv_locks                 wp_wpfv_schema_version          [NEW: locking, migrations]
```

### 6.2 Key submission fields

```text
id
source_plugin
source_form_id
source_submission_id
adapter_type            -- [NEW] datastore | capture
form_name
submitted_at            -- [FIXED] UTC
indexed_at              -- UTC
updated_at              -- UTC
submitted_by_user_id
source_page_url
ip_address              -- optional, privacy-gated
user_agent              -- optional, privacy-gated
submission_status
source_deleted
sync_status
data_hash
snapshot_id             -- [NEW] FK to snapshot table
```

### 6.3 Workflow fields

```text
submission_id, workflow_status, priority, assigned_user_id,
follow_up_at (UTC), last_activity_at (UTC), created_by, updated_by,
row_version  -- [NEW] optimistic-lock counter
```

### 6.4 Report fields

```text
id, schedule_id, report_name, report_type,
period_start (UTC), period_end (UTC),
generated_at (UTC), generated_by,
submission_count, form_count, status,
is_outdated, outdated_reason,
file_expiry_at (UTC), delivery_status, retry_count,
idempotency_key  -- [NEW] prevents duplicate generation/send
```

### 6.5 Storage strategy for values **[FIXED — resolves C7]**

Hybrid model instead of pure EAV:

1. **`wp_wpfv_submission_snapshot`** — one row per submission holding the full normalized record as canonical JSON (`LONGTEXT` or MySQL `JSON`). This is the reproducible record and the primary read path for detail views and report generation.
2. **`wp_wpfv_submission_values`** — indexed EAV **only for fields flagged filterable/sortable** by the admin per form. Keeps the EAV small and every column indexed.
3. On MySQL 5.7+/MariaDB 10.2+, optionally expose **generated columns** over the JSON for hot filter fields, with indexes, avoiding EAV entirely for those.

This keeps filtering fast without exploding row counts, and keeps a clean reproducible snapshot for reports.

### 6.6 Indexes & constraints **[HARDENED]**

- **UNIQUE** `(source_plugin, source_form_id, source_submission_id)` on `wp_wpfv_submissions` → idempotent capture/reconciliation (resolves double-index on repeated hooks).
- Indexes on `submitted_at`, `source_form_id`, `submission_status`, `workflow_status`, `assigned_user_id`, `follow_up_at`, `data_hash`, and `(schedule_id, period_start)`.
- `wp_wpfv_submission_values`: index `(field_key, value_indexed)` and `(submission_id, field_key)`.
- `wp_wpfv_download_tokens`: index on `token_hash`, `expires_at`, `revoked`.
- Foreign-key-style integrity enforced in application layer (WP core avoids DB-level FKs across engines).

---

## 7. Schedule Management

### 7.1 Schedule types

One-time, Daily, Weekly, Alternate-week, Monthly, Quarterly, Custom recurrence.

### 7.2 Schedule configuration **[HARDENED]**

Each schedule includes: name; status (active/inactive/draft/completed/paused); recurrence type; delivery day; delivery time; **site timezone captured at creation** (so later WP timezone changes don't silently shift history); one-time date; selected forms; per-form field selection, labels, order; filters; unified mapping; export mode; Excel + email templates; recipients; retry settings; no-submission behavior; upload handling; notification settings; **alternate-week anchor date** (§8.2); **download mode (authenticated | public-opt-in) + optional password** (§14).

### 7.3 Duplicate schedule

Duplicating copies forms, fields, filters, mapping, recipients, delivery settings, email content, export mode, branding, retry rules, no-data behavior, upload/download settings. The copy is saved as **Draft/Inactive**. Public-download acknowledgment is **not** copied — it must be re-affirmed.

---

## 8. Calendar-Based Reporting Windows **[FIXED — resolves C3, C4]**

Windows are calendar-based, not "last N days," and are timezone-safe.

### 8.1 Timezone & storage rules

- All submission timestamps are stored in **UTC**.
- A window's human boundary (e.g. "Monday 00:00:00") is computed in the **schedule's captured site timezone** using `DateTimeImmutable` + `DateTimeZone`, then converted to UTC for the DB query. This makes boundaries correct across DST and across 30-minute-offset zones (e.g. Asia/Kolkata).
- DST edge: when a local midnight doesn't exist or repeats, boundaries are resolved via UTC-anchored arithmetic so no submissions are dropped or double-counted.

### 8.2 Window definitions

- **Weekly** (day = Monday): `[Mon 00:00:00 local … next Mon 00:00:00 local)` — half-open interval avoids the `23:59:59` gap that loses sub-second records.
- **Alternate-week**: parity is measured from a **fixed anchor date** stored on the schedule. Period = two consecutive weeks starting on an "on" week relative to the anchor. Without the anchor, alternate-week is undefined — the anchor is required.
- **Monthly**: `[first day 00:00 local … first day of next month 00:00 local)`.
- **Quarterly**: **calendar quarters** by default (Jan–Mar, Apr–Jun, Jul–Sep, Oct–Dec). A fiscal-quarter offset is an optional setting.
- **Custom**: cron-like or interval definition; still resolved to explicit start/end instants.

Half-open intervals `[start, end)` are used everywhere to eliminate boundary double-counting.

### 8.3 Period membership rule **[NEW]**

A submission belongs to a period by its **`submitted_at`**, not its edit time. Editing a record does not move it between periods; it marks affected reports outdated (§17.3). Records indexed *after* a period's report already ran (late arrivals via reconciliation) trigger an **"in-period records added"** outdated reason so the admin can regenerate.

### 8.4 Reliability & catch-up **[FIXED — resolves C4]**

Store per run: intended `period_start`/`period_end`, actual execution time, last successful run, next expected run, submission count, file references, delivery status.

On every scheduler heartbeat, the **CatchUpService** computes *all* intended periods between the last successful run and now. Any period with no completed run is enqueued using **its own intended window** — so a report missed for days is still produced correctly rather than skipped. Late cron never shifts the window.

---

## 9. Cron, Job Queue, Concurrency

### 9.1 Scheduling approach **[HARDENED]**

- **Action Scheduler is the primary queue** (batched, self-healing, admin-visible). WP-Cron is a trigger; **server cron** (`wp cron event run` or a real crontab hitting `wp-cron.php`) is the recommended reliability backstop and is surfaced as a health check (§44).
- All heavy work (generate, zip, email, bulk, sync) runs as **queued jobs**, never inline in a page request.

### 9.2 Job types

```text
sync_submission        reconcile_source        capture_integrity_check
generate_report        generate_excel          generate_zip
send_report_email       retry_failed_email      cleanup_expired_files
process_bulk_action     send_notification       run_automation_rule
catchup_missed_windows                          [NEW]
```

### 9.3 Retry behavior

On failure: preserve intended period; retry without recomputing the window; notify admins; allow manual regenerate/resend; record every attempt.

Default (configurable) sequence: `+0` (scheduled), `+15m`, `+1h`, `+3h`, then final-failure state.

### 9.4 Concurrency & locking **[NEW — resolves I]**

- **Per-schedule run lock** (`wp_wpfv_locks` row or MySQL `GET_LOCK`) claimed before generation; prevents two overlapping cron passes from double-generating.
- **Stuck-job reclaim**: a job `claimed_at` older than a timeout is reclaimed for retry (covers PHP crashes mid-run).
- **Max simultaneous report jobs** enforced by the queue, not just documented.
- Schedule and submission edits use `row_version` **optimistic locking** so two admins can't silently clobber each other.

### 9.5 Idempotency **[NEW — resolves R]**

- Each report run carries an **`idempotency_key`** = `hash(schedule_id + period_start + period_end)`. Generation and send check this key so a retried/duplicated job never produces a second file or second email for the same period.
- Real-time indexing is idempotent via the unique source-key constraint (§6.6) — a re-fired submit hook updates rather than duplicates.

---

## 10. Report Builder

### 10.1 Form & field selection

Per form: select all / include-exclude fields, rename headers, reorder columns, include system fields, preview export structure. Field selection keys on **stable field IDs** (§3.5) so label changes don't break saved reports.

### 10.2 Optional system columns

Submission ID, form name, submission date, last updated, source page, source plugin, WP user ID, IP address, status, tags, assignee, priority, follow-up date, internal-note summary.

**IP address and other sensitive fields require `wpfv_view_sensitive_fields`** and are gated by export settings (§23, §42).

### 10.3 Filters

Operators: equals, not-equals, contains, not-contains, starts-with, ends-with, is-empty, is-not-empty, >, <, between, before-date, after-date, date-between, has-file, logged-in, guest, source-page, status, tag, assignee, priority, follow-up-date. Logic: AND / OR / nested groups. Filters compile to **parameterized queries only** (no string concatenation) against indexed columns (§6.5).

### 10.4 Unified worksheet mapping

Combine forms into a shared sheet by mapping stable source field IDs to unified columns:

```text
Unified worksheet: Leads
Name    ← your-name / full_name / applicant_name
Email   ← your-email / email / contact_email
Phone   ← phone / mobile / contact_number
Company ← company / organisation / employer
Source Form, Submitted At
```

Supports saved reusable mappings, field-conflict warnings, missing-field fallback, a Source Form column, unmatched fields routed to form-specific sheets, and preview before generation.

### 10.5 Excluded functionality

No Excel formulas, charts, pivots, or analytical worksheets. Exports stay clean and data-focused (this also reduces the formula-injection surface).

---

## 11. Excel / CSV Generation

Use **PhpSpreadsheet** for XLSX; a **streaming CSV writer** for large CSV.

### 11.1 Capabilities

XLSX output; multiple worksheets; separate files per form; unified worksheets; custom sheet names; frozen header row; auto filters; configurable column widths; date formatting; company logo (by attachment ID); title/subtitle; header styling; footer; print orientation; filename templates; optional cover sheet; branding templates.

### 11.2 Formula / injection prevention **[HARDENED — resolves C6]**

Handled by a single **CellSanitizer** applied to all text output (XLSX, CSV, and manifest.csv):

- Neutralize any cell whose value, **after stripping leading whitespace**, begins with any of: `=`  `+`  `-`  `@`  **TAB (0x09)**  **CR (0x0D)**. This is the full injection trigger set including DDE (`=cmd|...`).
- Text cells are written with **explicit string type** (`setValueExplicit(..., DataType::TYPE_STRING)`) and the default value binder's auto-type detection is **disabled**, so a value is never silently coerced into a formula or number.
- When neutralization is needed, prefix with a single quote `'` (Excel treats as literal) **only for text-typed cells**; numeric/date cells that are genuinely numeric keep their type and are not prefixed.
- Escape/validate external hyperlinks; reject non-http(s) schemes.

Also: sanitize sheet names, enforce the 31-char sheet-name limit, de-duplicate sheet names, sanitize filenames, prevent path traversal, avoid raw server paths, normalize checkbox/array fields, and preserve line breaks safely.

### 11.3 Large-file strategy

Generate in background jobs; process records in batches; write XLSX to a temp file (not memory); stream CSV; never load a full dataset into memory. Enforce configurable row/size caps with graceful truncation warnings.

### 11.4 Branding / SSRF guard **[NEW — resolves AG]**

Logos and branding images are selected from the **WordPress media library by attachment ID**. The plugin never fetches an admin-supplied arbitrary URL server-side, eliminating SSRF via the logo field.

---

## 12. Uploaded Files & ZIP Handling

Per schedule: Excel links only / uploaded files in ZIP / both.

### 12.1 ZIP behavior

Organize by form and submission; safe filenames; collision-free naming; exclude missing/inaccessible files; log omissions; record archive size; create a secure download token; retain 30 days; allow revoke and regenerate.

```text
report-name/
├── Contact Form/Submission-1001/ …
├── Registration Form/Submission-2001/ …
└── manifest.csv   (CellSanitizer-escaped)
```

### 12.2 File-access security **[HARDENED — resolves H]**

- **Path containment**: before reading or zipping any file, resolve `realpath()` and confirm it is inside an **allow-list of upload roots** (`wp_upload_dir()` base and configured source dirs). Reject anything outside — blocks path traversal and symlink escape.
- **Streaming ZIP** via ZipStream to avoid building the whole archive in memory.
- **Cumulative caps**: max total ZIP size, max file count, per-file size — enforced to prevent disk-exhaustion DoS from bundling many/large uploads.
- Files are validated (exists, readable, within caps) before inclusion; failures are logged and reported in the manifest, not fatal.

---

## 13. Email Delivery

### 13.1 Recipients

To / CC / BCC / failure-notification recipients / recipient groups; every address validated.

### 13.2 Configuration

Subject; HTML body; plain-text fallback; sender name; reply-to; Excel attachment; ZIP link; test email; preview.

### 13.3 Placeholders

```text
{{report_name}} {{schedule_name}} {{period_start}} {{period_end}}
{{generated_date}} {{form_names}} {{submission_count}}
{{recipient_name}} {{zip_download_link}} {{zip_expiry_date}}
{{site_name}} {{site_url}}
```

All placeholder values are escaped for the body context (HTML-escaped in HTML parts).

### 13.4 No-submission behavior

Per schedule: send "no submissions" email / send empty Excel with headers / skip / mark complete with zero.

### 13.5 Delivery, size, and honesty about `wp_mail` **[HARDENED — resolves J]**

- Email send runs as a **queued job**, never inline in cron, so a slow SMTP handoff can't time out the scheduler.
- **Attachment-size fallback**: if the Excel exceeds the configured mail limit (conservative default, e.g. 10 MB), the plugin preserves the report, switches the attachment to a secure link, warns the admin, and logs the change.
- `wp_mail()` returning `true` means **handoff to PHP/SMTP, not delivery**. The delivery log distinguishes: report generated → email handed to WP → handoff failed → retry scheduled → final failure → manually resent. Where an SMTP plugin exposes result codes, capture them; otherwise the log states plainly that delivery confirmation isn't available.
- Recommend/detect SMTP or transactional providers.

### 13.6 Header-injection guard **[NEW — resolves J]**

Strip CR/LF from sender names, reply-to, and subject; reject addresses failing `is_email()`; never pass unsanitized user input into mail headers.

---

## 14. Secure Download Links **[HARDENED — resolves C5, F]**

ZIP/report downloads default to **authenticated** access. A **public token mode** is available but is opt-in per schedule with an explicit acknowledgment.

### 14.1 Token model

- Token = **256 bits** from `random_bytes()` (CSPRNG), URL-safe encoded.
- **Only a hash of the token is stored** (`token_hash = sha256(token)`); the raw token exists only in the emailed URL. A DB leak therefore does not expose live download URLs.
- Lookups compare with `hash_equals()` (constant-time).
- Non-sequential URLs; no internal IDs exposed. Example: `https://example.com/?wpfv_download=<token>`.

### 14.2 Access modes

- **Authenticated (default):** requires login + `wpfv_download_reports` (and form-level access, §23). Suitable for internal reporting.
- **Public (opt-in):** anyone with the link. Requires the admin to tick an acknowledgment that a bundle of personal data will be reachable without login. Strongly recommended to combine with a password.
- **Optional password:** stored as a hash; prompt gate before serving the file.
- **Optional IP binding:** restrict to the requester's network where feasible.

### 14.3 Controls

Expiry (default 30 days, configurable); manual revoke (endpoint then returns **410 Gone**); download-count cap; download-count + last-download tracking; files served through a controlled PHP endpoint with correct headers and **no server-path disclosure**; files stored under an unpredictable, deny-listed directory (`.htaccess` deny + `index.php`; documented **Nginx** deny rules since Nginx ignores `.htaccess`), ideally outside webroot where the host allows.

### 14.4 Abuse resistance **[NEW]**

- **Brute-force lockout**: after N failed/invalid token attempts from an IP, throttle/lock that IP for a cooldown.
- **Rate limiting** per IP and global on the download endpoint.
- Failed and revoked-token hits are logged (§30.3, redacted).

---

## 15. Generated-File Retention

### 15.1 Permanent data

Never auto-deleted: original submissions, normalized history, report records, report-configuration snapshots, delivery logs, audit logs, download history, schedule history.

### 15.2 Temporary files

Auto-deleted after retention (default 30 days): XLSX, CSV, ZIP, temp build files. After cleanup, report history stays visible, file status becomes "Expired and cleaned," and the report is regenerable.

### 15.3 Regeneration semantics **[FIXED — resolves AA, P]**

Two explicit modes, admin's choice:

- **Reproduce (as-of):** rebuild from the stored `report_records` **snapshot** — byte-for-byte the original data set, even if source data has since changed. This is the default for regenerating an expired file so history stays truthful.
- **Refresh (current):** rebuild from current source/index data — used when the admin *wants* an updated version after edits. Produces a new report/file/token and leaves the original history intact.

Snapshots may be pruned by a separate configurable retention (they contain PII); pruning is opt-in and audited (§42).

---

## 16. Centralized Submission Management

### 16.1 Submission list

All forms/plugins; search; date/form/source-plugin/status filters; tags; assignee; priority; follow-up; saved views; custom columns; sorting; pagination; bulk selection; trash view. **Every query is scoped by the requesting user's form-level access** (§23.3).

### 16.2 Submission detail

Original fields; uploaded files; source plugin/form; source page; submission date; last sync; workflow status; tags; assignee; priority; follow-up; internal notes; activity timeline; audit history; reports containing the record. For capture-mode sources, a banner clarifies the index is the only copy.

---

## 17. Editing & Deletion

### 17.1 Editing **[HARDENED]**

Where the datastore source supports updates: edit from the plugin, validate values, update the original, update the index, store old/new values, record editor + timestamp, mark affected reports outdated, allow regeneration. For **capture-mode** sources, edits are stored in the index only (there is no writable source) and clearly audited.

Where editing isn't supported: read-only with a reason and a link to the original plugin.

### 17.2 Trash workflow

Move to trash / restore / permanent-delete (with capability) / sync with source where supported / confirm destructive actions / audit everything. Permanent delete also removes the submission's PII from snapshot + values while preserving an anonymized audit record (§42).

### 17.3 Report impact

On edit/trash/restore/delete, or when late in-period records are added: find affected reports, mark outdated with a reason, allow regenerate/resend, preserve original generated history.

---

## 18. Workflow Management

Per submission: status, tags, assigned user, internal notes, priority, follow-up date, last activity, created-by, updated-by.

### 18.1 Default statuses

New, In Review, Qualified, Rejected, Follow-up Required, Closed. Custom statuses allowed.

### 18.2 Internal notes

Not written back to the source; author + timestamp; can @mention WP users; can trigger notifications; included in activity history.

---

## 19. Bulk Actions

Change status; add/remove tags; assign/reassign; set priority; add note; set follow-up; trash; restore; permanent-delete; export selected; generate report; email immediately; add to a one-time report.

All bulk actions run as **batched, resumable jobs** with confirmation, progress tracking, per-record success/failure, final summary, audit log, and retry where appropriate. Destructive bulk actions require the matching capability and a nonce.

---

## 20. Saved Views

Stores forms, source plugins, filters, search, visible columns, order, sorting, status, tags, priority, assignee, follow-up, date range, active/trash scope, records-per-page.

Visibility: private / shared with users / shared with roles / default per user / default per role. **Shared views never widen a user's data access** — the viewer still only sees submissions their form-level access permits (§23.3).

---

## 21. Notifications

### 21.1 Events

Assignment, reassignment, status/priority change, urgent record, note added, mention, follow-up reached/overdue, submission edited/trashed/restored/deleted, report generated/sent/failed, retry started/exhausted, report regenerated, file expired, bulk completed/partially failed, **capture-hook stopped firing**, **cron heartbeat missed** (§44).

### 21.2 Channels

Admin notices, email, daily digest.

### 21.3 Preferences

Users configure non-critical notifications. Admins enforce critical alerts: final report failure, sync failure, storage failure, security issue, permanent deletion, cron inactivity, capture-integrity failure.

---

## 22. Automation Rule Engine

Triggers, conditions, and actions as in the original, plus safety:

- **Loop prevention** with recursion-depth cap and an automation-originated flag so an automation-driven change can't re-trigger the same rule chain indefinitely.
- **Max actions per run**; rule priority; enable/disable; test mode; dry-run preview; per-action success/failure logs.
- Automations that send email/create reports respect the same recipient validation, size fallback, and rate limits as manual sends.

Example rule unchanged:

```text
When new submission from "Demo Request"
If Country = India
Then set status = New Lead; add tag India; assign sales user;
     set follow-up = +2 days; send email notification
```

---

## 23. Roles & Permissions

### 23.1 Default roles

**Administrator** (full), **Report Manager** (schedules, generate/send/resend, recipients, assigned forms), **Report Viewer** (view records/history, download, no edit/delete), **Form Manager** (only assigned forms + related submissions/schedules/reports).

### 23.2 Capabilities

```text
wpfv_view_dashboard        wpfv_view_submissions
wpfv_edit_submissions      wpfv_trash_submissions
wpfv_delete_submissions    wpfv_manage_workflow
wpfv_manage_schedules      wpfv_generate_reports
wpfv_send_reports          wpfv_download_reports
wpfv_manage_templates      wpfv_manage_integrations
wpfv_manage_settings       wpfv_view_audit_logs
wpfv_manage_permissions    wpfv_manage_automation
wpfv_view_sensitive_fields                       [NEW]
```

### 23.3 Access enforcement at the query layer **[HARDENED — resolves M]**

- Form-level and schedule-level restrictions are stored in **`wp_wpfv_access_grants`** (subject = user or role; object = form or schedule).
- The **AccessScope** service injects an accessible-forms constraint into **every** submission/report query for non-admins — enforcement lives in the data layer, not just the UI. Saved views, exports, bulk actions, and downloads all pass through AccessScope, so no path can leak out-of-scope records.
- `wpfv_view_sensitive_fields` gates IP address, user agent, and any admin-marked sensitive field in both UI and exports.
- **Public download links** bypass role checks by design; that is exactly why public mode is opt-in with acknowledgment (§14.2). Authenticated download mode honors AccessScope.

---

## 24. Dashboard

KPI cards (indexed total, new, generated, sent, failed, upcoming schedules, overdue follow-ups, expired files) and sections (submission trend, most active forms, recent submissions, upcoming schedules, recent reports, failing/retrying jobs, recent downloads, schedules needing attention, sync health per integration, storage usage, follow-up workload). Charts are dashboard-only, never in exports. All dashboard queries respect AccessScope.

---

## 25. Admin Navigation

```text
WP FormVault
├── Dashboard        ├── Reports          ├── Integrations
├── Submissions      ├── Report Templates ├── Audit Logs
├── Saved Views      ├── Automation Rules ├── Permissions
├── Schedules        ├── Notifications    └── Settings
```

---

## 26. Admin Page Requirements

### 26.1 Schedules page

Search; status/recurrence filters; next/last run; forms; recipients; duplicate; pause; run-now; edit; delete; history. Run-now and delete require nonce + capability.

### 26.2 Schedule builder (step wizard)

1 Basic info · 2 Forms · 3 Fields · 4 Filters · 5 Unified mapping · 6 Export format · 7 Upload/Download handling (incl. public-link acknowledgment) · 8 Branding · 9 Email & recipients · 10 Timing (+ alternate-week anchor) · 11 Retry & no-data · 12 Review & activate.

### 26.3 Reports page

Name; schedule; period; forms; submission count; generated date; email status; file status; outdated status; download count; regenerate (reproduce/refresh); resend; view log.

---

## 27. Audit Trail

Permanent. Records: user, action, entity type/ID, old/new value, timestamp, IP where appropriate, source screen, source plugin, related report/submission, result, error details.

Events: submission edits; trash/restore; permanent delete; workflow changes; schedule changes; report generation; delivery; file cleanup; download-link revocation; permission changes; automation execution; integration sync; **privacy export/erasure requests** (§42).

Audit records that reference an erased data subject are **anonymized, not deleted**, so the action history survives without retaining PII (§42.3).

---

## 28. Security Requirements

### 28.1 WordPress security

Nonces on every write; capability checks; **prepared statements only**; escaped output (context-appropriate: `esc_html`, `esc_attr`, `esc_url`, `wp_kses`); sanitized input; CSRF/XSS/SQLi protection; file-path validation; secured AJAX and REST.

### 28.2 File security

Protected storage dir; directory listing disabled (Apache + documented Nginx); random internal filenames; controlled ZIP endpoint; validate every file before zipping; path-traversal + symlink containment (§12.2); enforced size/count caps; log failed access.

### 28.3 Data security

Minimize duplicated PII; IP export optional and capability-gated; sensitive fields restricted; privacy export/erasure hooks wired (§42); respect WP personal-data tools; preserve source permissions where possible; never expose internal IDs in public links.

### 28.4 Excel security

Full formula-injection neutralization + explicit string typing (§11.2); sanitized sheet names; validated/escaped external links; no macros; XLSX/CSV only, no executable content.

### 28.5 REST / AJAX endpoint rules **[NEW — resolves V]**

- Every data endpoint declares a real `permission_callback` that checks capability **and** AccessScope. **`__return_true` is prohibited** except on the single public download route, which is protected by hashed-token + throttle instead.
- Logged-in REST uses cookie auth + `X-WP-Nonce`; no data mutation without a valid nonce.
- Input is schema-validated (`args` with `sanitize_callback` / `validate_callback`) before use.

### 28.6 Deserialization & SSRF **[NEW — resolves AG, AH]**

- **No `unserialize()` on untrusted/source-supplied data** — prevents PHP object injection. Prefer JSON; where legacy serialized postmeta must be read, use guarded reading that rejects objects.
- Server-side image/logo selection is by **attachment ID only** — no arbitrary URL fetch (no SSRF).

---

## 29. Performance & Scalability

### 29.1 Large data

Background jobs; batch processing; streaming CSV; temp-file XLSX; no full-dataset memory loads; cache form metadata (with cache invalidation on schema drift); index filterable fields (§6.5); paginate admin lists; incremental sync (§5.4); indexes on date/form/status/assignee/source IDs.

### 29.2 Operational limits (configurable)

Max attachment size; max ZIP size; max file count per ZIP; max records per synchronous manual export; max batch size; max retry count; max retained temp storage; max simultaneous report jobs. Exceeding the mail size triggers the link fallback (§13.5).

---

## 30. Error Handling & Logging

### 30.1 Error categories

Adapter unavailable; form missing; source table missing; read/update failure; file missing; ZIP/Excel/storage failure; email failure; cron failure; permission failure; token expired/revoked; invalid schedule; mapping conflict; memory/timeout; **capture-hook failure**; **lock contention**.

### 30.2 Recovery tools

Retry job; regenerate (reproduce/refresh); resend; rebuild index; resync form; resync date range; validate integration; download error log; test email; test cron; test storage; **reclaim stuck jobs**.

### 30.3 Log hygiene **[NEW — resolves X]**

Sync/error logs **redact field values and PII** (store field keys, counts, and hashes, not raw personal data). Log rotation/retention is configurable so logs don't grow unbounded or become an accidental PII store.

---

## 31. Integration Health

Integrations page shows: plugin installed/active; adapter available + **adapter type**; forms detected; submissions detected; last sync / last successful sync; failed records; read/edit/delete/upload support; real-time hook support + **capture-integrity status**; reconciliation status (or "not applicable — capture-mode").

---

## 32. Settings

### 32.1 General

Site timezone (display); date/time format; default retention (30d); default sender name/reply-to; default recipients; storage location; attachment + ZIP size limits; max file count; job batch size; retry defaults; audit retention (permanent).

### 32.2 Email

Test mail; default template; HTML/plain; sender identity; SMTP detection; default failure recipients; default attachment-size limit + fallback behavior.

### 32.3 Privacy **[HARDENED]**

Store IP (default off); export IP (capability-gated); store user agent (default off); erasure behavior; export behavior; **audit anonymization policy**; **snapshot retention/pruning**; optional submission anonymization schedule (§42).

### 32.4 Downloads **[NEW]**

Default download mode (authenticated); allow public mode (admin toggle); require password for public links; default expiry; download-count cap; brute-force lockout threshold.

### 32.5 Cleanup

```text
Generated report files: delete after 30 days (configurable)
Report history:         keep permanently
Submission data:        never auto-delete (optional anonymization opt-in)
Audit logs:             keep permanently (anonymized on erasure)
```

The UI displays policy and never silently changes it.

---

## 33. API & Extensibility

### 33.1 Filters

```php
wpfv_supported_adapters        wpfv_submission_normalized_data
wpfv_report_query_args         wpfv_exported_cell_value
wpfv_excel_filename            wpfv_email_subject
wpfv_email_body                wpfv_zip_file_path
wpfv_download_expiry           wpfv_schedule_next_run
wpfv_access_scope_forms        // [NEW] filter accessible forms
wpfv_cell_sanitizer_triggers   // [NEW] adjust injection trigger set
```

### 33.2 Actions

```php
wpfv_submission_indexed        wpfv_submission_updated
wpfv_submission_trashed        wpfv_report_generated
wpfv_report_sent               wpfv_report_failed
wpfv_report_regenerated        wpfv_file_cleaned
wpfv_download_completed        wpfv_automation_rule_executed
wpfv_capture_integrity_failed  // [NEW]
```

---

## 34. Implementation Phases **[HARDENED]**

**Phase 1 — Foundation:** bootstrap; DB installer + **migration/versioning** (§40); capabilities; nav; service container; **logger with redaction**; **Action Scheduler queue + locking + idempotency**; settings; security foundation (nonces, caps, REST permission scaffolding, AccessScope skeleton).

**Phase 2 — CF7 (capture) + CF7 datastore:** CF7 capture-mode adapter (`wpcf7_mail_sent`); CFDB7/Flamingo datastore adapter; field detection; real-time indexing; **capture-integrity checks** (reconciliation only for the datastore companion); central submissions list; detail screen. *(Corrects the original's assumption that CF7 has a reconcilable store.)*

**Phase 3 — Reporting core:** manual date-range reports; field selection; filters (parameterized); XLSX via PhpSpreadsheet with **CellSanitizer**; one-sheet-per-form; separate-file mode; attachment delivery with size fallback; snapshots + report history.

**Phase 4 — Scheduling:** multiple schedules; **UTC-anchored calendar windows** (weekly/alternate-week with anchor/monthly/quarterly); one-time; **catch-up for missed windows**; retry flow; no-submission behavior; duplicate.

**Phase 5 — Upload ZIP delivery:** file collection with **path/symlink containment**; **streaming ZIP** with caps; **hashed-token** secure downloads (authenticated default, public opt-in); email link; download logging + throttle; 30-day cleanup; regeneration.

**Phase 6 — Workflow:** status/tags/assignment/priority/follow-up/notes; activity timeline; batched bulk actions; trash/restore.

**Phase 7 — Editing, audit, report impact:** source editing (datastore) / index-only editing (capture); deletion support; audit snapshots; outdated detection incl. late in-period arrivals; regenerate (reproduce/refresh) + resend.

**Phase 8 — Saved views & unified mapping:** private/shared/default views (AccessScope-safe); unified worksheets; mapping templates + preview.

**Phase 9 — Notifications & automation:** admin/email/digest; automation engine with loop prevention, depth cap, dry-run.

**Phase 10 — Additional integrations:** WPForms, Gravity, Fluent, Ninja, Formidable, Elementor — each with declared type, capabilities, version detection, and normalization.

**Phase 11 — Dashboard, privacy, hardening, optimization:** KPI dashboard; **GDPR export/erasure/anonymization** (§42); **multisite** (§39); **Site Health + cron heartbeat** (§44); queue monitoring; large-export + load testing; final security pass.

---

## 35. Testing Plan **[EXPANDED]**

### 35.1 Unit

Reporting-window math (weekly, **alternate-week with anchor**, monthly, quarterly); leap years; **DST transitions in America/New_York and half-hour offset Asia/Kolkata**; **UTC↔local boundary conversion**; filter parsing → parameterized SQL; field mapping + schema drift; filename/worksheet sanitization; **CellSanitizer full trigger set incl. TAB/CR/DDE**; token generation + `hash_equals`; retry logic; cleanup calculations; **catch-up missed-window enumeration**; **idempotency-key** behavior; `data_hash` canonicalization.

### 35.2 Integration

Each adapter (datastore + capture paths); **capture-mode integrity when hook stops firing**; multisite network activation; SMTP plugins; WP-Cron; server cron; uploaded-file handling; multiple hosting/webserver (Apache + **Nginx** deny rules); object-cache environments.

### 35.3 Functional

Multiple schedules; same form in multiple schedules; different recipients/fields; empty behavior; manual + one-time; duplication; attachment; ZIP link; expiry; **revocation → 410**; regeneration (reproduce vs refresh); outdated marking incl. late arrivals; edit/delete sync; saved views; bulk actions; notifications; automation; **attachment-size link fallback**.

### 35.4 Security

Unauthorized admin access; capability bypass; **AccessScope leakage across every query path**; CSRF; XSS; SQLi; **public-token guessing + brute-force lockout**; token reuse after revocation; path traversal + **symlink escape**; malicious filenames; formula injection (full set); large-file abuse; disk-exhaustion via oversized ZIP; **email-header injection**; **object-injection via serialized source data**; **SSRF via logo/branding**; rate limiting; REST `permission_callback` audit.

### 35.5 Performance

1k / 10k / 100k submissions; multiple forms; large field counts; large uploads; concurrent schedules; **overlapping cron / lock contention**; failed email retries; large bulk actions; **EAV vs JSON filter-query benchmarks**.

### 35.6 Lifecycle **[NEW]**

Fresh install; **upgrade/migration** across schema versions; **uninstall with and without "delete data"**; multisite new-site provisioning; capability matrix; i18n string coverage; basic accessibility (keyboard nav, labels, contrast) on admin screens.

### 35.7 Engineering quality and CI contract **[NEW]**

The authoritative engineering-quality contract is `docs/architecture/quality-policy.json`; the rationale and operating rules are in `docs/architecture/engineering-quality-and-ci-policy.md`. Policy definition is owned by `ARCH-005`; installation of PHPUnit, PHPCS/WPCS, PHPCompatibilityWP, PHPStan, the WordPress test harness, and hosted CI workflows remains `QA-001`.

- First-party PHP must pass `WordPress-Core`, `WordPress-Docs`, `WordPress-Extra`, and `PHPCompatibilityWP` with `testVersion` `8.1-`.
- Runtime PHP starts at PHPStan level 8 with WordPress and Action Scheduler stubs, unmatched ignores reported, and no generated baseline.
- Tests are separated into Unit, Integration, Functional, Security, Performance, Support, and synthetic/redacted Fixtures. Unit tests do not bootstrap WordPress; integration tests use dedicated ephemeral databases.
- Blocking pull-request coverage includes quality checks on minimum and latest supported PHP, exact minimum WordPress/PHP lanes on both MySQL and MariaDB, the full currently supported PHP band on latest stable WordPress, a current multisite lane, and the locked dependency build.
- WordPress trunk runs nightly as forward-compatibility evidence. A failure must be recorded or linked in `BUGS.md` before release even though the lane is informational.
- Rolling targets resolve to exact versions at job start and write those versions to durable logs. Missing, skipped, cancelled, or unresolved blocking lanes fail the release gate.
- PHP 8.1 remains a blocking legacy-compatibility lane while it is advertised, even though upstream support has ended. Removing it is a product compatibility decision, not an incidental tooling update.

---

## 36. Acceptance Criteria **[HARDENED]**

Original 1–28 remain, with these corrections/additions:

1–14 as before. **12 is amended:** ZIP links are secure; **default is authenticated**, and public token mode works only when explicitly enabled with acknowledgment.

29. CF7 and other **capture-mode** sources are captured at submit time and clearly labeled as index-only; no false promise of historical reconciliation.
30. **Missed schedule windows are caught up** using their intended periods, not silently skipped.
31. Calendar windows are **correct across DST and half-hour-offset timezones**.
32. Download **tokens are stored hashed**; a DB dump does not reveal live URLs.
33. **Formula injection is neutralized across the full trigger set** (incl. TAB/CR/DDE) with explicit string typing.
34. **Non-admins cannot retrieve out-of-scope records via any path** (list, export, saved view, bulk, download).
35. **Overlapping cron does not double-generate or double-send** (locking + idempotency).
36. **GDPR export and erasure** remove/annonymize the subject's indexed values, snapshots, and report references while preserving anonymized audit history.
37. **Uninstall** honors the data-preservation setting; nothing is destroyed without opt-in.
38. **No SSRF, object-injection, or email-header-injection** paths remain.

---

## 37. Recommended MVP **[HARDENED]**

- CF7 capture-mode adapter + CFDB7/Flamingo datastore adapter (clearly typed)
- Hybrid submission index (JSON snapshot + indexed filter fields) with unique source-key constraint
- Central submissions page with **AccessScope** enforcement
- Manual Excel export with **CellSanitizer**
- Multiple schedules; weekly / alternate-week (with anchor) / monthly / one-time; **UTC calendar windows + catch-up**
- Field selection; basic + advanced (parameterized) filters
- One workbook with tabs; separate files
- Email recipients; attachments with **size fallback**
- ZIP uploaded-file delivery with **path containment + streaming + caps**
- **Hashed-token** secure downloads, **authenticated by default**
- Retry + **locking + idempotency**
- Report history + snapshots + 30-day cleanup
- Role permissions + query-level access
- Basic audit logging
- **Migration/versioning + uninstall safety + baseline GDPR hooks** from day one (cheaper now than retrofitting)

Automation, additional adapters, unified mapping, advanced saved views, and dashboard depth follow once the foundation is stable.

---

## 38. Final Technical Recommendation

Build as a modular, adapter-driven plugin with:

- PHP 8.1+; WordPress coding standards; custom tables with a migration runner
- PhpSpreadsheet (XLSX) + streaming CSV + ZipStream (ZIP)
- **Action Scheduler as the primary queue**, WP-Cron as trigger, server cron recommended
- REST/AJAX with strict `permission_callback`s and AccessScope
- Hashed download tokens, authenticated-by-default delivery
- Strict capability controls enforced at the query layer
- Permanent, anonymization-aware audit and report history
- Temporary generated-file retention with reproduce/refresh regeneration

**The core architectural rule, corrected:**

> Keep the datastore form plugin as the source of truth and the normalized index as the workflow/reporting layer — **except for capture-mode sources (CF7, Elementor-no-storage), where the index is the only store and must be treated and labeled as such.**

This yields broad compatibility without coupling to any single form plugin, while being honest about the sources that store nothing.

---

## 39. Multisite Strategy **[NEW]**

- **Per-site tables** using `$wpdb->prefix` (recommended) so each site's submissions, schedules, and reports are isolated.
- **Network activation** loops installation across existing sites; a `wp_initialize_site` hook provisions tables/capabilities for **newly created** sites.
- Capabilities are granted per-site; a network-admin settings screen sets defaults.
- Uninstall iterates all sites (respecting the data-preservation setting, §41).
- Cron/Action Scheduler run per-site; the health check reports per-site cron status.

---

## 40. Schema Migration & Versioning **[NEW]**

- A `wp_wpfv_schema_version` record tracks the installed schema.
- **`Migrations`** runs ordered, idempotent upgrade steps on activation and on version bump (using `dbDelta` for additive changes and explicit `ALTER`s for the rest).
- Each migration is reversible-documented and tested (§35.6). Data-transforming migrations run as **batched background jobs** so large sites don't time out on upgrade.
- The plugin refuses to run report generation mid-migration (guarded by a migration lock).

---

## 41. Uninstall & Data Lifecycle **[NEW]**

- **Deactivation:** unschedule cron/Action Scheduler events, clear locks; **keep all data**.
- **Uninstall (`uninstall.php`):** driven by a **"Delete all plugin data on uninstall" setting that defaults to OFF**. When OFF, tables and files are preserved (safe reinstall). When ON: drop `wp_wpfv_*` tables, remove capabilities, delete generated files and the private storage dir, clear scheduled events and queued actions — across all sites on multisite.
- Generated-file directory is always cleaned of expired temp files by the retention job regardless of uninstall setting.

---

## 42. Privacy / GDPR Engineering **[NEW — resolves N]**

### 42.1 Data minimization

Storing normalized copies duplicates PII by design (needed for cross-form reporting). This is disclosed, scoped, and made erasable. IP and user-agent capture default **off** and are opt-in with a stated purpose.

### 42.2 WordPress personal-data tools

- Register `wp_privacy_personal_data_exporters`: return a subject's indexed submissions, workflow metadata, and the reports referencing them.
- Register `wp_privacy_personal_data_erasers`: remove/anonymize the subject's snapshot + indexed values and scrub PII from report_records referencing them.

### 42.3 Audit anonymization

Audit and delivery logs are permanent, but on erasure the subject's identifiers are **replaced with a stable pseudonym** (e.g. `subject#<hash>`) so the action history survives without retaining personal data — reconciling "permanent audit" with "right to erasure."

### 42.4 Public-link risk

Bundling PII into a **public** ZIP is a documented risk requiring admin acknowledgment (§14.2); authenticated mode is the default. Retention of snapshots (which hold PII) is separately prunable (§32.3).

### 42.5 Lawful-basis prompts

Settings surface plain-language notes on why IP/user-agent/public-links carry data-protection obligations, nudging admins toward the safer defaults.

---

## 43. Internationalization & Accessibility **[NEW]**

- **i18n:** load text domain from `/languages`; wrap all strings; ship a `.pot`; support RTL in admin CSS; localize dates/numbers to site locale.
- **a11y:** admin screens meet basic WCAG interaction requirements — keyboard navigation, form labels, focus states, sufficient contrast, ARIA where the wizard/step UI needs it.

---

## 44. Observability & Health **[NEW]**

- **WP Site Health integration:** checks for cron reliability, writable/protected storage dir, Action Scheduler backlog, and each integration's adapter/capture status.
- **Cron heartbeat:** store the last scheduler tick; if it lapses beyond a threshold, raise the "cron inactivity" critical alert (§21.3) and recommend server cron.
- **Storage probe:** verify the private directory is writable and not web-listable; alert on failure.
- **Queue monitor:** surface failing/stuck jobs, reclaim actions, and per-schedule last-run/next-run on the dashboard.

---

*End of upgraded plan.*
