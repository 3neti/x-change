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

Database-queue characterization and drain planning was verified on 2026-10-07:

- read-only Cloud probes `cexe-a2ec1be0-01f6-4d8c-9489-7fcce8472322`
  and `cexe-a2ec1e12-c5ca-43e1-a6b4-e7e1c22948a6`, taken more than six
  scheduler cycles apart, both found zero database jobs and zero failed jobs;
- the sole worker is still one database process for
  `x-change-funding,x-change-feedback,default`, with a 60-second worker
  timeout against a 90-second database `retry_after`;
- deployed package manifests declare five queues, so `campaigns`,
  `partner-payments`, and `x-change-issuance` are not consumed by the current
  database worker;
- partner payment events and Campaign NetBank dispatch are disabled, both
  related durable sources are empty, and no current backlog exists for those
  lanes;
- on-demand issuance is enabled and targets `x-change-issuance`. No issuance
  funding order is currently in an open state, but the worker omission is a
  pre-existing coverage gap that must be corrected or the producer must be
  fenced before a Cloud Horizon canary;
- redemption feedback remains enabled on the covered `x-change-feedback`
  queue, while scheduled Standing Funding remains disabled;
- all current Funding Intents and Payment Attempts are settled, expired, or
  still `pending_instructions`, so the enabled minute schedules had no
  eligible records to enqueue during the observation window;
- Campaign batch, partner commission, Standing Funding runtime, and pending
  slice-journal outboxes contain no pending work; and
- no job was dispatched, retried, deleted, migrated, or drained during this
  characterization gate.

The approved drain plan is fail-closed: first correct the database worker's
`x-change-issuance` coverage in a separate infrastructure gate, then repeat two
zero-backlog snapshots around a bounded quiet window. Existing database work
must drain through its original worker and may not be copied into Redis. The
later non-financial canary may authorize only `campaigns`, must dispatch only
the host-owned synthetic job explicitly to Redis, and must leave every package
producer on its existing database connection.

Database worker coverage correction was staged in the Cloud control plane on
2026-10-07, but runtime materialization remains pending:

- pre-change probe `cexe-a2ec2437-c162-4e88-a9d3-1b21a912c0bc` found zero
  database jobs, zero failed jobs, and zero open issuance funding orders while
  Horizon and scheduled Standing Funding remained disabled;
- Laravel Cloud process `process-a26660bc-ca50-4c26-9f41-9ca29ca5d5ba` now
  stores desired configuration changing
  `x-change-funding,x-change-feedback,default` to
  `x-change-funding,x-change-issuance,x-change-feedback,default`;
- the process remains a single database worker with 3 tries, 30-second
  backoff, 3-second sleep, no rest, and a 60-second timeout;
- the first update request was rejected before mutation because the Cloud API
  did not accept a textual `false` maintenance-mode value; the successful
  retry omitted that unchanged optional field;
- observations at `05:33:06Z`, `05:34:17Z`, and `05:35:32Z` all found zero
  database jobs, zero failed jobs, and zero open issuance funding orders;
- final probe `cexe-a2ec25f8-52d8-4505-83cf-d499370c2855` also found ready,
  delayed, and reserved depth zero on all five Redis queues;
- the Cloud process resource continued to render its earlier command without
  `x-change-issuance`, so a deployment and post-deployment process check are
  still required before the coverage correction may be called operational;
- `QUEUE_CONNECTION` and `CACHE_STORE` remain `database`, Horizon remains
  disabled with no authorized queues, scheduled Standing Funding remains
  disabled, and the corrected database worker remains the only background
  process; and
- no test job, retry, deletion, provider call, voucher issuance, or financial
  operation occurred.

The first bounded Cloud Horizon canary attempt was safely stopped on
2026-10-07:

- command `comm-a2ec2829-33c7-48b1-ac03-7c4fd6f451ab` proved that
  command-scoped Horizon configuration was topology-ready for only the
  `campaigns` planning lane over Redis while both financial lanes remained
  uncommissioned;
- temporary process `process-a2ec2853-9074-47ad-a57b-549fd0573aea` was created
  without changing persistent environment variables or deploying application
  code;
- Horizon remained inactive with both the direct scoped command and one
  corrective `/bin/sh -lc` command, indicating that the new process definition
  was not materialized by the running deployment;
- no canary job was dispatched;
- the inactive temporary process was deleted; and
- recovery probe `cexe-a2ec2a23-2855-431a-aa2d-2a2c55df7808` confirmed zero
  database jobs, zero failed jobs, all Redis queue states empty, database queue
  and cache defaults intact, Horizon disabled with no authorized queues, and
  scheduled Standing Funding disabled.

The next gate must explicitly authorize deployment materialization of the
corrected database worker and a temporary Horizon process. It must verify the
rendered process commands before any canary dispatch and include a second
deployment or equivalent verified removal step during cleanup.

Background-process materialization deployment was safely stopped on
2026-10-07 before canary dispatch:

- temporary campaigns-only Horizon process
  `process-a2ec2ca1-dbfa-419f-86fa-d9fa30af82a0` was recreated with
  command-scoped enablement and no persistent environment change;
- deployment `depl-a2ec2cd9-a435-48de-a736-6e066dfafb01` succeeded on exact
  tested host commit `4c8d3a696fdbfb9de0f943b8b8d045fcb25995be`;
- the deployed Horizon master became active and its live supervisor command
  proved one Redis worker for only `campaigns`, with one process, 60-second
  timeout, and one try;
- live process inspection also proved that the database worker still executed
  its earlier command for
  `x-change-funding,x-change-feedback,default`, despite the Cloud resource's
  desired configuration listing `x-change-issuance`;
- because both rendered process commands did not match the approved state, no
  synthetic canary job was dispatched;
- the temporary process definition was deleted and `horizon:terminate`
  gracefully stopped the active master and supervisor;
- cleanup deployment `depl-a2ec2ea1-a439-4860-be73-47496f0f9816` succeeded on
  the same exact host commit; and
- recovery probe `cexe-a2ec2f99-8cdb-43e1-a8e2-fe7480fd42a0` confirmed zero
  database jobs, zero failed jobs, every Redis queue empty in ready, delayed,
  and reserved state, database queue and cache defaults, Horizon disabled with
  no authorized queues, and scheduled Standing Funding disabled.

The existing worker resource now has a proven control-plane/runtime divergence:
updating its queue configuration and redeploying does not change its rendered
command. Do not proceed to a Horizon canary until a separately authorized
database-worker replacement creates a new one-process worker with the complete
queue list, deploys it, verifies its live command, and retires the stale worker
under a zero-backlog fence.

Zero-backlog database-worker replacement was verified on 2026-10-07:

- preflight probe `cexe-a2ec32da-3623-426a-9cfb-5fb28aebe917` found zero
  database jobs, zero failed jobs, zero open issuance funding orders, and zero
  Redis queue depth while Horizon and scheduled Standing Funding were disabled;
- replacement process `process-a2ec3303-51b9-43e6-9bd3-97533118bd70` was
  created as one database worker for
  `x-change-funding,x-change-issuance,x-change-feedback,default`, preserving 3
  tries, 30-second backoff, 3-second sleep, zero rest, and 60-second timeout;
- deployment `depl-a2ec332c-8934-4627-a3b2-955ee35da9ab` succeeded on exact
  tested host commit `4c8d3a696fdbfb9de0f943b8b8d045fcb25995be`;
- live process command `comm-a2ec33f5-a0be-4d1d-8523-d117c5b25818`
  proved the replacement worker rendered the complete queue list alongside the
  stale worker;
- a fresh zero-backlog fence then permitted deletion of stale process
  `process-a26660bc-ca50-4c26-9f41-9ca29ca5d5ba`;
- cleanup deployment `depl-a2ec3519-9f7a-4e13-8374-abfef201334f` succeeded on
  the same exact host commit and materialized stale-worker retirement;
- final live command `comm-a2ec3655-320f-41b3-8f2f-2c3e651d9f12` proved the
  replacement is the sole runtime worker and visibly includes
  `x-change-issuance`;
- final recovery probe `cexe-a2ec365b-f750-4632-bc88-4f5882d46f59` found zero
  database jobs, zero failed jobs, zero open issuance funding orders, and zero
  ready, delayed, or reserved work on every Redis queue; and
- Horizon remained absent and disabled, no queue authorization changed,
  scheduled Standing Funding remained disabled, and no job, retry, provider
  call, voucher issuance, or financial operation occurred.

The bounded synthetic `campaigns` Horizon canary completed and recovered on
2026-10-07:

- preflight probe `cexe-a2ec3a53-4d46-4114-8561-5e853575f4cb` found zero
  database jobs, zero failed jobs, and zero ready, delayed, or reserved work
  on every declared Redis queue;
- temporary process `process-a2ec3aa8-e36d-4942-a75d-9b311f903a50` authorized
  only `campaigns` and selected Redis only inside its Horizon command;
- materialization deployment `depl-a2ec3adb-7337-49a7-84a9-8499a40a066e`
  succeeded on exact tested host commit
  `4c8d3a696fdbfb9de0f943b8b8d045fcb25995be`;
- live command inspection `comm-a2ec3bbb-985d-4111-a258-086251623548`
  proved the database worker retained
  `x-change-funding,x-change-issuance,x-change-feedback,default`, while the
  sole Horizon supervisor used Redis, one process, one try, and only the
  `campaigns` queue;
- Horizon reported active through
  `comm-a2ec3bdd-efdd-4fd8-b808-3dfadc0e076d`, and the immediate pre-dispatch
  fence `cexe-a2ec3c04-ca47-44b9-9c1a-fd298c4ddea3` remained empty;
- command `comm-a2ec3c3d-f09a-4fab-8758-8e05506e12a3` dispatched exactly one
  host-owned synthetic, non-financial canary. Canary
  `01M4AHA89J0JD449DFGJXHE7XR` completed on `campaigns` and wrote its bounded
  completion marker;
- post-canary probe `cexe-a2ec3c65-d9bb-474d-b209-d49d59491ff8` found zero
  database jobs, zero failed jobs, and zero Redis queue residue;
- the temporary process was deleted, Horizon was gracefully terminated by
  `comm-a2ec3cca-808d-4bf4-868a-17db92eca9be`, and cleanup deployment
  `depl-a2ec3cec-c21f-41b4-b920-419aabd8f83b` succeeded on the same exact
  host commit;
- final live inspection `comm-a2ec3d9c-311d-43ed-ac06-6e958601b747` showed
  only the full-coverage database worker, while
  `comm-a2ec3dc9-2688-4d16-b3da-c659969f14f0` confirmed Horizon inactive;
  and
- restored topology inspection `comm-a2ec3ded-675e-4a13-92b4-f98a7c368919`
  remained ready with database queue/cache defaults, no Horizon authorization,
  and uncommissioned financial lanes. Final probe
  `cexe-a2ec3e17-178f-41fd-862c-bff155a1179d` found every database and Redis
  queue empty.

No package producer was migrated, no persistent queue default changed, and no
provider call, voucher issuance, Standing Funding schedule, or financial
operation occurred. This proves the temporary campaigns-only Horizon
commissioning, one-job consumption, and deployment-backed recovery procedure.

Authenticated operator authorization and the synthetic retry/failure path were
characterized on 2026-10-07:

- host commit `ea775eb5a3ea426bdfb7ec4710f7f71e817bc294` added a failure-only
  canary job with exactly two attempts, a one-second backoff, sanitized tags,
  and short-lived Redis markers; 17 focused tests passed with 83 assertions;
- push deployment `depl-a2ec4128-6bf3-44f6-b166-ffe5f8ceb577` adopted that
  exact commit while Horizon remained absent, and preflight probe
  `cexe-a2ec439d-f587-439f-b0d7-181f18a02ff4` found all database and Redis
  queue states empty;
- temporary process `process-a2ec43e1-8e90-416f-ae39-69b290d2590d` was
  materialized by deployment `depl-a2ec440d-9aa4-4216-b00f-aa51664a5dc1` on
  the same exact commit;
- live process inspection `comm-a2ec44c4-413f-4697-93cf-7ec02581dd3f`
  proved that the database worker retained its complete queue list and the
  sole Horizon supervisor used Redis for only `campaigns`; Horizon reported
  active through `comm-a2ec44f0-e810-4105-bc24-ee8f6828a459`;
- authorization probe `cexe-a2ec4524-4480-45ca-8bfe-6fc01d79f1f6` found an
  existing authenticated operator authorized, a guest denied, and the route
  stack protected by enablement, authentication, and recent password
  confirmation. Persistent Horizon enablement and authorization remained
  disabled and empty;
- the public browser route remained intercepted by the host commissioning
  screen, so this gate proves live authorization and Horizon read-model access,
  not external browser acceptance. The commissioning boundary was not
  bypassed;
- immediate pre-dispatch probe
  `cexe-a2ec4559-31d0-472d-8ff3-8a82753baec3` remained empty before command
  `comm-a2ec457d-74d4-4fb4-ab5c-116fb7a36df1` dispatched exactly one
  synthetic, non-financial failure canary;
- canary `01M4AJSKF7ZR0P6JCWRG1WRYWE`, queue job
  `8a90dab8-c472-4f8f-b24d-38b7cae36730`, failed as planned after exactly two
  attempts. Database probe `cexe-a2ec4604-2e95-4f31-8b8b-90d5e75f9be2` and
  Horizon probe `cexe-a2ec4628-0023-469e-8d46-1b6ac3ed1924` proved durable
  failed-job evidence and the sanitized dashboard row without reading payload
  or exception detail;
- stock `horizon:forget` and `queue:forget` commands did not consistently
  reconcile Horizon's job UUID with this host's failed-job row identifier.
  No bulk cleanup was used. Guarded cleanup
  `cexe-a2ec4710-b64a-43aa-bd7f-478d7b2503d4` deleted only verified row
  `4145` and the two known marker keys, while
  `cexe-a2ec47c4-e21f-4ac9-83d5-7ca58fa86e06` removed only the exact Horizon
  repository record;
- the temporary process was deleted, Horizon was terminated by
  `comm-a2ec481a-ac70-48ad-9501-eea964827d20`, and cleanup deployment
  `depl-a2ec4848-d345-4c9e-a3e3-7a650c59ebf1` succeeded on the same exact
  host commit; and
- final live inspection `comm-a2ec4912-3953-41d4-80e9-b78180f00e12` found
  only the full-coverage database worker, Horizon reported inactive through
  `comm-a2ec4931-5b78-4061-9af7-9f476bdf23e3`, fail-closed topology remained
  ready through `comm-a2ec495e-ba86-4a42-816a-082bc627b600`, and final probes
  `cexe-a2ec49a6-22ca-4d57-a355-04cd24c3fe3b` and
  `cexe-a2ec49d0-e5de-45bf-9801-16eea5a46376` found no database, failed-job,
  Redis queue, or synthetic Horizon residue.

No financial lane was commissioned and no provider or voucher operation was
performed. Before another deliberate failure canary, add and test a host-owned
targeted cleanup command that resolves the Horizon UUID and failed-job storage
identifier without relying on the incompatible stock-command sequence.

The targeted failure-cleanup prerequisite was completed on 2026-10-07:

- host commit `7d11dda16a64a8d339e3a2e12a5159d94768d894` adds
  `settlement-os:queues:cleanup-failure`, restricted to local and testing
  environments and to the exact synthetic `campaigns` failure job;
- the command requires both the job UUID and canary ULID, validates the
  configured database-UUID provider, connection, queue, display name, job
  class, and serialized canary identity before deleting any record;
- cleanup removes only the exact guarded database row, exact Horizon UUID, and
  the two known short-lived marker keys. It fails closed on mismatched or
  ambiguous evidence and supports database-only, Horizon-only, and
  already-clean recovery states;
- 24 focused queue/Horizon tests passed with 142 assertions, including exact
  dual-store cleanup, both partial-state recoveries, idempotent rerun, mismatch
  refusal, and invalid-identifier refusal;
- deployment `depl-a2ec7a9c-0714-4ce8-8db9-2660468b9266` adopted the exact
  tested host revision while Horizon remained disabled;
- live commands `comm-a2ec7b85-4b29-4185-b468-dfdc20c03a73` and
  `comm-a2ec7bb1-6ce4-4512-85b1-466b53f66ee8` both returned
  `already_clean` for the prior synthetic job and canary, with zero database,
  Horizon, or marker deletions;
- `comm-a2ec7be6-f40e-4291-895a-b4a559e8c353` confirmed Horizon inactive,
  and `cexe-a2ec7c1a-750c-429a-a943-18e50d4b00b8` confirmed Horizon
  authorization empty, zero database jobs, zero failed jobs, and zero pending,
  delayed, or reserved `campaigns` work; and
- the sole process remains the full-coverage database worker
  `process-a2ec3303-51b9-43e6-9bd3-97533118bd70`. No Horizon process, job
  dispatch, provider call, voucher issuance, or financial operation occurred.

The cleanup debt is closed. External authenticated `/horizon` browser
acceptance remains behind host commissioning, and financial-lane commissioning
remains a separate gate.

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
