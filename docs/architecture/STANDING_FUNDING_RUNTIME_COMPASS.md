# Standing Funding Runtime Compass

**Last updated:** 2026-10-09

**Current phase:** Slices 1–10, binding-time campaign-rule hardening, the
operator-authorized NetBank canary and bounded fleet observation, and the
campaign-only polling implementation are complete. Campaign polling is released
as immutable `v1.0.113` and commissioned in testing for one campaign binding;
the legacy address remains quarantined

**Runtime posture:** NetBank is `scheduled` at generation 12 in
`x-change-testing/testing` with batch limit one. Only campaign
`01M3CBP94CANQD2JRANFXHQZSE`, binding `3`, address `6` is live. The global
Standing Funding schedule remains disabled, address `5` remains paused by
absent control, and address `4` remains quarantined.

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
11. Reusable campaign payment polling uses a dedicated, explicitly controlled
    dispatcher. It must not enable or reuse the global all-address schedule.
12. Campaign monitoring control is an additional fail-closed gate; missing
    control means paused, and the existing runtime admission remains mandatory.

## Current implementation gate — campaign-only payment monitoring

The approved code-first work is documented in
[Campaign Payment Monitoring Plan](campaign-qr-ph/CAMPAIGN_PAYMENT_MONITORING_PLAN.md).
It adds persisted live/paused control per immutable campaign QR binding, a
campaign-only bounded dispatcher, and compact Cockpit controls. The global
`XCHANGE_STANDING_FUNDING_SCHEDULED_SYNC_ENABLED` flag remains false.

No operational mode transition, provider call, live payment, infrastructure
change, or Cloud deployment is authorized merely by implementing this gate.

### Local implementation checkpoint — 2026-10-09

The campaign-only control plane and dispatcher are implemented locally. Missing
campaign monitoring control fails closed as paused; transitions are generation
fenced; candidate selection excludes account-funding addresses and delegates to
the unchanged Standing Funding admission service. The new scheduler command is
separately named, defaults disabled, checks its own feature flag, and has an
initial batch ceiling of one.

Focused backend verification passed at eight tests / 63 assertions, including
the established global dispatcher regressions. The Cockpit frontend gate passed
27 tests. Runtime remains operationally disabled at generation 9 in testing;
neither the global nor campaign-only schedule has been enabled or deployed by
this local checkpoint.

### Immutable release and disabled local adoption — 2026-10-09

The campaign-only lane is published in `v1.0.113` at `7119b8b1c2` and adopted
by the local sandbox. Disabled-adoption verification caught and corrected a
missing scheduler-registration fence in superseded candidate `v1.0.112` before
any schedule or provider operation ran. A focused regression now proves that a
false campaign switch prevents registration even when Standing Funding
addresses are enabled.

The local host has zero campaign-monitoring controls, both global and campaign
schedule flags resolve false, neither command is registered with the scheduler,
and the new migration is applied without applying two unrelated older pending
migrations. Asset drift is zero across 776 generated inputs and the production
build passes. Testing Cloud subsequently adopted this exact release while
preserving the disabled runtime posture at generation 9; no operational
transition is implied.

### Disabled testing adoption checkpoint — 2026-10-09

Laravel Cloud deployment `depl-a2ef9485-f504-419c-b885-4c8a4828ff6d`
succeeded from exact host commit
`cabaf8c804da808b1997704d112c28821c5adc54`. The runtime contains immutable
x-change `v1.0.113`, and the campaign monitoring migration is recorded as batch
47. No campaign monitoring control exists yet.

Both `XCHANGE_STANDING_FUNDING_SCHEDULED_SYNC_ENABLED` and
`XCHANGE_CAMPAIGN_PAYMENT_SCHEDULED_SYNC_ENABLED` resolve false. Neither the
global nor campaign-only polling command is registered with the scheduler.
NetBank remains `disabled` at generation 9 with batch limit one, zero active
runs, one quarantined address, and no ambiguous addresses. No campaign
activation, provider request, payment, or financial operation occurred.

### Campaign-only commissioning preflight — 2026-10-09

The read-only preflight found zero database jobs, zero failed jobs, and zero
Redis ready/reserved/delayed entries across all inspected queue lanes. It also
found zero active runtime runs, zero campaign monitoring controls, no unsafe
runtime run in the preceding 24 hours, and no undelivered runtime outbox
evidence. All eight historical runtime runs are successful. An immediate
second snapshot remained fully empty.

The live database worker has one process and covers `x-change-funding`,
`x-change-issuance`, `x-change-feedback`, and `default`. Horizon remains
intentionally inactive. Both polling schedules remain disabled and absent from
the scheduler; NetBank remains disabled at generation 9.

The commissioning target is campaign `01M3CBP94CANQD2JRANFXHQZSE`, immutable
binding `3`, Standing Funding address `6`. Address `5` is the earlier supported
campaign and remains paused by missing monitoring control. Address `4` remains
quarantined. No provider or financial activity occurred during preflight.

### Campaign-only commissioning complete — 2026-10-09

Testing deployment `depl-a2ef9f20-ccad-402a-8ae8-7bbdbb6f783b` enabled only
the campaign scheduler with batch size one. The global Standing Funding schedule
remained false. NetBank promoted from disabled generation 9 to scheduled
generation 10, and only binding `3` / address `6` received a live monitoring
control.

The first observer sampled a normal just-dispatched job and conservatively
paused the runtime at generation 11. The job drained successfully with no
failure, overlap, or residue. The runtime resumed at generation 12 after the
observer was corrected to distinguish one fresh in-flight job from backlog or
a stuck lease.

Ten campaign-schedule checks succeeded for address `6` only: four at generation
10 and six at generation 12. The uninterrupted generation-12 observation
covered more than ten scheduler minutes. Expected no-op ticks occurred when the
60-second minimum interval had not elapsed. All checks reported zero newly
observed or applied provider items, and no campaign payment recognition was
created.

The final runtime is scheduled at generation 12 with batch limit one, closed
circuit, zero consecutive failures, zero active or failed jobs, zero Redis
residue, and a fully delivered runtime outbox. Address `6` is idle, address `5`
was untouched, and address `4` remains quarantined. No payment or financial
mutation occurred.

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
| 10 | Immutable release and disabled host adoption | Complete (`v1.0.103`) |
| Live gate 1 | One-address NetBank canary and fail-closed return | Complete |
| Live gate 2 | Bounded NetBank fleet observation | Complete across the initial stop and gate 2B resume |
| Live gate 2A | Address-4 campaign rule investigation and disposition | Complete; address quarantined |
| Live gate 2B | Binding validation, immutable patch, and bounded resume | Complete (`v1.0.104`); addresses 2, 3, 5, and 6 succeeded |

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

Release, disabled-host adoption, and Cloud deployment closure on 2026-10-07:

- initial immutable release `v1.0.102` exposed a PostgreSQL 63-character
  identifier collision during the first Cloud deploy attempt;
- immutable patch release `v1.0.103` points to
  `2f9db7b56c05a172e327fd604f059a3358cee0ea` and assigns distinct explicit
  unique and foreign-key names;
- the host lockfile resolves exactly `v1.0.103` and that commit;
- during local host adoption, only the four Standing Funding runtime migrations
  were applied; the unrelated pending partner-payment migration was not applied
  locally;
- no runtime-control, address-state, synchronization-run, or runtime-outbox rows
  were created during adoption;
- runtime status for `netbank` is disabled with no generation, active run,
  quarantine, or ambiguous address;
- the scheduled synchronization command is absent while the empty outbox
  projector remains scheduled;
- the authenticated Pay Code Explorer Dusk smoke passed with 18 assertions;
- host strict Composer validation passed.
- Laravel Cloud deployment `depl-a2eb82e3-47fc-48e1-af18-752710ed4f69`
  succeeded at host commit `992366c4050e79c2643a34a5944753dcd509b797`;
- Cloud reports all four runtime migrations installed, zero runtime controls,
  address states, synchronization runs, and outbox records, effective
  environment and configuration values of `false`, disabled `netbank` runtime,
  and no standing synchronization schedule;
- the failed first deployment applied the previously pending
  `2026_09_22_000000_create_partner_payment_events_table` migration and the
  runtime-control migration before PostgreSQL rolled back the failing
  address-state migration; the successful retry completed the remaining
  runtime migrations without runtime mutations.

This closes Slice 10 and the disabled Cloud deployment gate.

Live NetBank canary closure on 2026-10-07:

- the operator explicitly authorized one live NetBank canary only;
- address ID `1`, an active `account_funding` address with the most recent
  prior check, was selected to minimize the unobserved interval;
- runtime transitioned from missing/disabled generation 1 to `canary` at
  generation 2 with batch limit 1 and that address as the sole admissible
  target;
- the manual scanner inspected the six due candidates but admitted and queued
  exactly one synchronization run;
- run `01M49M8JJGQJMWY20A2KCQ0DRY` succeeded with 67 already-known settled
  observations, zero newly applied receipts, zero suspense, zero ambiguity,
  and zero failure;
- receipt totals stayed at 67, settled net stayed at 9,127,684 minor PHP units,
  and wallet-effect references stayed at 67, proving no new financial effect;
- queue and failed-job counts returned to zero, with no active, quarantined, or
  ambiguous address;
- runtime was deliberately returned to `disabled` at generation 3 rather than
  promoted to bounded mode;
- an immediate disabled rerun queued zero work;
- both generation-2 and generation-3 mode-change outbox records were delivered
  once to journal and broadcast; and
- effective `scheduled_sync_enabled` remained `false` throughout.

This closes live operational gate 1. It does not authorize bounded mode,
schedule enablement, infrastructure changes, or another provider/financial
operation.

Bounded NetBank observation checkpoint on 2026-10-07:

- the operator explicitly authorized a bounded live NetBank observation;
- the runtime transitioned from disabled generation 3 to bounded generation 4
  while preserving batch limit 1 and scheduled synchronization `false`;
- the plan was to process the five addresses not covered by the preceding
  canary sequentially, stopping on failure, ambiguity, quarantine, or suspense;
- the oldest address, ID `4`, completed run
  `01M49MRC9WZCQ6NP71RM4APDTV` with one suspense result and no applied,
  settled, recognized, awaiting-approval, or failed result;
- the suspense was the replay of the already-known campaign-payment quarantine
  `qualification_rejected / unsupported_rule`; it did not create a new
  provider observation, quarantine, receipt, campaign recognition, or wallet
  effect;
- global totals remained 228 provider observations, 71 receipts, 71 receipt
  wallet-effect references, 11 campaign recognitions, one campaign quarantine,
  and zero funding suspense cases;
- the runtime immediately returned to disabled at generation 5, a disabled
  rerun queued zero work, and all runtime outbox projections were delivered;
- addresses `2`, `3`, `5`, and `6` were not contacted after the stop condition.

Live gate 2 is therefore a controlled partial pass, not a completed fleet run.
The existing campaign qualification quarantine required explicit disposition
before any separately authorized bounded resume.

Address-4 disposition closure on 2026-10-07:

- the immutable binding belongs to the active scenario-run campaign `AUI
  On-Demand Insurance Payment`; it is not a governed workflow publication and
  has zero payment recognitions;
- its fixed PHP 50 binding stored legacy rule keys `payer_applications` and
  `rails`, while production recognition supports only `allowed_rails`,
  `minimum_amount_minor`, `maximum_amount_minor`, and `maximum_payments`;
- `rails` cannot be silently rewritten because the stored canonical provider
  evidence has no settlement-rail value, and `payer_applications` has no
  implemented authoritative observation fact;
- the historical PHP 50 payment therefore remains in its immutable
  `qualification_rejected / unsupported_rule` quarantine and is not
  retroactively recognized;
- generation-fenced recovery quarantined Standing Funding Address ID `4` with
  reason `legacy_campaign_binding_unsupported_rules` while runtime remained
  disabled at generation 5;
- receipt, wallet-effect-reference, campaign-recognition, and campaign-
  quarantine totals remained unchanged; no lease, queued job, failed job, or
  financial effect was created; and
- the quarantine event was delivered through both x-journal and the private
  broadcast outbox projection.

The domain campaign and its immutable binding remain historical evidence. A
future usable campaign QR must use a new revision/binding with supported rules;
the legacy row must not be rewritten or released merely to resume polling.

Binding hardening and bounded-resume closure on 2026-10-07:

- campaign payment QR binding now rejects every rule key outside
  `allowed_rails`, `minimum_amount_minor`, `maximum_amount_minor`, and
  `maximum_payments` before persistence, while recognition retains its
  defense-in-depth check for legacy rows;
- focused validation, standing-protocol, and runtime-recovery verification
  passed at 17 tests / 116 assertions, with Pint and strict Composer validation
  passing;
- immutable release `v1.0.104` points to
  `8deada0aa711200872c8b7298be6e14ff2b20308`; host commit
  `ad7c1bc43f27987cd6348ab6ba1da5eb76d47de4` adopts it, and Laravel Cloud
  deployment `depl-a2ebab9b-cb82-4ab3-86f0-2533f8c017c4` succeeded;
- the runtime moved from disabled generation 5 to bounded generation 6 with
  batch limit 1 and scheduled synchronization still `false`;
- sequential runs for addresses `3`, `2`, `5`, and `6` all succeeded; address
  `4` was never admitted and remained quarantined with reason
  `legacy_campaign_binding_unsupported_rules`;
- provider observations remained 228, receipts 71, wallet-effect references
  71, campaign recognitions 11, campaign quarantines 1, and funding suspense
  cases 0;
- runtime returned to disabled at generation 7, the immediate rerun queued
  zero work, no active or failed jobs remained, and all journal/broadcast
  outbox records were delivered.

Automated acceptance uses fake providers and may not initiate a payment,
provider mutation, wallet credit outside test transactions, or other live
financial operation.

The package remains adopted while runtime mode is disabled. Bounded mode,
schedule enablement, cache/queue isolation, worker changes, database capacity
changes, and any later live provider operation require explicit authorization.

## Next action

Keep the commissioned campaign-only runtime under observation. The next
separate gate is one fresh PHP 50 AUI payment acceptance against campaign
`01M3CBP94CANQD2JRANFXHQZSE`, requiring exactly one recognition, provisional
coverage, settlement envelope, completion Pay Code, the expected SMS journey,
and a mutation-free replay. Keep the global schedule false, address `5` paused,
and address `4` quarantined. Stop on any backlog, failure, overlap, quarantine
growth, ambiguity, duplicate, or database pressure.
