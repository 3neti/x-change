# Medicard Campaign Demonstration Plan

Last updated: 2026-10-09

## Objective

Build a Medicard-branded demonstration on the proven Campaign QR Ph lifecycle
without forking payment recognition, Standing Funding synchronization,
settlement orchestration, completion Pay Code issuance, claim evidence,
feedback delivery, or replay protection.

The demonstration must look and read like a Medicard benefit journey while
remaining explicit that it creates no real HMO membership, healthcare coverage,
letter of authorization, reimbursement right, or provider entitlement.

No implementation, live campaign, payment, or external Medicard integration is
authorized by this document.

## Reference Journey

The accepted AUI flow is the reference implementation:

```text
Reusable campaign QR
    -> provider payment recognition
    -> provisional product record plus settlement envelope
    -> zero-value completion Pay Code
    -> completion SMS
    -> beneficiary details
    -> product completion driver
    -> persisted demonstration outcome
    -> summary SMS and private result page
```

Medicard should reuse that lifecycle. It should not copy the AUI implementation
into a second payment or issuance path.

## Reuse and Isolation

### Reuse unchanged

- reusable Campaign QR provisioning and immutable campaign revision binding;
- campaign-only monitoring controls and global-schedule safety fence;
- NetBank observation and canonical payment recognition;
- exactly-once recognition, coverage, envelope, and Pay Code behavior;
- settlement envelope correlation and completion claim evidence;
- x-feedback delivery, retry evidence, and redacted operator status;
- x-journal evidence and Cockpit campaign activity projections;
- replay/idempotency protections and queue topology; and
- bounded commissioning, stop conditions, and mutation-free replay checks.

### Isolate for Medicard

- campaign title, artwork, rider, and customer-facing copy;
- fixed demo price and displayed benefit description;
- beneficiary input requirements and validation rules;
- first SMS and result-summary SMS templates;
- claim/completion presentation and result page;
- a separate `medicard.demo-benefit@1.0.0` completion driver;
- a separate, versioned, sanitized result contract; and
- Medicard-specific Cockpit labels and progress wording.

The AUI driver and its `policy_issued_demo` result must remain unchanged. The
Medicard driver should return a distinct result such as
`benefit_ready_demo`; it must not claim that a policy or HMO membership exists.

## Demonstration Product Definition

The first planning gate must obtain written decisions for:

| Decision | Required disposition |
| --- | --- |
| Demonstration name | Medicard-approved working title |
| Demo price | Fixed PHP amount; PHP 50 is the recommended rehearsal default |
| Demonstrated benefit | Plain-language benefit being simulated |
| Beneficiary inputs | Minimal fields required to tell the story |
| Result fields | Safe facts shown in the private result page |
| First SMS | Payment received plus personal-details link |
| Second SMS | Demo benefit summary link and disclaimer |
| Branding | Approved logo, colors, and copy assets |
| Retention | How long demo personal data remains available |
| Presenter fallback | Non-financial prerecorded or synthetic fallback path |

The baseline input recommendation is full name, verified mobile destination,
email, birth date, and address. Medical history, diagnosis, symptoms, dependent
records, government identifiers, and other health data are prohibited in the
first demonstration.

## Customer Journey

1. The participant scans a reusable Medicard Campaign QR.
2. The participant pays the fixed demonstration amount.
3. Campaign-only monitoring recognizes the settled payment exactly once.
4. x-change creates one provisional demo-benefit record, one settlement
   envelope, and one zero-value completion Pay Code.
5. The participant receives the first SMS and opens the private claim journey.
6. The participant supplies the approved minimal personal details.
7. The Medicard demonstration driver returns a deterministic mocked result.
8. The result is persisted exactly once and presented as a Demo Benefit
   Summary.
9. The participant receives the second SMS with the private summary link.
10. Cockpit shows the sanitized payment-to-summary progress and evidence.

Every customer surface must display:

> DEMONSTRATION ONLY. This does not create Medicard membership, healthcare
> coverage, a policy, a letter of authorization, or a right to treatment or
> reimbursement.

## Operator Experience

The existing Campaigns page remains the operator entry point:

`https://x-change-testing-testing-uw1gvj.laravel.cloud/x/cockpit/campaigns`

The Medicard campaign card should show only the scan-friendly facts by default:

- campaign status;
- Payment Monitoring status;
- fixed demo amount;
- recognized payments;
- details awaiting completion;
- demo summaries ready; and
- a control to display the payment QR.

Detailed payment, claim, SMS, and completion evidence stays in disclosure
panels. Raw provider payloads, phone numbers, private applicant data, and
external response bodies must not be exposed.

## Controlled Implementation Gates

### Gate M0 — Product and language lock

Status: **Next gate; documentation and fixtures only**

- Agree on the demonstration name, benefit story, fixed price, fields, result,
  SMS wording, branding, disclaimer, and retention policy.
- Confirm that the first demo uses mocked completion and no Medicard API.
- Produce sanitized request/result fixtures for stakeholder review.
- Stop if the proposed language could reasonably be interpreted as real HMO
  membership, coverage, authorization, or reimbursement.

### Gate M1 — Multi-product completion characterization

Status: **Not started**

- Characterize the current AUI completion driver registry and persisted policy
  request/outcome naming.
- Prove a second driver can be selected from immutable campaign configuration
  without changing the AUI result or replay behavior.
- Decide whether the current internal `PolicyCompletion*` persistence names are
  acceptable implementation detail for the demo or need a separately approved
  generic product-completion evolution.
- Make no customer UI or live provider call in this gate.

### Gate M2 — Medicard demonstration contract and driver

Status: **Not started**

- Add the versioned Medicard demo input and sanitized result contract.
- Add `medicard.demo-benefit@1.0.0` as a deterministic mocked completion driver.
- Reject missing, unsupported, or mismatched driver versions fail closed.
- Prove one request and one outcome under retries and concurrent execution.
- Do not integrate with a real Medicard endpoint.

### Gate M3 — Messaging and customer UI

Status: **Not started**

- Add distinct Medicard first-SMS and summary-SMS templates.
- Add the Medicard claim presentation and private Demo Benefit Summary.
- Reuse the existing claim token, completion evidence, feedback queue, and
  redaction boundaries.
- Verify mobile layout, accessibility, expiry, replay, and disclaimer presence.

### Gate M4 — Cockpit presentation

Status: **Not started**

- Add Medicard-oriented campaign progress labels using existing read models.
- Keep monitoring controls compact and reuse the existing QR dialog.
- Show sanitized evidence for payment, Pay Code, details, outcome, and SMS.
- Do not add a second dashboard or a Medicard-specific operations runtime.

### Gate M5 — Automated lifecycle acceptance

Status: **Not started**

- Use fake NetBank and SMS transports.
- Prove exactly one recognition, demo-benefit record, envelope, Pay Code, claim
  projection, completion request, completion outcome, and each SMS intent.
- Prove replay is mutation-free and failures stop closed.
- Run focused backend, frontend, browser, asset-drift, and production-build
  verification.

### Gate M6 — Immutable release and disabled testing adoption

Status: **Not started**

- Publish an immutable x-change release and adopt it in the testing host.
- Apply required migrations, if any, with campaign and global schedules false.
- Verify the campaign is absent or paused and creates no provider or financial
  activity merely by deployment.

### Gate M7 — Private synthetic rehearsal

Status: **Not started; separate authorization required**

- Create a fresh Medicard demo campaign and immutable revision.
- Exercise the whole journey using synthetic provider and SMS boundaries.
- Obtain stakeholder approval of copy, fields, UI, and result presentation.
- Keep live payment monitoring paused.

### Gate M8 — Bounded live dress rehearsal

Status: **Not started; separate financial authorization required**

- Provision a dedicated payment-purpose Standing Funding Address and QR.
- Enable only the Medicard campaign under batch-one monitoring while the global
  schedule remains false.
- Observe a clean bounded window before accepting payment.
- Accept exactly one low-value payment and prove the complete two-SMS journey.
- Prove immediate and later polling replay are mutation-free.
- Pause the campaign after acceptance unless presentation-day monitoring is
  separately authorized.

### Gate M9 — Presentation-day commissioning

Status: **Not started; separate operational authorization required**

- Run a read-only queue, failed-job, runtime, address, and campaign preflight.
- Enable only the approved Medicard campaign shortly before the meeting.
- Keep a tested synthetic fallback ready.
- Monitor payment, queues, SMS evidence, and Cockpit progress during the demo.
- Pause monitoring after the agreed window and record sanitized evidence.

## Acceptance Criteria

The demonstration is ready only when:

- AUI acceptance remains green and unchanged;
- Medicard campaign configuration selects only the Medicard driver and copy;
- payment creates one and only one complete lifecycle;
- both SMS records are accepted and the handset journey is rehearsed;
- every public/customer surface carries the demonstration disclaimer;
- no medical or unnecessary identity data is collected;
- replay creates no duplicate recognition, envelope, Pay Code, outcome, or SMS;
- queues drain to zero with no failed jobs;
- the global Standing Funding schedule remains false; and
- the campaign can be paused without altering immutable historical evidence.

## Stop Conditions

Pause monitoring and stop the rehearsal or presentation on:

- ambiguous, mismatched, or duplicate payment evidence;
- queue accumulation, failed jobs, provider errors, or database pressure;
- a missing or incorrect disclaimer;
- accidental selection of the AUI driver or result contract;
- collection or exposure of unapproved personal or health information;
- a second financial effect or duplicate SMS;
- any unrelated Standing Funding Address being admitted; or
- any UI claim that the demonstration created real Medicard coverage.

## Deferred Real Medicard Integration

A production relationship is a separate program. It would require Medicard's
authoritative API and commercial rules for eligibility, member enrollment,
benefit activation, provider access, authorization, reconciliation, reversal,
support, privacy, security, and audit. None of those capabilities may be
inferred from the mocked demonstration driver.
