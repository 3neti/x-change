# Settlement OS Queue Topology and Horizon Commissioning Plan

**Created:** 2026-10-07

## Objective

Give operators a secured visual surface for queued, running, completed,
retried, and failed work while removing queue polling from PostgreSQL. Package
queue requirements must be discoverable, host commissioning must remain
explicit, and Redis must never become financial truth.

Laravel Horizon is installed and operated by the host application. x-change
and the other Settlement OS packages describe their queue lanes, safe tags,
and operating recommendations without requiring Horizon as a package
dependency.

## Verified starting posture

The `x-change-testing/testing` Laravel Cloud environment currently reports:

| Concern | Current value |
| --- | --- |
| Default queue | `database` |
| Default cache | `database` |
| Failed jobs | `database-uuids` |
| Redis client | `phpredis` |
| Horizon installed | No |
| Horizon process | None |
| Scheduled Standing Funding sync | Disabled |

This means installing Horizon alone is insufficient. Horizon requires Redis
queues. Redis provisioning, queue migration, a Horizon background process, and
dashboard authorization are separate host commissioning operations.

## Package and host boundary

### Packages own

- stable queue-lane names;
- a versioned machine-readable queue manifest;
- workload purpose and criticality;
- suggested timeout, retry, backoff, and concurrency boundaries;
- safe Horizon tags and display names;
- idempotency and durable-recovery requirements;
- payload-redaction rules; and
- compatibility checks that work when Horizon is absent.

Packages must not:

- require `laravel/horizon` merely to dispatch a job;
- publish or overwrite the host's `config/horizon.php`;
- authorize `/horizon`;
- provision Redis or workers;
- decide host capacity; or
- treat Horizon history as domain or financial evidence.

### The host owns

- installing `laravel/horizon`;
- publishing and maintaining `config/horizon.php`;
- Redis connections and environment isolation;
- supervisor composition and process limits;
- `/horizon` authentication and authorization;
- Laravel Cloud background processes;
- deployment restarts and `horizon:terminate`;
- metrics snapshots, pruning, and alerts; and
- the final decision to commission a newly declared queue lane.

## Queue naming convention

Use stable capability lanes:

```text
{package}-{capability}
```

Examples:

```text
x-change-funding
x-change-issuance
x-change-integrations
x-journal-projection
x-action-continuation
x-feedback-delivery
x-campaign-distribution
```

Rules:

1. Names are lowercase kebab case.
2. A lane represents an operational workload class, not one job class.
3. Queue names do not contain user, tenant, provider-account, order, campaign,
   environment, deployment, or run identifiers.
4. Environment isolation uses the Redis connection/prefix, not a generated
   queue name.
5. New jobs use an existing lane unless they need materially different
   authority, ordering, timeout, retry, or capacity semantics.
6. Financial lanes never fall through silently to `default`.
7. Renaming or retiring a lane requires a drain and compatibility plan.

## Machine-readable queue manifest

**Implementation checkpoint:** x-change Gate 1 now publishes this manifest at
`resources/settlement-os/queues.php` and advertises it through Composer extra
metadata. It characterizes all 17 queued x-change jobs, routes on-demand
issuance recovery through the configurable `x-change-issuance` lane, and gives
every queued job sanitized package/lane/job tags. No host queue connection or
infrastructure has changed.

**Host checkpoint:** Gate 3 is implemented in host commit `c079ff13`. Horizon
5.50 is installed but disabled, `/horizon` fails closed while disabled,
authenticated access requires recent password confirmation by default, and
supervisors are generated only from installed manifests plus an explicit queue
allowlist. Package releases containing the manifests must be published and
adopted before Redis or any Horizon worker is commissioned.

**Local characterization checkpoint:** The host adopted x-change `v1.0.105`,
x-journal `v1.1.1`, x-action `v1.0.2`, x-feedback `v1.1.1`, and x-campaign
`v1.1.3`, then completed a bounded local Redis rehearsal. DBngin Redis answered
through PhpRedis, strict inspection authorized only the non-financial
`campaigns` planning lane, and one synthetic Redis-only canary completed under
a single Horizon process while the application's default queue connection
remained `database`. The canary alone selected Redis explicitly. The process
was terminated, the queue drained to zero, and the ordinary host baseline
returned to Horizon disabled with no authorized lanes. No domain, financial,
campaign, feedback, or provider job was dispatched. Laravel Cloud Redis remains
Gate 4 and requires separate authorization.

Each participating package should expose a package-owned manifest with this
conceptual shape:

```php
return [
    'schema' => 'settlement-os.queue-topology.v1',
    'package' => '3neti/x-change',
    'lanes' => [
        [
            'key' => 'funding',
            'queue' => 'x-change-funding',
            'criticality' => 'financial',
            'ordering' => 'subject-serialized',
            'idempotency_required' => true,
            'durable_recovery_required' => true,
            'explicit_commissioning' => true,
            'recommended' => [
                'timeout' => 60,
                'tries' => 1,
                'backoff' => [30, 120, 300, 900],
                'max_processes' => 1,
            ],
            'tag_policy' => 'sanitized-operational-identifiers-only',
        ],
    ],
];
```

The manifest is descriptive. The host may adopt stricter settings but must not
weaken package invariants such as idempotency or subject serialization.

Composer metadata may advertise the manifest path:

```json
{
  "extra": {
    "settlement-os": {
      "queue-manifest": "resources/settlement-os/queues.php"
    }
  }
}
```

This allows cross-package discovery without making x-journal, x-action,
x-feedback, or x-campaign depend on x-change.

## Discovery and strict inspection

The host should aggregate installed manifests and expose:

```text
php artisan settlement-os:queues:discover
php artisan settlement-os:queues:inspect
php artisan settlement-os:queues:inspect --strict
```

The report compares:

```text
Installed package declarations
    -> effective queue names
    -> Horizon supervisors
    -> Redis connection
    -> worker limits
    -> missing, unknown, conflicting, and retired lanes
```

Strict inspection fails when:

- Horizon is enabled but the supervised connection is not Redis;
- a critical package lane has no supervisor;
- a financial lane falls through to `default`;
- two packages claim one queue with incompatible semantics;
- supervisor timeout is not safely below Redis `retry_after`;
- a queue exceeds an approved concurrency ceiling;
- a configured queue is unknown or retired; or
- required dashboard authorization is absent.

Discovery is dynamic; authorization is not. A package upgrade may reveal a new
lane, but a financial lane stays unsupervised until the host explicitly adopts
its settings. Do not rerun `vendor:publish --force` as an upgrade mechanism.

## Horizon dashboard authorization

The initial testing policy is:

```dotenv
HORIZON_ENABLED=true
HORIZON_AUTH_MODE=authenticated
HORIZON_REQUIRE_PASSWORD_CONFIRMATION=true
```

The host's Horizon middleware uses `web`, `auth`, and, when enabled,
`password.confirm`. The `viewHorizon` gate initially allows any authenticated
user. This is the agreed testing default.

A separate shared Horizon password is not part of the initial design. Existing
user credentials preserve individual identity and auditability. Any future
break-glass secret must be disabled by default, stored as a Laravel Cloud
secret, independently audited, and must not replace normal authentication.

The testing dashboard URL will be:

```text
https://x-change-testing-testing-uw1gvj.laravel.cloud/horizon
```

Production authorization remains a later decision and should move to a
specific permission such as `view-queue-operations` before broad production
adoption.

## Payload and tag policy

Horizon can expose job names, serialized properties, tags, timings, and
exception detail. Every package must keep queued payloads and tags safe for the
authorized operational audience.

Allowed examples:

- opaque model IDs;
- sanitized order or run references;
- provider code;
- queue lane;
- correlation ID; and
- non-sensitive terminal classification.

Prohibited examples:

- mobile numbers or email addresses;
- bank or wallet account numbers;
- provider request/response payloads;
- credentials, signatures, or tokens;
- possession or recovery tokens;
- signed URLs;
- claim evidence; and
- raw financial-instruction payloads.

Exceptions must be sanitized before they become dashboard-visible. x-journal
continues to receive selected immutable evidence; Horizon remains transient
operational telemetry.

## Gated implementation sequence

### Gate 1 — Queue convention and package catalog

1. Add the versioned manifest schema and program-level documentation.
2. Characterize every queue currently selected by x-change jobs and config.
3. Consolidate duplicate literals behind stable package configuration or
   constants without changing runtime routing.
4. Add the x-change manifest and tests proving all queued jobs select a declared
   lane.
5. Add safe job tags and display names only after payload review.

No Horizon dependency, Redis migration, or worker change occurs in Gate 1.

### Gate 2 — Cross-package adoption

1. Add manifests to x-journal, x-action, x-feedback, and x-campaign in their own
   repositories.
2. Keep each package independently usable without Horizon.
3. Add schema fixtures and compatibility tests.
4. Document owner, recovery authority, and payload policy for every lane.

### Gate 3 — Host discovery and Horizon installation

1. Install `laravel/horizon` in the host application.
2. Publish Horizon configuration and its service provider.
3. Implement aggregate discovery and strict inspection.
4. Implement authenticated-user authorization with password confirmation.
5. Add tests for unauthenticated denial, authenticated access, disabled mode,
   and strict topology failures.
6. Keep `QUEUE_CONNECTION=database`; do not start Horizon yet.

### Gate 4 — Laravel Cloud Redis foundation

This is an infrastructure mutation and requires separate authorization.

1. Provision a supported non-cluster Redis service for the testing environment.
2. Configure an isolated Redis queue connection and prefix.
3. Verify connectivity without dispatching financial work.
4. Keep database queue workers active until the migration fence is approved.
5. Record capacity, eviction policy, persistence expectations, and recovery
   posture.

Redis is transport and transient coordination. PostgreSQL remains authoritative
for funding orders, receipts, settlements, runtime generations, leases,
quarantine, Treasury effects, and audit evidence.

**Completion checkpoint (2026-10-07):** Gate 4 is complete. Laravel Cloud cache
`cache-a2ec07b3-e957-456f-af26-7226b45406d4`, named
`x-change-testing-queue-operations`, is attached only through the
`x-change-testing/testing` environment identity. It is a private, same-region
Laravel Valkey Pro 250 MB resource with automatic upsizing disabled. Probe
`cexe-a2ec11d8-0284-4f9f-a73c-bedfdcefe8bb` confirms the effective
`noeviction` policy and 262,144,000-byte memory ceiling. The deployed runtime
reports Valkey 9.0.0, primary role, AOF disabled, and a successful most recent
RDB save. Provider persistence is recovery assistance, not financial authority.

Deployment `depl-a2ec0be9-09d0-471f-a047-3d96eb8b1752` redeployed the unchanged
remote host commit `ad7c1bc43f27987cd6348ab6ba1da5eb76d47de4` only to inject
the attached cache credentials. Read-only probe
`cexe-a2ec0df2-a3d8-4f57-8cdc-4401fff2173a` proved Redis connectivity while
`QUEUE_CONNECTION=database`, `CACHE_STORE=database`, `HORIZON_ENABLED=false`,
the authorized queue list empty, and scheduled Standing Funding disabled. All
five declared Redis queue sizes were zero. Stable prefixes
`x_change_testing_testing_database_` and
`x_change_testing_testing_horizon:` are persisted for the next deployment.
No Horizon process, Redis queue migration, job dispatch, or financial operation
occurred.

### Gate 5 — Drain and non-financial canary

1. Stop new dispatch to the selected canary lane.
2. Drain and record all database-queue work.
3. Switch one non-financial lane to Redis.
4. Start `php artisan horizon` as a Laravel Cloud background process.
5. Verify queued, running, completed, retried, and deliberately failed synthetic
   work in `/horizon`.
6. Verify dashboard authorization and sanitization.
7. Prove deployment restart with `horizon:terminate`.

### Gate 6 — Bounded x-change canary

1. Keep scheduled Standing Funding synchronization disabled.
2. Commission `x-change-funding` with one worker and an explicit concurrency
   ceiling.
3. Run only a rollback-safe or otherwise separately authorized bounded job.
4. Verify Horizon evidence, package run history, x-journal evidence, queue
   drainage, and mutation-free rerun.
5. Stop on pressure, duplication, ambiguous execution, or unsanitized data.

### Gate 7 — Full declared-lane adoption

1. Migrate remaining approved lanes one at a time.
2. Add `horizon:snapshot`, retention, queue-wait alerts, and failure alerts.
3. Add a restricted Cockpit Operations link to `/horizon`.
4. Require strict topology inspection in deployment readiness.
5. Retire database queue workers only after every declared lane is drained and
   accepted.

## Recovery rules

- Redis loss must not make an executed financial operation replayable without
  database idempotency checks.
- Durable PostgreSQL state must support redispatch of missing work.
- Horizon retry is transport retry, not financial authorization.
- Clearing a Horizon queue is destructive and requires an explicit operational
  decision after durable-state inspection.
- A failed Horizon job does not replace package run history, suspense,
  quarantine, or x-journal evidence.
- Restarting Horizon must not enable scheduled Standing Funding.

## Stop conditions

Stop rather than improvise if:

- Horizon would run against the database queue;
- the Laravel Cloud Redis offering is an unsupported Redis Cluster topology;
- a critical queue is undeclared or unsupervised;
- a new financial queue would be auto-commissioned;
- database queue work cannot be drained deterministically;
- timeout and `retry_after` permit duplicate execution;
- a job payload or exception exposes sensitive data;
- Redis becomes a source of financial truth;
- a migration would enable scheduled Standing Funding; or
- `/horizon` is reachable without authentication.

## Acceptance criteria

The workstream is complete only when:

1. Installed Settlement OS packages expose valid queue manifests.
2. The host discovers every manifest and passes strict inspection.
3. Horizon processes only approved Redis queues.
4. `/horizon` requires an authenticated user and password confirmation under
   the initial testing policy.
5. Operators can see safe queued, running, completed, retried, and failed work.
6. One bounded x-change canary and its immediate no-op rerun pass.
7. PostgreSQL remains authoritative and recovery-safe.
8. Scheduled Standing Funding remains disabled unless separately authorized.

## Explicitly not authorized by this plan update

- adding the Horizon Composer dependency;
- publishing host configuration;
- provisioning Redis;
- changing `QUEUE_CONNECTION` or `CACHE_STORE`;
- creating or changing Laravel Cloud background processes;
- deploying to Laravel Cloud;
- enabling scheduled Standing Funding; or
- initiating a provider call or financial operation.
