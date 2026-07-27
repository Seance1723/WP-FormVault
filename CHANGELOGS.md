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
- Added a repeatable compatibility verifier that detects drift between the selected platform, plugin metadata/constants, dependency policy, memory, and task register. (`ARCH-002`, `BUG-0005`)
- Added a Composer manifest with explicit PHP extensions, locked runtime constraints, a PHP 8.1 platform target, stable-only resolution, and a deny-by-default Composer plugin policy. (`FND-002`)
- Added a repository-owned PHP 8.1/Composer 2.10 dependency-build container and repeatable PowerShell entry point. (`FND-002`, `BUG-0004`)
- Added dependency platform preflight, safe generated-tree cleanup, Strauss namespace isolation, Action Scheduler 3.9.3 staging, exact-version checks, and deliberate cross-plugin conflict verification. (`FND-002`)
- Added an isolated PhpSpreadsheet XLSX-write/ZIP-structure smoke test and Composer lock platform-requirement check to the dependency build gate. (`FND-002`)
- Added deterministic runtime dependency/license notices and syntax verification for every generated prefixed and Action Scheduler PHP file. (`FND-002`)
- Added the service-container composition contract, fail-closed startup phases, service scope rules, public cross-module surfaces, and ownership boundaries for all 15 modules. (`ARCH-004`)
- Added a machine-readable 15-module dependency graph and PHP architecture verifier that enforces 63 inward edges, terminal transport modules, declared module directories, public cross-module imports, and composition-root-only container access. (`ARCH-004`)

### Changed

- Renamed the planned plugin from the generic “Universal WordPress Form Reporting & Workflow Plugin” to **WP FormVault**. (`GOV-002`, `ARCH-001`)
- Corrected the previous README name typo so all project-facing documents consistently use **WP FormVault**. (`GOV-002`)
- Standardized planned identifiers as plugin slug/text domain `wp-formvault`, bootstrap `wp-formvault.php`, namespace root `WPFormVault`, and technical prefix `wpfv`. (`GOV-002`, `ARCH-001`)
- Updated planned table names, WordPress capabilities, hooks/filters, public download parameter, admin menu label, plugin structure, schema version record, and uninstall table pattern to use the WP FormVault identity. (`GOV-002`, `ARCH-001`)
- Advanced the project from documentation-only status to a verified foundation scaffold; no service container, activation, database, queue, or product runtime has been implemented yet. (`FND-001`)
- Defined PhpSpreadsheet `~5.8.1` (the last PHP 8.1-compatible line), ZipStream `~3.0.2`, Action Scheduler `~3.9.3`, lock/platform rules, and build-only Strauss 0.28.1 as the dependency baseline. (`ARCH-003`, `BUG-0007`, `BUG-0008`)
- Raised the required WordPress version from 6.2 to 6.5 and selected Action Scheduler 3.9.3 after explicit user approval, resolving the dependency compatibility conflict. (`ARCH-002`, `ARCH-003`, `BUG-0005`)
- Replaced reliance on the incomplete shared PHP environment with a repository-owned, digest-pinned dependency build and completed the first audited PHP 8.1 lock. (`FND-002`, `BUG-0004`)
- Established `WPFormVault\Core\Plugin` as the sole composition root and `WPFormVault\Core\ServiceContainer` as the explicit, frozen, request/site-scoped container contract required by `FND-003`. (`ARCH-004`)

### Deprecated

- None.

### Removed

- None.

### Fixed

- Corrected circular task dependencies that blocked Composer, repository/security, adapter safety, branding validation, and privacy/audit execution order. (`BUG-0001`)
- Corrected the task-graph verifier so its root traversal accepts an empty path. (`BUG-0002`)
- Corrected task-file path resolution for fresh `powershell -File` verifier invocations. (`BUG-0003`)
- Resolved the missing local Composer/GD build prerequisites with a repository-owned, digest-pinned PHP 8.1 dependency environment and fail-fast platform checks. (`BUG-0004`)
- Resolved the platform/dependency mismatch by aligning the implementation plan, plugin metadata/constants, policy, memory, and task register on WordPress 6.5+ with Action Scheduler 3.9.3. (`BUG-0005`)
- Corrected dependency lifecycle wording: Action Scheduler 4.0.0 is current but requires WordPress 6.8; selected 3.9.3 is the latest line compatible with WP FormVault's WordPress 6.5 floor. (`BUG-0006`)
- Corrected the dependency-isolation verifier to recognize Strauss's intentionally prefixed Composer class loader before performing behavioral conflict checks. (`BUG-0009`)
- Corrected Strauss-generated namespace/class-homonym return types through a count-locked, fail-closed post-prefix step, preventing fatal XLSX, Complex, and Matrix operations. (`BUG-0010`)
- Corrected the provisional PhpSpreadsheet baseline to 5.8.1, the final upstream line supporting PHP 8.1. (`BUG-0007`)
- Corrected the Action Scheduler manifest constraint so strict Composer validation passes while the lock preserves exact version 3.9.3 and excludes 4.x. (`BUG-0008`)
- Decoupled the compatibility verifier from mutable policy lifecycle wording while retaining enforcement of the selected platform and dependency boundaries. (`ARCH-002`, `BUG-0011`)

### Security

- Added direct-access guards to runtime PHP files.
- Restricted autoloading to validated `WPFormVault` namespace segments so class names cannot construct traversal paths. (`FND-001`)
- Required build-time namespace isolation for generic Composer packages, explicit Composer plugin allow-listing, locked/audited dependencies, conflict testing, and no runtime dependency downloads. (`ARCH-003`)

## Release history

No versioned release exists yet.
