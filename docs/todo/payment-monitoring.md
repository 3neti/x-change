# Payment Monitoring — Paused Workstream TODO

Status: paused after the five-attempt Laravel Cloud testing canary.

## Immediate Resume Gate

- [ ] Create several payable QR attempts inside one monitoring window.
- [ ] Pay at least two attempts without using **Check payment status**.
- [ ] Leave one attempt unpaid through expiry and grace.
- [ ] If provider behavior permits, make two payments against one reusable QR
  and prove both provider transactions remain independently observable.
- [ ] Confirm every provider transaction creates exactly one immutable
  observation and status history.
- [ ] Confirm each eligible Pay Code settles at most once and no Client Funds,
  Treasury position, or compatibility balance is credited twice.
- [ ] Confirm the queue drains with zero failed jobs and no renewed PostgreSQL
  memory pressure.

## Controlled Expansion

- [ ] Increase the testing batch only after the immediate gate passes:
  `5 -> 10 -> 25`.
- [ ] Record response time, provider-call count, queue latency, observation
  count, and failed-job count at each step.
- [ ] Exercise provider throttling, timeouts, empty histories, malformed
  responses, and recovery after a temporary provider failure.
- [ ] Add operator-visible monitor health, last-monitor timestamp, last
  observation timestamp, and stale/degraded state without exposing payer PII.

## Host Rollout

- [ ] Keep x-PayOut and every production-like host disabled until the testing
  expansion is accepted.
- [ ] Enable one x-PayOut canary with the smallest batch and verify the same
  lifecycle independently.
- [ ] Require an explicit production disposition before enabling automatic
  monitoring in a real production environment.
- [ ] Document rollback: disable scheduled monitoring without deleting
  observations, attempts, collections, Treasury records, or journal evidence.

## Non-Negotiable Safety Checks

- [ ] Payment discovery remains provider-evidence collection, not payment
  application semantics.
- [ ] Settlement readiness and authorization continue to fail closed.
- [ ] Observations remain append-only and idempotent by provider transaction.
- [ ] Retry or recovery never repeats provider verification unnecessarily and
  never repeats financial application.
- [ ] Expired QR attempts stop being monitored after the configured grace.
- [ ] Payer contact and account evidence remains encrypted and excluded from
  ordinary logs, queue payloads, and public events.

## Pause Snapshot

Recorded on 2026-09-23:

- testing monitoring enabled;
- scheduled batch size: `5`;
- pending jobs: `0`;
- failed jobs: `0`;
- immutable observed payments: `1`;
- proven scheduled lifecycle: Pay Code `8DPZ`, ₱60.00;
- production/x-PayOut monitoring: not enabled.

