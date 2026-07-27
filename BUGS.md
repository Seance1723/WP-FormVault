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

## Open bugs

No open bugs.

## Resolved bugs

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
