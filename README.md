# WP FormVault

WP FormVault is a planned adapter-driven WordPress plugin for centralized form-submission indexing, reporting, scheduling, workflow, export, and secure delivery across supported form plugins.

Current status: the guarded plugin entry file, foundation constants, internal namespace autoloader, compatibility profile, Composer lock, isolated dependency build, and planned module directories are implemented and verified. Service startup, activation, database, queue integration, adapters, and product features are not implemented yet.

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

## Project documents

- [Implementation plan](./IMPLEMENTATION_PLAN.md)
- [Task register](./TASKS.md)
- [Changelog](./CHANGELOGS.md)
- [Bug register](./BUGS.md)
- [Project memory](./MEMORY.md)
- [Dependency and packaging policy](./docs/architecture/dependency-policy.md)
- [Repository instructions](./AGENTS.md)
