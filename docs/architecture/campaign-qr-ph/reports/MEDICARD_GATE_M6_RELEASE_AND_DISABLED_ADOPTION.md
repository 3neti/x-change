# Medicard Gate M6 — Immutable Release and Disabled Testing Adoption

Date: 2026-10-09

## Disposition

Gate M6 is complete.

Immutable x-change release `v1.0.114` points to exact commit
`6a66711c3c61c7e7b4d1283a79a40f05639b3a73`. The testing host adopted that
release in commit `1ee05e53` with a lockfile-only dependency change.

Laravel Cloud deployment `depl-a2efd6d9-0840-4428-a392-aa57fdccb69c`
succeeded after both schedule variables were explicitly set false:

- `XCHANGE_STANDING_FUNDING_SCHEDULED_SYNC_ENABLED=false`
- `XCHANGE_CAMPAIGN_PAYMENT_SCHEDULED_SYNC_ENABLED=false`

## Verification

Local adoption verification passed:

- Composer installed x-change `v1.0.114` at the exact tagged commit;
- build publication verified all 15 x-change resources;
- both local schedule configuration values resolved false; and
- the production Vite build completed successfully.

The first Cloud verification after the push-triggered deployment identified
that the host campaign-payment schedule still resolved true while the global
Standing Funding schedule resolved false. The queue and failed-job tables were
empty. Both Cloud variables were then explicitly written as false and the exact
host commit was redeployed before the gate was accepted.

After the corrected deployment, two read-only snapshots separated by a full
scheduler interval were identical:

| Fact | Snapshot 1 | Snapshot 2 |
| --- | ---: | ---: |
| Campaign payment recognitions | 13 | 13 |
| Provisional coverages | 13 | 13 |
| Completion Pay Code issuances | 13 | 13 |
| Completion requests | 11 | 11 |
| Completion outcomes | 11 | 11 |
| Feedback delivery records | 42 | 42 |
| Queued database jobs | 0 | 0 |
| Failed jobs | 0 | 0 |

Both snapshots reported x-change `v1.0.114`, exact reference
`6a66711c3c61c7e7b4d1283a79a40f05639b3a73`, and both schedule switches false.

## Safety Boundary

M6 did not create or activate a Medicard campaign. It did not call NetBank or
another provider, accept a payment, send an SMS, issue a new Pay Code, or move
money. The aggregate lifecycle facts remained unchanged after the corrected
disabled adoption.

Gate M7, the private synthetic rehearsal, remains separately authorized. Live
payment monitoring must remain paused during that gate.
