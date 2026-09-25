# Claim UX Compiler Migration Summary (Replacement for claim-ux.md)

## Campaign QR Ph completion correction — 2026-09-25

The intended journey comes from voucher instructions through the existing
`ClaimWorkflowResolverContract` / `DefaultClaimWorkflowResolver`. Persisted
records establish actual progress. X-Ray projects presentation; it must not
execute payments, send SMS, or request a policy when a page is read.

The immediate correction is deliberately limited to
`campaign.coverage-completion.v1`. This is the details-after-payment journey,
not ordinary settlement intake followed by payment. Its original template may
contain a rider saying “Continue to payment”; that is not authoritative once
the template has produced a completion Pay Code.

For this journey only:

- Obtain classification through the existing workflow contract, not a second
  set of voucher-type rules.
- Read the immutable completion issuance and linked payment/envelope evidence
  before using paid wording; missing or mismatched evidence is unverified.
- Project details required, processing, ready, or attention states through
  X-Ray's success presentation. Policy readiness requires a recorded result.
- Suppress inherited post-claim rider content and automatic redirects; preserve
  the pre-claim experience. The frontend consumes the server-owned
  `suppress_legacy_rider` flag rather than classifying the voucher again.
- Never offer a second payment action on this completion journey.
- Resolve a `View demo policy` action outside X-Ray, only for an eligible
  recorded demo result and a matching successful claimant session receipt.
  Code possession alone must never disclose the signed applicant-details URL.

### Private result action

The actual form-flow submit path records a receipt only after successful claim
execution and persisted evidence projection. It matches voucher, issuance,
projection, claim, and the server-generated submission idempotency key. A
pre-submit snapshot prevents later replays from creating a fresh receipt.
Receipts expire after 30 minutes and are capped at ten per session.

The success page rechecks the chain and delegates URL eligibility and fixed
expiry to `DemonstrationPolicySummary`. Disabled summaries, missing outcomes,
expired links/receipts, other sessions, and mismatched records produce no action.
Completion responses are private/no-store with no-referrer protection; a response
containing the private action uses encrypted Inertia history. X-Ray contains no
signed URL or applicant fields. Reads do not send SMS or execute a policy request.

Older claims cannot acquire a receipt by revisiting the success page. They retain
the existing signed SMS-link route. The compiled-submit path does not yet carry
the exact persisted submission-key proof and therefore does not mint this receipt;
unifying that path is a separate gate, not an authorization shortcut.

Existing vouchers benefit at read time. No instruction, campaign snapshot,
payment, Treasury, claim, or policy record is rewritten. Onboarding (including
Maker/Checker invitations), campaign officer authorization, ordinary intake,
disbursement, recovery, account funding, and stored-value behavior are unchanged.

The existing Claim-tab preview shares claim compilation, so inherited completion
rider/redirect phases are suppressed there too. A state-selectable preview with
simulated payment/policy records remains a separate follow-up;
this slice does not claim complete preview/runtime outcome parity. Wider journey
centralization is deferred to avoid disturbing working flows.

## Purpose

This document replaces the original Claim UX Compiler Strategy.

The original strategy was written before the Claim Experience Compiler, compiled result contracts, approval contracts, redirect ownership seams, and frontend view models existed.

Most of the architectural migration has now been completed.

The remaining work is no longer centered on extracting logic from ClaimWidget. The remaining work is centered on allowing the compiler to actively drive visible UX behavior.

---

# Part I — Remaining Work

The following items are intentionally left unfinished.

These are activation slices rather than refactoring slices.

## A. Splash Ownership Activation

Current State

```text
Compiler can describe splash ownership.
Claim experience metadata can skip consumed splash.
```

Remaining

```text
Guarantee rider splash and form-flow splash never render twice.
Promote splash ownership to a hard compiler invariant.
Remove remaining fallback splash interpretation.
```

Target

```text
Exactly one splash owner.
```

---

## B. Redirect Ownership Activation

Current State

```text
Compiled redirect metadata exists.
Claim experience redirect metadata is available.
```

Remaining

```text
Make redirect ownership explicit.
Remove redirect inference from UI layers.
Guarantee a single redirect executor.
```

Target

```text
Exactly one redirect owner.
```

---

## C. Countdown Ownership Activation

Current State

```text
Success page understands redirect metadata.
```

Remaining

```text
Drive countdown visibility entirely from compiler output.
Standardize redirect delay handling.
Remove countdown duplication.
```

Target

```text
Consistent redirect countdown behavior.
```

---

## D. Real Approval Provider Integration

Current State

```text
Approval metadata contract exists.
OTP contract exists.
Approval redirect loop exists.
Authorization seam exists.
```

Remaining

```text
Bind real providers.
Bind Paynamics OTP.
Bind payout authorization gate.
Bind manual review workflow.
Bind polling workflow.
```

Target

```text
Approval UI becomes provider-driven.
```

---

## E. Direct Compiled Form Rendering

Current State

```text
Compiled form payload reaches redemption.
ClaimWidget operates against compiled contracts.
```

Remaining

```text
Make compiled form the primary rendering source.
Reduce remaining legacy fallback logic.
Unify payload normalization.
```

Target

```text
ClaimWidget becomes a renderer rather than an interpreter.
```

---

## F. Live Demonstration Scenario

Current State

```text
Compiler path is operational.
Approval loop is operational.
Success hydration is operational.
```

Remaining

```text
Create canonical demo voucher.
Exercise entire compiled journey manually.
```

Target

```text
Rider Splash
→ Compiled Form
→ Approval / OTP
→ Success
→ Countdown
→ Redirect
```

---

# Part II — Completed Work

## Compiler Foundation

Completed

```text
Claim Experience Compiler introduced.
Claim Experience promoted to a first-class contract.
Claim experience payload normalized.
Claim experience ownership model established.
```

---

## ClaimWidget Refactor

Completed

```text
ClaimWidget extraction completed.
View-model based rendering introduced.
Public behavior preserved.
Legacy regressions avoided.
Compiled submission path established.
```

---

## Result Contracts

Completed

```text
Compiled claim result contract established.
Success result hydration established.
Approval result hydration established.
Session transport contract established.
Redirector pattern established.
```

---

## Approval Flow Foundation

Completed

```text
Approval metadata contract established.
Approval page view model established.
Approval action view model established.
Approval OTP submission contract established.
Approval endpoint established.
Approval redirect loop established.
Approval result session hydration established.
```

---

## Authorization Architecture

Completed

```text
ClaimApprovalOtpAuthorizer contract established.
NullClaimApprovalOtpAuthorizer introduced.
SubmitClaimApprovalOtp delegates through authorizer seam.
Provider-specific authorization can now be plugged in.
```

---

## Success / Approval Navigation

Completed

```text
Pending claim results route to approval page.
Completed claim results route to success page.
OTP completion participates in the same routing model.
Success page rehydrates compiled results.
Approval page rehydrates compiled results.
```

---

## Frontend Contracts

Completed

```text
ApprovalMetadataViewModel
ApprovalActionViewModel
ApprovalPageViewModel
ApprovalOtpSubmission
ApprovalOtpSubmitAdapter
SuccessCompiledClaimResult
```

All currently covered by frontend tests.

---

## Test Coverage Achieved

Completed

```text
Compiled claim submission path.
Pending approval path.
Approval OTP path.
Success hydration path.
Approval hydration path.
Redirector path.
Session transport path.
Authorization seam path.
```

---

# Current Status

## Prepaid completion status refresh (2026-09-26, local only)

Only `campaign.coverage-completion.v1` with authoritative `processing` state and
legacy-rider suppression activates bounded presentation polling. The existing
success GET is reused through Inertia partial reloads of `success_presentation`
and `success_action`; no client-side policy URL is constructed. The server's
claim-session receipt remains required to expose a private demo-policy action.

Checks run every five seconds without overlap, pause while hidden, and stop
after two minutes, on errors, on terminal state or when the component unmounts.
Timeout/error offers a manual Check status and SMS fallback. A manual check does
not restart the automatic window. No claim submission or processing retry is
performed by this UI. Normal onboarding, payout and payment handoff do not poll.
Local verification: 133 frontend tests and 12 success-controller tests pass.
Host build/browser acceptance and publication remain the next controlled gate.

The compiler migration is effectively complete.

The system is no longer proving that the compiler can coexist with the legacy flow.

The system is now running through the compiler path while preserving existing user experience.

The next phase is not refactoring.

The next phase is allowing the compiler to actively control visible behavior:

```text
One splash owner.
One redirect owner.
Consistent countdowns.
Provider-driven approval UX.
Direct compiled form rendering.
Canonical demo journey.
```
