# Public Auto-Generate Invite-Controlled Beta

Last updated: 2026-10-08

## Decision

Public Auto-Generate is suitable for an **invite-controlled, low-value beta**.
It is not yet approved for unrestricted anonymous public launch.

This decision recognizes the testing-instance evidence through Gate 5A:

- exact provider evidence binds to one eligible funding intent;
- settlement, Treasury consumption, and Pay Code issuance are correlated;
- retries and immediate replay are mutation-free;
- the amount-lease adjustment is contained in a separate Treasury hold instead
  of becoming generally spendable Client Funds; and
- the tested lifecycle created exactly one Pay Code with empty database and
  failed queues.

An infrastructure upgrade improves capacity but does not replace the controls
below. The beta must remain bounded, observable, reversible, and fail-closed.

## Beta boundary

The beta may admit only explicitly invited participants under these limits:

- invite or allowlist authority is checked by the server before payable-order
  creation;
- one invite cannot be used to bypass the active-order and idempotency rules;
- transaction, participant, daily-value, and concurrent-order ceilings are
  configured and enforced server-side;
- a low approved payment ceiling applies to every order;
- no public marketing or unrestricted anonymous order creation occurs;
- the public issuance kill switch remains tested and immediately available;
- an operator observes payment, settlement, issuance, queue, and suspense
  health during the beta window; and
- disabling new entry never strands an already paid order or its recovery
  route.

Read-only discovery and estimates may remain public. An invitation is required
for the first state-changing operation.

## Entry checklist

The beta is not operationally open until every item in this section is green
and linked to retained evidence.

- [ ] Enforce durable, server-side invite or allowlist authorization before
  `POST /x/auto-generate` may create a payable order.
- [ ] Define single-use, expiry, revocation, participant binding, replay, and
  audit semantics for invitations without placing bearer secrets in logs.
- [ ] Configure and test per-order, per-invite, daily-value, daily-count, and
  maximum-concurrent-unpaid-order ceilings.
- [ ] Add Turnstile or an equivalent challenge at payable-order creation.
  Challenge success is abuse-control evidence only and never payment evidence.
- [ ] Prove the public issuance kill switch blocks new orders while paid,
  frozen, and recovery-capable orders remain operable.
- [ ] Commission an operator-owned queue posture with worker-health, backlog,
  retry, failed-job, and oldest-job alerts for every participating lane.
  Database queues are acceptable if deliberately commissioned; Horizon and
  Redis are implementation choices rather than financial truth.
- [ ] Run a bounded concurrency and failure-recovery acceptance covering
  duplicate callbacks, delayed callbacks, provider outage, worker restart,
  database pressure, and recovery after the entry point is disabled.
- [ ] Record the approved beta fund-flow, fee, tax/invoicing, refund,
  complaint, privacy, and regulatory disposition. An invite does not waive
  these obligations.
- [ ] Name the beta operator, incident owner, commercial owner, observation
  window, allowed participants, allowed rails, monetary ceilings, and rollback
  authority.
- [ ] Verify secrets, signing keys, provider credentials, log redaction,
  retention, backups, and recovery evidence for the exact deployment.

## Remaining risks and mitigations

### 1. Contained amount-lease residuals have no final disposition

**Risk:** Gate 5A prevents a lease adjustment from becoming spendable Client
Funds, but its deterministic residual hold remains active. Repeated beta orders
can accumulate small contained balances.

**Beta mitigation:** expose residual hold count, value, and age to operators;
set a hard aggregate residual ceiling; stop new order creation when the ceiling
or maximum age is exceeded; reconcile the position at least daily; and prohibit
manual wallet credits or releases outside an authorized correction workflow.

**Required completion:** Gate 5B must define and prove the append-only release,
refund, fee-recognition, suspense, or other audited final disposition. Gate 5B
is required before unrestricted launch.

### 2. Anonymous abuse resistance is incomplete

**Risk:** basic throttles do not prevent distributed order spam, amount-lease
exhaustion, provider polling pressure, or automated abuse.

**Beta mitigation:** enforce server-side invitations, single-participant and
daily ceilings, one active unpaid order per participant, Turnstile at order
creation, endpoint-specific throttles, expiry cleanup, provider-call budgets,
and a circuit breaker. Reject rather than queue work when a safety ceiling is
reached.

**Required completion:** characterize abuse limits under load and document the
promotion criteria before increasing audience size or transaction ceilings.

### 3. Financial queue operations are not fully commissioned for scale

**Risk:** the database worker completed the accepted lifecycle, but persistent
financial-lane monitoring, backlog alerting, dead-letter handling, and recovery
drills are not yet complete. A larger database does not prevent retry storms or
silent worker loss.

**Beta mitigation:** use an explicitly declared queue topology; cap worker
concurrency; require finite tries, backoff, timeouts, overlap protection, and
idempotency; alert on worker absence, oldest-job age, failed jobs, and backlog;
and keep the financial database worker isolated from optional workloads.

**Required completion:** pass a queue outage and recovery drill. Persistent
Horizon/Redis may be commissioned later, but must never become payment or audit
truth.

### 4. Public-traffic capacity and failure behavior are not characterized

**Risk:** live acceptance proves correctness for a bounded lifecycle, not
capacity under concurrent visitors, provider latency, callback bursts, or
database pressure.

**Beta mitigation:** keep the audience and value caps small; use staged
admission; monitor database memory, connection count, queue age, provider
latency, error rate, and issuance duration; and stop admission automatically
when thresholds are breached.

**Required completion:** execute bounded load, soak, callback-burst, provider
outage, database-pressure, and restart tests against the exact release and
document capacity and service-level objectives before unrestricted launch.

### 5. Commercial and regulatory authority remains environment-specific

**Risk:** successful issuance does not itself authorize customer charging,
fee recognition, tax invoices, custody treatment, refunds, or a public
financial service.

**Beta mitigation:** do not enable a fee or rail beyond its written authority;
publish beta terms and support/escalation routes; keep charge, tax, and invoice
behavior fail-closed; and retain the exact applicable pricing snapshot and
acceptance evidence.

**Required completion:** obtain and record management, finance, tax, and legal
dispositions for the actual beta topology and participant journey.

### 6. Operator visibility is fragmented

**Risk:** account-scoped Pay Code Explorer behavior is correct, but public
orders are issued by the Commercial Principal. Operators need a coherent view
of public orders, intents, evidence, suspense, settlements, residual holds,
issuance, and recovery without bypassing ownership boundaries.

**Beta mitigation:** provide a read-only operational view or retained report
for invited-beta activity, including correlation identifiers and sanitized
status. Do not expose bearer Pay Codes, possession tokens, provider payloads,
or credentials in shared diagnostics.

**Required completion:** add alert-to-record navigation, residual aging,
suspense aging, and end-to-end correlation before audience expansion.

## Required operating metrics

At minimum, the beta operator must be able to observe:

- invitations issued, active, expired, revoked, and consumed;
- payable orders created, awaiting payment, expired, funded, issuing, issued,
  and in attention states;
- provider observations unmatched, mismatched, duplicated, reversed, and late;
- evidence claims, settlements, Treasury holds, residual holds, and Pay Codes;
- queue depth, oldest-job age, retries, failures, worker health, and processing
  latency; and
- aggregate principal, fees, received funds, consumed holds, contained
  residuals, refunds, and unresolved variances.

Metrics and dashboards are operational aids. Provider evidence, Treasury
records, settlement records, and immutable journal evidence remain the
authoritative facts.

## Stop and rollback triggers

Immediately disable new payable-order creation when any of these occurs:

- issuance without exact eligible provider evidence;
- more than one settlement or Pay Code for one order;
- replay changes financial or issuance state;
- money enters generally spendable Client Funds on the successful issuance
  path;
- a residual hold breaches its age or aggregate-value ceiling;
- provider evidence cannot be matched unambiguously;
- queues exceed the approved age/backlog threshold or workers are absent;
- database, cache, provider, or object-storage health crosses the approved
  error threshold;
- invitation enforcement or possession-token isolation fails;
- reconciliation is incomplete or a conservation check fails; or
- the required commercial, legal, privacy, or operational authority is
  withdrawn.

Stopping admission must not delete evidence, cancel a settled order, invent a
refund, or strand a paid order. Recovery follows the existing signed receipt,
evidence, settlement, suspense, and reconciliation contracts.

## Promotion criteria

Promotion from invite-controlled beta to unrestricted public availability
requires:

- every entry item above remains green on the production topology;
- Gate 5B is complete and no residual hold lacks an authorized disposition;
- abuse controls and capacity limits are characterized under load;
- financial queue outage and recovery acceptance is green;
- public commercial and regulatory authority is recorded;
- operational dashboards, alerts, incident response, and support ownership are
  staffed; and
- an immediate no-op/replay acceptance remains mutation-free on the exact
  release proposed for promotion.

## Related sources

- `docs/architecture/public-auto-generate/PUBLIC_AUTO_GENERATE_COMPASS.md`
- `docs/architecture/public-auto-generate/TESTING_INSTANCE_ACCEPTANCE_COMPASS.md`
- `docs/architecture/public-auto-generate/PUBLIC_AUTO_GENERATE_PLAN.md`
- `docs/architecture/QUEUE_OPERATIONS_COMPASS.md`
- `docs/todo/PRODUCTION_BETA_READINESS.md`

