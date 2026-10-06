# Standing Funding Runtime Isolation and Controlled Recommissioning Plan

**Opened:** 2026-10-06

**Status:** Slices 1–10 and disabled Cloud deployment complete on immutable
release `v1.0.103`; live recommissioning remains separately gated

## Implementation checkpoint — 2026-10-06

The package now contains the disabled-by-default runtime control plane:

- provider runtime modes and generation fencing;
- per-address persisted leases, cooldown, quarantine, and ambiguous states;
- append-only synchronization runs;
- shared scheduler/webhook admission and hard backlog ceilings;
- classified failure handling and provider circuit opening;
- preview-first, generation-fenced recovery commands;
- a transactional runtime event outbox;
- replayable x-journal and rescued private-broadcast projections;
- a queued-to-running replay fence and drain-mode continuation for already
  admitted current-generation work;
- sanitized persistence-failure fallback that cannot replace the originating
  provider or database failure;
- a fake-provider recovery lifecycle scenario with a mutation-free rerun.

The existing provider observation, receipt recognition, settlement, wallet,
and Treasury paths remain authoritative. No production runtime was enabled,
no infrastructure was changed, and no live provider or financial operation was
performed by this implementation wave.

Slice 10 closed on 2026-10-07. The initial `v1.0.102` Cloud deployment exposed
a PostgreSQL identifier collision between the generated unique and foreign-key
names for Standing Funding address state. Immutable patch release `v1.0.103`
at `2f9db7b56c05a172e327fd604f059a3358cee0ea` gives both constraints explicit,
distinct PostgreSQL-safe names. Deployment
`depl-a2eb82e3-47fc-48e1-af18-752710ed4f69` then succeeded at host commit
`992366c4050e79c2643a34a5944753dcd509b797`.

The Cloud environment resolves `v1.0.103`, has all four runtime migrations,
and contains zero runtime controls, address states, synchronization runs, or
outbox records. Both environment and effective configuration report scheduled
synchronization as `false`; `netbank` reports disabled and the synchronization
command is absent from the effective scheduler. The first failed deployment's
global migration command also applied the previously pending partner-payment
migration before reaching the runtime migration failure. No provider call,
financial operation, runtime admission, or recommissioning occurred.

Combined focused verification is green at 47 tests / 369 assertions across the
new runtime control plane and the established Standing Funding protocol, with
Pint and strict Composer validation passing. The package-wide Pest wrapper
exceeded its existing 300-second timeout. A diagnostic run across the 717-file
unit tree also surfaced unrelated legacy failures and continued beyond the
bounded release-gate window; neither broad run is claimed as green. The
code-first, disabled-host adoption, and disabled Cloud deployment gates are
closed. Live recommissioning remains open as a separate decision.

## Objective

Make Standing Funding Address synchronization bounded, recoverable, observable,
and safe to recommission before changing Laravel Cloud infrastructure.

The immediate trigger was a transient 500 on `/x/cockpit/pay-codes` in the
`x-change-testing/testing` environment. Laravel Cloud logs attributed the
incident to PostgreSQL memory exhaustion while
`SyncStandingFundingAddressJob` repeatedly processed the six Standing Funding
Addresses. The Cockpit page later recovered and was not the deterministic
source of the failure.

Current environment characterization on 2026-10-06:

- deployed x-change release: `v1.0.95`;
- Standing Funding Addresses: 6;
- scheduled Standing Funding synchronization: disabled;
- queued jobs: 0;
- failed jobs: 0;
- default queue: database;
- default cache: database;
- dedicated Laravel Cloud cache: absent;
- dedicated managed queue: absent;
- PostgreSQL maximum capacity: 0.25 CU.

This plan changes the package-owned control plane first. It does not resize the
database, provision Redis, create a managed queue, enable the schedule, call a
live provider, or move money.

## Root failure mechanism

The existing job already has bounded attempts, progressive backoff, timeout,
uniqueness, overlap prevention, and provider rate limiting. Those controls
bound one queued job, but they do not bound repeated redispatch across scheduler
runs.

`last_checked_at` advances only after a successful synchronization. A terminal
failure therefore leaves the address immediately due. The next scheduler run
may enqueue the same address again. Webhook verification also has an independent
fan-out path. With database-backed queues and cache locks, retries, unique locks,
overlap locks, rate-limit counters, failed-job writes, and web traffic all
compete for the same PostgreSQL resource.

## Non-negotiable invariants

1. A failed run must not make an address immediately eligible forever.
2. At most one current runtime generation may operate on an address.
3. A stale job must exit without calling a provider or changing financial truth.
4. Queue delivery is at least once; financial effects must remain idempotent.
5. An ambiguous provider outcome must be reconciled, never blindly replayed.
6. Runtime recovery must be possible at global, provider, and address scope.
7. A broadcast is an optional invalidation signal, never runtime or financial truth.
8. x-journal is immutable evidence, never the runtime control plane.
9. A journal or broadcast outage must not roll back a committed runtime transition.
10. Database resource exhaustion must not trigger a second mandatory database write.
11. Missing, corrupt, or unreadable runtime control state fails closed.
12. Recommissioning always returns through one canary before broader scheduling.

## Ownership

| Concern | Owner |
| --- | --- |
| Runtime modes, generations, leases, admission, cooldown, circuits, quarantine, run history, and recovery commands | x-change |
| Provider observation and normalization | EMI provider adapter plus existing x-change funding actions |
| Receipt identity, recognition, settlement, suspense, and idempotent financial effects | Existing x-change funding domain |
| Immutable consequential evidence | x-journal through an x-change-owned adapter |
| Realtime operator invalidation | x-change broadcast events on authorized private channels |
| Human notification or escalation delivery | x-feedback, deferred to a separately approved slice |
| Workflow continuation or automated remediation | x-action, deferred; recovery remains operator-authorized |
| Infrastructure capacity, cache, queue, and worker topology | Host deployment; explicitly outside this code-first wave |

## Runtime state model

The provider runtime progresses only through explicit states:

```text
disabled
    -> canary
    -> bounded
    -> scheduled

any active state
    -> draining
    -> paused
    -> circuit_open
```

- `disabled`: no synchronization admission.
- `canary`: one explicitly selected address may be admitted.
- `bounded`: a small configured batch may be admitted manually.
- `scheduled`: the package schedule may admit bounded work.
- `draining`: no new work; already admitted work may finish.
- `paused`: no new work and queued work from older generations becomes stale.
- `circuit_open`: automatic fail-closed state after classified failures.

Every pause, reset, or material mode transition increments a runtime generation.
Every job carries that generation and a lease token. Jobs from older generations
exit without provider activity.

## Persistence model

### Runtime controls

One provider-scoped control record contains:

- provider code;
- current mode and generation;
- hard batch and backlog limits;
- consecutive failure count;
- circuit reason and open-until timestamp;
- last mode transition and actor reference;
- optimistic version.

An absent control record means `disabled`.

### Address synchronization state

One record per Standing Funding Address contains:

- state: `idle`, `queued`, `running`, `cooldown`, `quarantined`, or `ambiguous`;
- current generation and lease token;
- lease expiry and next eligible timestamp;
- consecutive failure count;
- last admitted, started, succeeded, failed, and reconciled timestamps;
- sanitized last failure classification;
- optimistic version.

### Append-only synchronization runs

Every admitted run records:

- immutable run reference;
- provider and address references;
- trigger and runtime generation;
- lease token and attempt number;
- admitted, started, and completed timestamps;
- outcome classification;
- sanitized result counts;
- whether the outcome is definitive or ambiguous;
- correlation and causation references.

Runtime controls answer what may happen next. Append-only runs explain what
happened.

## Admission protocol

Scheduled, webhook, and operator triggers use one
`StandingFundingSyncAdmissionService`.

Admission rejects work when:

- the runtime is disabled, paused, draining, or circuit-open;
- the requested generation is stale;
- the queue backlog meets the configured ceiling;
- the requested batch exceeds the package hard cap;
- the address is inactive, quarantined, ambiguous, cooling down, or not due;
- an unexpired lease exists;
- runtime state cannot be read or updated safely.

Admission atomically claims a lease and records a run before dispatch. A failed
dispatch leaves an expiring lease that can be recovered; it does not create an
unbounded immediate redispatch loop.

Webhook synchronization must use the same admission service. It may identify
candidate addresses, but it may not bypass runtime mode, circuit, lease, batch,
or backlog policy.

## Job execution and failure policy

The job validates its provider, generation, lease token, address status, and run
state before a provider call. A stale or superseded job records a sanitized
terminal outcome and exits successfully.

Failures are classified as:

- `provider_transient`;
- `provider_throttled`;
- `configuration_permanent`;
- `database_resource_exhausted`;
- `database_unavailable`;
- `ambiguous_after_provider_call`;
- `unexpected`.

Provider failures receive escalating address cooldown. A provider-wide
threshold opens the circuit. Database resource exhaustion opens the circuit
immediately when persistence remains possible and otherwise writes a sanitized
critical application log without requiring a second database write.

Terminal failure evidence becomes best effort: try durable package evidence,
fall back to structured application logging, and never throw a second exception
from `failed()`.

## Ambiguous outcome recovery

A provider call may complete while subsequent persistence fails. Such a run is
`ambiguous`, not merely failed.

Recovery must:

1. quarantine that address from ordinary admission;
2. inspect authoritative provider history;
3. reconcile by stable provider transaction identity;
4. reuse existing receipt, observation, Treasury, and wallet idempotency;
5. open suspense when evidence changed or cannot be proven;
6. record a definitive reconciliation outcome before releasing the address.

Tests must prove that webhook and scheduled observation of the same provider
transaction converge on one receipt and one financial effect.

## Event catalog

### Durable x-journal milestones

The following sanitized events are projected to x-journal:

- `standing_funding.runtime.mode_changed`;
- `standing_funding.runtime.circuit_opened`;
- `standing_funding.runtime.circuit_closed`;
- `standing_funding.address.quarantined`;
- `standing_funding.address.released`;
- `standing_funding.sync.resource_exhausted`;
- `standing_funding.sync.terminal_failure`;
- `standing_funding.sync.ambiguous_detected`;
- `standing_funding.sync.reconciled`;
- `standing_funding.runtime.canary_completed`;
- `standing_funding.recovery.command_executed`.

Ordinary queued, running, and cooldown transitions remain in x-change run
history and are not journaled individually.

x-change owns the domain-to-journal mapping. x-journal stays domain-neutral and
stores the resulting generic immutable event with the runtime event reference
as its idempotency key.

### Private broadcasts

Operator-facing invalidation uses one `StandingFundingRuntimeChanged` transport
envelope. The sanitized reason identifies runtime, address, run, and recovery
changes while keeping the client contract narrow.

It uses an authorized, instance-scoped private channel. Its payload contains
only a schema, hashed event reference, sanitized reason, and occurrence time.
It never carries a funding address, account reference, provider payload,
exception message, stack trace, customer identity, balance, or transaction
amount.

The Cockpit treats broadcasts as invalidation signals and reloads the
authoritative read model. Polling and explicit refresh remain fallback paths.

## Runtime event outbox

Runtime transitions and a sanitized outbox record commit together. The existing
x-change outbox convention is reused rather than inventing a direct dual-write.

The projection processor independently tracks:

- journal status, attempts, next attempt, and completion time;
- broadcast status, attempts, next attempt, and completion time;
- sanitized projection failure classification.

Journal projection is idempotent. Broadcast delivery is best effort. A Reverb
failure cannot block journal evidence or runtime progress. Delayed broadcasts
still instruct clients to refresh current state rather than trusting an old
payload.

## Operator recovery commands

Commands are narrow and preview-first:

- `xchange:funding:standing-runtime:status`;
- `xchange:funding:standing-runtime:pause`;
- `xchange:funding:standing-runtime:drain`;
- `xchange:funding:standing-runtime:quarantine-address`;
- `xchange:funding:standing-runtime:release-stale-lease`;
- `xchange:funding:standing-runtime:reconcile-ambiguous`;
- `xchange:funding:standing-runtime:retry`;
- `xchange:funding:standing-runtime:resume-canary`;
- `xchange:funding:standing-runtime:promote`.

Mutation commands require explicit confirmation flags, journal the operator
action through the outbox, and refuse stale runtime generations. No command
offers an unrestricted retry-all or evidence purge.

## Ordered implementation slices

### Slice 1 — Incident characterization

- Prove terminal failure leaves an address due under current behavior.
- Prove the next scheduler run can redispatch the same addresses.
- Characterize webhook fan-out and database-backed lock writes.
- Prove terminal evidence recording can encounter a secondary database failure.

### Slice 2 — Runtime contracts and persistence

- Add enums, immutable DTOs, runtime controls, address state, run history, and
  migrations.
- Default absent runtime state to disabled.
- Add package-neutral repositories and deterministic read models.

### Slice 3 — Admission, generation, and leases

- Add the shared admission service.
- Route scheduled and webhook candidates through it.
- Add generation validation, hard batch caps, backlog gates, cooldown, and
  lease expiry.
- Prove immediate reruns are mutation-free.

### Slice 4 — Failure classification and circuits

- Add exception classification, progressive cooldown, provider circuits, and
  safe terminal evidence fallback.
- Consolidate redundant cache-backed coordination around the persisted lease.
- Preserve existing provider and financial idempotency.

### Slice 5 — Recovery commands and read models

- Add preview-first status, pause, drain, quarantine, stale-lease release,
  ambiguous reconciliation, retry, canary, and promotion commands.
- Add sanitized Cockpit-ready runtime and recovery read models without adding
  mutation controls to Cockpit.

### Slice 6 — Runtime event outbox

- Define the versioned event catalog and payload whitelist.
- Persist outbox events in the same transaction as runtime transitions.
- Prove projection interruption and replay are idempotent.

### Slice 7 — x-journal projection

- Map selected consequential events through the existing x-change journal seam.
- Use stable event references as journal idempotency keys.
- Prove ordinary attempt noise is not journaled.
- Prove journal unavailability does not roll back runtime state.

### Slice 8 — Private broadcasts

- Add a rescued, after-commit, private invalidation envelope.
- Add instance-scoped channel authorization and payload-redaction tests.
- Keep the authoritative read model and polling fallback.

### Slice 9 — Recovery lifecycle scenario

- Exercise disabled, canary, crash, stale lease, stale generation, circuit,
  ambiguous outcome, reconciliation, quarantine, bounded promotion, and a
  mutation-free rerun with fake providers only.
- Prove one receipt and one financial effect under replay.
- Prove `/x/cockpit/pay-codes` remains available throughout the simulated
  runtime incident.

### Slice 10 — Release and host adoption gate

- Run focused funding, queue, journal, broadcast, Cockpit, and lifecycle suites.
- Run Pint, Composer validation, frontend tests where applicable, and build.
- Publish an immutable x-change release only after all code gates pass.
- Adopt the release in the host with synchronization still disabled.

Live canary, schedule enablement, worker changes, Redis, managed queues, and
database resizing remain separate explicitly authorized operational gates.

## Acceptance gates

The code-first wave is complete only when:

- repeated scheduler invocations cannot create an unbounded redispatch loop;
- scheduled and webhook work share one admission policy;
- stale generations and stale leases are deterministic;
- provider and database failures open bounded recovery paths;
- ambiguous outcomes reconcile without duplicate financial effects;
- every recovery mutation is attributable and journalable;
- private broadcasts contain no sensitive or monetary truth;
- journal and broadcast projection replay is idempotent;
- journal, Reverb, and provider failures cannot break Cockpit page reads;
- the lifecycle scenario ends with a mutation-free immediate rerun;
- no automated acceptance calls a live provider or moves money.

## Explicitly deferred

- Laravel Cloud database resizing;
- Redis or another dedicated cache;
- managed queue provisioning;
- worker concurrency changes;
- live NetBank canary;
- schedule enablement;
- x-feedback incident notifications;
- x-action automated remediation;
- Cockpit mutation controls.
