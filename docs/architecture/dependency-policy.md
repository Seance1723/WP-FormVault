# WP FormVault Dependency, Isolation, and Packaging Policy

Last reviewed: 2026-07-27  
Owning tasks: `ARCH-002`, `ARCH-003`, `FND-002`  
Status: Accepted and implemented by `FND-002`; release artifact verification remains `RELEASE-002`

## Purpose

WP FormVault runs in a shared WordPress PHP process where another plugin may load a different version of the same Composer package. This policy defines the supported dependency lines, namespace-isolation boundary, Action Scheduler coexistence rules, reproducible build inputs, and production artifact requirements.

This document is not proof that a release artifact contains the dependencies. `composer.json`, `composer.lock`, the `FND-002` build evidence, generated-tree tests, and final artifact inspection under `RELEASE-002` control implementation claims.

## Verified upstream facts

The following facts were checked against upstream project metadata on 2026-07-27:

| Component | Verified upstream state | WP FormVault treatment |
|---|---|---|
| PhpSpreadsheet | Release `5.8.1`; requires PHP `^8.1` and is the last release line supporting PHP 8.1. | Root constraint `~5.8.1`; current lock target `5.8.1`. |
| ZipStream-PHP | Release `3.2.2` requires 64-bit PHP `^8.3`; PhpSpreadsheet permits ZipStream `^2.1 || ^3.0`. | Direct root constraint `~3.0.2` while PHP 8.1 remains supported, preventing a build on newer PHP from locking a PHP 8.2/8.3-only minor. |
| Action Scheduler | Current release `4.0.0` declares WordPress 6.8+ and includes breaking behavior changes. Release `3.9.3` declares WordPress 6.5+. The library uses site-wide latest-version arbitration. | Pin `3.9.3`, the latest line compatible with the selected WordPress 6.5 minimum; remains unprefixed. |
| Strauss | Release `0.28.1`; the package requires Composer `^2.10`. It is designed to prefix dependencies for WordPress plugins. | Build-only namespace prefixer, pinned to `0.28.1`; never shipped as a runtime dependency. |

Primary references:

- [PhpSpreadsheet 5.8.1 release and PHP 8.1 boundary](https://github.com/PHPOffice/PhpSpreadsheet/releases/tag/5.8.1)
- [PhpSpreadsheet 5.8.1 Composer requirements](https://github.com/PHPOffice/PhpSpreadsheet/blob/5.8.1/composer.json)
- [ZipStream-PHP package metadata](https://packagist.org/packages/maennchen/zipstream-php)
- [Action Scheduler 4.0.0 release and WordPress 6.8 requirement](https://github.com/woocommerce/action-scheduler/releases/tag/4.0.0)
- [Action Scheduler 3.9.3 requirements](https://github.com/woocommerce/action-scheduler/blob/3.9.3/readme.txt)
- [Action Scheduler 3.7.4 requirements](https://github.com/woocommerce/action-scheduler/blob/3.7.4/readme.txt)
- [Action Scheduler library loading and arbitration](https://actionscheduler.org/usage/)
- [Strauss package metadata and configuration](https://packagist.org/packages/brianhenryie/strauss)

## Runtime platform requirements

The dependency set requires:

- PHP 8.1 or newer, subject to the final compatibility matrix.
- A 64-bit PHP build.
- Extensions `ctype`, `dom`, `fileinfo`, `filter`, `gd`, `iconv`, `libxml`, `mbstring`, `simplexml`, `xml`, `xmlreader`, `xmlwriter`, `zip`, and `zlib`.
- WordPress 6.5 or newer.

Activation/runtime preflight must report missing requirements without fatal-loading optional report services. The WordPress plugin header and runtime compatibility guard must match the selected WordPress minimum.

## Composer constraints

`FND-002` must implement these root constraints:

```text
phpoffice/phpspreadsheet: ~5.8.1
maennchen/zipstream-php: ~3.0.2
brianhenryie/strauss: 0.28.1 (require-dev only)
woocommerce/action-scheduler: ~3.9.3
```

The manifest must also:

- Set `minimum-stability` to `stable` and avoid unbounded development branches.
- Commit `composer.lock`.
- Set Composer's platform PHP to `8.1.0` while PHP 8.1 is advertised, so resolving on a newer build machine cannot silently raise the production minimum.
- Retain Composer platform checks in the generated runtime.
- Sort packages and prefer distribution archives.
- Permit Composer plugins only through an explicit `allow-plugins` list; the default is deny.

The lock file, not a loose version range or this document, is the authoritative installed dependency set.

Compatible-line constraints intentionally exclude the next minor/major boundary. The committed lock and dependency verifier identify the exact shipped versions; changing a locked version remains an explicit update operation.

## Namespace and symbol isolation

All generic runtime Composer packages must be copied to `vendor-prefixed/` and rewritten with:

```text
Namespace prefix: WPFormVault\Vendor\
Global class prefix: WPFormVault_Vendor_
Global constant prefix: WPFV_VENDOR_
```

The prefixing input is the complete resolved production dependency closure for PhpSpreadsheet and ZipStream, including new transitive packages introduced by a future lock update. It is not limited to a permanently hard-coded package list.

The build must:

1. Generate the prefixed tree from a clean, locked Composer install.
2. Update WP FormVault call sites to the prefixed namespace.
3. Generate an optimized, authoritative autoloader for the prefixed tree.
4. Verify no unprefixed PhpSpreadsheet, ZipStream, MarkBaker, Composer PCRE, or PSR Simple Cache runtime references remain in WP FormVault code or the generated dependency tree.
5. Run smoke tests with deliberately conflicting unprefixed package versions loaded first.

Action Scheduler is excluded from prefixing because its public functions, classes, hooks, data tables, and version-arbitration mechanism are intentionally shared across WordPress plugins.

Strauss 0.28.1 ambiguously rewrites return types when a short class name equals its root namespace (`Complex`, `Matrix`, and `ZipStream`) in the current lock. The build therefore runs `tools/patch-prefixed-dependencies.php` immediately after Strauss. The patch expands only those generated return types to their full isolated class names, requires the reviewed exact correction counts, and fails closed on dependency/prefixer drift. Removal or alteration of this workaround requires a real XLSX write plus Complex/Matrix regression tests.

## Action Scheduler coexistence rules

- Package Action Scheduler under `libraries/action-scheduler/`, outside `vendor-prefixed/`.
- Include `action-scheduler.php` while the plugin bootstrap file is loading and before `plugins_loaded` priority `0`.
- Do not call its APIs before `action_scheduler_init` or `init` priority `1`.
- Assume the newest Action Scheduler version registered by any plugin is the active version.
- Code against APIs available in Action Scheduler 3.9.3. Any API introduced after 3.9.3 must be guarded by feature detection such as `function_exists()` and `as_supports()`.
- Prefix WP FormVault action hooks and groups with `wpfv_`.
- Add integration tests where an older compatible copy, the bundled copy, and a newer compatible copy are registered in different orders.
- If the active site-wide version is below WP FormVault's minimum API contract, disable queue-backed WP FormVault operations safely and surface an administrator/Site Health diagnostic instead of fatal erroring.

## Development and production layouts

Development/build workspace:

```text
vendor/                         Composer install, including build tools
vendor-prefixed/                generated isolated runtime libraries
libraries/action-scheduler/     generated/copied unprefixed shared library
```

Production ZIP:

```text
wp-formvault/
  wp-formvault.php
  includes/
  assets/
  languages/
  templates/
  vendor-prefixed/
  libraries/action-scheduler/
  readme.txt
  dependency and license notices
```

The production artifact must exclude development dependencies, tests and fixtures not needed at runtime, Composer caches, source-control metadata, local environment files, raw unprefixed generic packages, and the Strauss executable/package. It must never download or update dependencies at WordPress runtime.

## Reproducible build and upgrade gates

`FND-002` and release tooling must provide repeatable commands that:

1. Validate `composer.json` and `composer.lock` strictly.
2. Install the exact lock in a clean build directory using Composer 2.10+.
3. Run `composer audit` and fail on unresolved applicable security advisories.
4. Generate the prefixed runtime tree and Action Scheduler library copy.
5. Install production dependencies from the lock with scripts/plugins restricted to the reviewed allow-list.
6. Run syntax, dependency-isolation, minimum-platform, Action Scheduler coexistence, and report/ZIP smoke tests.
7. Build the ZIP twice from the same source and compare normalized contents/checksums.
8. Produce dependency and license notices from the locked packages.
9. Inspect the final ZIP rather than only the source tree.

A dependency update requires its own task/change record, review of upstream changelogs and requirements, a refreshed lock, audit, minimum/maximum platform tests, conflict tests, report-format regression tests, and artifact inspection. No dependency may update automatically in a production WordPress installation.

## Local environment preflight

Observed on 2026-07-27:

- The running `localdev_php_apache` container uses PHP 8.2.31 on 64-bit architecture.
- It has all currently listed PhpSpreadsheet runtime extensions except `gd`.
- No Composer executable is available in that container.
- The host has a Composer launcher, but it is unusable because host PHP is not on `PATH`.

`FND-002` resolved this without changing the shared environment. `docker/dependency-build/Dockerfile` defines a repository-owned, digest-pinned PHP 8.1.34 / Composer 2.10.2 build image with the complete extension set. `tools/run-dependency-build.ps1` uses that image for explicit lock updates and normal lock-only builds. See the closed `BUG-0004`.

The verified lock-only build on 2026-07-27 passed strict manifest/lock validation, security audit, platform checks, Strauss generation, count-locked homonym corrections, Action Scheduler staging, notices, syntax checks for 722 generated PHP files, namespace-conflict checks, Complex/Matrix runtime checks, and real XLSX/ZIP generation.

## Selected compatibility profile

The user selected option A on 2026-07-27:

```text
WordPress minimum: 6.5
PHP minimum: 8.1 on 64-bit architecture
Database minimum: MySQL 5.7 or MariaDB 10.4
Bundled Action Scheduler: 3.9.3
```

This intentionally replaces the original WordPress 6.2 baseline so WP FormVault can use the selected WordPress-6.5-compatible Action Scheduler line. `BUG-0005` preserves the discovery, tradeoff, and resolution evidence.

Action Scheduler 4.0.0 is the current upstream release, but it requires WordPress 6.8 and includes breaking uniqueness/retention behavior changes. It is intentionally excluded from this profile. Adopting 4.x requires a separate compatibility/dependency task, explicit review of those behavior changes, and a decision to raise WP FormVault's WordPress minimum.
