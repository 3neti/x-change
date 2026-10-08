# Campaign Payment Monitoring Plan

Last updated: 2026-10-09

## Objective

Make reusable Campaign QR Ph payment polling safe and operable for the AUI
demonstration without re-enabling the global Standing Funding Address scanner.

The implementation must let an operator explicitly choose which reusable
campaign QR bindings are monitored. Account-funding addresses, paused
campaigns, legacy unsupported bindings, quarantined addresses, and bindings
without an explicit monitoring control remain excluded.

## Safety Decision

Do not enable `XCHANGE_STANDING_FUNDING_SCHEDULED_SYNC_ENABLED` for this work.
That switch schedules every eligible Standing Funding Address and is too broad
for a campaign demonstration.

The campaign lane is separately configured:

```dotenv
XCHANGE_CAMPAIGN_PAYMENT_SCHEDULED_SYNC_ENABLED=true
XCHANGE_CAMPAIGN_PAYMENT_SCHEDULED_BATCH_SIZE=1
XCHANGE_CAMPAIGN_PAYMENT_SCHEDULED_MINIMUM_INTERVAL_SECONDS=60
XCHANGE_STANDING_FUNDING_SCHEDULED_SYNC_ENABLED=false
```

The host setting only permits the scheduler to run. Persisted runtime control,
campaign monitoring control, address state, campaign status, and binding
eligibility remain independent fail-closed admission gates.

## Operator Experience

Each reusable-payment campaign card receives one compact Payment Monitoring
row:

- `Live`, `Paused`, `Needs attention`, or `Unavailable`;
- last successful check when available; and
- a Manage action.

Manage opens a small dialog rather than adding a permanent dashboard panel.
The existing payment QR dialog receives a readiness strip. It must warn the
operator when the QR can be displayed but background monitoring is not live.

The UI is an operator control and status projection. It does not display raw
provider payloads, account identifiers, payer identity, or financial secrets.

## Persisted Control

Add a package-owned `CampaignPaymentMonitoringControl` associated one-to-one
with an immutable `CampaignPaymentQrBinding`.

Minimum fields:

- binding identifier;
- mode: `live` or `paused`;
- generation;
- transition reason;
- actor type and identifier;
- transitioned timestamp; and
- ordinary timestamps.

Missing control means paused. Generation increments on each effective
transition, allowing stale operator requests and future queued campaign-level
work to fail closed. The immutable QR binding is never rewritten.

Initial testing disposition after deployment:

| Campaign/address | Disposition |
| --- | --- |
| AUI acceptance campaign / address 6 | Explicitly promote to live |
| Earlier supported campaign / address 5 | Paused |
| Legacy unsupported campaign / address 4 | Quarantined |
| Account-funding addresses 1–3 | Outside this scheduler |

## Campaign-Only Scheduler

Add a dedicated command:

```text
php artisan xchange:campaigns:sync-payment-addresses --provider=netbank --limit=1
```

The command selects only bindings whose monitoring control is live and whose:

- campaign is active;
- binding is currently available;
- address purpose is payment;
- provider and address are enabled;
- address is due under the campaign minimum interval; and
- address is not quarantined or ambiguous.

Selection is deterministic: never checked first, then stalest checked, then
address ID. The command delegates admission to the existing
`StandingFundingSyncAdmission`; it does not bypass runtime mode, generation,
backlog, cooldown, lease, quarantine, or circuit rules.

When enabled, the package scheduler runs every minute with `onOneServer()` and
`withoutOverlapping(5)`. The batch ceiling is one for the initial deployment.

## Campaign Pause and Resume

For reusable-payment campaigns:

- Pause atomically pauses payment monitoring and the public campaign before
  returning success. Existing Pay Codes and immutable evidence remain intact.
- Resume reopens the campaign but does not silently make monitoring live unless
  the operator explicitly requests that transition.
- The Manage action may explicitly start or pause monitoring using the current
  monitoring generation as an optimistic concurrency token.

An immediate catch-up check after starting monitoring is optional for the first
release; scheduled polling is authoritative. Draining and QR revocation remain
deferred.

## Implementation Slices

Current checkpoint: Slices 1–5 and the disabled-deployment prerequisite of
Slice 6 are complete. Controlled commissioning and Slice 7 remain gated.

### Slice 1 — Documentation and characterization

Status: **Complete locally**

- Persist this plan and update the campaign and Standing Funding compasses.
- Characterize campaign-only selection and current pause behavior in tests.

### Slice 2 — Monitoring control

Status: **Complete locally**

- Add enum, model, migration, transition action, validation, and owner-scoped
  Cockpit endpoints.
- Prove missing state is paused, transitions are generation-fenced and
  idempotent, and one owner cannot control another owner's campaign.

### Slice 3 — Campaign-only dispatcher

Status: **Complete locally**

- Add configuration, command, and schedule registration.
- Prove only explicitly live campaign-payment addresses are selected.
- Prove account-funding, paused, unavailable, quarantined, ambiguous, and
  unsupported bindings are excluded.
- Prove replay under an active lease queues nothing additional.

### Slice 4 — Cockpit monitoring UX

Status: **Complete locally**

- Add the compact status row and management dialog.
- Add QR readiness warning.
- Use named routes through Wayfinder-generated functions when the host build
  publishes the package routes.
- Verify responsive rendering, no horizontal overflow, and no raw provider data.

### Slice 5 — Release and disabled adoption

Status: **Complete on `v1.0.113`, including disabled testing adoption**

- Run focused package tests, formatter, frontend tests, build, and drift checks.
- Publish an immutable x-change patch.
- Adopt it locally with both campaign and global schedules disabled.

Release-gate verification found that the first immutable candidate,
`v1.0.112`, registered the campaign schedule whenever Standing Funding
addresses were enabled even though the campaign schedule switch was false.
No scheduler or provider operation ran. The missing schedule fence was covered
by a regression and published as corrective immutable release `v1.0.113` at
`7119b8b1c2`. The sandbox host is locked to that release, its package-owned
assets are synchronized, the campaign control migration is applied, both
schedules resolve false and are absent from `schedule:list`, and the control
table is empty.

Testing deployment `depl-a2ef9485-f504-419c-b885-4c8a4828ff6d` succeeded from
exact host commit `cabaf8c804da808b1997704d112c28821c5adc54`. The campaign
monitoring migration is recorded as batch 47, and the control table still has
zero rows. Cloud configuration resolves both schedule switches to false,
neither polling command is registered in `schedule:list`, and NetBank remains
disabled at runtime generation 9 with zero active runs. No campaign was
activated and no provider or financial operation occurred.

### Slice 6 — Controlled commissioning

Status: **Disabled deployment prerequisite complete; commissioning not started**

- Completed: deploy `v1.0.113` to testing with both schedules still disabled
  and verify the same no-schedule posture before any runtime transition.
- Confirm queues and failed jobs are empty.
- Promote the NetBank Standing Funding runtime from disabled generation 9 to a
  separately authorized scheduled generation.
- Mark only the AUI acceptance campaign live.
- Enable only the campaign-payment schedule with batch size one.
- Observe at least ten scheduler cycles with no overlap, retry storm, backlog,
  quarantine growth, or database pressure.

### Slice 7 — Fresh live acceptance

Status: **Not started; separate financial authorization required**

- Under separate financial authorization, pay one fresh PHP 50 AUI premium.
- Require exactly one recognition, one provisional coverage, one settlement
  envelope, one completion Pay Code, the expected two SMS interactions, and one
  demonstration policy outcome.
- Require the immediate replay to be mutation-free.

## Stop Conditions

Stop campaign polling and return the runtime to disabled if any of these occur:

- database or provider resource exhaustion;
- queue backlog above the configured ceiling;
- failed job or repeated provider failure;
- ambiguous provider outcome;
- new quarantine or suspense evidence;
- duplicate recognition, coverage, envelope, Pay Code, or message;
- monitoring a binding without an explicit live control; or
- any account-funding address admitted by the campaign command.

## Deferred Work

- Medicard-specific demonstration workflow;
- scheduled account-funding polling;
- address-4 legacy binding remediation;
- infrastructure scaling;
- multi-provider campaign polling;
- draining mode and provider-side QR revocation; and
- production deployment.
