# Settlement OS Queue Operations Compass

**Last updated:** 2026-10-07

## North Star

Operators can inspect safe queue throughput, runtime, retries, and failures in
Horizon while every package declares its workload requirements, every critical
lane is explicitly commissioned, and durable PostgreSQL state remains the
authority for financial recovery.

## Current position

**Status: Gate 1 complete and verified; package manifests for adjacent systems are next.**

The testing host uses database queues and database cache. PHP Redis support is
available, but Horizon is not installed, no Redis queue has been commissioned,
and no Horizon background process exists. Scheduled Standing Funding remains
disabled.

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
| 2 | x-journal, x-action, x-feedback, and x-campaign manifests | Planned |
| 3 | Host discovery, Horizon install, and authenticated dashboard | Planned |
| 4 | Laravel Cloud Redis foundation | Separately gated infrastructure change |
| 5 | Database drain and non-financial Redis canary | Pending Gate 4 |
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

**Gate 2 — Adjacent Settlement OS package manifests.**

Inventory queued work in x-journal, x-action, x-feedback, and x-campaign. Add a
versioned manifest to each clean package worktree, preserve current runtime
routing, and fail package tests when a queued job is undeclared. Do not install
Horizon or change host queue infrastructure in this gate.

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
