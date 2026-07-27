# WP FormVault Engineering Quality and CI Policy

Last reviewed: 2026-07-27  
Owning task: `ARCH-005`  
Implementation task: `QA-001`  
Status: Policy accepted; tool installation, test bootstraps, and hosted CI workflows are not yet implemented

## Purpose and truth boundary

This policy freezes the coding standard, static-analysis strictness, test taxonomy, and CI coverage required for WP FormVault. The machine-readable contract is [`quality-policy.json`](./quality-policy.json).

Policy acceptance is not evidence that PHPUnit, PHPCS, PHPStan, a WordPress integration harness, or hosted CI exists. `QA-001` owns those installations and configurations. A future release cannot treat a required lane as passed when that lane is missing, skipped, or unable to resolve its environment.

## Dated upstream reference snapshot

These facts were checked against primary upstream sources on 2026-07-27. They explain the initial matrix but do not turn rolling targets into permanent version pins.

| Upstream fact | Verified state | Policy consequence |
|---|---|---|
| WordPress stable | WordPress `7.0.2` is the latest stable release. | Current lanes resolve `latest-stable` at run time and log the exact version. |
| PHP support | PHP `8.2`–`8.5` are supported upstream; PHP `8.1` is end-of-life. | WP FormVault retains PHP 8.1 because it is the user-selected product minimum, marks it as legacy coverage, and also tests every currently supported PHP minor. |
| PHPUnit/PHP compatibility | PHPUnit 10 requires PHP 8.1+; newer PHPUnit majors require newer PHP minimums. | `QA-001` must select a maintained PHPUnit 10 minor that can execute the same suite across the advertised PHP band. |
| WordPress Coding Standards | WordPressCS provides `WordPress-Core`, `WordPress-Docs`, and `WordPress-Extra`, and recommends PHPCompatibilityWP for plugin compatibility checks. | All four rulesets are mandatory for first-party PHP. |
| PHPStan rule levels | PHPStan levels are cumulative; level 8 adds nullable method/property safety. | First-party runtime code starts at level 8, with no generated baseline. |

Primary references:

- [WordPress release archive](https://wordpress.org/download/releases/)
- [PHP supported versions](https://www.php.net/supported-versions.php)
- [Supported versions of PHPUnit](https://phpunit.de/supported-versions.html)
- [WordPress Coding Standards](https://github.com/WordPress/WordPress-Coding-Standards)
- [PHPStan rule levels](https://phpstan.org/user-guide/rule-levels)
- [WordPress PHPUnit testing handbook](https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/)

## Coding standard

First-party PHP must pass PHP_CodeSniffer with:

- `WordPress-Core`
- `WordPress-Docs`
- `WordPress-Extra`
- `PHPCompatibilityWP` with `testVersion` set to `8.1-`

The scan includes `wp-formvault.php`, `includes/`, `tools/`, and `tests/`. Generated or third-party trees (`vendor/`, `vendor-prefixed/`, `libraries/`) and build outputs are excluded because their upstream source is not owned here.

The stable naming rules remain:

- Namespace root: `WPFormVault`
- Global function, option, hook, capability, route, and database suffix prefix: `wpfv`
- Text domain and slug: `wp-formvault`
- Bootstrap: `wp-formvault.php`

No new PHPCS error or warning is accepted. An inline suppression must name the exact sniff and include a nearby reason explaining why the code is safe. Broad file-level or ruleset-level exclusions require a task, changelog entry, and reviewer-visible justification.

## Static analysis

PHPStan runs at **level 8** against `wp-formvault.php` and `includes/`. WordPress and Action Scheduler symbols must come from version-compatible stubs or explicit project stubs; application defects must not be hidden by declaring broad symbols as `mixed`.

The following gates are mandatory:

- no generated PHPStan baseline;
- unmatched ignored-error patterns fail;
- every ignore is narrow, reasoned, and tied to a specific upstream dynamic boundary;
- generated dependencies and third-party libraries are excluded;
- a level reduction or new blanket ignore is an architectural change owned by a task and recorded in `CHANGELOGS.md`.

Level 8 is a floor, not a ceiling. Raising it requires the full current suite to pass and an update to both policy files.

## Test taxonomy and layout

`QA-001` creates the following layout:

```text
tests/
  Unit/          Pure domain and utility tests; no WordPress bootstrap
  Integration/   WordPress/database/queue/adapter/mail/filesystem/cache boundaries
  Functional/    Complete user-visible and scheduled workflows
  Security/      Authorization, injection, token, path, serialization, and leakage regressions
  Performance/   Explicit scale and concurrency benchmarks
  Support/       Bootstraps, factories, doubles, and deterministic helpers
  Fixtures/      Synthetic, redacted, non-production fixtures only
```

Rules:

- Unit tests must not load WordPress or connect to a database.
- WordPress integration tests use a dedicated ephemeral database and leave no state for another test.
- Network access is denied by default. A test that genuinely exercises an external boundary must use a local substitute or be explicitly isolated.
- Time, randomness, queue dispatch, mail, and filesystem behavior use injected/frozen substitutes when determinism matters.
- Fixtures must never contain secrets, live tokens, passwords, personal form values, or unredacted production data.
- Regression tests reference the corresponding `BUGS.md` ID in their docblock, data-provider label, or test name when practical.
- Performance tests do not run in the fast pull-request lane; they are release-candidate gates with recorded environment and thresholds.

## Required CI matrix

The authoritative lane objects live in `quality-policy.json`. The concise human view is:

| Lane | WordPress | PHP | Database/mode | Cadence | Gate |
|---|---|---|---|---|---|
| `quality-minimum` | none | 8.1 | none | PR + push | Blocking |
| `quality-latest` | none | latest supported | none | PR + push | Blocking |
| `integration-minimum-mysql` | 6.5.0 | 8.1 | MySQL 5.7 / single site | PR + push | Blocking |
| `integration-minimum-mariadb` | 6.5.0 | 8.1 | MariaDB 10.4 / single site | PR + push | Blocking |
| `integration-current-php-band` | latest stable | every upstream-supported PHP minor | latest supported stable MySQL / single site | PR + push | Blocking |
| `integration-current-mariadb-multisite` | latest stable | latest supported | latest supported stable MariaDB / multisite | PR + push | Blocking |
| `dependency-build-minimum` | none | 8.1 | none | PR + push | Blocking |
| `wordpress-trunk-forward-compatibility` | trunk | latest supported | latest supported stable MySQL / single site | Nightly | Informational |
| `performance-release-candidate` | latest stable | latest supported | latest supported stable MySQL / single site | Release candidate | Blocking |

The exact versions behind every rolling label must be resolved immediately before the job starts and printed in the durable job log. A cache or container tag must not make a reported “latest” value unverifiable.

The WordPress 6.5.0 minimum lanes are isolated compatibility tests only: no public network ingress and no production data. They prove the advertised API floor even when that historical release is no longer maintained.

PHP 8.1 remains a blocking lane while WP FormVault advertises it, despite upstream end-of-life. Any proposal to remove it changes the product compatibility contract and requires explicit user approval plus synchronized updates to the plugin header, runtime guard, dependency policy, plan, memory, tasks, and release notes.

## CI failure and release rules

- Every blocking lane must exist, execute, and pass before release.
- A skipped, cancelled, unresolved, or missing blocking lane is a failure, not neutral evidence.
- Nightly WordPress-trunk failures do not automatically block a release, but must be recorded or linked in `BUGS.md` before the next release and classified as project defect, dependency limitation, or upstream pre-release breakage.
- Dependency audit findings follow the dependency policy and cannot be silenced without an explicit reviewed risk decision.
- CI logs record resolved WordPress, PHP, database, PHPUnit, PHPCS/WPCS, PHPCompatibilityWP, PHPStan, Composer, and dependency-lock identities.
- Release evidence links to immutable CI runs and artifact checks; a local pass alone is insufficient once hosted CI exists.

## Change control

`ARCH-005` owns this policy. `QA-001` must implement it without weakening it. Changes to rulesets, analysis level, test taxonomy, required lanes, blocking status, or compatibility targets require:

1. an owning task in `TASKS.md`;
2. synchronized edits to this document and `quality-policy.json`;
3. an entry in `CHANGELOGS.md`;
4. a `MEMORY.md` update for stable decisions;
5. `php tools/verify-quality-policy.php`;
6. relevant installed quality checks once `QA-001` is complete.
