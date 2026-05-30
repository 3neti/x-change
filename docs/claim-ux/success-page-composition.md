# Success Page Composition

## Purpose

This document defines the visual composition contract for:

```text
resources/js/pages/x-change/claim/Success.vue
```

The claim success page is no longer a single-purpose success message.

It is a composition surface capable of rendering:

- x-rider success stages
- x-rider success content
- redirect runtime stages
- compiler-driven redirect countdowns
- fallback success messaging

The goal is to allow the Claim Experience Compiler to drive the post-claim experience while preserving a safe fallback experience when no rider content exists.

---

# Architecture

```text
ClaimExperienceCompiler
        ↓
ClaimExperienceData
        ↓
ClaimExperiencePayload
        ↓
ClaimSuccessPageController
        ↓
Success.vue
```

The success page should render what has already been decided by the compiler.

It should not reinterpret claim experience ownership rules.

---

# Visual Regions

The page is composed of independent visual regions.

Each region owns a specific responsibility.

---

## Success Stage Region

```text
data-testid="success-stage-region"
```

Rendered when:

```text
hasSuccessVisualStages === true
```

Purpose:

```text
Render rider-provided success stages.
```

Examples:

- Success messages
- Completion banners
- Success illustrations
- Post-claim instructions
- Thank-you screens

Ownership:

```text
x-rider
```

The success stage region represents the primary success experience when rider stages are available.

---

## Compiled Success Rider Rendering

Success.vue now supports compiler-first rendering for success rider content.

The success page no longer relies exclusively on legacy rider stages.

Instead, it prefers compiled success rider phases when they are available.

---

### Compiled Success Rider Phase

Compiled success rider stages originate from:

```text
claim_experience.phases.success_rider
```

Example:

```text
ClaimExperienceCompiler
        ↓
ClaimExperienceData
        ↓
success_rider
        ↓
Success.vue
```

A compiled success rider phase is eligible when:

```text
phase.key = success_rider

phase.status = active
```

and contains visual stages.

---

### Compiled Success Stage Selection

Success.vue derives compiled success stages from:

```text
success_rider
```

and filters them using:

```text
stage.enabled !== false
```

while excluding redirect-stage execution content.

Only visual success stages participate in success rendering.

---

### Legacy Success Rider Fallback

Backward compatibility remains supported.

When no active compiled success rider phase exists:

```text
success_rider phase absent
```

or:

```text
success_rider phase inactive
```

Success.vue falls back to:

```text
rider.stages
```

using the existing success-stage selection rules.

---

### Rendering Priority

The contract is:

```text
compiled success_rider wins

otherwise

legacy rider success fallback
```

In pseudocode:

```text
compiled success rider available?
        ↓
      yes
        ↓
render compiled success stages

      no
        ↓
render legacy success stages
```

---

### Inactive Success Rider Phases

Inactive compiled phases are ignored.

Example:

```text
status = skipped
status = completed
status = disabled
```

must not suppress legacy success rider rendering.

The fallback path remains available.

---

### Success Rider Ownership

Success rider ownership belongs to:

```text
Success.vue
```

not:

```text
ClaimWidget.vue
```

This follows the ownership boundary established elsewhere:

```text
ClaimWidget
    → rider_intro
    → runtime
    → redirect

Success.vue
    → success_rider
    → redirect countdown
    → redirect completion experience
```

---

### Product Decision

Compiled success rider rendering is considered:

```text
compiler-first
```

rather than:

```text
legacy-first
```

The compiler becomes the authoritative source of post-claim rider experiences.

Legacy rider stages remain a compatibility layer during migration.

---

### Test Coverage

Covered by:

```text
tests/frontend/Success.redirect-countdown.test.ts
```

Key assertions include:

```text
prefers compiled success_rider stages over rider success fallback stages

falls back to rider success stages when compiled success_rider phase is absent

ignores inactive compiled success_rider phase and falls back to rider success stages
```

---

## Redirect Countdown Region

```text
data-testid="redirect-countdown-region"
```

Rendered when:

```text
hasRedirect === true
```

Purpose:

```text
Inform the claimant that navigation will occur shortly.
```

The countdown must navigate through:

```text
redirectEndpoint
```

and never directly to the raw rider URL.

This ensures:

```text
Claim Success Page
        ↓
Redirect Gate
        ↓
Final Destination
```

rather than:

```text
Claim Success Page
        ↓
Raw Rider URL
```

Ownership:

```text
claim-widget
```

as determined by:

```php
ClaimExperiencePayload::isClaimWidgetRedirect(...)
```

---

## Fallback Success Region

```text
data-testid="fallback-success-region"
```

Rendered when:

```text
no success stages
no rider message
no redirect countdown
```

Purpose:

```text
Provide a safe default success experience.
```

Examples:

```text
Disbursed to your account
Pay Code claimed
Your claim is being processed
```

The fallback region ensures that the page remains usable even when no rider experience has been configured.

---

# Product Decision

## Success Stages Do Not Suppress Redirect Countdown

The project explicitly adopts:

```text
Option A — Render Both
```

When success stages and a redirect countdown are both present:

```text
success-stage-region
        +
redirect-countdown-region
```

both must render.

Reasoning:

```text
Success stages communicate outcome.

Redirect countdown communicates continuation.
```

These concerns are complementary rather than mutually exclusive.

---

# Composition Rules

The page should be composed using independent regions.

Correct:

```text
success-stage-region      may render

redirect-countdown-region may render

fallback-success-region   renders only when both are absent
```

Incorrect:

```text
success-stage-region
else redirect-countdown-region
else fallback-success-region
```

The success page is a composition surface, not a mutually-exclusive state machine.

---

# Redirect Runtime Stages

The page may also render:

```text
RiderRuntimeSequencer
```

when redirect runtime stages exist.

Purpose:

```text
Allow x-rider to own redirect-stage execution.
```

Examples:

- Timed redirects
- Final notices
- Runtime actions
- Redirect workflows

Ownership is determined by the rider runtime rather than the claim widget.

---

# Ownership Model

The success page respects ownership decisions produced by the compiler.

Examples:

```text
splash_owner   = x-rider
redirect_owner = claim-widget
```

These values refer to subsystem ownership.

They do not refer to:

```text
voucher owner
user owner
account owner
```

Ownership indicates which subsystem is responsible for rendering or executing a particular portion of the experience.

---

# Test Coverage

Covered by:

```text
tests/frontend/Success.redirect-countdown.test.ts
```

Key assertions include:

```text
renders success rider stages inside a dedicated success stage region

renders redirect countdown inside a dedicated countdown region

renders fallback success region only when there are no success stages and no countdown

renders success visual stages together with redirect countdown when both exist

does not let success visual stages suppress redirect countdown

passes redirectEndpoint to RiderCountdown instead of raw rider url

passes the redirect gate endpoint to RiderCountdown for final navigation
```

---

# Future Direction

The long-term goal is:

```text
Claim Experience Compiler
        ↓
ClaimExperienceData
        ↓
compiled success_rider
        ↓
Success.vue
```

where Success.vue becomes primarily a renderer and no longer contains business decisions about ownership, splash policy, redirect policy, or experience sequencing.

Those decisions belong to the compiler and its associated contract.
```
:::
