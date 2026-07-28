# WP FormVault Bug Register

Last updated: 2026-07-28

## Purpose

This is the mandatory record for defects found during development, testing, deployment, runtime operation, or production. It captures not only what failed, but why it failed and how recurrence will be prevented.

## Bug states

| State | Meaning |
|---|---|
| `NEW` | Reported but not yet reproduced/triaged. |
| `TRIAGED` | Scope, severity, and owner are understood. |
| `IN_PROGRESS` | A fix is being implemented. |
| `BLOCKED` | Fix cannot proceed; blocker is documented. |
| `VERIFY` | Fix exists; regression and relevant broader tests are pending. |
| `RESOLVED` | Root cause addressed and verification passed. |
| `CLOSED` | Resolution accepted and no further work remains. |
| `WONT_FIX` | Intentionally not fixed; rationale and risk acceptance recorded. |
| `DUPLICATE` | Same root cause as another bug; canonical ID recorded. |
| `CANNOT_REPRODUCE` | Evidence is insufficient after documented attempts; may be reopened. |

## Severity

| Severity | Definition |
|---|---|
| `S0 Critical` | Active data loss, remote compromise, cross-tenant/form data exposure, or complete production outage. |
| `S1 High` | Major feature unusable, serious security/privacy failure, repeated duplicate delivery, or unrecoverable job failure. |
| `S2 Medium` | Material functional defect with a workaround; limited incorrect results or operational degradation. |
| `S3 Low` | Minor functional, accessibility, UI, documentation, or edge-case issue. |

## Mandatory defect workflow

1. Allocate the next ID as `BUG-0001`, `BUG-0002`, and so on.
2. Record exact observed behavior and expected behavior. Do not substitute assumptions for reproduction evidence.
3. Capture environment, versions, timestamps, sanitized logs, and minimal reproduction steps.
4. Link affected task IDs and move completed tasks back to an active state when a regression invalidates their evidence.
5. Record the proximate cause and systemic root cause separately.
6. A fix is not `RESOLVED` until a regression test passes and relevant adjacent behavior has been checked.
7. Record the fix in [CHANGELOGS.md](./CHANGELOGS.md) and update stable lessons/invariants in [MEMORY.md](./MEMORY.md).
8. Never paste passwords, raw download tokens, personal form values, secret keys, unrestricted filesystem paths, or other sensitive data into this file.

## Recent bugs

Records are ordered by discovery recency. The `State` field is authoritative.

### BUG-0024 - Local integration database assumed an available host port

- **State:** CLOSED
- **Severity:** S3 Low
- **First seen:** 2026-07-28 15:41:00 UTC
- **Last seen:** 2026-07-28 15:50:00 UTC
- **Environment:** local Docker Desktop integration harness
- **Affected version/commit:** unreleased working tree
- **Affected modules/tasks:** `DB-002`, `QA-001`
- **Reporter/owner:** Codex

#### Observed behavior

The first disposable MySQL 5.7 launch could not bind host port 3307 and exited before any test ran.

#### Expected behavior

Repository database verification must not depend on an arbitrarily selected host port being free.

#### Reproduction steps

1. Leave another local service bound to host port 3307.
2. Start the disposable database with `--publish 3307:3306`.
3. Observe Docker reject the bind.

#### Evidence

- Sanitized error: `Bind for 0.0.0.0:3307 failed: port is already allocated`.
- Test name/result: no PHPUnit test started on the failed attempt.
- Frequency: deterministic while the external port remained occupied.

#### Impact and scope

Only local verification was delayed. No repository data, WordPress data, or running project container was changed.

#### Cause analysis

- **Proximate cause:** The disposable database published a fixed host port.
- **Root cause:** The ad hoc local verification command assumed a host networking resource instead of isolating both containers together.
- **Contributing factors:** An unrelated local process already owned the port.
- **Why existing controls missed it:** CI uses its own clean runner and service port, while this was the first local disposable DB-002 run.

#### Resolution

- **Fix:** Connect the PHP runner and disposable database through a private named Docker network without publishing a host port.
- **Data repair:** Not required.
- **Backward compatibility:** None; this changes only the local verification procedure.

#### Recurrence prevention

- New invariant/guard: disposable local database verification must prefer a private container network over a fixed host-port mapping.
- Regression test: MySQL and MariaDB suites both ran through the private network.
- Broader related tests: single-site, multisite, security, MySQL 5.7, and MariaDB 10.4 lanes passed.
- Documentation/task/memory updates: operational lesson recorded in project memory and DB-002 evidence.
- Monitoring/alert: Docker's non-zero launch exit remains the signal; tests must not be claimed when setup fails.

#### Verification

- Command/check: run the pinned PHP image and both database images on `wpfv-db002-test`, addressed by container name.
- Result: MySQL and MariaDB harnesses connected without published ports; the disposable containers and network were removed afterward.
- Verified by/date: Codex, 2026-07-28.

#### Timeline

- `2026-07-28 15:41 UTC` - Fixed host-port launch failed.
- `2026-07-28 15:42 UTC` - Private-network retry connected successfully.
- `2026-07-28 15:50 UTC` - Cross-engine verification completed and disposable resources were removed.

### BUG-0023 - Initial DB-002 runtime did not satisfy locked static-quality rules

- **State:** CLOSED
- **Severity:** S3 Low
- **First seen:** 2026-07-28 15:30:00 UTC
- **Last seen:** 2026-07-28 15:45:00 UTC
- **Environment:** pinned PHP 8.1 QA image, WPCS 3.4.1, PHPStan 2.2.6 level 8
- **Affected version/commit:** unreleased working tree
- **Affected modules/tasks:** `DB-002`
- **Reporter/owner:** Codex

#### Observed behavior

The first coding-standards run rejected documentation, formatting, exception-flow, and reviewed SQL-boundary annotations. The first PHPStan run then reported 11 wpdb adapter type errors.

#### Expected behavior

All new runtime code must pass the locked aggregate QA gate with no warning or baseline.

#### Reproduction steps

1. Run `composer lint:phpcs` against the initial DB-002 source.
2. Observe standards failures across the new database/migration classes.
3. Run `composer analyse`.
4. Observe missing WordPress constant/type narrowing, dynamic reviewed-query, and `wpdb::query()` return-union errors.

#### Evidence

- Sanitized error: PHPCS reported multiple errors/warnings; PHPStan reported 11 errors in `WordPressSchemaDatabase`.
- Test name/result: unit tests passed after their separate assertion correction, but aggregate QA remained blocked.
- Frequency: every run against the initial source.

#### Impact and scope

The unreleased implementation could not complete DB-002 or enter a release artifact. No runtime database was affected.

#### Cause analysis

- **Proximate cause:** New boundary code lacked the repository's exact docblocks, WordPress output-mode literals, defensive row typing, and narrow reviewed-query annotations.
- **Root cause:** The initial implementation pass preceded feedback from the repository's locked high-strictness tools.
- **Contributing factors:** WordPress stubs type `wpdb` more broadly than the runtime path and require literal output-mode strings for conditional return inference.
- **Why existing controls missed it:** The errors were found by the intended controls on their first run.

#### Resolution

- **Fix:** Apply scoped mechanical formatting, add precise throw contracts, use `'ARRAY_A'` literals, narrow the `wpdb::query()` result, validate metadata rows, and document the single allow-list-reviewed dynamic prepare boundary.
- **Data repair:** Not required.
- **Backward compatibility:** None; runtime behavior remains fail closed.

#### Recurrence prevention

- New invariant/guard: wpdb adapters must expose narrow project-owned return types and document every dynamic identifier boundary.
- Regression test: the full `composer qa` gate runs PHPCS, PHPCompatibilityWP, PHPStan level 8, and unit tests.
- Broader related tests: bootstrap, architecture, foundation, and real database suites also passed.
- Documentation/task/memory updates: changelog, bug register, task evidence, and memory updated.
- Monitoring/alert: any PHPCS warning or PHPStan finding fails aggregate QA.

#### Verification

- Command/check: pinned PHP 8.1 `composer qa`.
- Result: 59/59 files passed both coding/compatibility scans, PHPStan reported no errors, and 13 unit tests/29 assertions passed.
- Verified by/date: Codex, 2026-07-28.

#### Timeline

- `2026-07-28 15:30 UTC` - Initial PHPCS failures recorded.
- `2026-07-28 15:35 UTC` - Initial PHPStan adapter failures recorded.
- `2026-07-28 15:45 UTC` - Aggregate QA passed.

### BUG-0022 - Migration-registry unit test expected the wrong exception family

- **State:** CLOSED
- **Severity:** S3 Low
- **First seen:** 2026-07-28 15:27:00 UTC
- **Last seen:** 2026-07-28 15:28:00 UTC
- **Environment:** pinned PHP 8.1 image, PHPUnit 9.6.35
- **Affected version/commit:** unreleased working tree
- **Affected modules/tasks:** `DB-002`
- **Reporter/owner:** Codex

#### Observed behavior

The first expanded unit run had one failure because the version-gap test expected `SchemaException`, while the registry correctly threw `InvalidArgumentException` for invalid developer configuration.

#### Expected behavior

The test must assert the registry's documented constructor contract and distinguish configuration errors from runtime schema failures.

#### Reproduction steps

1. Construct `MigrationRegistry` with a first migration from version 1.
2. Expect `SchemaException`.
3. Run the unit suite and observe the exception-type mismatch.

#### Evidence

- Sanitized error: expected `SchemaException`; actual `InvalidArgumentException` with a missing-version message.
- Test name/result: `MigrationRegistryTest::test_registry_rejects_a_version_gap`, one failure in 13 tests.
- Frequency: deterministic.

#### Impact and scope

Only the new test was incorrect. Production registry behavior already failed early as required.

#### Cause analysis

- **Proximate cause:** The test imported and expected the runtime exception type.
- **Root cause:** The test was written from an assumed error taxonomy instead of the registry's documented constructor contract.
- **Contributing factors:** Both exception families intentionally represent fail-fast paths at different boundaries.
- **Why existing controls missed it:** This was the first execution of the new test.

#### Resolution

- **Fix:** Expect `InvalidArgumentException` for an invalid registered chain.
- **Data repair:** Not required.
- **Backward compatibility:** None.

#### Recurrence prevention

- New invariant/guard: tests for invalid dependency/configuration construction follow each constructor's declared exception contract.
- Regression test: rerun the complete unit suite.
- Broader related tests: aggregate QA and WordPress database suites passed.
- Documentation/task/memory updates: defect and changelog recorded.
- Monitoring/alert: PHPUnit exception mismatches fail the unit gate.

#### Verification

- Command/check: pinned PHP 8.1 `composer test:unit`.
- Result: 13 tests and 29 assertions passed.
- Verified by/date: Codex, 2026-07-28.

#### Timeline

- `2026-07-28 15:27 UTC` - Exception mismatch reproduced.
- `2026-07-28 15:28 UTC` - Corrected unit suite passed.

### BUG-0021 - Failed-run retry count was evaluated after changing state

- **State:** CLOSED
- **Severity:** S2 Medium
- **First seen:** 2026-07-28 15:20:00 UTC
- **Last seen:** 2026-07-28 15:48:00 UTC
- **Environment:** unreleased DB-002 SQL review; MySQL 5.7 and MariaDB 10.4 verification
- **Affected version/commit:** unreleased working tree
- **Affected modules/tasks:** `DB-002`
- **Reporter/owner:** Codex

#### Observed behavior

The initial `mark_pending` SET list changed `state` to `pending` before evaluating `IF(state = 'failed', retry_count + 1, retry_count)`, so left-to-right assignment evaluation would not count a failed-run retry.

#### Expected behavior

Moving from `failed` into a new owned attempt must increment `retry_count` exactly once.

#### Reproduction steps

1. Persist singleton state `failed` with retry count 2.
2. Execute the initial SET order.
3. Observe the retry expression evaluate the newly assigned `pending` state instead of the previous `failed` state.

#### Evidence

- Sanitized source evidence: `state = pending` preceded the conditional retry assignment.
- Test name/result: `SchemaMigrationTest::test_failed_run_increments_retry_count_before_state_change`.
- Frequency: every failed-to-pending transition with the initial statement.

#### Impact and scope

Failure telemetry and retry policy inputs would undercount repeated migration attempts. Installed schema versions and form data were not advanced or lost.

#### Cause analysis

- **Proximate cause:** Assignment order changed the value inspected by the retry expression.
- **Root cause:** The transition query mixed prior-state inspection and state mutation without explicitly ordering the inspection first.
- **Contributing factors:** The runner uses one atomic SET for all transition fields.
- **Why existing controls missed it:** The defect was found during pre-test state-machine review before DB-002 existed in a release.

#### Resolution

- **Fix:** Evaluate and assign `retry_count` before assigning the new state.
- **Data repair:** Not required; unreleased code had not run in production.
- **Backward compatibility:** None.

#### Recurrence prevention

- New invariant/guard: SQL expressions that depend on pre-transition state must precede mutation of that state.
- Regression test: start at failed/retry 2, mark pending, and require retry 3.
- Broader related tests: the same test passed on MySQL 5.7 and MariaDB 10.4.
- Documentation/task/memory updates: state-transition lesson and bug ID recorded.
- Monitoring/alert: persisted retry count remains available for future health diagnostics.

#### Verification

- Command/check: WordPress 6.5 `required-minimum-mysql` and MariaDB 10.4 integration suites.
- Result: failed-to-pending transition persisted retry count 3 on both engines.
- Verified by/date: Codex, 2026-07-28.

#### Timeline

- `2026-07-28 15:20 UTC` - Incorrect SET order identified.
- `2026-07-28 15:48 UTC` - Cross-engine regression verification passed.

### BUG-0020 - Lease release reset fencing history

- **State:** CLOSED
- **Severity:** S2 Medium
- **First seen:** 2026-07-28 15:18:00 UTC
- **Last seen:** 2026-07-28 15:48:00 UTC
- **Environment:** unreleased DB-002 state-machine review; MySQL 5.7 and MariaDB 10.4 verification
- **Affected version/commit:** unreleased working tree
- **Affected modules/tasks:** `DB-002`
- **Reporter/owner:** Codex

#### Observed behavior

The initial release operation deleted the `schema_migration` row. A later acquisition inserted a new row with fencing token 1 instead of advancing the previous fence.

#### Expected behavior

Every successful acquisition for a site's migration lock must produce a fencing token greater than all earlier acquisitions, including after an orderly release.

#### Reproduction steps

1. Acquire a fresh schema lease and observe fence 1.
2. Release it using the initial DELETE implementation.
3. Acquire again and observe a new insert with fence 1.

#### Evidence

- Sanitized source evidence: release used `DELETE FROM ... WHERE lock_key/hash/fence`; acquisition inserts fence 1 when no row exists.
- Test name/result: `SchemaMigrationTest::test_lease_serialization_and_monotonic_fencing`.
- Frequency: every orderly release followed by acquisition.

#### Impact and scope

The monotonic fencing contract was broken and future downstream consumers that rely on fence ordering could accept an ambiguous fence. Owner-hash checks still reduced immediate state-store risk; no released or production data was affected.

#### Cause analysis

- **Proximate cause:** Releasing ownership deleted the persisted fence counter.
- **Root cause:** Lease cleanup was modeled as row cleanup instead of ownership expiry with retained concurrency history.
- **Contributing factors:** The lock table combines current ownership and fencing sequence in one row.
- **Why existing controls missed it:** The implementation was still in its first correctness pass and had not yet reached concurrency tests.

#### Resolution

- **Fix:** Release now compares owner hash/fence, marks the row `released`, clears metadata, and expires it at the current UTC time. Atomic takeover increments the retained fence.
- **Data repair:** Not required; unreleased code had not run in production.
- **Backward compatibility:** The control-table contract already permits expired lock rows.

#### Recurrence prevention

- New invariant/guard: orderly release never deletes the fencing history.
- Regression test: require first acquisition fence 1, reject a concurrent acquire, release, reacquire, and require fence 2.
- Broader related tests: stale-owner state mutation is rejected and both database engines pass.
- Documentation/task/memory updates: lease contract, changelog, task evidence, and memory updated.
- Monitoring/alert: lease/state failures return stable sanitized gate codes.

#### Verification

- Command/check: WordPress 6.5 integration suites on MySQL 5.7 and MariaDB 10.4.
- Result: serialized acquisition, inactive release, monotonic reacquisition, hashed owner storage, and stale-owner rejection passed.
- Verified by/date: Codex, 2026-07-28.

#### Timeline

- `2026-07-28 15:18 UTC` - Fencing reset identified during review.
- `2026-07-28 15:48 UTC` - Cross-engine lease regressions passed.

### BUG-0019 — Initial database-policy verifier violated project coding standards

- **State:** CLOSED
- **Severity:** S3 Low
- **First seen:** 2026-07-27 17:40:46 UTC
- **Last seen:** 2026-07-27 17:52:18 UTC
- **Environment:** pinned PHP 8.1.34 QA container, WPCS 3.4.1 / PHPCS 3.13.5
- **Affected version/commit:** unreleased working tree
- **Affected modules/tasks:** `DB-001`
- **Reporter/owner:** Codex

#### Observed behavior

The first full QA run accepted the database contract itself but PHPCS rejected the new verifier with five errors and nine warnings. A later count-guard edit briefly reintroduced five alignment warnings before final verification.

#### Expected behavior

The DB-001 verifier must pass the repository's WordPress coding and documentation standards before DB-001 can complete.

#### Reproduction steps

1. Add the initial `tools/verify-database-schema-policy.php`.
2. Run `composer run qa` in the pinned PHP 8.1 container.
3. Observe invalid generic docblock types, non-Yoda comparisons, assignment-alignment warnings, and development-function warnings.

#### Evidence

- Sanitized error: `FOUND 5 ERRORS AND 9 WARNINGS AFFECTING 14 LINES`.
- Recurrence error: `FOUND 0 ERRORS AND 5 WARNINGS AFFECTING 5 LINES`.
- Test name/result: the database-policy verifier passed before the PHPCS phase failed.
- Frequency: every standards run against the initial verifier.

#### Impact and scope

DB-001 could not satisfy its quality gate. No production runtime, database, or user data was affected.

#### Cause analysis

- **Proximate cause:** The initial verifier used `list<string>` documentation unsupported by the configured Squiz sniff, variable-first strict comparisons, unaligned assignments, and `var_export()` in diagnostic paths.
- **Root cause:** The verifier's first structural execution occurred before its first complete standards execution.
- **Contributing factors:** The policy logic itself passed, so the failure occurred only in the later aggregate QA phase; an additional verifier edit occurred after the first successful aggregate run.
- **Why existing controls missed it:** The file was new and had no earlier standards evidence; the first closure was recorded before the last PHP edit.

#### Resolution

- **Fix:** Replaced incompatible docblock types, removed debug-style value rendering, used the required comparison order, and aligned both the original assignments and the later count-guard assignment block.
- **Data repair:** Not required.
- **Backward compatibility:** No runtime impact; the verifier remains a standalone development control.

#### Recurrence prevention

- New invariant/guard: the database-policy verifier is part of `composer run qa`, and the complete gate is rerun after the last PHP edit.
- Regression test: run both PHPCS and PHPCompatibilityWP against all first-party PHP.
- Broader related tests: run the full PHPStan and unit suite after the standards correction.
- Documentation/task/memory updates: bug, changelog, task evidence, and memory synchronized.
- Monitoring/alert: any standards drift exits the QA script non-zero.

#### Verification

- Command/check: `composer run qa` in `wp-formvault-dependency-build:php8.1-composer2.10`.
- Result: both standards scans passed 37/37 files, PHPStan reported no errors, and PHPUnit passed 2 tests with 4 assertions.
- Verified by/date: Codex, 2026-07-27 17:52:18 UTC.

#### Timeline

- `2026-07-27 17:40:46 UTC` — Aggregate QA failure recorded and triaged.
- `2026-07-27 17:42:35 UTC` — Corrected verifier passed the complete QA gate.
- `2026-07-27 17:52:18 UTC` — The later count-guard recurrence was recorded; DB-001 had returned to `VERIFY` until the gate passed.
- `2026-07-27 17:52:18 UTC` — Targeted PHPCS and the complete QA gate passed after the final PHP edit.

### BUG-0018 — Host Composer and PHP environment could not execute repository QA

- **State:** CLOSED
- **Severity:** S3 Low
- **First seen:** 2026-07-27 17:40:46 UTC
- **Last seen:** 2026-07-27 17:42:35 UTC
- **Environment:** local Windows host Composer wrapper and PHP runtime; pinned Docker QA environment unaffected
- **Affected version/commit:** local development environment, unreleased working tree
- **Affected modules/tasks:** `DB-001`, `QA-001`
- **Reporter/owner:** Codex

#### Observed behavior

Host Composer commands stopped before reading the repository because the process inherited a `COMPOSER` variable that pointed to a directory. Removing only that override exposed a second host-runtime failure: OpenSSL was unavailable. Direct PHPCS execution also stopped at Composer's platform check because `fileinfo`, `gd`, and `zip` were unavailable.

#### Expected behavior

Project verification must execute in a runtime satisfying the locked platform requirements and must not depend on a poisoned global Composer override.

#### Reproduction steps

1. Run `composer validate --strict --no-check-publish` in the repository using the host environment.
2. Observe Composer reject the directory-valued `COMPOSER` variable.
3. Remove the variable for that process and rerun.
4. Observe the missing OpenSSL failure; direct `php vendor/bin/phpcs` also reports missing locked extensions.

#### Evidence

- Sanitized errors: `The COMPOSER environment variable ... is a directory`; `The openssl extension is required`; Composer platform check reports missing `fileinfo`, `gd`, and `zip`.
- Frequency: every attempted host Composer/Composer-autoloaded QA command in this session.

#### Impact and scope

The host runtime cannot be authoritative QA evidence. Repository code and production data were unaffected because no migrations or runtime writes occurred.

#### Cause analysis

- **Proximate cause:** A machine-global Composer override was invalid and the host PHP extension set did not meet the project platform.
- **Root cause:** The host environment is outside the frozen, repository-owned QA baseline.
- **Contributing factors:** Composer became available after the earlier dependency-toolchain assessment, but availability did not imply a compatible configuration or extension set.
- **Why existing controls missed it:** The pinned container is the supported verification route; the unsupported host route was exercised only as a convenience attempt.

#### Resolution

- **Fix:** Used the digest-built repository QA image `wp-formvault-dependency-build:php8.1-composer2.10` for strict validation, standards, analysis, and tests. The host's global configuration was not mutated.
- **Data repair:** Not required.
- **Backward compatibility:** None.

#### Recurrence prevention

- New invariant/guard: authoritative local QA uses the repository-owned container unless the host first passes the complete platform verifier.
- Regression test: strict Composer validation and `composer run qa` in the pinned image.
- Broader related tests: the aggregate QA script includes policy checks, both standards scanners, PHPStan, and PHPUnit.
- Documentation/task/memory updates: bug, changelog, task evidence, and memory synchronized.
- Monitoring/alert: container commands fail non-zero when platform or QA requirements drift.

#### Verification

- Command/check: strict Composer validation followed by `composer run qa` in the pinned PHP 8.1 image.
- Result: manifest valid; database contract passed; both standards scans, PHPStan, and unit tests passed.
- Verified by/date: Codex, 2026-07-27 17:42:35 UTC.

#### Timeline

- `2026-07-27 17:40:46 UTC` — Host failures recorded and verification moved to the frozen runtime.
- `2026-07-27 17:42:35 UTC` — Pinned-container validation and aggregate QA passed.

### BUG-0017 — Automatic prose capitalization changed a machine-readable policy key

- **State:** CLOSED
- **Severity:** S3 Low
- **First seen:** 2026-07-27 16:11:41 UTC
- **Last seen:** 2026-07-27 16:16:08 UTC
- **Environment:** local PHP 8.1 QA container, WPCS 3.4.1 / PHPCS 3.13.5
- **Affected version/commit:** unreleased working tree
- **Affected modules/tasks:** `ARCH-005`, `QA-001`
- **Reporter/owner:** Codex

#### Observed behavior

After the quality-policy status moved to `policy_implemented`, `tools/verify-quality-policy.php` rejected every CI lane as incomplete even though every lane contained the required lowercase `wordpress` field.

#### Expected behavior

The verifier must read the exact case-sensitive field names defined by `quality-policy.json`, and automatic prose formatting must not alter machine-readable identifiers.

#### Reproduction steps

1. Set `docs/architecture/quality-policy.json` status to `policy_implemented`.
2. Run `php tools/verify-quality-policy.php`.
3. Observe `every CI lane needs identity, cadence, blocking state, platform, and site mode`.
4. Compare the JSON field `wordpress` with the verifier lookup `WordPress`.

#### Evidence

- The working-tree diff showed `array_key_exists( 'wordpress', $lane )` had changed to `array_key_exists( 'WordPress', $lane )`.
- The incorrect capitalization appeared after automatic PHPCBF cleanup under the WordPress prose-capitalization rule.
- Frequency: every policy-verifier run with the affected lookup.

#### Impact and scope

The policy guard could not validate the implemented QA state and therefore blocked task completion. Plugin runtime and user data were not affected.

#### Cause analysis

- **Proximate cause:** A case-sensitive JSON key was treated as prose and capitalized.
- **Root cause:** The WPCS prose-capitalization sniff was allowed to rewrite a machine-readable identifier inside a verifier.
- **Contributing factors:** The string value spells the product name but semantically represents a fixed JSON field.
- **Why existing controls missed it:** The policy verifier had not been rerun after the automatic formatting pass and lifecycle status transition.

#### Resolution

- **Fix:** Restore the lowercase `wordpress` lookup and exclude only `tools/verify-quality-policy.php` from the prose-capitalization sniff with a machine-key rationale.
- **Data repair:** Not required.
- **Backward compatibility:** No contract change; the verifier again matches the existing schema.

#### Recurrence prevention

- New invariant/guard: Formatters must not rewrite case-sensitive schema keys; automated formatting is followed by semantic verifier execution.
- Regression test: run both `verify-quality-policy.php` and `verify-qa-tooling.php` after WPCS.
- Broader related tests: rerun PHPCS and verify the lowercase lookup remains present.
- Documentation/task/memory updates: capture the defect, guard, and verification in project records.
- Monitoring/alert: the QA anti-drift verifier and policy verifier remain blocking quality checks.

#### Verification

- Command/check: `composer run lint:phpcs`; `php tools/verify-quality-policy.php`; `php tools/verify-qa-tooling.php`; complete `composer run qa`.
- Result: WPCS passed 36 files; both semantic verifiers passed; the complete QA command exited 0.
- Verified by/date: Codex, 2026-07-27 16:16:08 UTC.

#### Timeline

- `2026-07-27 16:11:41 UTC` — Implemented-state policy verification reproduced the case-sensitive lookup defect; correction started.
- `2026-07-27 16:16:08 UTC` — Lowercase lookup, narrow sniff exclusion, dual verifiers, WPCS, and full QA gate passed; bug closed.

### BUG-0016 — Initial WordPress integration smoke tests assumed an undefined constant and unauthenticated notice access

- **State:** CLOSED
- **Severity:** S3 Low
- **First seen:** 2026-07-27 16:02:08 UTC
- **Last seen:** 2026-07-27 16:16:08 UTC
- **Environment:** WordPress 6.5, PHP 8.1.34, PHPUnit 9.6.35, MySQL 5.7.44, single-site isolated QA containers
- **Affected version/commit:** unreleased working tree
- **Affected modules/tasks:** `QA-001`
- **Reporter/owner:** Codex

#### Observed behavior

The first real WordPress integration run reached PHPUnit but reported one error because `BootstrapTest` referenced undefined `WPFV_NAME`, and one failure because `DiagnosticEscapingTest` rendered an administrator-only notice without authenticating an administrator.

#### Expected behavior

Integration smoke tests must assert constants that the plugin actually declares and must establish the WordPress role/capability context required by the behavior under test.

#### Reproduction steps

1. Build the repository QA image from `docker/dependency-build/Dockerfile`.
2. Start an isolated MySQL 5.7 database with a synthetic `wordpress_test` database.
3. Run `bash tools/run-wordpress-integration-tests.sh 6.5.0 required-minimum-mysql` under PHP 8.1.
4. Observe the undefined-constant error and the empty diagnostic output assertion failure.

#### Evidence

- Sanitized errors: `Undefined constant "WPFormVault\Tests\Integration\WPFV_NAME"` and `Failed asserting that '' contains "&lt;script&gt;"`.
- Test result: 3 tests, 3 assertions, 1 error, 1 failure.
- WordPress runtime resolved to 6.5; database reported 5.7.44.
- Frequency: reproduced in the first real WordPress-backed execution.

#### Impact and scope

The QA lane failed and could not provide integration evidence. Plugin production behavior and user data were not affected; the diagnostic sink correctly withheld administrator output from an unauthenticated test request.

#### Cause analysis

- **Proximate cause:** Test fixtures asserted an undeclared constant and omitted the administrator current-user setup required by `current_user_can( 'activate_plugins' )`.
- **Root cause:** The initial smoke tests were written before the real WordPress harness was executable, so assumptions were not yet checked against WordPress capability behavior.
- **Contributing factors:** The pure unit suite cannot expose WordPress constant and current-user context mistakes.
- **Why existing controls missed it:** No WordPress-backed test had run before the QA image gained `mysqli` and the ephemeral database runner was available.

#### Resolution

- **Fix:** Assert the declared `WPFV_TEXT_DOMAIN` identity and create/set an administrator user before rendering the protected notice.
- **Data repair:** Not required.
- **Backward compatibility:** Test-only correction; runtime behavior remains unchanged.

#### Recurrence prevention

- New invariant/guard: WordPress-backed tests must establish their required site mode, role, and capability context explicitly.
- Regression test: rerun the exact WordPress 6.5/MySQL 5.7 `required-minimum-mysql` suite.
- Broader related tests: execute WPCS, PHPCompatibilityWP, PHPStan, unit tests, and the multisite/current hosted lanes.
- Documentation/task/memory updates: record the defect and final QA-001 evidence in the mandatory project records.
- Monitoring/alert: any non-zero required hosted lane blocks release.

#### Verification

- Command/check: `bash tools/run-wordpress-integration-tests.sh 6.5.0 required-minimum-mysql` and the same WordPress 6.5 harness with `WPFV_TEST_MULTISITE=1` / `required-multisite`.
- Result: single-site integration/security passed 3 tests and 5 assertions; multisite integration/functional passed 3 tests and 4 assertions on MySQL 5.7.44.
- Verified by/date: Codex, 2026-07-27 16:16:08 UTC.

#### Timeline

- `2026-07-27 16:02:08 UTC` — Real WordPress minimum lane reproduced both test-fixture defects; fix started.
- `2026-07-27 16:16:08 UTC` — Corrected single-site and multisite WordPress-backed suites passed; bug closed.

### BUG-0015 — Quality policy selected a PHPUnit major unsupported by the WordPress core test suite

- **State:** CLOSED
- **Severity:** S2 Medium
- **First seen:** 2026-07-27 15:23:26 UTC
- **Last seen:** 2026-07-27 16:16:08 UTC
- **Environment:** QA-001 dependency research / WordPress 6.5–7.0 compatibility range
- **Affected version/commit:** unreleased working tree
- **Affected modules/tasks:** `ARCH-005`, `QA-001`
- **Reporter/owner:** Codex

#### Observed behavior

The accepted policy required PHPUnit 10 for all PHP lanes. The official WordPress core compatibility matrix states that the WordPress 6.5 through 7.0 test suites use PHPUnit 9 across the relevant PHP versions.

#### Expected behavior

The common PHPUnit runner must be supported by both PHP 8.1–8.5 and the official WordPress 6.5/current core test harness so minimum and rolling integration lanes execute the same test suites.

#### Reproduction steps

1. Read `docs/architecture/quality-policy.json`.
2. Observe `phpunit_php_8_1_compatible_major` and `runner_major_for_all_php_lanes` set to 10.
3. Compare the official WordPress “PHPUnit Compatibility and WordPress Versions” table for WordPress 6.5 through 7.0.
4. Observe that the supported PHPUnit major is 9.

#### Evidence

- Sanitized log/error: not applicable; incompatibility was detected before package installation.
- Test name/result: official WordPress compatibility-table review failed the policy assumption.
- Screenshot/artifact: [WordPress PHPUnit compatibility handbook](https://make.wordpress.org/core/handbook/references/phpunit-compatibility-and-wordpress-versions/).
- Frequency: deterministic for the selected WordPress range.

#### Impact and scope

Leaving the policy unchanged would prevent the official minimum/current WordPress integration harness from running as specified or force an unsupported PHPUnit configuration. No production runtime or user data is affected.

#### Cause analysis

- **Proximate cause:** The policy selected PHPUnit solely from its PHP 8.1 requirement.
- **Root cause:** Generic test-runner/PHP compatibility was treated as sufficient without cross-checking the WordPress core test-suite matrix.
- **Contributing factors:** PHPUnit 10 itself supports PHP 8.1+, which made the selection appear valid until the WordPress harness was considered.
- **Why existing controls missed it:** `ARCH-005` verified policy consistency but did not validate the runner major against WordPress core’s official matrix.

#### Resolution

- **Fix:** Change the common runner to the latest compatible PHPUnit 9.6 line, update the machine/human policy and verifier, then install and exercise it through `QA-001`.
- **Data repair:** Not required.
- **Backward compatibility:** No runtime impact; this is a pre-release development-tool correction.

#### Recurrence prevention

- New invariant/guard: test-runner selection must satisfy the official WordPress/PHPUnit matrix as well as generic PHP requirements.
- Regression test: quality-policy verifier asserts PHPUnit major 9 and the engineering policy cites the WordPress matrix.
- Broader related tests: minimum/current WordPress integration smoke tests across the CI matrix.
- Documentation/task/memory updates: policy, plan, tasks, changelog, and memory.
- Monitoring/alert: dependency updates recheck both upstream matrices before changing the runner.

#### Verification

- Command/check: quality-policy verifier, locked PHPUnit 9.6.35 unit suite, WordPress 6.5 single-site and multisite harnesses, and complete `composer run qa`.
- Result: policy verified; PHPUnit 9.6.35 passed pure unit and official WordPress 6.5-backed tests; full QA command exited 0.
- Verified by/date: Codex, 2026-07-27 16:16:08 UTC.

#### Timeline

- `2026-07-27 15:23:26 UTC` — QA-001 research identified the incompatible PHPUnit-major assumption and reopened ARCH-005.
- `2026-07-27 16:16:08 UTC` — Corrected policy, locked runner, WordPress harness, and complete QA gate passed; bug closed.

### BUG-0014 — Quality snapshot changed a frozen compatibility-memory contract

- **State:** CLOSED
- **Severity:** S3 Low
- **First seen:** 2026-07-27 15:13:20 UTC
- **Last seen:** 2026-07-27 15:15:46 UTC
- **Environment:** Windows workspace / `localdev_php_apache` PHP 8.2.31 container
- **Affected version/commit:** unreleased working tree
- **Affected modules/tasks:** `ARCH-002`, `ARCH-005`
- **Reporter/owner:** Codex

#### Observed behavior

The repository regression sequence stopped in `tools/verify-compatibility.php` because the frozen WordPress 6.5 row in `MEMORY.md` had been extended with a dated latest-WordPress note.

#### Expected behavior

The selected minimum-platform row must remain byte-for-byte compatible with the stable contract enforced by the compatibility verifier. Dated rolling-version research belongs in the engineering-quality snapshot, not in the frozen minimum row.

#### Reproduction steps

1. Extend the `MEMORY.md` WordPress 6.5 minimum row with the current WordPress release.
2. Run `php tools/verify-compatibility.php` in the mounted PHP container.
3. Observe exit code 1 and the missing frozen memory-fragment message.

#### Evidence

- Sanitized log/error: `Compatibility source 'memory' is missing: | WordPress | 6.5 | Required/frozen by user decision on 2026-07-27 |`
- Test name/result: compatibility verifier failed before the remaining regression checks ran.
- Screenshot/artifact: not required.
- Frequency: deterministic.

#### Impact and scope

No runtime code or user data is affected. The documentation-only drift invalidates the compatibility regression gate and prevents `ARCH-005` completion until corrected.

#### Cause analysis

- **Proximate cause:** A current-release note was appended to the exact frozen compatibility row.
- **Root cause:** Minimum product compatibility and rolling upstream reference data were combined in one field despite having different change lifecycles.
- **Contributing factors:** The quality-policy snapshot and the supported-platform table are adjacent concepts in project memory.
- **Why existing controls missed it:** The focused quality-policy verifier passed; the broader compatibility verifier had not yet run against the synchronized memory update.

#### Resolution

- **Fix:** Restore the exact frozen WordPress row, keep the PHP minimum row free of rolling data, and retain dated upstream/current-lane facts only in the separate engineering-quality section.
- **Data repair:** Not required.
- **Backward compatibility:** No impact.

#### Recurrence prevention

- New invariant/guard: keep immutable advertised minimums separate from dated or rolling CI reference data.
- Regression test: `php tools/verify-compatibility.php`.
- Broader related tests: quality-policy, foundation, architecture, bootstrap, dependency, and task-graph verifiers.
- Documentation/task/memory updates: bug register, changelog, and `ARCH-005` evidence.
- Monitoring/alert: non-zero compatibility-verifier exit remains the failure signal.

#### Verification

- Command/check: Run the foundation, compatibility, architecture, bootstrap, quality-policy, and dependency verifiers sequentially in `wp-formvault-dependency-build:php8.1-composer2.10`; run the PowerShell task-graph verifier.
- Result: all PHP verifiers passed under PHP 8.1.34 with the required extensions; the task graph passed with 198 tasks, 325 edges, no missing references, and no cycles.
- Verified by/date: Codex, 2026-07-27 15:15:46 UTC.

#### Timeline

- `2026-07-27 15:13:20 UTC` — Full regression run detected the stable-memory contract drift.
- `2026-07-27 15:15:46 UTC` — Frozen/current facts were separated and the complete regression set passed.

### BUG-0013 — Clean dependency verification exceeded the command time limit

- **State:** CLOSED
- **Severity:** S3 Low
- **First seen:** 2026-07-27 14:41:01 UTC
- **Last seen:** 2026-07-27 14:50:45 UTC
- **Environment:** Windows Docker Desktop / repository-owned PHP 8.1.34 dependency image / bind-mounted workspace
- **Affected version/commit:** unreleased working tree
- **Affected modules/tasks:** FND-003, FND-002
- **Reporter/owner:** Codex

#### Observed behavior

The normal lock-only `tools/run-dependency-build.ps1` verification exceeded the tool's 120-second limit and returned exit code 124 without its final result. The Docker container remained active in the Strauss `composer run build-dependencies` copy phase after the calling command timed out.

#### Expected behavior

The reproducible dependency build must complete with a captured success/failure result inside the allowed verification window, or the caller must use a documented sufficient timeout and recover/observe the detached child safely.

#### Reproduction steps

1. Run `powershell -NoProfile -ExecutionPolicy Bypass -File tools\run-dependency-build.ps1` with a 120-second caller limit.
2. Observe exit code 124 at approximately 124 seconds.
3. Run `docker ps` and observe the `wp-formvault-dependency-build` container still executing `composer run build-dependencies`.
4. Read its logs and observe Strauss in file enumeration/copying rather than a recorded failure.

#### Evidence

- Timed command result: `command timed out after 124071 milliseconds`.
- Active container command: `docker-php-entrypoint composer run build-dependencies`.
- Latest logs: Strauss reached `Scanning files`, `Determining changes`, and `Copying files`.
- Frequency: deterministic with the original 120-second caller limit; the captured clean rerun completed successfully in 305.9 seconds.

#### Impact and scope

No product runtime or user data is affected. Final FND-003 completion evidence is withheld because the dependency build result was not captured. A lingering build container/process may consume local resources until it finishes or is safely cleaned up.

#### Cause analysis

- **Proximate cause:** The external command limit expired while Strauss was copying the generated dependency tree through the Windows bind mount.
- **Root cause:** The verification caller used a 120-second limit without a measured clean-build duration; the actual clean run required 305.9 seconds.
- **Contributing factors:** Namespace generation processes hundreds of dependency files on a Docker Desktop bind mount.
- **Why existing controls missed it:** Earlier successful runs did not establish/document a bounded worst-case duration for a fully clean dependency regeneration.

#### Resolution

- **Fix:** Observed the first project container until its automatic `--rm` exit, confirmed no active project build container remained, reran the exact normal lock-only command with a 420-second bound, and documented that bound plus interrupted-container inspection.
- **Data repair:** Not required.
- **Backward compatibility:** Build/verification behavior only.

#### Recurrence prevention

- New invariant/guard: final dependency verification uses a timeout that covers measured clean-generation duration and checks for leftover project build containers after caller interruption.
- Regression test: normal lock-only dependency build plus the FND-003 bootstrap suite against the regenerated tree.
- Broader related tests: architecture, compatibility, foundation, task graph, PHP syntax, and diff checks.
- Documentation/task/memory updates: README, dependency policy, task evidence, changelog, bug register, and project memory updated.
- Monitoring/alert: a timed-out caller must inspect project-scoped container state instead of assuming the build failed or stopped.

#### Verification

- Command/check: `powershell -NoProfile -ExecutionPolicy Bypass -File tools\run-dependency-build.ps1` with a 420-second caller limit; project-scoped `docker ps` inspection; full PHP/bootstrap/architecture/compatibility/foundation/task-graph/diff suite.
- Result: clean lock-only build exited 0 in 305.9 seconds; validation, audit, platform requirements, Strauss generation/corrections, Action Scheduler staging, notices, 722 generated PHP syntax checks, namespace/conflict/type tests, and real XLSX/ZIP smoke passed. No project build container remained; all adjacent final gates passed.
- Verified by/date: Codex, 2026-07-27 14:50:45 UTC.

#### Timeline

- `2026-07-27 14:41:01 UTC` — Caller timed out; active Strauss build container and progress logs confirmed; FND-003 reopened.
- `2026-07-27 14:44:34 UTC` — First `--rm` container reached a terminal state after progressing through generation/lint; no concurrent retry was started.
- `2026-07-27 14:49:40 UTC` — Exact bounded rerun exited 0 after 305.9 seconds with every dependency gate passing.
- `2026-07-27 14:50:45 UTC` — Adjacent final verification and documentation synchronization passed; defect closed.

### BUG-0012 — Bootstrap stopped before the expected pending-schema gate

- **State:** CLOSED
- **Severity:** S3 Low
- **First seen:** 2026-07-27 14:29:27 UTC
- **Last seen:** 2026-07-27 14:37:48 UTC
- **Environment:** repository-owned PHP 8.1.34 / generated locked runtime dependencies
- **Affected version/commit:** unreleased working tree
- **Affected modules/tasks:** FND-003
- **Reporter/owner:** Codex

#### Observed behavior

The new deterministic bootstrap verifier loaded the real plugin bootstrap but the production composition root did not reach the expected `blocked_schema` state.

#### Expected behavior

With the reviewed dependency tree, PHP 8.1 runtime, WordPress 6.5 test context, and the intentionally pending schema implementation, dependency and compatibility gates must pass and bootstrap must stop specifically at the schema gate.

#### Reproduction steps

1. Generate/install the locked `vendor-prefixed/` and `libraries/action-scheduler/` trees.
2. Run `php tools/verify-bootstrap.php` in the repository-owned PHP 8.1 image.
3. Observe `Bootstrap verification failed: production bootstrap must stop at the pending schema gate`.

#### Evidence

- All project PHP files passed PHP 8.1 syntax checks immediately before the failure.
- The verifier failed on its first production-state assertion.
- Exact earlier gate/result code: `blocked_dependency` / sanitized message `The packaged Action Scheduler library could not be registered.`
- Isolated probe: the typed verifier stub rejected Action Scheduler's deferred callback string before that function was declared.
- Frequency: deterministic with the original typed stub; absent with WordPress-compatible stub semantics.

#### Impact and scope

The production implementation remained fail closed and was not defective. The inaccurate test stub made the verifier report the wrong terminal state and initially obscured whether the packaged loader was valid. No product service or user data was exposed.

#### Cause analysis

- **Proximate cause:** The verifier declared its `add_action()` callback parameter as PHP `callable`; Action Scheduler registers a string callback before the conditional function declaration becomes callable.
- **Root cause:** The WordPress stub narrowed the real `add_action()` contract, whose callback parameter is intentionally untyped and accepts deferred callback strings.
- **Contributing factors:** The production dependency loader correctly sanitized the caught `TypeError`, so the first black-box assertion exposed only the terminal state.
- **Why existing controls missed it:** This was the first test executing Action Scheduler's early registration path through a locally defined WordPress hook stub.

#### Resolution

- **Fix:** Changed both standalone WordPress `add_action()` stubs to accept `mixed` callbacks, matching WordPress's deferred-callback behavior. Kept production exception sanitization and fail-closed dependency handling unchanged.
- **Data repair:** Not required.
- **Backward compatibility:** Unreleased foundation code only.

#### Recurrence prevention

- New invariant/guard: WordPress test doubles must not narrow callback/input contracts in ways the real API does not.
- Regression test: corrected `tools/verify-bootstrap.php` loads the real staged Action Scheduler and reaches `blocked_schema`.
- Broader related tests: dependency, architecture, compatibility, foundation, container negative paths, hook idempotency, and task graph.
- Documentation/task/memory updates: task evidence, changelog, bug register, and project memory updated.
- Monitoring/alert: bootstrap verification fails before task completion when the terminal state differs from the intended gate.

#### Verification

- Command/check: isolated Action Scheduler load probe before/after the stub correction; PHP 8.1 `php tools/verify-bootstrap.php`; architecture, compatibility, and foundation verifiers.
- Result: the isolated probe identified the narrowed stub; corrected bootstrap verification passed dependency loading, intended schema stop, diagnostics, gates, container failures, hook idempotency, and site isolation; adjacent verifiers passed.
- Verified by/date: Codex, 2026-07-27 14:37:48 UTC.

#### Timeline

- `2026-07-27 14:29:27 UTC` — First complete bootstrap verifier stopped before the expected schema state; defect opened.
- `2026-07-27 14:32:00 UTC` — Stable state, sanitized diagnostic, and isolated probe traced the failure to the narrowed `add_action()` stub.
- `2026-07-27 14:37:48 UTC` — WordPress-compatible stub semantics and the expanded bootstrap/adjacent regression suite passed; defect closed.

### BUG-0011 — Compatibility verifier coupled platform checks to mutable policy status

- **State:** CLOSED
- **Severity:** S3 Low
- **First seen:** 2026-07-27 13:10:04 UTC
- **Last seen:** 2026-07-27 13:12:52 UTC
- **Environment:** repository-owned PHP 8.1.34 final verification
- **Affected version/commit:** unreleased working tree
- **Affected modules/tasks:** ARCH-002, FND-002
- **Reporter/owner:** Codex

#### Observed behavior

`tools/verify-compatibility.php` failed after `FND-002` was completed because it still required the dependency policy's former `Status: Accepted; option A selected...` sentence. The policy now correctly says the strategy is accepted and implemented.

#### Expected behavior

The compatibility verifier must enforce stable platform and dependency facts without coupling success to a mutable implementation-lifecycle sentence.

#### Reproduction steps

1. Complete `FND-002` and update `docs/architecture/dependency-policy.md` from accepted to implemented.
2. Run `php tools/verify-compatibility.php`.
3. Observe `Compatibility source 'policy' is missing: Status: Accepted; option A selected by the user on 2026-07-27`.

#### Evidence

- Failing command: repository-owned PHP 8.1 invocation of `php tools/verify-compatibility.php`.
- Policy status: `Accepted and implemented by FND-002`.
- Frequency: every compatibility-verifier run after the correct lifecycle update.

#### Impact and scope

The final verification gate failed even though the selected compatibility profile did not change. No runtime, package, site, or user data was affected.

#### Cause analysis

- **Proximate cause:** The verifier searched for the exact former policy-status sentence.
- **Root cause:** A stable compatibility contract check included mutable task/lifecycle prose outside its responsibility.
- **Contributing factors:** The status sentence happened to contain the original option-selection evidence when the verifier was introduced.
- **Why existing controls missed it:** The verifier had not been rerun after advancing the policy implementation status.

#### Resolution

- **Fix:** Removed the mutable policy-status assertion and synchronized the Action Scheduler constraint check with the reviewed `~3.9.3` manifest line. The verifier retains checks for the selected WordPress, PHP, exact locked dependency intent, and upstream-version boundary.
- **Data repair:** Not required.
- **Backward compatibility:** Verification-only correction; the selected platform remains unchanged.

#### Recurrence prevention

- New invariant/guard: automated compatibility assertions target stable compatibility facts, not task or document lifecycle wording.
- Regression test: rerun compatibility, foundation, dependency, task-graph, and diff checks after project-record updates.
- Broader related tests: policy and memory checks continue to require the selected and intentionally excluded dependency lines.
- Documentation/task/memory updates: task evidence, changelog, bug register, and anti-hallucination memory rule updated.
- Monitoring/alert: the final verification gate remains fail-closed on real compatibility drift.

#### Verification

- Command/check: PHP 8.1.34 `php tools/verify-compatibility.php`; `php tools/verify-foundation.php`; `php -l tools/verify-compatibility.php`; `powershell -NoProfile -ExecutionPolicy Bypass -File tools\verify-task-graph.ps1`.
- Result: compatibility and foundation verification passed; corrected verifier syntax passed; task graph passed with 198 tasks, 325 dependency edges, no missing references, and no cycles. The clean dependency build had passed immediately before this final-gate regression was found.
- Verified by/date: Codex, 2026-07-27 13:12:52 UTC.

#### Timeline

- `2026-07-27 13:10:04 UTC` — Final compatibility gate reproduced the stale-status assertion; `ARCH-002` reopened.
- `2026-07-27 13:12:52 UTC` — Stable-contract assertions and the complete relevant verification set passed; `ARCH-002` restored to complete and defect closed.

### BUG-0010 — Strauss corrupted namespace/class-homonym return types

- **State:** CLOSED
- **Severity:** S1 High
- **First seen:** 2026-07-27 12:47:15 UTC
- **Last seen:** 2026-07-27 13:07:28 UTC
- **Environment:** repository-owned PHP 8.1.34 / Composer 2.10.2 / Strauss 0.28.1 build
- **Affected version/commit:** unreleased working tree
- **Affected modules/tasks:** ARCH-003, FND-002, EXPORT-001
- **Reporter/owner:** Codex

#### Observed behavior

The prefixed PhpSpreadsheet XLSX writer fatally failed because Strauss rewrote a `ZipStream` return type alias to `WPFormVault\Vendor\ZipStream`, which PHP resolved relative to the current writer namespace. The returned `WPFormVault\Vendor\ZipStream\ZipStream` object therefore violated the generated return type. The same pattern affected `Complex` and `Matrix` return types.

#### Expected behavior

Every generated type declaration must resolve to the same isolated class as the corresponding rewritten import, and a prefixed spreadsheet must write a valid XLSX archive.

#### Reproduction steps

1. Generate `vendor-prefixed/` with Strauss 0.28.1 from the locked dependencies.
2. Instantiate `WPFormVault\Vendor\PhpOffice\PhpSpreadsheet\Spreadsheet`.
3. Save it with the prefixed `Writer\Xlsx`.
4. Observe a `TypeError` from `Writer\ZipStream3::newZipStream()`.
5. Inspect generated return types and observe the same root-namespace substitution for `ZipStream`, `Complex`, and `Matrix`.

#### Evidence

- Fatal type expected: `WPFormVault\Vendor\PhpOffice\PhpSpreadsheet\Writer\WPFormVault\Vendor\ZipStream`.
- Actual returned type: `WPFormVault\Vendor\ZipStream\ZipStream`.
- Generated `ZipStream0.php`, `ZipStream2.php`, `ZipStream3.php`, `Ods.php`, and MarkBaker files contain affected return types.
- Frequency: every XLSX write using the first generated tree.

#### Impact and scope

All XLSX report generation would fail at runtime, and matrix/complex calculations could also fail when their affected return types execute. The build gate caught the defect before release; no production site or user data was affected.

#### Cause analysis

- **Proximate cause:** Strauss treated a short class name that matched its root namespace (`ZipStream`, `Complex`, or `Matrix`) as a namespace reference inside a return type.
- **Root cause:** The selected prefixer's textual namespace replacement is ambiguous for namespace/class homonyms.
- **Contributing factors:** Class loading checks do not execute method return-type contracts; only a real XLSX write exposed the failure.
- **Why existing controls missed it:** The earlier verifier instantiated classes but did not create an XLSX file.

#### Resolution

- **Fix:** Added a deterministic, fail-closed post-prefix patch that expands only the affected `Complex`, `Matrix`, and `ZipStream` return types to their full isolated class names. The patch requires the reviewed match counts (42, 21, and 4 respectively) and the verifier rejects any remaining ambiguous return type.
- **Data repair:** Not required.
- **Backward compatibility:** Generated-code correction only; public WP FormVault APIs do not exist yet.

#### Recurrence prevention

- New invariant/guard: prefix generation is followed by a reviewed homonym-type correction and a scan proving no ambiguous generated return types remain.
- Regression test: write and inspect a real XLSX archive using the prefixed classes.
- Broader related tests: syntax-lint the generated tree and exercise Complex/Matrix return types.
- Documentation/task/memory updates: dependency policy, task evidence, changelog, bug register, and project memory updated.
- Monitoring/alert: dependency build fails before staging/package creation on any correction-count or smoke-test mismatch.

#### Verification

- Command/check: `powershell -NoProfile -ExecutionPolicy Bypass -File tools\run-dependency-build.ps1`
- Result: clean PHP 8.1 build passed; 722 generated PHP files passed syntax checks; reviewed correction counts were enforced; Complex and Matrix return types executed successfully; a real prefixed XLSX file was written and its ZIP structure verified.
- Verified by/date: Codex, 2026-07-27 13:07:28 UTC.

#### Timeline

- `2026-07-27 12:47:15 UTC` — Real XLSX smoke test reproduced the fatal and the generated-type pattern was traced.
- `2026-07-27 13:07:28 UTC` — Fail-closed correction, generated-tree scan, type-contract tests, and real XLSX smoke test passed; defect closed.

### BUG-0009 — Isolation verifier expected the unprefixed Composer loader class

- **State:** CLOSED
- **Severity:** S3 Low
- **First seen:** 2026-07-27 12:36:10 UTC
- **Last seen:** 2026-07-27 13:07:28 UTC
- **Environment:** repository-owned PHP 8.1.34 / Composer 2.10.2 / Strauss 0.28.1 build
- **Affected version/commit:** unreleased working tree
- **Affected modules/tasks:** FND-002
- **Reporter/owner:** Codex

#### Observed behavior

Strauss completed and generated a working autoloader, but `tools/verify-dependencies.php` rejected its return value because the test expected `Composer\Autoload\ClassLoader`.

#### Expected behavior

The verifier must recognize the intentionally prefixed `WPFormVault\Vendor\Composer\Autoload\ClassLoader` and then test its behavior.

#### Reproduction steps

1. Run `composer run build-dependencies` after a locked install.
2. Observe Strauss complete and Action Scheduler stage successfully.
3. Observe the verifier report that the prefixed autoloader did not return a Composer class loader.
4. Inspect `vendor-prefixed/composer/ClassLoader.php` and observe its namespace is `WPFormVault\Vendor\Composer\Autoload`.

#### Evidence

- Generated `vendor-prefixed/autoload.php` returns its loader.
- Generated `ClassLoader.php` declares `WPFormVault\Vendor\Composer\Autoload\ClassLoader`.
- Frequency: every current isolation-verifier run.

#### Impact and scope

The build stopped at verification even though prefix generation and staging succeeded. No production artifact or user data was affected.

#### Cause analysis

- **Proximate cause:** The assertion checked the development Composer loader type rather than the prefixed loader type.
- **Root cause:** The verifier encoded a pre-prefix class-name assumption at the exact boundary intended to rename Composer symbols.
- **Contributing factors:** Both loaders expose the same behavioral methods but are deliberately different classes.
- **Why existing controls missed it:** This was the first successfully generated Strauss autoloader.

#### Resolution

- **Fix:** Updated the verifier to assert `WPFormVault\Vendor\Composer\Autoload\ClassLoader` and retained behavioral `findFile()` and conflict-isolation checks.
- **Data repair:** Not required.
- **Backward compatibility:** Test-only correction.

#### Recurrence prevention

- New invariant/guard: assertions at namespace boundaries use the generated prefixed contract and verify behavior, not an unprefixed implementation identity.
- Regression test: complete `composer run build-dependencies`.
- Broader related tests: conflicting unprefixed classes loaded before scoped classes.
- Documentation/task/memory updates: task evidence, changelog, bug register, and project memory updated.
- Monitoring/alert: build remains fail-closed on isolation-test failure.

#### Verification

- Command/check: `powershell -NoProfile -ExecutionPolicy Bypass -File tools\run-dependency-build.ps1`
- Result: the clean dependency build loaded the prefixed Composer loader, resolved isolated classes, and passed the unprefixed-class conflict fixture.
- Verified by/date: Codex, 2026-07-27 13:07:28 UTC.

#### Timeline

- `2026-07-27 12:36:10 UTC` — Failure reproduced; generated loader namespace inspected; fix started.
- `2026-07-27 13:07:28 UTC` — Correct prefixed-loader assertion and behavioral isolation checks passed; defect closed.

### BUG-0008 — Strict Composer validation rejected the exact Action Scheduler constraint

- **State:** CLOSED
- **Severity:** S3 Low
- **First seen:** 2026-07-27 12:29:59 UTC
- **Last seen:** 2026-07-27 13:07:28 UTC
- **Environment:** repository-owned PHP 8.1.34 / Composer 2.10.2 dependency build
- **Affected version/commit:** unreleased working tree
- **Affected modules/tasks:** FND-002
- **Reporter/owner:** Codex

#### Observed behavior

`composer validate --strict` returned a non-zero result because the manifest used the exact runtime constraint `woocommerce/action-scheduler: 3.9.3`.

#### Expected behavior

The manifest must pass strict validation while the committed lock and verification tooling preserve the reviewed 3.9.3 runtime.

#### Reproduction steps

1. Generate the initial lock with `tools/run-dependency-build.ps1 -UpdateLock`.
2. Allow the script to run `composer validate --strict`.
3. Observe the exact-version-constraint warning and non-zero build result.

#### Evidence

- Composer warning: `exact version constraints (3.9.3) should be avoided if the package follows semantic versioning`.
- Composer had already locked Action Scheduler 3.9.3.
- Frequency: every strict validation with the initial manifest.

#### Impact and scope

The reproducible dependency build stopped before namespace prefixing and staging. No runtime package or user data was affected.

#### Cause analysis

- **Proximate cause:** The root manifest used an exact semantic-version constraint.
- **Root cause:** The architecture policy conflated the allowed dependency line with the exact version committed in `composer.lock`.
- **Contributing factors:** Action Scheduler 4.x must remain excluded because it raises the WordPress minimum.
- **Why existing controls missed it:** Strict Composer validation was first introduced and executed in `FND-002`.

#### Resolution

- **Fix:** Change the root constraint to `~3.9.3`, which permits reviewed 3.9 patch releases but excludes 4.x; retain exact 3.9.3 verification in the current lock.
- **Data repair:** Not required.
- **Backward compatibility:** No selected runtime-version change.

#### Recurrence prevention

- New invariant/guard: semantic-version constraints define an allowed compatible line; `composer.lock` and build verification define the exact shipped version.
- Regression test: `composer validate --strict` and exact installed-version check.
- Broader related tests: Action Scheduler metadata/coexistence verification.
- Documentation/task/memory updates: dependency policy, manifest rationale, task evidence, changelog, bug register, and project memory updated.
- Monitoring/alert: the dependency build fails on strict validation warnings.

#### Verification

- Command/check: `powershell -NoProfile -ExecutionPolicy Bypass -File tools\run-dependency-build.ps1`
- Result: `composer validate --strict` passed; the manifest permits only the reviewed 3.9 patch line and the lock/verifier preserved exact Action Scheduler 3.9.3.
- Verified by/date: Codex, 2026-07-27 13:07:28 UTC.

#### Timeline

- `2026-07-27 12:29:59 UTC` — Strict-validation failure reproduced and fix started.
- `2026-07-27 13:07:28 UTC` — Strict validation and exact locked-version verification passed; defect closed.

### BUG-0007 — PhpSpreadsheet baseline was stale before first lock resolution

- **State:** CLOSED
- **Severity:** S3 Low
- **First seen:** 2026-07-27 12:29:59 UTC
- **Last seen:** 2026-07-27 13:07:28 UTC
- **Environment:** repository-owned PHP 8.1.34 / Composer 2.10.2 dependency build and official upstream release review
- **Affected version/commit:** unreleased working tree
- **Affected modules/tasks:** ARCH-003, FND-002, EXPORT-001
- **Reporter/owner:** Codex

#### Observed behavior

The policy called PhpSpreadsheet 5.7.0 the current/initial lock target, but Composer resolved 5.8.1 and upstream identifies 5.8.1 as the last release supporting PHP 8.1.

#### Expected behavior

The dependency policy and verifier must use the latest reviewed release compatible with WP FormVault's PHP 8.1 minimum.

#### Reproduction steps

1. Resolve `phpoffice/phpspreadsheet: ^5.7.0` with Composer platform PHP 8.1.0.
2. Observe Composer lock PhpSpreadsheet 5.8.1.
3. Open the official 5.8.1 release and confirm it is the last PHP 8.1-supporting version.

#### Evidence

- [PhpSpreadsheet 5.8.1 release](https://github.com/PHPOffice/PhpSpreadsheet/releases/tag/5.8.1): explicitly identifies the PHP 8.1 support boundary.
- Initial Composer resolution locked `phpoffice/phpspreadsheet` 5.8.1.
- Frequency: deterministic under the current package repository and platform constraint.

#### Impact and scope

The first verifier would have rejected the correct lock, and future `^5.7.0` updates could obscure the intentional PHP 8.1 minor-line boundary. No export code or user data exists yet.

#### Cause analysis

- **Proximate cause:** Upstream released 5.8.1 after the earlier 5.7.0 research snapshot.
- **Root cause:** The pre-lock policy froze an “initial lock target” before Composer resolution and did not recheck immediately before implementation.
- **Contributing factors:** PHP 8.1 is at the end of PhpSpreadsheet's supported line.
- **Why existing controls missed it:** The repository had no manifest, lock, or reproducible PHP 8.1 resolver until `FND-002`.

#### Resolution

- **Fix:** Constrain PhpSpreadsheet to `~5.8.1`, lock/verify 5.8.1, and record it as the last PHP 8.1-compatible line.
- **Data repair:** Not required.
- **Backward compatibility:** Preserves PHP 8.1 and moves to a newer compatible patch/minor than the provisional target.

#### Recurrence prevention

- New invariant/guard: dependency baselines are provisional until resolved on the minimum platform; upstream freshness is checked immediately before lock updates.
- Regression test: exact locked-version verification under Composer platform PHP 8.1.0.
- Broader related tests: spreadsheet creation/write smoke test after isolation.
- Documentation/task/memory updates: dependency policy, manifest, task evidence, changelog, bug register, and project memory updated.
- Monitoring/alert: future PhpSpreadsheet minor upgrades require explicit PHP-minimum review.

#### Verification

- Command/check: `powershell -NoProfile -ExecutionPolicy Bypass -File tools\run-dependency-build.ps1`
- Result: the PHP 8.1 build resolved, installed, audited, prefixed, and smoke-tested exact PhpSpreadsheet 5.8.1; the verifier enforces that lock.
- Verified by/date: Codex, 2026-07-27 13:07:28 UTC.

#### Timeline

- `2026-07-27 12:29:59 UTC` — Initial lock resolved 5.8.1; upstream PHP-support boundary confirmed; fix started.
- `2026-07-27 13:07:28 UTC` — Corrected policy, constraint, exact lock, prefixing, and XLSX smoke test passed; defect closed.

### BUG-0006 — Dependency policy mislabeled Action Scheduler 3.9.3 as current

- **State:** CLOSED
- **Severity:** S3 Low
- **First seen:** 2026-07-27 12:19:07 UTC
- **Last seen:** 2026-07-27 12:21:16 UTC
- **Environment:** architecture/dependency review against GitHub and Packagist upstream metadata
- **Affected version/commit:** unreleased working tree
- **Affected modules/tasks:** ARCH-002, ARCH-003, FND-002
- **Reporter/owner:** Codex

#### Observed behavior

The dependency policy and decision rationale described Action Scheduler 3.9.3 as the current stable line. A fresh Packagist resolution check showed Action Scheduler 4.0.0 is the current upstream release.

#### Expected behavior

Dependency records must distinguish the current upstream release from the exact version selected for WP FormVault's approved compatibility floor.

#### Reproduction steps

1. Open the Action Scheduler GitHub latest release or Packagist package page.
2. Observe release 4.0.0 dated 2026-06-16.
3. Compare the policy's earlier “current stable line” wording for 3.9.3.
4. Read 4.0.0 `readme.txt` and observe it requires WordPress 6.8, while selected 3.9.3 requires WordPress 6.5.

#### Evidence

- [Action Scheduler 4.0.0 release](https://github.com/woocommerce/action-scheduler/releases/tag/4.0.0): marked latest and requires WordPress 6.8.
- [Action Scheduler package metadata](https://packagist.org/packages/woocommerce/action-scheduler): 4.0.0 is newer than 3.9.3.
- Frequency: deterministic documentation error.

#### Impact and scope

The selected WordPress 6.5 + Action Scheduler 3.9.3 profile remains internally compatible and was explicitly approved. The error affected dependency lifecycle wording and could have led readers to infer WP FormVault used the latest upstream release. No runtime code or user data was affected.

#### Cause analysis

- **Proximate cause:** Initial research opened the 3.9.3 release directly and did not confirm the repository's latest release marker or current Packagist version.
- **Root cause:** The dependency research checklist did not require two-source latest-version verification immediately before freezing a policy.
- **Contributing factors:** The current 4.0.0 release raises the WordPress minimum to 6.8, while 3.9.3 remains the appropriate line for the selected 6.5 floor.
- **Why existing controls missed it:** Compatibility verification checked internal document agreement, not freshness against upstream package metadata.

#### Resolution

- **Fix:** Relabeled 3.9.3 as the selected/latest WordPress-6.5-compatible line, recorded 4.0.0 and its WordPress 6.8/breaking-change boundary, and added an upstream freshness rule.
- **Data repair:** Not required.
- **Backward compatibility:** No version or platform change; wording/evidence correction only.

#### Recurrence prevention

- New invariant/guard: verify both the upstream latest release marker and Packagist current version immediately before dependency policy/lock changes.
- Regression test: extend compatibility verification to require the documented current-versus-selected distinction.
- Broader related tests: Composer resolution must prove the exact 3.9.3 lock and WordPress 6.5 metadata alignment.
- Documentation/task/memory updates: dependency policy, task register, bug register, changelog, memory, and compatibility verifier updated.
- Monitoring/alert: dependency update reviews must record research date and newer intentionally excluded releases.

#### Verification

- Command/check: `php tools/verify-compatibility.php`; `php tools/verify-foundation.php`; task-graph and diff checks.
- Result: compatibility and foundation verification passed; 198-task/325-edge graph remained valid and acyclic; diff whitespace check passed.
- Verified by/date: Codex, 2026-07-27 12:21:16 UTC.

#### Timeline

- `2026-07-27 12:19:07 UTC` — Packagist and GitHub latest-release check identified Action Scheduler 4.0.0; architecture task reopened.
- `2026-07-27 12:21:16 UTC` — Current-versus-selected distinction documented and regression checks passed; defect closed.

### BUG-0004 — Local dependency build runtime lacks usable Composer and GD

- **State:** CLOSED
- **Severity:** S2 Medium
- **First seen:** 2026-07-27 11:58:53 UTC
- **Last seen:** 2026-07-27 13:07:28 UTC
- **Environment:** local Windows host and `localdev_php_apache` container
- **Affected version/commit:** unreleased working tree
- **Affected modules/tasks:** ARCH-003, FND-002, EXPORT-001, FILES-003
- **Reporter/owner:** Codex / local environment owner

#### Observed behavior

The local PHP container has no Composer executable and does not load `ext-gd`. The host has `C:\ProgramData\ComposerSetup\bin\composer.bat`, but invoking it fails because host PHP is not available on `PATH`.

#### Expected behavior

The dependency build runtime must provide Composer 2.10+, 64-bit PHP compatible with the declared minimum, and all PhpSpreadsheet runtime extensions so it can resolve, audit, isolate, and verify the production dependency tree.

#### Reproduction steps

1. Run `docker exec localdev_php_apache php -m`.
2. Observe that `gd` is absent.
3. Run `docker exec localdev_php_apache sh -lc "command -v composer || true"`.
4. Observe no path/output.
5. Run `composer --version` on the host.
6. Observe that the launcher reports `php` is not recognized.

#### Evidence

- Container PHP: 8.2.31, 64-bit.
- Loaded modules include every currently listed PhpSpreadsheet runtime extension except `gd`.
- Host error: `'php' is not recognized as an internal or external command`.
- Frequency: every current local dependency-build attempt.

#### Impact and scope

Before the fix, `composer.json` could be written but its lock, platform resolution, security audit, and prefixed runtime tree could not be verified. No WordPress runtime or user data was affected.

#### Cause analysis

- **Proximate cause:** Composer is unavailable in the container, host Composer has no PHP runtime, and the container omits `ext-gd`.
- **Root cause:** The shared local Docker PHP image was not provisioned as a reproducible WP FormVault dependency-build environment.
- **Contributing factors:** The project did not previously have a Composer dependency set or preflight.
- **Why existing controls missed it:** Foundation verification required only PHP syntax/runtime behavior and did not exercise Composer or PhpSpreadsheet prerequisites.

#### Resolution

- **Fix:** Added a repository-owned, digest-pinned PHP 8.1 dependency-build container with Composer 2.10.2 and the complete required extension set, plus a PowerShell launcher and fail-fast platform preflight. The shared local PHP image remains unchanged.
- **Data repair:** Not required.
- **Backward compatibility:** No product impact; build environment only.

#### Recurrence prevention

- New invariant/guard: dependency builds must run an automated PHP architecture, extension, Composer-version, and platform-resolution preflight.
- Regression test: run the dependency preflight and strict Composer validate/install/audit flow.
- Broader related tests: generate and smoke-test the prefixed runtime tree on the declared minimum and current PHP versions.
- Documentation/task/memory updates: dependency policy, task evidence/blocker table, changelog, bug register, README, and memory updated.
- Monitoring/alert: CI/build must fail before packaging when any required extension or tool is absent.

#### Verification

- Command/check: `powershell -NoProfile -ExecutionPolicy Bypass -File tools\run-dependency-build.ps1`
- Result: repository-owned PHP 8.1.34 was confirmed 64-bit with all declared extensions; Composer 2.10.2 passed platform validation, install, audit, isolation, syntax, and runtime smoke checks.
- Verified by/date: Codex, 2026-07-27 13:07:28 UTC.

#### Timeline

- `2026-07-27 11:58:53 UTC` — Missing `ext-gd` and Composer identified during dependency preflight.
- `2026-07-27 12:00:38 UTC` — Host Composer failure and current container identity reconfirmed; task blocked.
- `2026-07-27 12:17:25 UTC` — Repository-owned build runtime selected; fix implementation started.
- `2026-07-27 13:07:28 UTC` — Digest-pinned build environment and the complete clean dependency pipeline passed; defect closed.

### BUG-0005 — Planned WordPress minimum conflicts with selected Action Scheduler

- **State:** CLOSED
- **Severity:** S2 Medium
- **First seen:** 2026-07-27 11:58:53 UTC
- **Last seen:** 2026-07-27 12:17:25 UTC
- **Environment:** architecture/dependency review against official upstream release metadata
- **Affected version/commit:** unreleased working tree
- **Affected modules/tasks:** ARCH-002, ARCH-003, FND-002, QUEUE-001
- **Reporter/owner:** Codex / product owner

#### Observed behavior

The WP FormVault plan declared WordPress 6.2+ while the selected Action Scheduler 3.9.3 line declares WordPress 6.5+.

#### Expected behavior

Every bundled runtime dependency must support the complete WordPress/PHP platform range advertised by WP FormVault.

#### Reproduction steps

1. Read the WordPress minimum in `IMPLEMENTATION_PLAN.md`, `TASKS.md`, or `wp-formvault.php`.
2. Read Action Scheduler 3.9.3 `readme.txt`.
3. Compare WordPress 6.2+ with Action Scheduler's `Requires at least: 6.5`.
4. Read Action Scheduler 3.7.4 `readme.txt` and observe it declares `Requires at least: 6.2`.

#### Evidence

- [Action Scheduler 3.9.3 readme](https://github.com/woocommerce/action-scheduler/blob/3.9.3/readme.txt): WordPress 6.5+.
- [Action Scheduler 3.7.4 readme](https://github.com/woocommerce/action-scheduler/blob/3.7.4/readme.txt): WordPress 6.2+.
- Frequency: deterministic requirement mismatch.

#### Impact and scope

Bundling 3.9.3 while advertising WordPress 6.2 could load unsupported dependency code on WordPress 6.2–6.4. Freezing 3.7.4 preserves the original support floor but accepts an older dependency line. No runtime sites or data are affected because Action Scheduler is not installed yet.

#### Cause analysis

- **Proximate cause:** Action Scheduler raised its WordPress minimum after the implementation plan's baseline was written.
- **Root cause:** The plan recorded a platform baseline without binding it to a verified dependency lifecycle/update policy.
- **Contributing factors:** Action Scheduler follows an L-2 WordPress dependency policy, so its minimum changes over time.
- **Why existing controls missed it:** Dependency versions and compatibility were intentionally deferred to `ARCH-002`/`ARCH-003`.

#### Resolution

- **Fix:** Option A selected by the user on 2026-07-27: WP FormVault now requires WordPress 6.5 and uses Action Scheduler 3.9.3, the latest compatible line for that floor. The plan, plugin metadata/constants, dependency policy, task register, memory, changelog, and compatibility verifier were synchronized.
- **Data repair:** Not required.
- **Backward compatibility:** Option A drops planned WordPress 6.2–6.4 support; Option B retains it but constrains dependency upgrades and APIs.

#### Recurrence prevention

- New invariant/guard: the advertised WordPress/PHP matrix must be checked against every locked runtime dependency during updates and release.
- Regression test: CI install/smoke matrix at the exact WordPress minimum with the packaged Action Scheduler and coexistence-order scenarios.
- Broader related tests: verify plugin headers, runtime guards, readme requirements, plan, task register, and package metadata agree.
- Documentation/task/memory updates: dependency policy, task blocker, changelog, and memory updated.
- Monitoring/alert: dependency-update automation must flag platform-minimum increases for explicit review, never auto-merge them.

#### Verification

- Command/check: `php tools/verify-compatibility.php`; `php tools/verify-foundation.php`; `powershell -NoProfile -ExecutionPolicy Bypass -File tools/verify-task-graph.ps1`; `git diff --check`.
- Result: compatibility and foundation verification passed in PHP 8.2.31; task graph passed with 198 tasks and 325 acyclic dependency edges; diff whitespace check passed.
- Verified by/date: Codex, 2026-07-27 12:17:25 UTC.

#### Timeline

- `2026-07-27 11:58:53 UTC` — Official 3.9.3 and 3.7.4 requirements compared; product decision opened.
- `2026-07-27` — User selected option A; compatibility synchronization started.
- `2026-07-27 12:17:25 UTC` — All active compatibility sources synchronized and regression checks passed; defect closed.

## Earlier resolved bugs

### BUG-0001 — Circular dependencies blocked task execution order

- **State:** CLOSED
- **Severity:** S3 Low
- **First seen:** 2026-07-27 11:44:08 UTC
- **Last seen:** 2026-07-27 11:46:12 UTC
- **Environment:** local documentation/task review
- **Affected version/commit:** unreleased working tree
- **Affected modules/tasks:** ARCH-003, FND-002, DB-008, SEC-004, SEC-007, ADAPTER-002, SEC-008, EXPORT-006, AUDIT-003, PRIVACY-003
- **Reporter/owner:** Codex

#### Observed behavior

The task register contained mutually dependent task pairs. The first observed pair was `ARCH-003` and `FND-002`; the new graph verifier then found additional cycles in repository/security, branding/SSRF, and privacy/audit work.

#### Expected behavior

The dependency/version policy must be decided before the Composer manifest is implemented, without a circular prerequisite.

#### Reproduction steps

1. Open `TASKS.md`.
2. Read the dependency cells for `ARCH-003` and `FND-002`.
3. Observe cycles including `ARCH-003 -> FND-002 -> ARCH-003`, `DB-008 -> SEC-004 -> DB-008`, `ADAPTER-002 -> SEC-007 -> ADAPTER-002`, `SEC-008 -> EXPORT-006 -> SEC-008`, and `AUDIT-003 -> PRIVACY-003 -> AUDIT-003`.

#### Evidence

- Task-register dependency cells reproduced the cycle deterministically.
- Frequency: every task-planning pass.

#### Impact and scope

No runtime or user data was affected. The defect blocked the next foundation task and could have caused contributors to bypass the mandatory task workflow.

#### Cause analysis

- **Proximate cause:** The prerequisite was entered in both directions.
- **Root cause:** The initial task decomposition did not include dependency-graph cycle verification.
- **Contributing factors:** Architecture-policy and manifest-implementation work were closely related and their direction was not reviewed separately.
- **Why existing controls missed it:** Task IDs and states were validated, but dependency cycles were not.

#### Resolution

- **Fix:** Correct dependency direction so policy/security primitives precede their consumers; validate the complete graph after all corrections.
- **Data repair:** Not required.
- **Backward compatibility:** No impact.

#### Recurrence prevention

- New invariant/guard: task dependency changes must pass a cycle check.
- Regression test: repeatable `tools/verify-task-graph.ps1`.
- Broader related tests: validate referenced task IDs and full graph acyclicity.
- Documentation/task/memory updates: task dependency rule, changelog, bug register, and memory updated.
- Monitoring/alert: verifier exits non-zero for missing dependency IDs or cycles.

#### Verification

- Command/check: `powershell -NoProfile -ExecutionPolicy Bypass -File tools/verify-task-graph.ps1`
- Result: passed with 198 tasks, 325 dependency edges, no missing references, and no cycles.
- Verified by/date: Codex, 2026-07-27 11:48:23 UTC.

#### Timeline

- `2026-07-27 11:44:08 UTC` — Composer dependency cycle identified and bug record opened.
- `2026-07-27 11:46:12 UTC` — Automated graph traversal found additional dependency cycles.
- `2026-07-27 11:48:23 UTC` — Dependency directions corrected; complete graph verification passed.

### BUG-0002 — Task-graph verifier rejected its initial empty traversal path

- **State:** CLOSED
- **Severity:** S3 Low
- **First seen:** 2026-07-27 11:45:25 UTC
- **Last seen:** 2026-07-27 11:45:25 UTC
- **Environment:** local Windows PowerShell
- **Affected version/commit:** unreleased working tree
- **Affected modules/tasks:** GOV-004, GOV-006
- **Reporter/owner:** Codex

#### Observed behavior

Running `tools/verify-task-graph.ps1` stopped before graph traversal with `ParameterArgumentValidationErrorEmptyArrayNotAllowed`.

#### Expected behavior

The verifier must accept an empty root traversal path and validate all tasks.

#### Reproduction steps

1. Run `powershell -File tools/verify-task-graph.ps1`.
2. Observe parameter binding fail when `Visit-Task` receives `-Path @()`.

#### Evidence

- PowerShell error: `Cannot bind argument to parameter 'Path' because it is an empty array.`
- Frequency: every verifier run.

#### Impact and scope

The new recurrence-prevention check could not run. No plugin runtime or user data was affected.

#### Cause analysis

- **Proximate cause:** A mandatory array parameter did not declare that an empty collection is valid.
- **Root cause:** The verifier's root traversal contract was not exercised before the first run.
- **Contributing factors:** Windows PowerShell parameter binding is stricter for mandatory empty-array arguments.
- **Why existing controls missed it:** This was the verifier's first execution.

#### Resolution

- **Fix:** Added `AllowEmptyCollection` to the traversal path parameter.
- **Data repair:** Not required.
- **Backward compatibility:** No impact.

#### Recurrence prevention

- New invariant/guard: run the verifier from an empty root path as its normal entry case.
- Regression test: rerun the verifier and confirm graph traversal completes.
- Broader related tests: confirm duplicate, missing-reference, and cycle checks remain active by inspection.
- Documentation/task/memory updates: changelog, bug register, and memory updated.
- Monitoring/alert: non-zero verifier exit remains the failure signal.

#### Verification

- Command/check: current-session, fresh-process, and explicit-task-file verifier invocations.
- Result: all three invocation modes passed with the complete acyclic task graph.
- Verified by/date: Codex, 2026-07-27 11:48:23 UTC.

#### Timeline

- `2026-07-27 11:45:25 UTC` — Failure reproduced and bug record opened.
- `2026-07-27 11:48:23 UTC` — Empty-root traversal fix verified in all invocation modes.

### BUG-0003 — Task-graph verifier resolved its default path too early

- **State:** CLOSED
- **Severity:** S3 Low
- **First seen:** 2026-07-27 11:47:33 UTC
- **Last seen:** 2026-07-27 11:47:33 UTC
- **Environment:** local Windows PowerShell launched as a fresh process
- **Affected version/commit:** unreleased working tree
- **Affected modules/tasks:** GOV-004, GOV-006
- **Reporter/owner:** Codex

#### Observed behavior

The verifier passed when invoked in the current session but failed under the documented `powershell -File` command because `$PSScriptRoot` was empty while evaluating the parameter's default value.

#### Expected behavior

The verifier must locate `TASKS.md` reliably when invoked from a fresh PowerShell process.

#### Reproduction steps

1. Run `powershell -NoProfile -ExecutionPolicy Bypass -File tools/verify-task-graph.ps1`.
2. Observe `Split-Path` reject an empty path during parameter initialization.

#### Evidence

- PowerShell error: `Cannot bind argument to parameter 'Path' because it is an empty string.`
- Frequency: every fresh-process invocation without an explicit `-TaskFile`.

#### Impact and scope

The exact command documented in `TASKS.md` did not work, so contributors and CI could not rely on the guard. No plugin runtime or user data was affected.

#### Cause analysis

- **Proximate cause:** `$PSScriptRoot` was used in a parameter default expression.
- **Root cause:** Script location resolution was performed before the script body instead of after parameter binding.
- **Contributing factors:** The initial check ran in the current PowerShell session, which exercised a different invocation path.
- **Why existing controls missed it:** The documented fresh-process command had not yet been executed.

#### Resolution

- **Fix:** Move default task-file resolution into the script body, after parameter binding.
- **Data repair:** Not required.
- **Backward compatibility:** Explicit `-TaskFile` usage remains supported.

#### Recurrence prevention

- New invariant/guard: command examples must be executed exactly as documented.
- Regression test: run both current-session and fresh-process invocations.
- Broader related tests: pass an explicit task-file path.
- Documentation/task/memory updates: task command, changelog, bug register, and memory verified/updated.
- Monitoring/alert: non-zero verifier exit remains the failure signal.

#### Verification

- Command/check: `powershell -NoProfile -ExecutionPolicy Bypass -File tools/verify-task-graph.ps1` with and without `-TaskFile`.
- Result: both fresh-process forms passed; current-session invocation also passed.
- Verified by/date: Codex, 2026-07-27 11:48:23 UTC.

#### Timeline

- `2026-07-27 11:47:33 UTC` — Fresh-process path-resolution failure reproduced and recorded.
- `2026-07-27 11:48:23 UTC` — Default and explicit task-file resolution verified.

## Bug record template

Copy this section for every new bug.

```markdown
### BUG-0000 — Concise title

- **State:** NEW
- **Severity:** S0 Critical | S1 High | S2 Medium | S3 Low
- **First seen:** YYYY-MM-DD HH:MM UTC
- **Last seen:** YYYY-MM-DD HH:MM UTC
- **Environment:** local | CI | staging | production
- **Affected version/commit:** unknown
- **Affected modules/tasks:** TASK-ID
- **Reporter/owner:** name or role

#### Observed behavior

State only what was observed.

#### Expected behavior

State the requirement or invariant, linking the plan/task when possible.

#### Reproduction steps

1. Exact setup/preconditions.
2. Exact action/input using sanitized example data.
3. Exact output/error.

#### Evidence

- Sanitized log/error:
- Test name/result:
- Screenshot/artifact:
- Frequency:

#### Impact and scope

Who/what is affected, data/privacy implications, whether processing may be incorrect, and the safest immediate mitigation.

#### Cause analysis

- **Proximate cause:** The direct technical failure.
- **Root cause:** The design/process/assumption gap that allowed it.
- **Contributing factors:** Environment, race, scale, compatibility, unclear requirement, missing test, and so on.
- **Why existing controls missed it:** Missing/incorrect test or review path.

#### Resolution

- **Fix:** Code/configuration/operational correction.
- **Data repair:** Required/not required; safe procedure if required.
- **Backward compatibility:** Impact and migration notes.

#### Recurrence prevention

- New invariant/guard:
- Regression test:
- Broader related tests:
- Documentation/task/memory updates:
- Monitoring/alert:

#### Verification

- Command/check:
- Result:
- Verified by/date:

#### Timeline

- `YYYY-MM-DD HH:MM UTC` — Event.
```

## Production incident addendum

For any production `S0` or `S1`, also capture:

- Detection and acknowledgement times
- Containment and recovery times
- Whether downloads/tokens/credentials require revocation
- Affected site/form/report/time window
- Data-loss or privacy assessment
- User/administrator communications
- Recovery validation
- Follow-up owner and due date
