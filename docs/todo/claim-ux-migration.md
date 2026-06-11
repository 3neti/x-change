# Claim UX Compiler Migration Summary (Replacement for claim-ux.md)

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
