# Public On-Demand Issuance Testing-Instance Acceptance Compass

**Last updated:** 2026-10-08

## North Star

One testing-instance visitor can fund one low-value public Pay Code instruction
and receive exactly one Pay Code through the commissioned Commercial Principal,
without an Account, wallet, Client Funds balance, scheduled Standing Funding
run, or duplicate issuance.

## Current position

**Status: Gates 0 through 4B complete. Gate 5 proved live issuance but stopped
fail-closed on a one-cent Client Funds residual. Gate 5A containment is
implemented and test-green; immutable publication, host adoption, deployment,
and a fresh live acceptance remain in progress.**

The implementation is deployed on x-change `v1.0.110`, public Auto-Generate and
On-Demand Issuance are configured, fixed-amount QR Ph is configured, and
scheduled Standing Funding synchronization remains disabled. The public
surface forces `full_amount` even though the host-wide authenticated policy is
`shortfall`.

Testing-instance commissioning and HTTPS URL integrity are now ready:

- guarded adoption moved commissioning from `installation_incomplete` to
  `operational` without provisioning infrastructure or performing a financial
  operation;
- the strict doctor passes 37 of 37 checks;
- `GET /x/ready`, `GET /api/x/v1/public-issuance`, and
  `GET /x/auto-generate?amount=25.00&currency=PHP` return HTTP 200;
- discovery is available and emits canonical, estimate, handoff, and pricing
  URLs with HTTPS; and
- scheduled Standing Funding, Horizon processing, and every financial Redis
  lane remain disabled.

Gate 2 repaired the rollback runner to invoke the webhook verification job
through Laravel's container, so the current Standing Funding admission
dependency and later method-injection additions resolve without positional
runner drift. The browser fixture now establishes a matching commissioning
manifest and current agreement acceptance instead of bypassing either gate.

The focused runner suite passed 3 tests with 43 assertions. The combined
runner, webhook verification, read-only issuance, commissioning, and provider
job contract suite passed 43 tests with 271 assertions.

Gate 1 completed live on 2026-10-07 with PHP 25.00 read-only inputs:

- both discovery documents reported the service available, `PHP` supported,
  bounds of PHP 1.00 through PHP 1,000.00, `full_amount` funding, and
  `creates_order: false`;
- the authoritative estimate returned PHP 25.00 principal, PHP 15.00 service
  fees, PHP 40.00 total, `billing_mode: billable`, and
  `creates_order: false`;
- the handoff returned method `GET`, the HTTPS URL
  `/x/auto-generate?amount=25.00&currency=PHP`, the same embedded estimate,
  and `creates_order: false`;
- baseline probe `cexe-a2ecaf9f-0143-45f2-b72d-7407dde929e3` and post-call
  probe `cexe-a2ecb022-0922-4904-93dd-3f504c8186d0` matched exactly for
  vouchers, issuance funding orders, Treasury holds, provider observations,
  receipts, settlements, payment attempts, wallet transactions, transfers,
  wallet count and aggregate balance, database jobs, failed jobs, and Standing
  Funding runs; and
- final strict queue inspection `comm-a2ecb05b-3061-49bd-b238-7786c46bd224`
  remained ready with database queues, Horizon disabled, and no authorized
  queues. Standing Funding remained disabled at generation 7 with zero active
  runs.

Gate 1 created no order, Pay Code, hold, provider observation, receipt,
settlement, wallet effect, queued job, or financial state.

Gate 2 completed live on 2026-10-07:

- immutable x-change `v1.0.108` points to package commit `db6e6a29`; host
  adoption commit `42079024` changed only the x-change lock entry, and Cloud
  deployment `depl-a2ecb8d7-c3a9-4c7c-8915-bb97bb0f1eba` succeeded;
- strict doctor command `comm-a2ecbb6d-afe8-4f40-bc79-2bd81c6b0347`
  remained green at 37 of 37 checks;
- authenticated Cockpit acceptance ran `public_auto_generate_demo` exactly
  once and reported full rollback, zero provider calls, no persisted value,
  same-order replay, one projected Pay Code, and a projected stamp/share
  result;
- pre-run probe `comm-a2ecbc1a-14ec-441f-aeae-56a87def3d07` and post-run
  probe `comm-a2ecbcdb-efdc-4389-a487-3496fa6da798` matched exactly across
  vouchers, funding orders and intents, Treasury holds, provider observations,
  receipts, settlements, simulated transactions, payment attempts, wallets,
  wallet transactions and aggregate amount, transfers, database jobs, failed
  jobs, Standing Funding address states, and Standing Funding runs;
- strict queue inspection `comm-a2ecbceb-ac3e-4ff4-9904-64a7d9bf5003`
  remained ready with the database queue, Horizon disabled, and no authorized
  Redis queues; and
- Standing Funding command `comm-a2ecbcfb-322d-40bf-9040-5c8fa0ffb75d`
  remained disabled at generation 7 with zero active runs and one quarantined
  address. Horizon remained inactive.

Gate 2 retained no order, Pay Code, hold, provider evidence, wallet effect,
queued job, failed job, Standing Funding state, or monetary value.

Gate 3 completed live on 2026-10-07:

- the operator explicitly authorized and ratified exactly one PHP 25.00
  payable order under a PHP 40.00 authoritative-total ceiling;
- the read-only estimate and browser cost review both returned PHP 25.00
  principal, PHP 15.00 service and instruction fees, and PHP 40.00 total;
- the browser submitted `POST /x/auto-generate` once and received the package's
  HTTP 202 payable-order workspace for order
  `01M4B6MS7DA7SMEF1WY76VYHVJ`;
- command `comm-a2ecc377-a1b5-4e4b-a4c5-7cd129679df9` confirmed the order is
  `awaiting_payment`, `full_amount`, PHP 40.00, and bound to NetBank funding
  intent 32 in `awaiting_funds` state;
- the order has no voucher, Treasury hold, acknowledgement, payment evidence,
  match, settlement, issuance, cancellation, expiry, reversal, or attention
  state;
- baseline command `comm-a2ecc057-19c3-4349-bf9d-94e8a1c57612` and post-order
  command `comm-a2ecc2db-d5b4-4bc3-ae71-3c5e447c50eb` differ only by one
  funding order and one funding intent; voucher, Treasury, provider,
  settlement, wallet, transfer, queue, failed-job, and Standing Funding facts
  are unchanged;
- strict queue inspection `comm-a2ecc31b-b2b5-4575-8191-67c9e6d7e2fd`
  remained ready with Horizon disabled and no authorized Redis queues; and
- Standing Funding command `comm-a2ecc33b-c2bf-479b-9c0c-17a36c80dfa1`
  remained disabled at generation 7 with zero active runs and one quarantined
  address.

The browser session and signed recovery capability remain in the controlled
acceptance browser. No possession token, idempotency secret, signed recovery
URL, provider payload, bank credential, or beneficiary secret is persisted in
this evidence.

## Canonical testing URLs

| Surface | URL |
| --- | --- |
| Readiness | `https://x-change-testing-testing-uw1gvj.laravel.cloud/x/commissioning` |
| Recovery checklist | `https://x-change-testing-testing-uw1gvj.laravel.cloud/x/commissioning/checklist` |
| Discovery | `https://x-change-testing-testing-uw1gvj.laravel.cloud/api/x/v1/public-issuance` |
| Estimate | `https://x-change-testing-testing-uw1gvj.laravel.cloud/api/x/v1/public-issuance/estimate` |
| Handoff | `https://x-change-testing-testing-uw1gvj.laravel.cloud/api/x/v1/public-issuance/handoff` |
| Exercise entry | `https://x-change-testing-testing-uw1gvj.laravel.cloud/x/auto-generate?amount=25.00&currency=PHP` |
| Rollback proof | `https://x-change-testing-testing-uw1gvj.laravel.cloud/x/cockpit/campaigns/public-auto-generate-scenario-runner` |
| Pay Code Explorer | `https://x-change-testing-testing-uw1gvj.laravel.cloud/x/cockpit/pay-codes` |

The mutation endpoint is the browser-managed `POST /x/auto-generate`. The
public JSON estimate and handoff endpoints are read-only and cannot issue a Pay
Code.

## Gate status

| Gate | Scope | Status |
| --- | --- | --- |
| 0 | Commissioning recovery and HTTPS URL integrity | Complete: operational, strict doctor 37/37, public surfaces HTTP 200, generated URLs HTTPS |
| 1 | Read-only discovery, estimate, and handoff | Complete: authoritative PHP 25 estimate and HTTPS handoff were mutation-free |
| 2 | Repair and run authenticated rollback-only lifecycle proof; prove queue posture | Complete: immutable repair adopted; authenticated run fully rolled back with unchanged baseline |
| 3 | One bounded PHP 25 payable order | Complete: historical order later expired; it has no Pay Code or retained issuance hold |
| 4 | First real-payment attempt and incident disposition | Complete through Gate 4A isolation repair and Gate 4B append-only correction; expired payment credited to Client Funds without issuance |
| 5 | Fresh exact-payment issuance acceptance | Partial: exactly-once settlement, hold/consume, and issuance passed; Client Funds residual invariant failed; replay not run |
| 5A | Amount-lease residual containment hardening | Implementation test-green; publication, deployment, and live acceptance pending |
| 6 | Claim and redemption | Separately gated; not authorized by Gate 5 |
| 7 | Persistent Horizon commissioning | Separately gated; not authorized by Gate 5 |

## Settled decisions

1. Public `/x/auto-generate` is the exercise surface.
2. Authenticated `/x/cockpit/quick-generate` is not substituted for the public
   exercise because it uses the host-wide funding policy.
3. Public issuance is forced to `full_amount` by the server.
4. The commissioned Commercial Principal is server-bound and cannot come from
   browser input.
5. Discovery, estimate, and handoff remain read-only.
6. Order creation, real payment, claim, and redemption are distinct authority
   gates.
7. Provider evidence is payment truth; acknowledgement and polling are not.
8. `GeneratePayCode` remains the sole issuance authority.
9. Scheduled Standing Funding remains disabled and is not required for public
   issuance.
10. Standing Funding address 4 remains quarantined.
11. Gate 3 payable-order acceptance is complete under the disabled-Horizon,
    database-queue safety posture proven by Queue Operations.

## Invariants

- No Pay Code exists before authoritative settlement.
- No estimate or handoff creates an order or financial state.
- No public request selects the issuer or funding basis.
- No live payment occurs without a separately approved ceiling and rail.
- No duplicate evidence, retry, refresh, or resume creates a second Pay Code.
- No received order-bound amount becomes generally spendable Client Funds
  while issuance is pending.
- No signed recovery or receipt URL is hand-built or persisted in public
  evidence.
- No externally presented URL uses HTTP.
- No Standing Funding schedule is enabled by this exercise.
- No action changes the quarantine of address 4.

## Immediate next gate

**Gate 5A — Publish, deploy, and accept the tested containment repair.**

The package repair is implemented and focused tests are green. A positive
amount-lease adjustment now receives its own deterministic Treasury hold in
Pay Code Reserve, while the primary issuance hold remains exactly the
authoritative required amount. Zero adjustment is a no-op; drift fails and
rolls back; settlement and issuance replay do not duplicate either hold.

The focused containment group passes 4 scenarios with 84 assertions. The
rollback lifecycle and public-surface regressions pass 20 tests with 155
assertions after aligning the runner with container-resolved job dependencies
and authoritative Treasury Client Funds.

Publish and adopt an immutable repair, deploy it under the existing safety
fences, then run one fresh authorized low-value payment. Acceptance requires
zero generally spendable Client Funds residual and an immediate mutation-free
replay. Final residual refund/release policy remains deferred.

## Companion documents

- [Testing-Instance Acceptance Plan](TESTING_INSTANCE_ACCEPTANCE_PLAN.md)
- [Public Auto-Generate Plan](PUBLIC_AUTO_GENERATE_PLAN.md)
- [Public Auto-Generate Compass](PUBLIC_AUTO_GENERATE_COMPASS.md)
- [On-Demand Issuance Funding Plan](../on-demand-issuance-funding/ON_DEMAND_ISSUANCE_FUNDING_PLAN.md)
- [On-Demand Issuance Funding Compass](../on-demand-issuance-funding/ON_DEMAND_ISSUANCE_FUNDING_COMPASS.md)
- [Queue Topology and Horizon Commissioning Plan](../QUEUE_TOPOLOGY_AND_HORIZON_PLAN.md)
- [Queue Operations Compass](../QUEUE_OPERATIONS_COMPASS.md)

Future agents must update this compass after every completed or blocked gate.

## 2026-10-07 Gate 4A — Expired Intent Evidence Isolation Hardening

The first live payment exercise exposed an evidence-isolation defect without
creating a Pay Code, Treasury hold, settlement, wallet credit, queued job, or
other platform financial effect. An expired PHP 40.00 intent inspected the
replacement order's exact PHP 40.01 NetBank observation before the replacement
intent and created the immutable evidence claim even though its amount did not
match. Both intents and the replacement order therefore remained fail-closed
in suspense/review.

The package repair now evaluates provider status, amount, currency,
destination, settlement timestamp, and required payer identity before an
On-Demand Issuance intent may claim evidence. Mismatched or indeterminate
evidence can still create review/suspense facts, but cannot monopolize the
provider transaction. An exact replacement intent continues through the
existing immutable exclusive-claim guard. Existing exact late-payment and
same-transaction duplicate protections remain covered.

The regression reproduces the production ordering: expired PHP 40.00 intent
first, exact PHP 40.01 replacement intent second. It proves the first intent
creates no claim and the replacement becomes the sole claimant. The existing
amount-mismatch expectations now explicitly require zero evidence claims.

Live order, intent, evidence-claim, and suspense records remain untouched.
Gate 4A authorizes repair, immutable release, adoption, deployment, and
read-only verification only. Reassigning claim 11, reconciling either suspense
case, settling funds, issuing a Pay Code, creating another order, or accepting
another payment remains prohibited and requires a later gate.

Adoption evidence:

- immutable release `v1.0.109` points to package commit `bd1c0f05`;
- host commit `ed3d7190` changes only the x-change lock entry;
- deployment `depl-a2ecdfd3-531f-46e9-940d-5d4ac6f29402` succeeded on that
  exact host commit;
- Cloud command `comm-a2ece165-8fca-4a80-8047-e4a83be2dda4` confirmed
  x-change `v1.0.109` at `bd1c0f05`;
- commissioning remained operational and strict doctor command
  `comm-a2ece1a7-28a3-4b1f-8f22-46a6719293f1` passed 37 of 37 checks;
- strict queue command `comm-a2ece1d2-d879-4793-8123-1a9932cfd3e0`
  remained ready with database queues, Horizon disabled, and no authorized
  Redis lanes;
- Standing Funding command `comm-a2ece2b4-be05-48bb-82c0-431fccd3651c`
  remained disabled at generation 7 with zero active runs and one quarantined
  address; and
- read-only probe `cexe-a2ece313-8156-4eb5-9859-c3f336d62629` confirmed
  claim 11 still belongs to intent 32, both suspense cases remain open, both
  intents remain in suspense, settlements remain zero, and neither order has a
  voucher or Treasury hold. The replacement order has since expired through
  normal time-based lifecycle.

Gate 4A is complete. Gate 4B, if authorized, must design and prove a guarded
live disposition for the pre-repair immutable claim and two suspense cases.
No reconciliation or further payment occurred in Gate 4A.

## 2026-10-07 Gate 4B — Authorized Append-Only Disposition

Gate 4B is explicitly authorized for the exact pre-repair records only:

- Funding Evidence Claim 11;
- NetBank observation 2847983 for PHP 40.01;
- mismatched source Funding Intent 32;
- exact replacement Funding Intent 34; and
- suspense cases 1 and 2.

The tested implementation keeps claim 11 immutable and appends a separate
supersession record that identifies intent 34 as the effective owner. The
existing maker-checker reconciliation then settles intent 34. Because the
replacement order expired normally during diagnosis, the established
late-payment rule credits PHP 40.01 to Client Funds, records the terminal
disposition, releases the identifying amount lease, and does not revive the
order, issue a Pay Code, create a Treasury hold, or enqueue issuance.

The correction fails closed unless both orders are expired or cancelled, the
source is a genuine amount mismatch, the target is an exact match, both open
suspense reason codes match the characterized incident, both intents belong to
the same Account/provider/currency, and neither side has prior settlement,
voucher, or Treasury-hold state. The supersession itself is immutable and an
exact reconciliation replay is mutation-free.

Local verification is green for the live-shaped correction, replay,
immutability, active-order rejection, existing reconciliation behavior, and
existing On-Demand Issuance funding behavior.

Gate 4B completion evidence:

- immutable release `v1.0.110` points to package commit `6bc14dbc`;
- host commit `97f97df9` adopted only that package release;
- deployment `depl-a2ecf75d-0449-412d-960e-7f0dcfa237bc` succeeded;
- read-only preflight `cexe-a2ecf901-34f2-40ce-abb5-448be5ad9abd` matched
  claim 11, observation 2847983, intents 32 and 34, cases 1 and 2, expired
  orders 33 and 35, and zero prior supersessions or settlements;
- final fence `cexe-a2ecf957-3782-4651-959e-c851acae42b6` found zero queued
  jobs, zero target-case requests, verified destination evidence, and a PHP
  40.01 Client Funds baseline;
- application `cexe-a2ecf99e-984d-46e4-9fc4-9b6ff8a06bee` created
  supersession 1, reconciliation request 1, and settlement 19;
- inspection `cexe-a2ecf9c5-e2bf-4513-8ee3-610e61066cfe` proved effective
  ownership is intent 34, both cases are resolved, both orders remain expired,
  and Client Funds increased exactly PHP 40.01 to PHP 80.02; and
- replay `cexe-a2ecfa0d-8f2b-4217-a5f7-270546f75099` returned identical
  before/after fingerprints with one supersession, one settlement, and zero
  queued jobs; and
- final safety probe `cexe-a2ecfaf7-20e4-47a4-9ca0-95d422831a64` verified
  `v1.0.110` at `6bc14dbc`, database queues, Horizon disabled with no
  authorized lanes, and scheduled Standing Funding disabled at generation 7
  with zero active runs and one quarantined address.

No Pay Code was issued, no order was revived, no Treasury hold was created,
and no new payment was accepted. Gate 4B is complete.

## 2026-10-08 Gate 5 — Pre-Payment Checkpoint

- Initial preflight `cexe-a2ed7b73-15b9-4bdd-9b8a-48bb9e009d99` rejected the
  existing 1,800-second lifetime before order creation.
- The testing-only lifetime was raised to 7,200 seconds and deployment
  `depl-a2ed7bbc-5196-4dbe-8520-9f8e7a52c416` succeeded.
- Fence `cexe-a2ed7c9d-a9f7-4dcf-ad8a-5c3a2c5c6dad` verified x-change
  `v1.0.110` at `6bc14dbc`, zero jobs and failed jobs, Horizon disabled, no
  authorized Redis lanes, and scheduled Standing Funding disabled at
  generation 7 with zero active runs and one quarantined address.
- Public discovery, estimate, and handoff were read-only. The authoritative
  estimate was PHP 25.00 principal plus PHP 15.00 fees, for PHP 40.00 total.
- Pre-order baseline `cexe-a2ed7d2a-2f62-45c7-ba92-86c0c28791c1` recorded
  voucher, order, intent, provider, claim, settlement, Treasury, wallet, and
  queue counts.
- One browser submission created order `01M4C4PEEKKVKPR5EWKMHY19AK`
  (database id 36) and Funding Intent 35. Amount leasing produced the exact
  PHP 40.01 Bank Transfer instruction.
- Verification `cexe-a2ed7d8a-da50-4916-9fd8-8ae89bac06f0` found the order
  `awaiting_payment`, the intent `awaiting_funds`, expiry
  `2026-10-07T23:35:45+00:00`, and no provider match, evidence claim,
  settlement, Treasury hold, voucher, queued job, or failed job.

Gate 5 is intentionally paused until the operator completes that exact payment
and reports it. No second order or payment is authorized.

## 2026-10-08 Gate 5 — Live Issuance Evidence and Fail-Closed Stop

- Payment was observed within the authorized window. Order 36 reached
  `issued`; intent 35 reached `settled`; observation 2847985, claim 12,
  settlement 20, and voucher 385 are the sole order-bound records.
- Inspection `cexe-a2ed80b2-058c-4799-bca6-527b83c0dfee` proved cardinality
  of one order, one claim, one settlement, and one voucher with zero database
  jobs and zero failed jobs.
- The Treasury hold path reserved PHP 40.00 and activated an allocation from
  zero to PHP 40.00, then consumed PHP 40.00 and returned the allocation to
  zero. Both position operations and both allocation operations are committed.
- The public receipt rendered one Pay Code and its canonical HTTPS claim link.
  The voucher remains unredeemed; claim and redemption were not exercised.
- The PHP 40.01 provider settlement includes the one-cent amount-lease
  adjustment, but the order-bound hold protects only the PHP 40.00
  authoritative issuance requirement. The exact issuer's Client Funds balance
  is now PHP 80.03 versus the documented PHP 80.02 pre-Gate-5 baseline.
  Inspection `cexe-a2ed8173-1c5c-46cf-8b8e-888f67a02ad8` therefore proves a
  PHP 0.01 generally spendable residual.
- This violates the Gate 5 isolation invariant. The acceptance stopped before
  replay, as required. No second order, payment, repair, claim, redemption,
  payout, persistent Horizon process, scheduled Standing Funding run, or
  quarantine change was performed.
