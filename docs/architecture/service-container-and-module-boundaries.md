# WP FormVault Service Container and Module Boundaries

Owning task: `ARCH-004`  
Status: Accepted architecture contract; runtime implementation remains `FND-003`  
Machine-readable graph: [`module-boundaries.json`](./module-boundaries.json)

## Scope and evidence boundary

This document defines how WP FormVault will compose services and which modules may depend on which other modules. It is an implementation constraint, not evidence that the service container, plugin bootstrap, migrations, hooks, or product services exist.

`FND-003` owns the first runtime implementation. Later module tasks own their services. `tools/verify-architecture.php` verifies the graph and current source imports, while runtime behavior must be proven by the tests attached to the implementing tasks.

## Architectural goals

The composition model must:

- keep WordPress/plugin bootstrapping separate from business logic;
- prevent circular module dependencies and hidden global state;
- allow services to be unit-tested with explicit constructor dependencies;
- prevent feature modules from using the container as a service locator;
- keep presentation/transports (`Admin`, `Rest`) at the outside edge;
- preserve query-layer authorization, migration, compatibility, and dependency-load gates;
- make optional integrations and optional services fail closed without partially starting the product;
- keep request/site state isolated, including multisite blog switches and queued jobs.

## Composition root

Composition root: `WPFormVault\Core\Plugin`  
Container: `WPFormVault\Core\ServiceContainer`

`Core\Plugin` is the only application composition root and the only production class permitted to know all concrete module implementations. Its responsibility is wiring and lifecycle coordination, not business behavior. `wp-formvault.php` remains the only WordPress entry file and delegates to this root after the entry guards and autoloader setup.

No feature service may receive `ServiceContainer`, a container interface, `Core\Plugin`, or a generic resolver callable. Dependencies are passed explicitly through constructors. This keeps missing dependencies visible and prevents runtime service lookup from hiding module coupling.

### Required startup sequence

`FND-003` must preserve this fail-closed order:

1. `wp-formvault.php` performs the direct-access guard, defines verified identity/path/platform constants, and registers the internal autoloader.
2. The isolated production autoloader is loaded when present. A missing/corrupt production dependency tree produces a safe administrator diagnostic; product services do not start.
3. The bundled unprefixed Action Scheduler loader is registered at the early boundary defined by the dependency policy. No Action Scheduler API is called before its initialization signal.
4. `Core\Plugin::boot()` is idempotent and creates only the minimal core graph needed for platform compatibility, site context, schema status, migration coordination, and safe diagnostics.
5. The WordPress/PHP architecture check runs before optional product classes are instantiated. Failure registers only safe diagnostics.
6. The per-site schema gate runs. If an installation/upgrade is required, the migration coordinator owns that path and report, queue, adapter, admin-product, REST-product, and scheduled services remain unavailable until the schema is current.
7. The composition root registers all reviewed definitions, validates required services and module providers, freezes the container, and only then registers product hooks.
8. Hook registration is idempotent. Constructors and definition factories do not perform queries, writes, scheduling, email, filesystem mutation, or remote calls.

Activation, deactivation, uninstall, multisite provisioning, and Action Scheduler integration remain in their owning tasks. They must enter through explicit lifecycle services, not through arbitrary container lookups.

## Service container contract

The container is deliberately small and project-owned. It is not a framework and does not auto-wire by reflection.

### Definition rules

- Service identifiers are class/interface FQCNs or named scalar/configuration identifiers owned by `Core\Contracts`.
- Definitions are registered only by `Core\Plugin` during composition.
- A definition is either a prebuilt immutable value or a factory with explicit dependencies.
- Object services are lazy, shared once per PHP request and current site context unless a factory is explicitly documented as producing a transient operation object.
- Aliases map a public contract to one reviewed implementation and cannot silently replace an existing definition.
- The container is frozen before hook registration; registration or replacement after freeze is an error.
- Missing services, duplicate definitions, circular factory resolution, and factory type mismatches throw a controlled architecture exception during boot.
- The container never catches a dependency error and substitutes `null`.

### Scope and state rules

- Container sharing is request-local, not persistent process state.
- A service must not retain a user-specific request, mutable report run, submission payload, raw token, password, personal data, or blog-specific object beyond its documented operation.
- Code that calls `switch_to_blog()` must build/use the target site's scoped graph and restore the previous blog in a `finally` path. A graph created for one blog cannot be reused for another.
- Queue handlers create an explicit job/run context from sanitized identifiers; that context is not registered as a global singleton.
- Clocks, randomness, logging, locks, storage, mail transport, and WordPress/database boundaries are injected behind contracts so tests can supply deterministic fakes.

### Container access rule

Only `Core\Plugin` may call general-purpose `get()`/`has()` methods while composing and registering hook subscribers. Other modules receive their exact dependencies. Factories live in the composition root; modules do not register themselves by reaching into the container.

## Module public surface

Within a module, implementation classes are private by convention. A different module may import only the provider module's reviewed public namespaces:

- `Contracts` for behavior/ports;
- `DTO` for immutable data-transfer objects;
- `Events` for immutable event messages;
- `Value` for validated immutable value objects.

Cross-module access to another module's repositories, WordPress controllers, concrete services, factories, or internal helpers is forbidden. `Core\Plugin` is the sole wiring exception and may import concrete implementations to construct the graph.

Contracts belong to the module that provides the capability. Consumers depend on those contracts; the composition root supplies implementations. Shared primitives belong in `Core` only when they are genuinely cross-cutting. A feature-specific type must not be moved into `Core` merely to bypass a boundary.

Domain/application modules never depend on `Admin` or `Rest`. These outer modules translate WordPress input into application DTOs and render/serialize results. They contain no SQL, token generation, report creation, scheduling math, or other business persistence logic.

## Approved module dependency graph

An arrow means “may depend on.” Omitted edges are forbidden, even when the graph would remain acyclic.

| Module | Layer | May depend on |
|---|---:|---|
| `Core` | 0 | — |
| `Adapters` | 1 | `Core` |
| `Audit` | 1 | `Core` |
| `Submissions` | 2 | `Core`, `Adapters`, `Audit` |
| `Email` | 2 | `Core`, `Audit` |
| `Sync` | 3 | `Core`, `Adapters`, `Submissions`, `Audit` |
| `Workflow` | 3 | `Core`, `Submissions`, `Audit` |
| `Reports` | 4 | `Core`, `Submissions`, `Workflow`, `Audit` |
| `Notifications` | 4 | `Core`, `Submissions`, `Workflow`, `Email`, `Audit` |
| `Downloads` | 5 | `Core`, `Reports`, `Audit` |
| `Privacy` | 5 | `Core`, `Submissions`, `Workflow`, `Reports`, `Audit` |
| `Scheduling` | 6 | `Core`, `Reports`, `Email`, `Downloads`, `Audit` |
| `Health` | 7 | `Core`, `Adapters`, `Sync`, `Scheduling`, `Audit` |
| `Rest` | 8 | `Core`, `Submissions`, `Workflow`, `Reports`, `Scheduling`, `Downloads`, `Notifications`, `Privacy`, `Audit` |
| `Admin` | 9 | `Core`, `Adapters`, `Sync`, `Submissions`, `Workflow`, `Reports`, `Scheduling`, `Email`, `Downloads`, `Notifications`, `Audit`, `Privacy`, `Health` |

The layer number is an enforcement aid: dependencies must point to a strictly lower layer. `Admin` and `Rest` are terminal inbound modules; no other module may depend on them.

## Interaction rules

### Commands and queries

- Inbound modules call narrow application contracts with validated DTOs.
- Query contracts apply `AccessScope` inside the repository/query path; callers cannot opt out by omitting a filter.
- Repositories own persistence and parameterized SQL. Controllers, pages, exporters, and templates never construct SQL.
- Write operations define transaction/idempotency boundaries in the owning application service.
- Cross-module return values are DTOs/value objects, not mutable ORM-style entities or raw `$wpdb` rows.

### Events and queued work

- Domain/application events are immutable facts and contain stable IDs plus the minimum non-sensitive context.
- Events do not contain raw tokens, passwords, unrestricted file paths, or full personal-data payloads.
- Synchronous listeners cannot be required for the committing operation's correctness unless they are part of the same owning module and transaction.
- Background work carries stable IDs and an idempotency key. The handler reloads authorized/current state through application contracts.
- Queue, email, notification, and audit side effects occur after the owning write succeeds. Retries must be safe.
- WordPress hooks are transport edges. Internal business correctness must not depend on an undocumented hook execution order.

### Optional integrations

- `Adapters` owns source-plugin detection and capability/version declarations.
- Consumers depend on adapter contracts and capability descriptors, never source-plugin classes.
- Missing source plugins produce an unavailable adapter/status, not a fatal service-definition failure.
- Unknown source versions degrade according to the adapter safety policy; the container does not pretend an unsupported implementation is available.

## Ownership boundaries

| Concern | Owning module |
|---|---|
| Compatibility, site context, migrations, settings, clock/RNG, locking, redacted operational logging | `Core` |
| Form-source detection, normalization, capabilities, source-version handling | `Adapters` |
| Normalized submission persistence, access-scoped reads, edit/trash/bulk/view behavior | `Submissions` |
| Capture/index/reconciliation orchestration and cursors | `Sync` |
| Workflow state and guarded automation | `Workflow` |
| Report datasets, snapshots, mappings, spreadsheet/CSV creation, report records | `Reports` |
| Recipient/header validation and mail handoff/attempt records | `Email` |
| Token/download authorization, throttle, response/file delivery, download log | `Downloads` |
| Intended windows, catch-up, queue dispatch, retries, delivery orchestration | `Scheduling` |
| Notification preferences and in-app/email/digest notification policy | `Notifications` |
| Immutable audit history and redaction-aware audit recording | `Audit` |
| Personal-data export, erasure, anonymization | `Privacy` |
| Read-only operational probes and Site Health integration | `Health` |
| REST/AJAX request/response translation and permissions | `Rest` |
| Admin navigation, forms, notices, pages, view models, rendering | `Admin` |

## Failure and observability behavior

- Boot failures are sanitized and recorded through the minimal redacted logger; no form values or secrets enter diagnostics.
- Administrators receive one actionable notice for platform, dependency, or migration failures; public requests receive no internal path/class details.
- A module provider must fail atomically. Its hooks are not registered if required definitions are invalid.
- Optional module availability is explicit through a capability/status contract. `has()` is not used throughout business code as an implicit feature flag.
- Health checks inspect public status contracts and remain read-only.

## Verification and implementation acceptance

Run:

```powershell
php tools/verify-architecture.php
powershell -NoProfile -ExecutionPolicy Bypass -File tools/verify-task-graph.ps1
```

`ARCH-004` is complete when the document and JSON graph agree, every planned module is represented, dependency layers are acyclic and inward-pointing, terminal modules are not depended upon, and current PHP imports do not violate the public-surface/container rules.

`FND-003` must additionally prove:

- idempotent plugin boot and hook registration;
- lazy shared service identity and explicit transient factories;
- duplicate/missing/circular/frozen-container failures;
- no product-service construction when compatibility, dependencies, or schema gates fail;
- container access limited to the composition root;
- constructor-injected substitutes in unit tests;
- safe multisite site-context isolation.

## Change control

Changing a module edge, public surface, composition root, container contract, or startup gate is an architecture change. Update this document, `module-boundaries.json`, `TASKS.md`, `CHANGELOGS.md`, and `MEMORY.md` in the same change, then rerun the architecture and task-graph verifiers.
