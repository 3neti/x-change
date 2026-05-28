# Current Claim Journey (Baseline Freeze)

**Document Status:** Baseline Snapshot  
**Purpose:** Freeze and document the current observed claim behavior before introducing the Claim Experience Compiler refactor.

---

# 1. Purpose

This document captures the currently observed claim UI/UX journey as of the latest x-rider hardening phase.

The purpose is:

- preserve current behavior before refactoring
- identify collisions and duplicate ownership
- define current UX sequencing
- establish a regression baseline
- avoid accidental orphan-generating slices during extraction

This document intentionally describes the current behavior exactly as observed, including inconsistencies and regressions.

It is not yet the target architecture.

---

# 2. Current Observed Claim Journey

## Scenario

Voucher characteristics:

```text
- voucher has rider splash
- voucher has rider image/message
- voucher uses voucher-redemption.yaml
- voucher uses form-flow
- voucher redirects to rider URL after success
```

---

# 3. Current Journey Sequence

## Phase 1 — Initial Rider Splash

### Observed Behavior

When the claimant enters the claim flow:

```text
ClaimWidget renders a fullscreen rider splash.
```

### Current Rendering Characteristics

Observed characteristics:

```text
- splash is centered properly
- continue button is centered
- fullscreen presentation looks correct
- rider splash appears visually polished
```

### Likely Ownership

Current likely owner:

```text
ClaimWidget.vue
    +
x-rider runtime
```

### Likely Source

```text
voucher.instructions.rider.splash
```

---

## Phase 2 — Pre-Claim Form

### Observed Behavior

After clicking continue:

```text
claimant is shown the pre-claim form
```

This is the stage where the claimant:

```text
"claims" the voucher code
```

### Observed Content

Current screen includes:

```text
- rider image
- rider message
- pre-claim action button
```

### Current Rendering Quality

Observed:

```text
- looks visually acceptable
- rider content correctly appears
- rider image/message appears sourced from demo.yaml
```

### Likely Ownership

Current likely owner:

```text
ClaimWidget.vue
```

---

## Phase 3 — Duplicate Splash (Regression)

### Observed Behavior

After clicking the pre-claim action button:

```text
another splash page appears
```

### Critical Observation

This splash appears to be:

```text
the same splash URL/content
```

as the original rider splash.

### Current Rendering Characteristics

Unlike the first splash:

```text
- splash stretches to full screen extents
- layout presentation differs
- fullscreen chrome behaves differently
- visual centering is inconsistent
```

### Additional Behavior

Observed:

```text
- splash contains auto-redirect timer
- automatically redirects into form-flow
```

### Current Likely Cause

Most likely current collision:

```text
voucher.instructions.rider.splash
+
voucher-redemption.yaml splash step
=
same splash rendered twice
```

### Current Likely Ownership Collision

Possible ownership overlap:

```text
ClaimWidget.vue
x-rider runtime
form-flow splash step
```

---

# 4. Current Form-Flow Journey

## Phase 4 — Wallet / Bank Form

### Observed Behavior

After auto-redirect:

```text
claimant enters the form-flow data collection sequence
```

Observed fields:

```text
- mobile number
- bank account details
```

### Likely Ownership

```text
form-flow package
```

### Likely Source

```text
voucher-redemption.yaml
```

---

## Phase 5 — Confirmation Screen

### Observed Behavior

After completing the form-flow:

```text
claimant reaches confirmation page
```

Claimant confirms submitted details.

### Likely Ownership

```text
form-flow package
```

---

# 5. Current Redemption Journey

## Phase 6 — Redemption Waiting

### Observed Behavior

After confirmation:

```text
claimant sees redemption waiting screen
```

### Notes

Behavior appears operational.

No major UI issue observed yet.

---

## Phase 7 — Success Screen

### Observed Behavior

Claimant eventually sees:

```text
SUCCESS DEMO: Thank you for claiming.
```

### Current Observed Problems

Observed:

```text
- no visible countdown timer
- redirect still eventually occurs
```

### Important Observation

Redirect behavior still works.

However:

```text
countdown ownership appears unclear
```

---

## Phase 8 — Redirect to Rider URL

### Observed Behavior

Claimant is redirected to rider URL.

### Current Likely Sources

Possible competing redirect sources:

```text
rider runtime redirect
ClaimWidget redirect logic
success page redirect
```

### Current Likely Regression

Observed symptom:

```text
redirect occurs
but countdown does not visibly render
```

This suggests:

```text
redirect execution and countdown rendering may belong to different owners
```

---

# 6. Current Suspected Ownership Collisions

## Collision A — Duplicate Splash Ownership

### Current Symptom

```text
same splash appears twice
```

### Likely Competing Owners

```text
voucher.instructions.rider.splash
voucher-redemption.yaml splash step
```

### Consequence

```text
duplicate intro experience
inconsistent fullscreen layout
confusing claimant journey
```

---

## Collision B — Fullscreen Shell Ownership

### Current Symptom

```text
first splash visually correct
second splash visually stretched
```

### Likely Competing Owners

```text
RiderRuntimeSequencer.vue
RiderStagePresenter.vue
```

Both may be attempting fullscreen layout ownership.

### Consequence

```text
inconsistent visual presentation
duplicated fullscreen wrappers
layout drift
```

---

## Collision C — Redirect Ownership

### Current Symptom

```text
redirect works
countdown missing
```

### Likely Competing Owners

```text
x-rider runtime
ClaimWidget.vue
success page redirect logic
```

### Consequence

```text
countdown rendering detached from redirect execution
```

---

# 7. Current Architectural Observation

The current flow works because:

```text
multiple layers are independently compensating for each other
```

However:

```text
there is no single authority defining the actual claim journey
```

Current layers independently interpret the claim experience:

```text
voucher instructions
ClaimWidget.vue
x-rider runtime
voucher-redemption.yaml
form-flow
success redirect logic
```

This increases regression risk whenever:

```text
new rider runtime behavior is added
new YAML behavior is introduced
frontend rendering changes
redirect handling changes
```

---

# 8. Current Working Components

The following components appear operational:

```text
x-rider runtime rendering
rider splash content
form-flow collection
confirmation flow
redemption execution
redirect execution
```

The current problem is not fundamental execution.

The problem is:

```text
ownership ambiguity
duplicate interpretation
lack of a compiled claim journey
```

---

# 9. Current Risk Assessment

## High Risk Areas

### Duplicate Splash Logic

Most likely current regression source.

### Redirect Logic

Likely fragmented across multiple owners.

### Fullscreen Layout Ownership

Likely duplicated between runtime shell and stage presenter.

### ClaimWidget.vue

Currently acting as:

```text
renderer
resolver
runtime orchestrator
fallback interpreter
redirect owner
rider merger
```

This makes it fragile.

---

# 10. Immediate Refactor Goal

The next architectural step is:

```text
introduce a Claim Experience Compiler
```

The compiler will become:

```text
the single authority that reconciles:
- voucher instructions
- YAML driver output
- rider stages
- form-flow stages
- redirect ownership
- splash ownership
```

Target future state:

```text
compiler owns truth
frontend renders compiled phases
```

---

# 11. Important Preservation Rule

During refactor:

```text
Do not delete current behavior first.
```

Required strategy:

```text
1. Observe existing behavior
2. Compile equivalent behavior
3. Snapshot compiled behavior
4. Switch rendering to compiled behavior
5. Remove old interpretation
```

This prevents:

```text
orphan-generating slices
accidental UX regressions
partial runtime breakage
```

---

# 12. Baseline Summary

Current claim journey is functionally operational but structurally ambiguous.

Primary current issues:

```text
duplicate splash ownership
duplicate fullscreen ownership
unclear redirect ownership
ClaimWidget over-responsibility
```

The system needs:

```text
a compiled claim journey contract
```

rather than additional frontend conditionals.

---

# 13. Baseline Freeze Statement

This document serves as the frozen behavioral baseline before introducing:

```text
ClaimExperienceCompiler
ClaimExperienceData
compiled phase ownership
single splash ownership
single redirect ownership
renderer-only ClaimWidget
```

Future refactors should preserve intentional behavior while eliminating duplicate interpretation layers.
