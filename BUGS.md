# WP FormVault Bug Register

Last updated: 2026-07-27

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
