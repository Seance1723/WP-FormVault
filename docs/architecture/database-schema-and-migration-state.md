# WP FormVault Database Schema and Migration-State Contract

Status: Accepted design for `DB-001`; control-plane runtime implemented by `DB-002`
Machine-readable authority: [`database-schema-policy.json`](./database-schema-policy.json)  
Runtime implementation owners: control plane and runner `DB-002`; numbered domain schema `DB-003` through `DB-007`

## Scope and current truth

This document freezes the database inventory, portable column types, application-level relations, schema sequence, and per-site migration-state model.

`DB-002` now implements the exact `wpfv_schema_version` and `wpfv_locks` tables, target-zero registry/coordinator, activation and ordinary-load checks, fenced state transitions, and fail-closed schema gate. The numbered migrations listed below remain reserved contracts and are not proof that domain table groups have been installed.

## Physical naming and site isolation

- Every table is site-local and resolves as `$wpdb->prefix . 'wpfv_' . $suffix`.
- The literal `wp_` prefix is illustrative only and must never appear in runtime table construction.
- A table does not store `site_id`; the current site's prefix is the isolation boundary.
- Network activation provisions existing sites individually. New multisite sites are provisioned from `wp_initialize_site`.
- Every site has an independent installed version, migration state, and `schema_migration` lease. There is no network-global schema version that can make an unprovisioned site appear ready.
- Code switching sites must construct the target site's graph after `switch_to_blog()` and must restore the original site in a `finally` path.

WordPress documents the active database prefix through `$wpdb->prefix`, exposes site-specific prefixes through `wpdb::get_blog_prefix()`, and fires `wp_initialize_site` during new-site initialization:

- [Creating Tables with Plugins](https://developer.wordpress.org/plugins/creating-tables-with-plugins/)
- [`wpdb::get_blog_prefix()`](https://developer.wordpress.org/reference/classes/wpdb/get_blog_prefix/)
- [`wp_initialize_site`](https://developer.wordpress.org/reference/hooks/wp_initialize_site/)

## Portable storage rules

| Concern | Contract |
|---|---|
| Database baseline | MySQL 5.7+ or MariaDB 10.4+ |
| Character set/collation | Append `$wpdb->get_charset_collate()` to every table definition |
| Identifiers | Unsigned `bigint(20)` values, matching WordPress-scale identifiers |
| Runtime timestamps | `datetime`, written and interpreted as UTC; nullable dates use SQL `NULL`, never a zero date |
| Human schedule zone | Store the IANA timezone name separately; calculate local boundaries before converting them to UTC |
| Boolean values | Unsigned `tinyint(1)` with application validation |
| JSON documents | UTF-8 JSON encoded into `longtext`, schema-validated before every write/read |
| Hashes | Raw 32-byte SHA-256 digests in `binary(32)`; live tokens and lease-owner secrets are never persisted |
| IP addresses | Optional packed `varbinary(16)` only where the privacy setting permits; download history uses a hash |
| Generated files | Store an opaque relative storage key, never an absolute server path or public URL |
| Table engine | Use the site's supported InnoDB-capable default; do not add database foreign keys |

`longtext` is deliberately used for the portable JSON contract. Native MySQL and MariaDB JSON behavior is not used as an invariant. Generated columns remain an optional, separately tested optimization rather than part of schema versions 1–4.

Relations are enforced in repository/services and migrations. This matches the plan's cross-engine requirement and avoids `dbDelta()` foreign-key limitations. Every destructive application operation must implement the relation's declared `cascade`, `restrict`, `set_null`, or `anonymize` action and run its verification before commit.

## Complete table inventory

The JSON policy owns exact ordered columns, SQL type profiles, primary keys, unique candidate keys, and relations for all 34 suffixes.

| Stage | Owner | Tables | Purpose |
|---:|---|---|---|
| Control plane | `DB-002` | `wpfv_schema_version`, `wpfv_locks` | Schema state and fenced leases; bootstrapped idempotently before numbered migrations |
| 1 | `DB-003` | `wpfv_forms`, `wpfv_form_fields`, `wpfv_submissions`, `wpfv_submission_snapshot`, `wpfv_submission_values` | Source/form catalog and hybrid canonical-snapshot plus selective-EAV submission index |
| 2 | `DB-004` | `wpfv_schedules`, `wpfv_schedule_forms`, `wpfv_schedule_fields`, `wpfv_schedule_filters`, `wpfv_schedule_recipients`, `wpfv_schedule_mappings`, `wpfv_report_templates`, `wpfv_reports`, `wpfv_report_files`, `wpfv_report_records`, `wpfv_report_deliveries` | Calendar-safe configuration, permanent report history, reproducibility snapshots, generated-file metadata, and handoff attempts |
| 3 | `DB-005` | `wpfv_submission_workflow`, `wpfv_submission_notes`, `wpfv_tags`, `wpfv_submission_tags`, `wpfv_saved_views`, `wpfv_notifications`, `wpfv_notification_prefs`, `wpfv_automation_rules`, `wpfv_automation_actions`, `wpfv_audit_logs` | Workflow, optimistic edits, user views, notifications, bounded automation, and anonymization-aware audit history |
| 4 | `DB-006` | `wpfv_download_tokens`, `wpfv_download_logs`, `wpfv_sync_logs`, `wpfv_jobs`, `wpfv_sync_cursors`, `wpfv_access_grants` | Hashed download credentials, operational history, reconciliation cursors, job correlation, and repository-level access grants |

`DB-007` owns the final physical index implementation and query-plan tests. The following candidate keys are already frozen and cannot be omitted:

- submissions: unique `(source_plugin, source_form_id, source_submission_id)`;
- forms: unique `(source_plugin, source_form_id)`;
- submission values: unique `(submission_id, field_key, value_position)`;
- reports, report deliveries, and jobs: unique binary `idempotency_key`;
- download tokens: unique binary `token_hash`;
- locks: unique `lock_key`;
- reconciliation cursors: unique `(source_plugin, source_form_id)`;
- join/configuration tables: the natural composite keys declared in the policy.

## Submission and privacy invariants

- `wpfv_submission_snapshot.normalized_json` is the current canonical normalized record. `wpfv_submission_values` contains only configured filter/sort projections.
- `data_hash` is a binary SHA-256 digest of canonical normalized data.
- The unique source triple makes real-time capture and datastore reconciliation idempotent.
- Capture-mode submissions treat this index as authoritative; datastore submissions retain source identity for reconciliation.
- IP and user-agent columns remain nullable and their capture defaults off.
- Report records are distinct as-of snapshots. Erasure changes their relation to `anonymize`; report history survives without retaining the erased subject's payload.
- Download credentials store only `token_hash`, optional password hashes, expiry/revocation/cap state, and optional IP-binding hashes.
- Audit and error fields store stable codes and redacted structured data, never secrets, raw tokens, passwords, or arbitrary exception messages.

## Schema version model

Schema versions are monotonically increasing non-negative integers. They are independent of the plugin's semantic version.

- Version `0` means no numbered domain migration is committed.
- The control-plane tables are an idempotent bootstrap prerequisite and do not advance the numbered installed version.
- The runtime target is the highest contiguous migration actually registered by the installed code. A future migration file does not become part of the target merely because it is described here.
- Fresh installation runs the same `0 → 1 → … → target` chain as upgrades.
- The runner advances `installed_version` only after a step's postconditions pass.
- Missing version numbers, duplicate migration IDs, or a target that is not contiguous are hard configuration failures.
- No automatic downgrade is permitted. If the database is newer than the code, startup enters `blocked_newer`.

The planned allocation is:

| Installed version | Owning task | Committed domain |
|---:|---|---|
| 0 | `DB-002` | Control-plane bootstrap only |
| 1 | `DB-003` | Submission index |
| 2 | `DB-004` | Schedules and reports |
| 3 | `DB-005` | Workflow and automation |
| 4 | `DB-006` | Operations and access |

## Per-site migration state

`wpfv_schema_version` has exactly one logical row with primary key `1`. It contains the last committed version, current code target, state, migration/run identifiers, UTC lifecycle timestamps, retry count, stable error code, and optimistic `row_version`.

| State | Meaning | Schema-dependent work |
|---|---|---|
| `uninitialized` | Control row absent or newly seeded at version 0 | Blocked |
| `pending` | Installed version is behind target and no step is executing | Blocked |
| `running` | A foreground schema step owns the lease | Blocked |
| `awaiting_background` | A batched data transform is incomplete | Blocked |
| `failed` | The last attempt failed; installed version remains last-known-good | Blocked |
| `ready` | Installed version equals target and no schema lease is active | Allowed |
| `blocked_newer` | Installed version is greater than the installed code's target | Blocked until compatible/newer code is installed |

Allowed normal transitions are:

```text
uninitialized -> pending -> running -> ready
                                  \-> awaiting_background -> running
                                  \-> failed -> running
ready -> pending                  (new target registered)
any state -> blocked_newer        (installed version exceeds code target)
```

`ready` is valid only when all of these are true:

1. `installed_version === target_version`;
2. state is `ready`;
3. no unexpired `schema_migration` lease exists;
4. control-plane and current-version postconditions pass.

The schema gate returns a typed, sanitized reason. It never starts report generation, queue dispatch, repositories, admin data controllers, or other schema-dependent registrars while blocked.

## Lease and recovery contract

The `wpfv_locks` row whose `lock_key` is `schema_migration` is a fenced lease:

1. Generate a cryptographically random owner token in memory and persist only its SHA-256 hash.
2. Acquire with one atomic insert, or an atomic conditional update where the existing `expires_at` is in the past.
3. Increment `fencing_token` on every successful acquisition. Every state write includes the current fence so a stale worker cannot commit after its lease is replaced.
4. Heartbeat before the lease window expires. Heartbeat and release compare both the owner-token hash and fence.
5. Re-read the schema singleton after acquisition; never trust the pre-lock read.
6. On failure, leave `installed_version` at the last completed step, set state `failed`, store a stable redacted error code/timestamp, and release the owned lease. An abandoned lease becomes reclaimable only after expiry.
7. A retry reruns the idempotent step and its pre/postconditions. It does not assume transactional rollback of DDL.

Production leases last 120 seconds. The implementation accepts only 30-3600 seconds through its explicit test/configuration seam and heartbeats immediately before and after every numbered foreground step. Release retains the row, clears its metadata, marks it released, and expires it at the current UTC instant; the next atomic acquisition therefore increments the existing fencing token instead of resetting it. Expiry/reclaim behavior is deterministic and every state write checks owner hash, fence, optimistic `row_version`, and unexpired ownership.

## Migration execution contract

- Bootstrap `wpfv_schema_version` and `wpfv_locks` with exact, idempotent definitions before acquiring the schema lease.
- Run the version check on activation and on an ordinary safe upgrade-check hook because WordPress does not invoke plugin activation hooks for normal plugin updates.
- Never perform a long migration inside an unrestricted front-end request. Foreground DDL remains bounded; data transforms move to the `awaiting_background` path owned by `DB-009`.
- Use `dbDelta()` only for table creation and reviewed additive changes. Renames, drops, type narrowing, destructive changes, and non-additive alterations require explicit SQL, preconditions, backups/rollback guidance, and postcondition checks.
- Every migration has a stable ID, from/to version, idempotent `up` behavior, postconditions, documented reversibility status, and sanitized failure codes.
- A migration is complete only after inspecting the actual schema, not because `dbDelta()` returned without throwing.
- Application data writes and queue work do not occur in constructors or migration definitions.
- Runtime table identifiers come only from the frozen suffix allow-list. Dynamic user values are always bound separately from SQL identifiers.

WordPress documents that `dbDelta()` compares a `CREATE TABLE` definition to the existing table, has strict SQL formatting requirements, and that activation does not run on ordinary plugin updates. The implementation must encode those constraints rather than depend on manual formatting.

## Verification and downstream acceptance

Run:

```text
php tools/verify-database-schema-policy.php
```

The verifier rejects invalid JSON, missing/extra table suffixes, unsafe physical naming, native JSON dependence, unknown type profiles, duplicate/missing columns, invalid primary/unique keys, broken relations, invalid deletion policies, non-UTC runtime timestamps, a non-contiguous migration sequence, missing security/idempotency keys, or drift from the canonical plan.

Runtime and downstream database-backed test ownership:

- `DB-002` (implemented): exact bootstrap postconditions, fresh target-zero convergence, ready-path idempotency, activation/ordinary hook registration, lease serialization, monotonic release/reacquisition fences, stale-owner rejection, failed-run retry counting, current-site prefixes in single-site/multisite, and newer-schema blocking on MySQL 5.7 and MariaDB 10.4;
- `DB-003`–`DB-006`: fresh creation and upgrade from every prior version on MySQL and MariaDB, single site and multisite;
- `DB-007`: physical indexes and representative `EXPLAIN` plans;
- `DB-009`: background-transform resumption and fail-closed report generation;
- `DB-010`: fresh install, partial failure, retry, downgrade refusal, backup/rollback guidance, and no-loss validation.
