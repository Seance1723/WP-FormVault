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

No confirmed project bugs have been recorded. The project currently contains documentation controls only; runtime/plugin code has not started.

## Resolved bugs

None.

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
