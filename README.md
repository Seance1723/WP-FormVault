# WP FormVault

WP FormVault is a planned adapter-driven WordPress plugin for centralized form-submission indexing, reporting, scheduling, workflow, export, and secure delivery across supported form plugins.

Current status: the guarded plugin entry file, foundation constants, internal namespace autoloader, compatibility profile, Composer lock, isolated dependency build, explicit service container, fail-closed composition root, planned module directories, and enforceable module-boundary architecture are implemented and verified. Production boot currently stops safely at the pending schema gate. Activation, database/migrations, product hook providers, queue APIs, adapters, and product features are not implemented yet.

## Architecture verification

The accepted architecture uses `WPFormVault\Core\Plugin` as the sole composition root and `WPFormVault\Core\ServiceContainer` as a small explicit-dependency container. The 15-module, 63-edge dependency graph is machine-readable and enforced against current PHP imports.

```powershell
php tools/verify-bootstrap.php
php tools/verify-architecture.php
powershell -NoProfile -ExecutionPolicy Bypass -File tools/verify-task-graph.ps1
```

The bootstrap verifier covers lazy/shared/transient services, aliases, duplicate/missing/circular/frozen failures, packaged dependency loading, gate order, safe diagnostics, hook idempotency, and site-graph isolation. The architecture verifier rejects outward/cyclic layer edges, undeclared modules, private cross-module imports, and container references outside the composition boundary.

## Dependency build

WP FormVault requires WordPress 6.5+, 64-bit PHP 8.1+, and the PHP extensions listed in `composer.json`. Generic Composer libraries are prefixed under `WPFormVault\Vendor`; Action Scheduler 3.9.3 is staged separately so its WordPress-wide version arbitration remains intact.

On Windows with Docker available:

```powershell
# Only when intentionally creating or updating composer.lock.
powershell -NoProfile -ExecutionPolicy Bypass -File tools/run-dependency-build.ps1 -UpdateLock

# Normal reproducible install/build from the committed lock.
powershell -NoProfile -ExecutionPolicy Bypass -File tools/run-dependency-build.ps1
```

The script builds a digest-pinned PHP 8.1/Composer 2.10 image, verifies required extensions, validates/audits the lock, generates `vendor-prefixed/`, stages `libraries/action-scheduler/`, regenerates dependency notices, syntax-checks generated PHP, and runs isolation/conflict plus real XLSX/ZIP checks. Generated directories are ignored; release packaging regenerates them from `composer.lock`.

A clean Docker Desktop build over the Windows bind mount measured approximately 306 seconds on 2026-07-27. Automated callers should allow at least 420 seconds for this command. If a caller is interrupted or times out, inspect project-scoped Docker containers before starting another build; the `--rm` container may still be completing Strauss generation.

## Project documents

- [Implementation plan](./IMPLEMENTATION_PLAN.md)
- [Task register](./TASKS.md)
- [Changelog](./CHANGELOGS.md)
- [Bug register](./BUGS.md)
- [Project memory](./MEMORY.md)
- [Dependency and packaging policy](./docs/architecture/dependency-policy.md)
- [Service-container and module-boundary architecture](./docs/architecture/service-container-and-module-boundaries.md)
- [Machine-readable module graph](./docs/architecture/module-boundaries.json)
- [Repository instructions](./AGENTS.md)
