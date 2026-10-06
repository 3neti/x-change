# Standing Funding Runtime Compass

**Last updated:** 2026-10-07

**Current phase:** Slices 1–10 complete; immutable release `v1.0.102` is
adopted by the local host with synchronization disabled

**Runtime posture:** Scheduled synchronization remains disabled. Live canary,
schedule enablement, and Laravel Cloud deployment remain separately gated.

## Mission

Bound Standing Funding Address synchronization, preserve financial
idempotency, recover deterministically from worker/provider/database failures,
and recommission only through an operator-authorized canary progression.

The detailed implementation sequence is in
[Standing Funding Runtime Isolation and Controlled Recommissioning Plan](STANDING_FUNDING_RUNTIME_ISOLATION_AND_RECOVERY_PLAN.md).

## Incident disposition

The transient `/x/cockpit/pay-codes` 500 was a symptom of PostgreSQL memory
exhaustion, not a deterministic Cockpit UI defect. The immediate workload was
repeated execution of `SyncStandingFundingAddressJob` for six addresses.

As characterized on 2026-10-06, the testing environment is running, has no
queued or failed jobs, and has scheduled Standing Funding synchronization
disabled. Its database, queue, cache locks, failed-job evidence, and web reads
still share one 0.25-CU PostgreSQL resource. The incident is mitigated, not
structurally resolved.

## Architectural decisions

1. x-change owns runtime controls, leases, circuits, quarantine, recovery
   commands, run history, and the runtime event outbox.
2. Existing funding observation, receipt, settlement, suspense, Treasury, and
   wallet semantics remain authoritative.
3. x-journal records selected consequential milestones through an x-change-owned
   mapping. It does not own runtime state.
4. Realtime broadcasts are sanitized private invalidation signals. They do not
   carry financial truth.
5. Runtime state and outbox records commit together; journal and broadcast
   projection are independently replayable.
6. Missing runtime control state fails closed as disabled.
7. Recommissioning progresses `disabled -> canary -> bounded -> scheduled` and
   never promotes automatically.
8. Pause or reset increments a runtime generation so older queued jobs become
   harmlessly stale.
9. Ambiguous provider outcomes are reconciled before retry.
10. No infrastructure change is part of the current code-first wave.

## Runtime states

| State | Admission | Meaning |
| --- | --- | --- |
| Disabled | None | Initial and missing-state posture |
| Canary | One explicit address | Recovery proof only |
| Bounded | Small explicit batch | Controlled observation |
| Scheduled | Bounded scheduled work | Requires later operational approval |
| Draining | No new work | Existing current-generation work may finish |
| Paused | None | Operator stop; old generation is invalidated |
| Circuit Open | None | Automatic stop after classified failure |

Address states are `idle`, `queued`, `running`, `cooldown`, `quarantined`, and
`ambiguous`.

## Evidence and visibility

| Evidence | Source of truth | Delivery |
| --- | --- | --- |
| Current runtime posture | x-change runtime-control records | Cockpit read model |
| Per-address coordination | x-change synchronization state | Cockpit read model |
| Attempt history | x-change append-only synchronization runs | Read-only operations view |
| Consequential audit evidence | x-journal | Idempotent outbox projection |
| Live operator refresh | x-change broadcast events | Authorized private channel |
| Broadcast fallback | Authoritative Cockpit read model | Polling/manual refresh |
| Database-unavailable fallback | Sanitized structured application log | Laravel Cloud logs |

## Journal policy

Journal:

- mode and generation changes;
- circuit open/close;
- quarantine/release;
- stale-lease operator release;
- resource exhaustion and terminal failure;
- ambiguous detection and reconciliation;
- canary completion and promotion;
- recovery command execution.

Do not journal ordinary queued, running, or cooldown transitions. They remain in
the append-only run history.

## Broadcast policy

The current transport uses one opaque `StandingFundingRuntimeChanged`
invalidation envelope. Its sanitized reason distinguishes runtime, address,
run, and recovery changes without publishing domain data or creating multiple
client subscriptions.

Use instance-scoped private channels and a versioned payload whitelist. The
client reloads the authorized read model. No broadcast contains an address,
account reference, provider payload, exception text, customer identity,
balance, or amount.

## Slice status

| Slice | Scope | Status |
| --- | --- | --- |
| 1 | Incident regression characterization | Complete |
| 2 | Runtime contracts and persistence | Complete |
| 3 | Admission, generations, and leases | Complete |
| 4 | Failure classification and circuits | Complete |
| 5 | Recovery commands and read models | Complete |
| 6 | Runtime event outbox | Complete |
| 7 | x-journal projection | Complete |
| 8 | Private broadcasts | Complete |
| 9 | Recovery lifecycle scenario | Complete |
| 10 | Immutable release and disabled host adoption | Complete (`v1.0.102`) |

## Recovery playbooks

| Failure | Automatic posture | Authorized recovery |
| --- | --- | --- |
| Provider unavailable | Provider circuit opens | Cooldown, then one canary |
| One poisoned address | Address quarantine | Inspect, reconcile, retry only that address |
| Worker crash | Lease eventually expires | Release/reacquire with generation check |
| Duplicate webhook | Admission rejects duplicate lease | Existing run continues |
| Database resource exhaustion | Stop admission where state can be persisted; log safely otherwise | Recover database, inspect, release stale leases, canary |
| Ambiguous provider outcome | Address becomes ambiguous | Reconcile provider truth before settlement |
| Operator emergency | Pause and increment generation | Old jobs self-discard; resume through canary |
| Backlog above ceiling | Admission closes | Drain before recommissioning |

## Verification boundary

Local implementation closure verification on 2026-10-06:

- combined runtime, scheduler, recovery, outbox, broadcast, and established
  Standing Funding protocol gate: 47 tests, 369 assertions;
- formatter: passed;
- strict Composer validation: passed;
- package-wide Pest run: the Composer wrapper exceeded its existing 300-second
  process limit; a diagnostic run across the 717-file unit tree was stopped
  after it surfaced unrelated legacy failures and continued well beyond the
  bounded release-gate window, so neither broad run is recorded as green.

The combined focused gate includes the pre-existing Standing Funding lifecycle
protocol rather than testing the new control plane in isolation.

Release and disabled-host adoption closure on 2026-10-07:

- annotated immutable release `v1.0.102` points to
  `c5fb539a448714144fe5ce029ea3c4ec419843f0`;
- the host lockfile resolves exactly that release and commit;
- only the four Standing Funding runtime migrations were applied; the unrelated
  pending partner-payment migration was not applied;
- no runtime-control, address-state, synchronization-run, or runtime-outbox rows
  were created during adoption;
- runtime status for `netbank` is disabled with no generation, active run,
  quarantine, or ambiguous address;
- the scheduled synchronization command is absent while the empty outbox
  projector remains scheduled;
- the authenticated Pay Code Explorer Dusk smoke passed with 18 assertions;
- host strict Composer validation passed.

This closes Slice 10. It does not authorize Laravel Cloud deployment, a live
canary, schedule enablement, or any provider or financial operation.

Automated acceptance uses fake providers and may not initiate a payment,
provider mutation, wallet credit outside test transactions, or other live
financial operation.

The package wave may publish and be adopted by a host while runtime mode remains
disabled. Live canary, schedule enablement, cache/queue isolation, worker
changes, and database capacity changes require later explicit authorization.

## Next action

Keep synchronization disabled. The next separate decision is whether to deploy
the adopted release to Laravel Cloud while preserving the disabled environment
flag. A live canary remains a later explicit operational gate.
