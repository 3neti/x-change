# Compiler-Driven Redirects

## Overview

The claim experience now uses a compiler-driven redirect model.

Instead of allowing frontend pages or rider definitions to directly control post-claim navigation, redirect behavior is compiled into a structured Claim Experience and executed through a dedicated redirect gate.

This provides:

- A single source of truth for redirect behavior.
- Consistent redirect ownership rules.
- Protection against duplicate countdowns.
- Future support for policy-based redirects.
- Testable separation between experience compilation and navigation execution.

---

# Architecture

```text
Voucher Instructions
        │
        ▼
ClaimExperienceCompiler
        │
        ▼
ClaimExperience
        │
        ├── phases
        ├── options
        └── diagnostics
                │
                ▼
ClaimSuccessPageController
                │
                ▼
Success.vue
                │
                ▼
RiderCountdown
                │
                ▼
ClaimRedirectController
                │
                ▼
SuccessRedirectResolver
                │
                ▼
External URL
```

---

# Claim Experience Contract

The claim experience is represented by two distinct layers:

## ClaimExperienceData

```php
ClaimExperienceData
```

This is the normalized domain contract produced by:

```php
ClaimExperienceCompiler
```

The compiler is responsible for determining:

- Entry mode
- Splash ownership
- Redirect ownership
- Countdown behavior
- Duplicate splash prevention
- Experience phases
- Diagnostics

Controllers and frontend components should not reimplement these decisions.

The compiler is the single source of truth.

---

## ClaimExperiencePayload

```php
ClaimExperiencePayload
```

This is the adapter layer that provides a stable API for reading, writing, and deriving behavior from the contract.

Examples:

```php
ClaimExperiencePayload::fromState($state);

ClaimExperiencePayload::putIntoInstructions(
    $instructions,
    $experience,
);

ClaimExperiencePayload::redirect($experience);

ClaimExperiencePayload::isClaimWidgetRedirect($experience);

ClaimExperiencePayload::isXRiderSplash($experience);

ClaimExperiencePayload::shouldSkipConsumedSplash($experience);
```

The payload helper exists so that controllers, tests, and future integrations do not need to understand the internal storage shape of the contract.

---

## Storage Location vs Contract

The claim experience is currently persisted inside Form Flow instructions:

```php
instructions.metadata.claim_experience
```

This location is an implementation detail.

Consumers should not depend on the storage path directly.

Instead:

```php
ClaimExperiencePayload::fromState($state)
```

should be used to retrieve the contract.

This allows the storage strategy to evolve without requiring controller or frontend changes.

The conceptual contract is:

```text
ClaimExperienceData
        ↓
ClaimExperiencePayload
        ↓
Controllers / Tests / Frontend
```

and not:

```text
instructions.metadata.claim_experience
```

The metadata path is merely where the contract is currently stored.

---

# Redirect Ownership

Only one component may own the redirect experience.

The compiler determines ownership and exposes it through:

```php
claim_experience.options.show_redirect_countdown
claim_experience.diagnostics.redirect_owner
```

Example:

```php
[
    'options' => [
        'show_redirect_countdown' => true,
    ],

    'diagnostics' => [
        'redirect_owner' => 'claim-widget',
    ],
]
```

Current ownership values:

| Owner | Meaning |
|---------|---------|
| claim-widget | Success page owns redirect countdown |
| null | No redirect configured |

Future ownership values may include:

| Owner | Meaning |
|---------|---------|
| rider-runtime | XRider runtime owns redirect |
| workflow | Workflow engine owns redirect |
| policy | Redirect determined by policy engine |

---

# Redirect Phase

The compiler emits a dedicated redirect phase when a rider URL exists.

Example:

```php
[
    'key' => 'redirect',
    'owner' => 'claim-widget',
    'delay_seconds' => 5,
]
```

This phase is informational.

The phase does not perform navigation.

Navigation is performed exclusively through the redirect gate.

---

# Redirect Gate

## Purpose

The redirect gate is implemented by:

```php
ClaimRedirectController
```

Route:

```text
/x/claim/{code}/redirect
```

The gate prevents frontend components from redirecting directly to arbitrary rider URLs.

Instead:

```text
Success Page
        │
        ▼
Redirect Gate
        │
        ▼
Resolved Destination
```

---

# Why A Redirect Gate Exists

Without a redirect gate:

```text
Success Page
        │
        ▼
https://example.com
```

The frontend would become responsible for:

- URL selection
- Redirect policies
- Validation
- Auditing
- Future authorization rules

With a redirect gate:

```text
Success Page
        │
        ▼
/x/claim/{code}/redirect
        │
        ▼
Resolver
        │
        ▼
Destination
```

The frontend only knows about the gate.

The backend owns the redirect decision.

---

# Success Redirect Resolution

Redirect destinations are resolved through:

```php
SuccessRedirectResolverContract
```

Current behavior:

```text
Voucher Rider URL
        │
        ▼
Resolved Destination
```

Future implementations may support:

```text
Voucher Rider URL
Campaign Redirect
Affiliate Redirect
Geo Redirect
A/B Redirect
Policy Redirect
```

without requiring controller changes.

---

# Success Page Integration

The success page receives:

```php
[
    'claim_experience' => [...],

    'redirect' => [
        'show_countdown' => true,
        'owner' => 'claim-widget',
        'delay_seconds' => 5,
    ],

    'redirectEndpoint' => '/x/claim/{code}/redirect',
]
```

The page never receives responsibility for destination selection.

Instead:

```text
Success.vue
        │
        ▼
RiderCountdown
        │
        ▼
redirectEndpoint
```

---

# Duplicate Splash Prevention

When the rider experience already consumed an introduction splash:

```php
claim_experience.options.skip_consumed_splash = true
```

Form Flow splash pages are skipped.

This prevents:

```text
XRider Splash
        ▼
Form Flow Splash
```

from appearing twice.

---

# Test Coverage

## Backend

### ClaimExperienceCompilerTest

Validates:

- Redirect phase emission
- Redirect ownership
- Countdown visibility
- No anonymous phases

### ClaimStartControllerTest

Validates:

- Claim experience persistence
- Countdown metadata exposure
- Splash skip flags

### ClaimFlowSkipsDuplicateSplashTest

Validates:

- Duplicate splash prevention
- Form Flow splash fallback behavior

### ClaimSuccessPageControllerTest

Validates:

- Redirect metadata exposure
- Countdown settings
- Redirect ownership propagation

### ClaimExperienceContractTest

Validates:

- Claim experience payload contract

### ClaimRedirectControllerTest

Validates:

- Successful redirect through gate
- Missing voucher returns 404
- Missing destination returns 404

---

## Frontend

### useClaimSuccessRedirect.test.ts

Validates:

- Redirect countdown logic
- Redirect ownership handling
- Redirect configuration behavior

### Success.redirect-countdown.test.ts

Validates:

- Countdown rendering
- Countdown suppression
- Redirect metadata propagation
- Redirect gate endpoint usage
- Success page redirect wiring

---

# Design Principle

The compiler decides.

The success page presents.

The redirect gate executes.

No frontend component should directly own redirect destination selection.
