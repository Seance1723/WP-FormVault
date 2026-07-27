# WP FormVault Repository Instructions

These instructions apply to the entire repository.

## Mandatory project records

Before making project changes, read:

1. `MEMORY.md` for the verified project orientation and anti-hallucination rules.
2. `TASKS.md` for current scope, dependencies, state, and completion criteria.
3. `IMPLEMENTATION_PLAN.md` for product and technical requirements relevant to the task.
4. `BUGS.md` when diagnosing or fixing a defect.
5. `CHANGELOGS.md` for current unreleased history.

## Required change workflow

1. Move the owning task in `TASKS.md` to `IN_PROGRESS` before implementation.
2. Keep changes within the named task or add/split a task before expanding scope.
3. Update `CHANGELOGS.md` for every material code, schema, security, dependency, compatibility, operational, or documentation change.
4. Add every discovered defect to `BUGS.md`; record reproduction, impact, proximate cause, root cause, fix, recurrence prevention, and verification.
5. Update `MEMORY.md` whenever a stable fact, decision, invariant, supported integration, or operational rule changes.
6. Mark a task `COMPLETE` only after its acceptance conditions and relevant automated/manual verification pass; record reproducible evidence in `TASKS.md`.
7. If verification fails or a regression appears, do not leave the task marked complete.

## Truth and naming rules

- A plan item is not proof of implementation. Inspect current code and tests before making status claims.
- Do not guess dependency versions, source-plugin APIs, compatibility, or runtime behavior.
- The product name is **WP FormVault**.
- Use slug/text domain `wp-formvault`, bootstrap `wp-formvault.php`, namespace root `WPFormVault`, and technical prefix `wpfv`.
- Use the current site's `$wpdb->prefix` plus the `wpfv_` suffix prefix; never hard-code `wp_`.
- Do not place secrets, raw tokens, passwords, personal form data, or unredacted production data in repository documentation, tests, fixtures, or logs.

User instructions given for a specific task take precedence when they explicitly amend scope or requirements; synchronize the project records when that happens.
