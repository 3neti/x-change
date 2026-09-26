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

### Gate 1 journey classifier contract — 2026-09-26

`DefaultClaimWorkflowResolver` remains the single claim-journey classifier and
returns the existing typed `ClaimWorkflowDescriptorData`. It interprets declared
instructions only; persisted payment, claim, or policy records establish progress
after classification. A campaign endpoint, QR scan, amount, voucher type, or rider
message does not select a claim journey.

The classifier applies this precedence:

1. `campaign_coverage_completion` execution;
2. the paired `provider_rejection` and `recovery_pending` recovery markers;
3. campaign officer authorization;
4. onboarding/account provisioning;
5. stored-value activation;
6. `account_funding` claim outcome;
7. `lead_intake` claim outcome;
8. the legacy disbursement default.

Ordinary `default`, live-cash, settlement-envelope, and payable-collection
drivers may use the established disbursement, account-funding, or lead-intake
outcomes. Special drivers accept only their compatible outcomes. The two
`x_change_*_funding` payment-only drivers remain recognized for legacy read-model
compatibility but do not declare a new claim journey. Recovery requires both
markers; one marker alone remains an incomplete legacy signal and does not acquire
recovery semantics.

Unknown drivers, unknown outcomes, and conflicting explicit combinations now
fail closed before the legacy disbursement fallback. Read-only consumers that
surface old unsupported instructions must eventually translate that validation
failure into an attention presentation instead of an HTTP 500; that is part of
the shared-consumer gate, not classifier execution.

### Gate 3a read-only X-Ray adoption — 2026-09-26

`ClaimWorkflowReadModelProjector` is a narrow adapter over the authoritative
resolver. It contains no classification rules. It returns the same typed workflow
descriptor for supported instructions and translates only the resolver's known
validation conflict into a typed `needs_attention` interpretation. Unexpected
exceptions continue to escape.

X-Ray now consumes that interpretation once per persisted voucher. Supported
claimable vouchers use the resolver-owned title, confirmation copy, and intent.
Unsupported historical instructions receive generic attention copy, no claim or
payment action, no Rider stage, and no redirect. `needs_attention` is an x-change
X-Ray status extension; it does not change the voucher lifecycle or claim-surface
operational state.

Mutable claim, authentication, compilation, submission, and execution paths still
call `resolve()` directly and therefore fail closed. Success pages and terminal
issuer audit surfaces are covered by Gate 3b below; broad exception catches remain
prohibited.

The existing Claim-tab preview shares claim compilation, so inherited completion
rider/redirect phases are suppressed there too. A state-selectable preview with
simulated payment/policy records remains a separate follow-up;
this slice does not claim complete preview/runtime outcome parity. Wider journey
centralization is deferred to avoid disturbing working flows.

### Gate 3b historical success and audit adoption — 2026-09-26

Historical success pages now project the workflow interpretation before resolving
campaign display state, claim experience, Rider, payment actions, destinations, or
compiled session results. A supported journey continues through the existing path
unchanged. An unsupported historical journey returns a private/no-store,
no-referrer attention-only payload: no workflow key, Rider, redirect, countdown,
payment/policy/onboarding action, paired-payment flag, destination, or compiled
result is exposed. The underlying driver or conflicting instruction is not shown.

Terminal and issuer claim surfaces use the same projector. Unsupported historical
instructions add a dedicated `claim_workflow_attention` component and skip claim
experience and artwork resolution. `ClaimSurfaceBuilder::suppressActions()` is
sticky: it clears actions already added and prevents later contributors from
reintroducing Open Pay Code or approval actions. Requirement and payout summaries
may remain visible to an authorized issuer because they are read-only evidence;
the unsafe next action does not.

The Vue success page renders `needs_attention` as its own warning state rather
than provider-payout pending. The claim-surface renderer displays the typed label
and message on both terminal and issuer views. Active claim entry, authentication,
claim compilation, receipt authorization, submission, and execution still use the
strict resolver and do not receive this fallback.

Focused local verification covers hostile historical Rider/payment copy, skipped
Rider resolution and external artwork reads, terminal and approval-required issuer
surfaces, sticky action suppression, warning rendering, and all existing valid
success/surface cases. Claim Workflow and X-Ray regression suites remain green.

### Gate 4 Quick Generate Claim-tab parity — 2026-09-26

The existing Quick Generate Claim tab now classifies its temporary preview
voucher through `ClaimWorkflowReadModelProjector`, the same read-only
interpretation used by X-Ray and historical claim surfaces. The preview no
longer performs an independent raw-instruction classification before the
temporary voucher exists. Live compilation remains strict for resolved
workflows; a known unsupported instruction combination produces only an inert
workflow-attention preview.

Simulation state is a preview option, not a voucher instruction. It is never
written to voucher metadata, payment evidence, claim records, settlement
envelopes, or policy results. The manifest identifies it as simulated and
unverified, and all preview safety flags continue to prohibit interaction,
money movement, provider calls, and claim submission. The state is included in
the artifact fingerprint so a processing preview cannot be reused as a ready
preview.

Supported explicit states are journey-scoped:

- intake can show its instruction-default journey or payment outstanding;
- campaign coverage completion can show instruction default, prepaid/details
  outstanding, processing, ready, or processing-needs-attention;
- other journeys retain the instruction-default walkthrough;
- classifier attention always overrides an issuer-selected simulated state.

Coverage completion previews and live success pages use the same
state-to-presentation mapping. Preview-ready copy does not imply that a policy
exists, and its policy action remains visual/inert. The Claim tab labels the
selector and outcome as a simulated, non-live presentation. Existing
engineering-preview JSON, ordinary claim walkthroughs, onboarding,
Maker/Checker, disbursement, recovery, account funding, and stored-value
contracts remain unchanged.

Focused evidence: seven state/projector tests (including exact live/preview copy
equivalence), the preview service and controller suites, live success and X-Ray
regressions, and the Quick Generate/Claim-preview Vue suites. The remaining
gate is final cross-journey browser verification and release/deployment review;
no package publication or Cloud mutation is part of Gate 4 itself.

### Gate 5 local release acceptance — 2026-09-26

The cross-journey acceptance matrix has been exercised locally without changing
financial rules, provider behavior, or historical records. It covers onboarding
and Maker/Checker presentation, campaign officer authorization, ordinary intake,
prepaid completion, disbursement, payout recovery, historical Rider behavior,
X-Ray, terminal claim surfaces, and the Quick Generate Claim preview.

Local evidence:

- 144 of 145 backend tests passed in one combined process (781 assertions).
  The only combined-process failure was a reused x-journal idempotency key in
  the campaign-officer test fixture; that complete suite passed 3 of 3 tests
  (19 assertions) when rerun in isolation. This is recorded as test-order
  contamination, not accepted as product-behavior evidence.
- Seven frontend suites passed: 130 tests covering success destinations,
  redirects, tones, claim surfaces, X-Ray, the Claim preview, and Quick Generate.
- The real-browser success-page matrix passed 8 of 8 cases at 375px and 1440px.
  Processing, ready, ready-without-receipt, and payment-unverified states showed
  no repeat-payment prompt, no horizontal overflow, and no JavaScript errors.
  The private policy action appeared only with an eligible receipt.
- Gate 4's new Claim-tab state selector is covered by package-level frontend
  tests. Browser acceptance in the host remains pending until this package
  revision is published and installed; the current local host still consumes
  x-change v1.0.55.

This completes the local part of Gate 5. Publication, host upgrade, testing-only
deployment, and one fresh authorized paid lifecycle remain separate release
actions. The Cloud lifecycle must confirm the first SMS, details completion,
processing-to-ready presentation, authorized policy action, policy-link SMS,
and absence of duplicate processing before the gate is finally closed.

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
