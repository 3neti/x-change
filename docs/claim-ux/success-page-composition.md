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
Success.vue
```

where Success.vue becomes primarily a renderer and no longer contains business decisions about ownership, splash policy, redirect policy, or experience sequencing.

Those decisions belong to the compiler and its associated contract.
```
:::
