# Settlement OS Queue Operations Compass

**Last updated:** 2026-10-07

## North Star

Operators can inspect safe queue throughput, runtime, retries, and failures in
Horizon while every package declares its workload requirements, every critical
lane is explicitly commissioned, and durable PostgreSQL state remains the
authority for financial recovery.

## Current position

**Status: Gates 1 through 4 complete; Cloud Redis is connected but no Cloud queue or Horizon process is commissioned.**

The testing environment still uses database queues and database cache. A
private same-region Laravel Valkey cache is now attached and reachable, but no
Redis queue has been commissioned and no Horizon background process exists.
The local host has Horizon installed and disabled; the current Cloud deployment
remains the unchanged remote commit `ad7c1bc4`, which predates the three local
host commits for Horizon foundation, package adoption, and bounded canary
tooling. Scheduled Standing Funding remains disabled.

The host now discovers package queue manifests from Composer metadata, builds
supervisors only for explicitly authorized queues, and exposes strict
inspection commands. Inspection rejects financial use of `default`, unknown
queue authorization, incompatible shared-lane semantics, unsafe timeout versus
`retry_after`, excessive concurrency, and enabled financial lanes that were
not explicitly commissioned. The host has adopted the five exact immutable
releases and now discovers all five manifests while remaining on the database
queue with Horizon disabled and an empty authorized-queue list.

x-change now publishes a versioned, Horizon-neutral queue manifest through
Composer metadata. The catalog characterizes all 17 queued package jobs across
four currently effective lane groups:

- `x-change-funding` for funding and payment verification work;
- `x-change-feedback` plus any explicitly configured feedback override;
- `partner-payments` for partner payment event delivery; and
- `x-change-issuance` for funded on-demand Pay Code issuance recovery.

The former implicit `default` issuance route now uses a backward-compatible
configuration selector whose default is `x-change-issuance`. Every queued job
exposes only package, lane, and job-class tags. Horizon remains optional and no
queue connection, worker, schedule, or Cloud resource changed.

The agreed ownership model is:

- packages declare stable queue lanes through versioned manifests;
- the host dynamically discovers manifests;
- new financial lanes require explicit host commissioning;
- the host installs and configures Horizon;
- Laravel Cloud supplies Redis and runs Horizon; and
- Horizon telemetry never becomes financial or audit truth.

## Initial dashboard policy

The testing default allows any authenticated user and requires recent password
confirmation:

```yaml
horizon:
  enabled: true
  auth_mode: authenticated
  require_password_confirmation: true
  shared_password: disabled
```

Planned URL:

```text
https://x-change-testing-testing-uw1gvj.laravel.cloud/horizon
```

Production permission hardening is deferred but must occur before broad
production adoption.

## Queue grammar

```text
{package}-{capability}
```

Queue names are stable capability lanes. Environment, tenant, user, order,
campaign, provider-account, and deployment identifiers do not belong in queue
names. Redis prefixes isolate environments.

## Gate status

| Gate | Scope | Status |
| --- | --- | --- |
| 1A | Characterize and publish the x-change queue catalog | Complete |
| 1B | Remove financial `default` fallback and add safe operational tags | Complete |
| 2 | x-journal, x-action, x-feedback, and x-campaign manifests | Complete |
| 3 | Host discovery, Horizon install, and authenticated dashboard | Complete, disabled |
| 3A | Exact package adoption with Horizon and Redis processing disabled | Complete |
| 3B | Local Redis characterization and planning-only Horizon canary | Complete; returned to disabled baseline |
| 4 | Laravel Cloud Redis foundation | Complete; attached, reachable, empty, and unused by queues |
| 5 | Database drain and non-financial Redis canary | Pending host release publication and disabled Cloud adoption |
| 6 | Bounded x-change Horizon canary | Pending Gate 5 |
| 7 | Full declared-lane adoption and alerts | Pending Gate 6 |

## Settled decisions

1. Horizon is a host dependency, not a hard x-change dependency.
2. Package manifests are Horizon-neutral.
3. `vendor:publish` seeds host configuration but is not an upgrade protocol.
4. Discovery may be dynamic; financial queue authorization is explicit.
5. New jobs normally reuse stable capability lanes.
6. New financial lanes fail readiness until commissioned.
7. The testing dashboard initially allows any authenticated user with password
   confirmation.
8. A separate shared password is deferred and disabled.
9. Horizon operates and observes Redis queues; it does not merely watch
   database queues.
10. PostgreSQL remains authoritative for all financial and recovery state.
11. x-journal remains immutable evidence; Horizon remains transient operational
    telemetry.
12. Standing Funding schedule enablement is outside this workstream.

## Immediate next gate

**Publish and deploy the host Horizon foundation while it remains disabled.**

Push the three tested host commits, deploy their exact remote commit with the
five immutable package releases, and prove the Cloud environment still uses
database queues and cache, Horizon remains disabled, the authorization list is
empty, and no Horizon process exists. Only after that release checkpoint may a
separate Gate 5 inspect and drain database work before a planning-only Cloud
canary.

## Verification checkpoint

Gate 1A was verified on 2026-10-07:

- queue manifest tests: 4 passed, 13 assertions;
- all 17 queued x-change jobs are declared exactly once;
- configurable queue selectors remain distinct rather than being silently
  merged;
- Horizon is suggested but is not a package requirement; and
- Composer lock metadata was refreshed without changing declared dependency
  versions.

Gate 1B was verified on 2026-10-07:

- queue topology tests: 6 passed, including explicit issuance routing and all
  17 sanitized tag contracts;
- deployment planner and applier tests: 4 passed;
- Cloud recipe command tests: 5 passed;
- commissioning checklist tests: 10 passed;
- deployment documentation tests: 3 passed;
- Cockpit runtime diagnostics: 5 frontend tests passed; and
- the broader on-demand issuance file retained 26 passing scenarios, while four
  existing HTTP scenarios returned unrelated 428/302 readiness responses and
  remain outside this queue-routing change.

Gate 2 was verified on 2026-10-07:

- x-journal commit `5791f1c` declares no queued workloads: 1 test passed,
  10 assertions;
- x-action commit `7312529` declares no queued workloads: 1 test passed,
  10 assertions;
- x-feedback commit `7d60330` declares no queued workloads: 1 test passed,
  10 assertions;
- x-campaign commit `f5e4d53` declares its existing `campaigns` planning lane,
  preserves dispatch-provided queue overrides, and exposes safe tags: 5 tests
  passed, 34 assertions; and
- no package installed Horizon, changed its queue connection, or commissioned
  a worker.

Gate 3 was verified on 2026-10-07:

- host commit `c079ff13` installs Horizon 5.50 while keeping it disabled;
- 11 focused tests passed with 44 assertions;
- `/horizon` is hidden while disabled and requires authentication plus recent
  password confirmation when enabled;
- supervisor configuration is generated only from installed manifests and an
  explicit queue allowlist;
- strict inspection covers Redis, authorization, financial commissioning,
  semantic conflicts, retry timing, and concurrency ceilings;
- configuration caching, Composer strict validation, and Horizon route
  registration passed; and
- live host discovery returned no manifests because the installed package
  releases have not yet adopted the Gate 1 and Gate 2 commits.

Immutable package publication was verified on 2026-10-07:

- `3neti/x-change` `v1.0.105` resolves to `380c3eed`;
- `3neti/x-journal` `v1.1.1` resolves to `5791f1c`;
- `3neti/x-action` `v1.0.2` resolves to `7312529`;
- `3neti/x-feedback` `v1.1.1` resolves to merge commit `fa82da0`, preserving
  the independently published `v1.1.0` transport work; and
- `3neti/x-campaign` `v1.1.3` resolves to `f5e4d53`.

All five annotated tags were verified against their remote peeled commit SHAs.
The x-feedback merge was additionally verified with 201 passing tests and
1,102 assertions before publication.

Disabled-host adoption was verified on 2026-10-07:

- the host lock resolves exactly to x-change `v1.0.105`, x-journal `v1.1.1`,
  x-action `v1.0.2`, x-feedback `v1.1.1`, and x-campaign `v1.1.3`;
- package discovery returned all five versioned manifests and declared
  `campaigns`, `x-change-funding`, `x-change-feedback`, `partner-payments`, and
  `x-change-issuance` without authorizing any of them;
- strict inspection returned ready with `QUEUE_CONNECTION=database`, Horizon
  disabled, and an empty authorized-queue list;
- the uncommissioned financial lanes were reported as warnings rather than
  activated;
- no Horizon process was running;
- the focused host suite passed 11 tests with 44 assertions; and
- Composer strict validation and configuration cache round-trip passed.

Local Redis characterization and the non-financial canary were verified on
2026-10-07:

- the local DBngin Redis endpoint answered `PING` through PhpRedis;
- strict inspection passed with a Redis Horizon supervisor enabled for exactly
  the `campaigns` planning lane while the host default queue remained
  `database`; `x-change-funding` and `x-change-issuance` stayed uncommissioned;
- a host-owned synthetic canary `01M4A5RZZ6YDZE7DPFPYJ5V8M2` was the only job
  dispatched and wrote only a five-minute Redis completion marker;
- Horizon recorded the canary as running and then completed in 7.76 ms;
- the `campaigns` queue drained to zero and Horizon terminated cleanly;
- the ordinary host baseline returned to database queues, Horizon disabled,
  and an empty authorized-queue list;
- the canary command rejects non-local execution, disabled Horizon, non-Redis
  queues, unauthorized or undeclared queues, and every lane that is not
  planning-only; and
- the focused queue-operations suite passed 15 tests with 64 assertions,
  Composer strict validation passed, and configuration caching passed.

Laravel Cloud Redis foundation was verified on 2026-10-07:

- private Laravel Valkey resource
  `cache-a2ec07b3-e957-456f-af26-7226b45406d4` is available in
  `ap-southeast-1` at the Pro 250 MB size;
- automatic upsizing is disabled, and read-only probe
  `cexe-a2ec11d8-0284-4f9f-a73c-bedfdcefe8bb` confirms the effective
  `noeviction` policy so capacity pressure fails visibly rather than evicting
  queued work;
- the resource is attached to `x-change-testing/testing`, with explicit Redis
  and Horizon prefixes persisted for environment isolation;
- unchanged host commit `ad7c1bc4` was redeployed successfully as
  `depl-a2ec0be9-09d0-471f-a047-3d96eb8b1752` solely to inject credentials;
- probe `cexe-a2ec0df2-a3d8-4f57-8cdc-4401fff2173a` confirmed injected host and
  password values plus a successful `PING`;
- the runtime reports Valkey 9.0.0, primary role, 250 MB maximum memory, AOF
  disabled, and successful RDB snapshot status; persistence remains
  non-authoritative;
- `QUEUE_CONNECTION` and `CACHE_STORE` remain `database`, Horizon is disabled,
  the authorization list is empty, and scheduled Standing Funding is disabled;
- all five declared Redis queue sizes are zero; and
- the only background process remains the pre-existing database queue worker;
  no Horizon process was created.

Tested host release deployment was verified on 2026-10-07:

- host `main` commits `c079ff13`, `d0cd4ce1`, and `4c8d3a69` were pushed
  without including unrelated host worktree changes;
- push-to-deploy produced successful deployment
  `depl-a2ec1775-ba1c-4257-ad03-e79b94f8caa3` from exact commit
  `4c8d3a696fdbfb9de0f943b8b8d045fcb25995be`;
- deployed discovery reports the topology ready with no errors and declares
  `campaigns`, `x-change-funding`, `x-change-feedback`, `partner-payments`, and
  `x-change-issuance`;
- the runtime remains on `QUEUE_CONNECTION=database` and
  `CACHE_STORE=database`, while the attached Redis service responds to
  `PING` and every declared Redis queue remains empty;
- `HORIZON_ENABLED=false`, the authorized queue list is empty, Horizon has
  zero configured supervisor environments, and its runtime guard returns 404;
- the only Cloud background process remains the existing one-process database
  worker; no Horizon process was created;
- scheduled Standing Funding synchronization remains disabled; and
- the public unauthenticated `/horizon` request is currently redirected to the
  host commissioning surface before Horizon route middleware runs. The
  deployed Horizon guard itself was therefore verified inside the application
  runtime rather than inferred from that public redirect.

## Stop conditions

- Redis or Horizon would become financial truth.
- A critical lane would fall through to `default`.
- A package upgrade would automatically commission a new financial queue.
- Sensitive payloads or exception detail would be dashboard-visible.
- `/horizon` would be public.
- Queue migration cannot preserve and drain existing database work.
- Horizon timeout is not below Redis `retry_after`.
- Standing Funding scheduling changes implicitly.

## Companion document

- [Queue Topology and Horizon Commissioning Plan](QUEUE_TOPOLOGY_AND_HORIZON_PLAN.md)

Future agents must update this compass after every completed, blocked, or
reversed gate.
