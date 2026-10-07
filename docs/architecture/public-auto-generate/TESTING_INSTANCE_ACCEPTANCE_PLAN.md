# Public On-Demand Issuance Testing-Instance Acceptance Plan

**Created:** 2026-10-07

## Objective

Prove, in the `x-change-testing/testing` Laravel Cloud environment, that a
public requester can compose one low-value Pay Code instruction, receive an
authoritative price, create one on-demand funding order, provide authoritative
payment, and receive exactly one Pay Code through the existing
`GeneratePayCode` authority.

This exercise uses public On-Demand Issuance. It does not use scheduled
Standing Funding synchronization to create Pay Codes.

## Verified starting posture

| Fact | Verified value |
| --- | --- |
| Testing base URL | `https://x-change-testing-testing-uw1gvj.laravel.cloud` |
| Adopted x-change release | `v1.0.104` (`8deada0aa711200872c8b7298be6e14ff2b20308`) |
| Host deployment | `depl-a2ebab9b-cb82-4ab3-86f0-2533f8c017c4` |
| Public Auto-Generate configured | `true` |
| On-Demand Issuance configured | `true` |
| Host-wide authenticated funding basis | `shortfall` |
| Public issuance funding basis | forced by the server to `full_amount` |
| Fixed-amount QR Ph configured | `true` |
| Scheduled Standing Funding sync | `false` |
| Standing Funding runtime | disabled at generation 7 |
| Standing Funding address 4 | quarantined; must remain quarantined |
| Commissioning state | `installation_incomplete` |
| Commissioning reason | `installation_manifest_stale` |
| Queue connection | `database` |
| Horizon | not installed or commissioned |

The public discovery endpoint currently returns HTTP 503 and
`/x/auto-generate` redirects to commissioning. This is a real Gate 0 blocker,
not an issuance failure.

The current redirect and JSON `status_url` are emitted with an `http` scheme
behind the Laravel Cloud proxy. The canonical URLs in this plan deliberately
use `https`. Generated external, signed, receipt, recovery, and claim URLs must
be verified as HTTPS before a payable order is created.

Focused verification on 2026-10-07 also found rollback-runner drift. The public
read API and page coverage passed, but the lifecycle runner still calls
`VerifyFundingWebhookReceiptJob::handle()` with three dependencies after the
job gained the fourth `StandingFundingSyncAdmission` dependency. The Cockpit
runner test also receives a commissioning redirect rather than the expected
Inertia page. Gate 2 must not run remotely until both regressions are repaired
and the focused suite is green.

## Route contract

### Primary exercise URLs

| Purpose | Exact testing URL | Effect |
| --- | --- | --- |
| Commissioning status | `https://x-change-testing-testing-uw1gvj.laravel.cloud/x/commissioning` | Read-only commissioning state |
| Commissioning checklist | `https://x-change-testing-testing-uw1gvj.laravel.cloud/x/commissioning/checklist` | Guarded recovery guidance |
| Public service discovery | `https://x-change-testing-testing-uw1gvj.laravel.cloud/.well-known/x-change-service` | Read-only capability discovery |
| Public issuance discovery | `https://x-change-testing-testing-uw1gvj.laravel.cloud/api/x/v1/public-issuance` | Read-only issuance contract |
| Authoritative estimate | `https://x-change-testing-testing-uw1gvj.laravel.cloud/api/x/v1/public-issuance/estimate` | Read-only pricing; creates no order |
| Browser handoff | `https://x-change-testing-testing-uw1gvj.laravel.cloud/api/x/v1/public-issuance/handoff` | Returns a safe browser URL; creates no order |
| Public issuance editor | `https://x-change-testing-testing-uw1gvj.laravel.cloud/x/auto-generate` | Human-reviewed issuance and funding workflow |
| PHP 25 prefill | `https://x-change-testing-testing-uw1gvj.laravel.cloud/x/auto-generate?amount=25.00&currency=PHP` | Prefills only; does not create an order |
| Public pricing | `https://x-change-testing-testing-uw1gvj.laravel.cloud/x/pricing` | Read-only pricing surface |
| Rollback scenario runner | `https://x-change-testing-testing-uw1gvj.laravel.cloud/x/cockpit/campaigns/public-auto-generate-scenario-runner` | Authenticated, rollback-only proof |

The actual order-creation mutation is `POST /x/auto-generate`. It is a
browser-session and CSRF-protected workflow, not a public JSON issuance API.
The estimate and handoff API endpoints do not issue a Pay Code.

### URLs produced during the exercise

These paths contain runtime references and must be taken from the server
response rather than assembled by an operator:

| Purpose | Testing URL shape |
| --- | --- |
| Funding order | `https://x-change-testing-testing-uw1gvj.laravel.cloud/x/auto-generate/funding-orders/{order-reference}` |
| Signed recovery | Server-returned signed URL under `https://x-change-testing-testing-uw1gvj.laravel.cloud/x/auto-generate/recover/{order-reference}` |
| Signed receipt | Server-returned signed URL under `https://x-change-testing-testing-uw1gvj.laravel.cloud/x/auto-generate/receipts/{order-reference}` |
| Claim experience | `https://x-change-testing-testing-uw1gvj.laravel.cloud/x/claim/{pay-code}/experience` |
| Cockpit Pay Code detail | `https://x-change-testing-testing-uw1gvj.laravel.cloud/x/cockpit/pay-codes/{pay-code}` |
| Cockpit distribution | `https://x-change-testing-testing-uw1gvj.laravel.cloud/x/cockpit/pay-codes/{pay-code}/distribution` |

`/x/cockpit/quick-generate` is not the primary URL for this exercise. It is an
authenticated institutional surface and follows the host-wide `shortfall`
policy. The public surface is server-forced to `full_amount` and binds the
commissioned Commercial Principal without accepting an issuer from the
browser.

## Authority and safety boundary

The exercise is divided so that read-only proof cannot silently become a real
payment:

1. Gates 0 through 2 are non-financial and may be run before live-payment
   authorization.
2. Gate 3 creates a payable order but does not prove payment or issue a Pay
   Code.
3. Gate 4 requires a separate explicit authorization that names the maximum
   authoritative total and chosen payment rail.
4. Claiming or redeeming the issued Pay Code is a different exercise and is not
   authorized by this plan.
5. Scheduled Standing Funding synchronization remains disabled throughout.

## Gate 0 — Recover commissioning and URL integrity

1. Capture the deployed release, host commit, database counts, queue depth,
   failed jobs, Standing Funding generation, schedule flag, and address-4
   quarantine before any change.
2. Inspect the stale installation manifest and current configuration
   fingerprint. Do not run a fresh install merely because the fingerprint is
   stale.
3. If the existing System Principal, Commercial Principal, Treasury, and
   installation facts are internally complete, use only the guarded adoption
   path proposed by the commissioning recovery guide:

   ```text
   php artisan x-change:commissioning:adopt --confirm-existing-installation --no-interaction
   ```

4. Treat commissioning adoption as a separately reviewed environment mutation.
   It must not rotate credentials, reprovision principals, change balances,
   enable scheduled synchronization, or contact a provider.
5. Correct or verify trusted-proxy URL generation so every externally returned
   URL is HTTPS. Do not create a payable order while the application returns an
   HTTP status, handoff, recovery, receipt, or claim URL.
6. Require the commissioning state to become `operational`, the discovery
   endpoint to return HTTP 200, and the public editor to return HTTP 200.
7. Recheck that scheduled Standing Funding synchronization is `false`, runtime
   mode is disabled, generation remains 7 unless an explicit recovery action
   changes it, and address 4 remains quarantined.
8. Complete the separately gated Queue Operations and Horizon readiness gates
   through a bounded x-change canary before creating the live payable order in
   Gate 3. The rollback-only proof may be used during that canary, but Redis,
   Horizon, and worker changes remain independently reviewed infrastructure
   operations.

## Gate 1 — Discovery, estimate, and handoff

1. Read both discovery documents and confirm that public issuance is available,
   currency is PHP, and the published bounds contain PHP 25.00.
2. Submit the read-only estimate payload:

   ```json
   {
     "amount_minor": 2500,
     "currency": "PHP"
   }
   ```

3. Record the authoritative principal, fees, and total. Do not calculate fees
   independently.
4. Submit the same payload to the handoff endpoint.
5. Require the returned URL to be HTTPS and to normalize to the PHP 25 public
   editor prefill.
6. Prove that discovery, estimate, and handoff created no funding order, Pay
   Code, Treasury hold, provider observation, receipt, or wallet effect.

## Gate 2 — Rollback-only lifecycle proof

1. Repair the package-owned runner so the webhook verification job is invoked
   through the container or with its complete current dependency contract.
2. Update the browser test fixture to establish the required commissioning and
   agreement posture rather than bypassing those gates.
3. Require the focused public issuance, page, command-runner, and browser-runner
   suite to pass before publishing or adopting a repair release.
4. Deploy the immutable repair while preserving scheduled Standing Funding as
   disabled.
5. Sign in to Cockpit and open the rollback scenario runner.
6. Run `public_auto_generate_demo` once.
7. Require the report to show:
   - full rollback;
   - zero provider calls;
   - zero retained monetary effects;
   - exactly one projected Pay Code within the rolled-back transaction; and
   - exactly-once issuance behavior.
8. Repeat the read-only baseline counts and prove that the environment remains
   unchanged.

Gate 2 is the final gate that may complete without live-payment authorization.

Acceptance evidence, 2026-10-07: x-change `v1.0.108` repaired queued-job
dependency resolution through Laravel's container; the commissioned browser
fixture now records real agreement acceptance; 43 adjacent tests passed with
271 assertions; deployment `depl-a2ecb8d7-c3a9-4c7c-8915-bb97bb0f1eba`
adopted the immutable repair; and one authenticated browser run reported full
rollback, zero provider calls, no persisted value, same-order replay, and one
projected Pay Code. Identical pre/post live probes confirmed no retained
voucher, funding, Treasury, provider, wallet, queue, failed-job, or Standing
Funding mutation. Gate 2 is complete.

## Gate 3 — Create one bounded payable order

1. Obtain explicit approval for order creation and a ceiling for the
   authoritative total. The proposed principal is PHP 25.00.
2. Open the exact PHP 25 prefill URL in a fresh browser session.
3. Review the full instruction and server-calculated total before submitting.
4. Submit once. Preserve the browser session, possession token, order
   reference, idempotency key, and returned recovery URL securely.
5. Require HTTP 202 and one funding order in a payable state.
6. Require no Pay Code yet, no unrestricted Client Funds credit, and no
   Standing Funding synchronization run.
7. Stop if the authoritative total exceeds the approved ceiling or if the
   payment instruction is open-amount, ambiguous, non-PHP, or not bound to the
   order.

Acceptance evidence, 2026-10-07: explicit authority and later ratification
bounded the exercise to one PHP 25.00 principal and a PHP 40.00
authoritative-total ceiling. The live estimate and editor agreed on PHP 15.00
fees and PHP 40.00 total. The browser submitted once and received the HTTP 202
payable workspace for order `01M4B6MS7DA7SMEF1WY76VYHVJ`. The order is
`awaiting_payment` with `full_amount` funding and a PHP 40.00 NetBank intent in
`awaiting_funds`; it has no voucher, hold, payment evidence, settlement, or
issuance state. Pre/post probes differ only by the expected single order and
single intent. Provider, wallet, transfer, queue, failed-job, and Standing
Funding facts are unchanged. The browser session and recovery capability were
preserved without writing their possession token, idempotency secret, signed
URL, or bank details into versioned evidence. Gate 3 is complete. Gate 4
remains separately gated.

## Gate 4 — One separately authorized real payment

1. Obtain explicit approval naming:
   - the maximum authoritative total;
   - Bank Transfer or fixed-amount QR Ph;
   - the payer; and
   - the observation window.
2. Use only the payment instruction issued for the Gate 3 order. Do not reuse a
   Standing Funding QR or a different order's transfer amount.
3. Pay exactly the authoritative amount once.
4. Treat provider evidence—not payer acknowledgement, screenshots, polling, or
   browser state—as payment truth.
5. Observe the order transition through settlement, order-bound Treasury hold,
   issuance resumption, hold consumption, and terminal issuance.
6. Require exactly one Pay Code and the normal claim/share result.
7. Stop on underpayment, excess payment, duplicate evidence, reversal,
   ambiguous evidence, provider error, unexpected Client Funds credit, or any
   second voucher.

## Gate 5 — Operator and beneficiary evidence

Record a sanitized evidence bundle containing:

- release, deployment, and configuration posture;
- order reference and terminal state;
- principal, fees, total, and currency;
- provider evidence reference hash or safe identifier;
- Treasury hold placement and consumption identifiers;
- exactly one Pay Code;
- the HTTPS claim URL;
- Cockpit detail and distribution projections;
- idempotency/replay outcome;
- queue, failed-job, suspense, quarantine, and outbox closure; and
- confirmation that scheduled Standing Funding stayed disabled.

Do not persist raw provider payloads, possession tokens, signed recovery URLs,
bank credentials, or beneficiary secrets in the report.

## Gate 6 — Retry and no-op proof

1. Refresh the terminal order and receipt using the original browser session.
2. Replay only safe application reads and the package-defined idempotent resume
   path. Do not make another provider payment.
3. Prove the same order resolves to the same Pay Code and no additional voucher,
   hold, receipt, provider claim, wallet effect, or journal fact is created.
4. Run an immediate subsequent observation and require it to be mutation-free.
5. Confirm queues and outboxes drain cleanly.

## Gate 7 — Closeout

1. Confirm public issuance remains in the explicitly approved posture.
2. Confirm Standing Funding remains disabled at the scheduler and runtime
   levels.
3. Confirm address 4 remains quarantined.
4. Record the accepted evidence in the companion compass.
5. Treat public production commissioning, schedule enablement, another live
   payment, Pay Code claim, and redemption as separate gates.

## Stop conditions

Stop rather than improvise if:

- commissioning is not operational;
- any external URL is generated with HTTP;
- the rollback runner or its focused browser/command tests are not green;
- Horizon strict topology inspection or its bounded x-change canary is not
  green before live order creation;
- the public route accepts a browser-supplied issuer or funding basis;
- the public workflow does not force `full_amount`;
- estimate or handoff creates persistent financial state;
- the authoritative total exceeds the approved ceiling;
- the provider instruction is not exact and order-bound;
- payment evidence is ambiguous, duplicated, reversed, short, or excessive;
- a Pay Code exists before authoritative settlement;
- more than one Pay Code is created;
- money becomes generally spendable between receipt and issuance;
- scheduled Standing Funding becomes enabled;
- address 4 leaves quarantine; or
- database, queue, outbox, journal, or provider pressure becomes unhealthy.

## Acceptance criteria

The exercise passes only when all of the following are true:

1. Commissioning is operational and all returned external URLs are HTTPS.
2. Discovery, estimate, and handoff are read-only.
3. The rollback lifecycle proof passes with zero provider calls and full
   rollback.
4. One authorized exact payment produces exactly one Pay Code.
5. The Pay Code is available through its HTTPS claim and Cockpit inspection
   URLs.
6. Replay and immediate rerun create no duplicate financial or voucher state.
7. The approved Redis/Horizon topology is healthy, authenticated, sanitized,
   and strict inspection is green.
8. Scheduled Standing Funding remains disabled and address 4 remains
   quarantined.

## Explicitly out of scope

- enabling scheduled Standing Funding;
- releasing Standing Funding address 4;
- infrastructure changes outside the separately approved
  [Queue Topology and Horizon Commissioning Plan](../QUEUE_TOPOLOGY_AND_HORIZON_PLAN.md);
- changing host-wide `shortfall` policy;
- public issuer selection;
- Pay Code claim, redemption, or payout;
- a second live payment; and
- production deployment or commissioning.

## Gate 4A — Expired Intent Evidence Isolation Hardening

1. Reproduce an expired mismatched intent observing replacement-order evidence
   before the exact replacement intent.
2. Require mismatch eligibility evaluation before immutable evidence claim.
3. Prove the expired mismatched intent creates no claim.
4. Prove the exact replacement intent becomes the sole claimant.
5. Preserve exact expired-payment disposition and exact duplicate rejection.
6. Publish and adopt one immutable x-change patch.
7. Deploy with Horizon, Redis financial lanes, and scheduled Standing Funding
   unchanged.
8. Verify the exact package release and safety fences read-only.
9. Stop before live claim reassignment, reconciliation, settlement, issuance,
   another order, or another payment.

Gate 4A repairs future evidence isolation. It does not retroactively mutate the
existing immutable claim or authorize resolution of the two live suspense
cases. That disposition is a separately approved Gate 4B.

## Gate 4B — Append-Only Evidence Claim Correction and Expired-Payment Disposition

1. Preserve the original Funding Evidence Claim without update or deletion.
2. Append one immutable supersession that records the original and effective
   Funding Intent, provider observation, source and target suspense cases,
   reconciliation request, and independent approver.
3. Permit correction only when the original intent is an amount mismatch, the
   replacement intent is an exact match, both issuance orders are terminal,
   both cases are open with the expected reason codes, and neither intent has
   a settlement, voucher, or Treasury hold.
4. Continue through the existing maker-checker reconciliation path.
5. Apply the existing expired-order policy: recognize PHP 40.01 into the
   Account's Client Funds without reviving issuance or creating a Pay Code.
6. Prove replay creates no second supersession, settlement, Treasury posting,
   Account credit, order transition, or queued issuance job.
7. Publish and adopt one immutable x-change patch, deploy it with all queue and
   Standing Funding safety fences unchanged, and verify the exact release.
8. Run a read-only live preflight against claim 11, observation 2847983,
   intents 32 and 34, and suspense cases 1 and 2 before applying the approved
   correction.
9. Stop if any identifier, amount, status, ownership fact, or absence check
   differs from the characterized contract.

Gate 4B does not authorize another order, another payment, Pay Code issuance,
order revival, claim redemption, provider payout, or unrelated reconciliation.

### Gate 4B completion

Gate 4B completed on 2026-10-07 using immutable x-change `v1.0.110`
(`6bc14dbc`), host adoption `97f97df9`, and deployment
`depl-a2ecf75d-0449-412d-960e-7f0dcfa237bc`. The exact-record preflight
passed before the correction. Supersession 1 now provides effective ownership
of claim 11 to intent 34 without altering the original claim. Settlement 19
credited PHP 40.01 to Client Funds under the existing expired-order policy,
resolved cases 1 and 2, and left both orders expired without a voucher,
Treasury hold, or queued issuance job. The immediate approval replay was
mutation-free. This plan authorizes no further order, payment, issuance,
redemption, payout, or reconciliation.

## Gate 5 — Fresh Exact-Payment Issuance Acceptance

Gate 5 is authorized for one new public issuance exercise only:

1. Verify commissioning, the deployed x-change release, database-queue
   posture, zero relevant backlog, disabled Horizon, disabled scheduled
   Standing Funding, generation 7, and the existing quarantine.
2. Run discovery, estimate, and handoff read-only for a PHP 25.00 principal.
3. Stop if the authoritative payable total exceeds PHP 40.99.
4. Require an order and Funding Intent lifetime of at least 60 minutes before
   creating exactly one payable order.
5. Use Bank Transfer only. The operator is the payer. Present the exact amount
   and provider instruction, then stop until the operator reports payment.
6. Observe continuously for at most 30 minutes after payment is reported.
7. Require exactly one immutable provider observation, one effective evidence
   claim, one settlement, one order-bound Treasury hold/consumption path, and
   one Pay Code issued through the existing `GeneratePayCode` authority.
8. Require the order to reach its issued terminal result without placing the
   received amount into generally spendable Client Funds.
9. Replay the same order immediately and compare sanitized financial,
   Treasury, voucher, provider-evidence, queue, and failed-job fingerprints.
10. Record the exact release, order, intent, observation, claim, settlement,
    Treasury operation, voucher, deployment, and replay evidence in the
    compasses.

Stop if the amount, expiry, payer binding, provider evidence, destination,
settlement, Treasury path, queue posture, or exactly-once facts diverge. Gate 5
does not authorize claim, redemption, payout, a second order or payment,
persistent Horizon commissioning, scheduled Standing Funding, or release of a
quarantined address.

### Gate 5 live outcome

Gate 5 reached exactly one settlement, one hold/consumption path, and one Pay
Code. It did not close. The PHP 40.01 collision-safe transfer amount funded a
PHP 40.00 authoritative issuance requirement, and the remaining PHP 0.01 was
left in the issuer's generally spendable Client Funds. The explicit stop
condition therefore applied before replay.

The next proposed slice is **Gate 5A — Amount-Lease Residual Containment
Hardening**. It must first reproduce this behavior in tests, define a
package-owned Treasury disposition for the adjustment, prevent the adjustment
from increasing generally spendable Client Funds, and retain exact matching,
hold, consumption, issuance, and replay idempotency. Publishing, adoption, and
any new live payment remain separately authorized steps.

## Gate 5A — Amount-Lease Residual Containment Hardening

Gate 5A keeps the authoritative issuance hold unchanged and contains any
positive amount-lease adjustment in a second, deterministic Treasury hold:

- the primary hold continues to contain exactly `required_amount_minor` and is
  the only hold consumed by Pay Code issuance;
- the residual hold contains exactly `reconciliation_adjustment_minor` in the
  issuer's Pay Code Reserve position;
- the residual never remains generally spendable Client Funds;
- a zero adjustment creates no residual hold;
- settlement fails and rolls back when the settled amount, expected leased
  amount, or recorded adjustment diverges; and
- deterministic references and idempotency keys make settlement and issuance
  replay mutation-free.

The residual hold is containment, not final commercial disposition. Refund,
release, fee recognition, or other disposition requires a later separately
authorized policy gate.

Before a fresh live acceptance payment, publish an immutable x-change release,
adopt and deploy that exact release, and prove the queue, Horizon, scheduled
Standing Funding, and quarantine safety fences remain unchanged. The live
exercise must then prove zero residual Client Funds and an immediate
mutation-free replay. Claim, redemption, payout, persistent Horizon, scheduled
Standing Funding, and quarantine changes remain excluded.
