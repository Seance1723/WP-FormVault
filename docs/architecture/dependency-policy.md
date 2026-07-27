# WP FormVault Dependency, Isolation, and Packaging Policy

Last reviewed: 2026-07-27  
Owning tasks: `ARCH-002`, `ARCH-003`, `FND-002`  
Status: Accepted except for the Action Scheduler/WordPress compatibility choice in [Decision required](#decision-required)

## Purpose

WP FormVault runs in a shared WordPress PHP process where another plugin may load a different version of the same Composer package. This policy defines the supported dependency lines, namespace-isolation boundary, Action Scheduler coexistence rules, reproducible build inputs, and production artifact requirements.

The implementation must not treat this document as proof that dependencies are installed. `composer.json`, `composer.lock`, generated prefixed code, package tests, and release-artifact inspection remain required under `FND-002`, `QA-001`, and `RELEASE-002`.

## Verified upstream facts

The following facts were checked against upstream project metadata on 2026-07-27:

| Component | Verified upstream state | WP FormVault treatment |
|---|---|---|
| PhpSpreadsheet | Release `5.7.0`; requires PHP `^8.1` and the PHP extensions listed below. | Root constraint `^5.7.0`; initial lock target `5.7.0`. |
| ZipStream-PHP | Release `3.2.2` requires 64-bit PHP `^8.3`; PhpSpreadsheet permits ZipStream `^2.1 || ^3.0`. | Direct root constraint `~3.0.2` while PHP 8.1 remains supported, preventing a build on newer PHP from locking a PHP 8.2/8.3-only minor. |
| Action Scheduler | Release `3.9.3` declares WordPress 6.5+; release `3.7.4` declares WordPress 6.2+. The library uses site-wide latest-version arbitration. | Remains unprefixed. The exact line depends on the product compatibility decision below. |
| Strauss | Release `0.28.1`; the package requires Composer `^2.10`. It is designed to prefix dependencies for WordPress plugins. | Build-only namespace prefixer, pinned to `0.28.1`; never shipped as a runtime dependency. |

Primary references:

- [PhpSpreadsheet 5.7.0 release](https://github.com/PHPOffice/PhpSpreadsheet/releases/tag/5.7.0)
- [PhpSpreadsheet 5.7.0 Composer requirements](https://github.com/PHPOffice/PhpSpreadsheet/blob/5.7.0/composer.json)
- [ZipStream-PHP package metadata](https://packagist.org/packages/maennchen/zipstream-php)
- [Action Scheduler 3.9.3 requirements](https://github.com/woocommerce/action-scheduler/blob/3.9.3/readme.txt)
- [Action Scheduler 3.7.4 requirements](https://github.com/woocommerce/action-scheduler/blob/3.7.4/readme.txt)
- [Action Scheduler library loading and arbitration](https://actionscheduler.org/usage/)
- [Strauss package metadata and configuration](https://packagist.org/packages/brianhenryie/strauss)

## Runtime platform requirements

The dependency set requires:

- PHP 8.1 or newer, subject to the final compatibility matrix.
- A 64-bit PHP build.
- Extensions `ctype`, `dom`, `fileinfo`, `filter`, `gd`, `iconv`, `libxml`, `mbstring`, `simplexml`, `xml`, `xmlreader`, `xmlwriter`, `zip`, and `zlib`.
- WordPress 6.2+ only if the legacy Action Scheduler option is selected; otherwise WordPress 6.5+.

Activation/runtime preflight must report missing requirements without fatal-loading optional report services. The WordPress plugin header and runtime compatibility guard must match the selected WordPress minimum.

## Composer constraints

`FND-002` must implement these root constraints after the compatibility decision:

```text
phpoffice/phpspreadsheet: ^5.7.0
maennchen/zipstream-php: ~3.0.2
brianhenryie/strauss: 0.28.1 (require-dev only)
```

Action Scheduler must use exactly one of these mutually exclusive profiles:

```text
Modern profile: woocommerce/action-scheduler 3.9.3; WordPress >= 6.5
Legacy profile: woocommerce/action-scheduler 3.7.4; WordPress >= 6.2
```

The manifest must also:

- Set `minimum-stability` to `stable` and avoid unbounded development branches.
- Commit `composer.lock`.
- Set Composer's platform PHP to `8.1.0` while PHP 8.1 is advertised, so resolving on a newer build machine cannot silently raise the production minimum.
- Retain Composer platform checks in the generated runtime.
- Sort packages and prefer distribution archives.
- Permit Composer plugins only through an explicit `allow-plugins` list; the default is deny.

The lock file, not a loose version range or this document, is the authoritative installed dependency set.

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

## Action Scheduler coexistence rules

- Package Action Scheduler under `libraries/action-scheduler/`, outside `vendor-prefixed/`.
- Include `action-scheduler.php` while the plugin bootstrap file is loading and before `plugins_loaded` priority `0`.
- Do not call its APIs before `action_scheduler_init` or `init` priority `1`.
- Assume the newest Action Scheduler version registered by any plugin is the active version.
- Code only against APIs available in the selected minimum profile. Any optional newer API must be guarded by feature detection such as `function_exists()` and, where available, `as_supports()`.
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

Consequently `FND-002` cannot produce or verify a lock or prefixed runtime tree in the current environment. See `BUG-0004`.

The preferred correction is a reproducible project build runtime containing Composer 2.10+, 64-bit PHP, and all required extensions, including `gd`. Environment changes outside this repository require separate authorization.

## Decision required

`BUG-0005` records a real compatibility conflict, not a documentation ambiguity:

| Option | WordPress minimum | Bundled Action Scheduler | Consequences |
|---|---:|---:|---|
| **A — Current dependency line (recommended)** | 6.5 | 3.9.3 | Uses the current stable line and its current APIs; drops WordPress 6.2–6.4 support. |
| **B — Preserve original compatibility** | 6.2 | 3.7.4 | Preserves the plan's original minimum but freezes WP FormVault on an older Action Scheduler line and requires a documented maintenance/security exception. |

Do not implement `composer.json`, change plugin headers, or claim a supported WordPress matrix until the user selects one option and `ARCH-002`, `ARCH-003`, `IMPLEMENTATION_PLAN.md`, `MEMORY.md`, and `TASKS.md` are synchronized.
