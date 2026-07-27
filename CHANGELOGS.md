# WP FormVault Changelog

All material project changes are recorded here. This file follows the spirit of [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and uses semantic versioning once release versions exist.

## Mandatory maintenance rules

- Add every user-visible, architectural, schema, security, dependency, compatibility, build, operational, or documentation-control change under `Unreleased` in the same change set.
- Use the categories `Added`, `Changed`, `Deprecated`, `Removed`, `Fixed`, and `Security`.
- Describe outcomes, not activity. Mention migrations, compatibility impact, and administrator action when relevant.
- Never rewrite released history. Correct it with a new dated note if necessary.
- When releasing, move the applicable `Unreleased` entries into a version heading formatted `## [x.y.z] - YYYY-MM-DD`.
- Bug fixes must reference the corresponding ID from [BUGS.md](./BUGS.md).
- Task completion must reference the relevant task IDs from [TASKS.md](./TASKS.md).

## [Unreleased]

### Added

- Added the canonical, hardened [WP FormVault implementation plan](./IMPLEMENTATION_PLAN.md). (`GOV-001`, `GOV-002`)
- Added the module/submodule task register with explicit workflow states, dependencies, completion evidence, phase mapping, and mandatory update rules. (`GOV-003`)
- Added a structured bug register with root-cause, corrective-action, recurrence-prevention, regression-test, and production-incident fields. (`GOV-003`)
- Added factual project memory with source-of-truth rules, current implementation state, architecture, invariants, scope, and decision records. (`GOV-003`)
- Added repository-wide contributor/agent instructions that enforce the mandatory task, changelog, bug, memory, evidence, naming, and sensitive-data workflows. (`GOV-008`)
- Added root README project orientation, current-state disclosure, and links to every canonical control document. (`GOV-002`, `GOV-003`)
- Added the WordPress plugin entry file with WP FormVault metadata, an explicit unreleased development version, compatibility constants, identity/path constants, and the table suffix prefix. (`FND-001`)
- Added a namespace-restricted, idempotent internal autoloader for `WPFormVault\*` classes before Composer integration. (`FND-001`)
- Added tracked directories for every planned application module, asset type, translation catalog, and admin/email template family. (`FND-001`)
- Added a repeatable PHP foundation verifier for bootstrap constants, resolved paths, namespace isolation, unsafe-path rejection, and single autoloader registration. (`FND-001`)
- Added a task dependency-graph verifier covering duplicate IDs, missing dependency references, and cycles. (`GOV-004`, `GOV-006`)
- Added a dependency, namespace-isolation, Action Scheduler coexistence, reproducible-build, and production-packaging policy based on verified upstream requirements. (`ARCH-003`)
- Added build preflight evidence and defect records for the unusable local Composer/GD toolchain and the WordPress/Action Scheduler minimum-version conflict. (`BUG-0004`, `BUG-0005`)

### Changed

- Renamed the planned plugin from the generic “Universal WordPress Form Reporting & Workflow Plugin” to **WP FormVault**. (`GOV-002`, `ARCH-001`)
- Corrected the previous README name typo so all project-facing documents consistently use **WP FormVault**. (`GOV-002`)
- Standardized planned identifiers as plugin slug/text domain `wp-formvault`, bootstrap `wp-formvault.php`, namespace root `WPFormVault`, and technical prefix `wpfv`. (`GOV-002`, `ARCH-001`)
- Updated planned table names, WordPress capabilities, hooks/filters, public download parameter, admin menu label, plugin structure, schema version record, and uninstall table pattern to use the WP FormVault identity. (`GOV-002`, `ARCH-001`)
- Advanced the project from documentation-only status to a verified foundation scaffold; no service container, activation, database, queue, or product runtime has been implemented yet. (`FND-001`)
- Defined PhpSpreadsheet `^5.7.0`, PHP-8.1-compatible ZipStream `~3.0.2`, lock/platform rules, and build-only Strauss 0.28.1 as the dependency baseline; exact lock generation remains blocked. (`ARCH-003`)
- Blocked Composer implementation until the user selects either WordPress 6.5 with Action Scheduler 3.9.3 or WordPress 6.2 with Action Scheduler 3.7.4, and until the local build runtime provides Composer 2.10+ and `ext-gd`. (`ARCH-002`, `ARCH-003`, `FND-002`, `BUG-0004`, `BUG-0005`)

### Deprecated

- None.

### Removed

- None.

### Fixed

- Corrected circular task dependencies that blocked Composer, repository/security, adapter safety, branding validation, and privacy/audit execution order. (`BUG-0001`)
- Corrected the task-graph verifier so its root traversal accepts an empty path. (`BUG-0002`)
- Corrected task-file path resolution for fresh `powershell -File` verifier invocations. (`BUG-0003`)

### Security

- Added direct-access guards to runtime PHP files.
- Restricted autoloading to validated `WPFormVault` namespace segments so class names cannot construct traversal paths. (`FND-001`)
- Required build-time namespace isolation for generic Composer packages, explicit Composer plugin allow-listing, locked/audited dependencies, conflict testing, and no runtime dependency downloads. (`ARCH-003`)

## Release history

No versioned release exists yet.
