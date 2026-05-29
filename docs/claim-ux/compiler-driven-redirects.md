# Compiler-Driven Redirects

## Purpose

This document defines the redirect ownership model for the x-change claim experience.

The objective is to ensure that:

- Exactly one component owns redirect behavior.
- Countdown rendering is compiler-driven.
- Rider-first and form-flow-first experiences behave consistently.
- The frontend never redirects directly to a voucher-provided URL.
- Redirect behavior can be tested independently of UI rendering.

---

# Background

Historically, redirect behavior could originate from multiple places:

- Rider splash stages
- Form-flow splash stages
- Success page widgets
- Voucher rider URLs

This created ambiguity regarding:

- Who should render the countdown?
- Who should perform the redirect?
- Whether duplicate countdowns could appear.

The compiler-driven model centralizes these decisions.

---

# Architectural Principle

## Redirect Ownership

At any point in the claim journey:

> Exactly one component may own redirect behavior.

Ownership is determined by the Claim Experience Compiler.

The compiler emits metadata describing:

```json
{
  "options": {
    "show_redirect_countdown": true
  },
  "diagnostics": {
    "redirect_owner": "claim-widget"
  }
}
```

Possible owners:

| Owner | Meaning |
|---------|----------|
| claim-widget | Success page owns countdown rendering |
| rider | Rider experience owns redirect |
| null | No redirect configured |

Only one owner may be emitted.

---

# Claim Experience Compiler

## Responsibility

The compiler inspects voucher instructions and determines:

- UX phases
- Redirect destination
- Redirect delay
- Redirect ownership
- Splash suppression rules

The compiler produces a normalized experience payload.

Example:

```json
{
  "phases": [
    {
      "key": "redirect",
      "redirect_url": "https://example.com",
      "delay_seconds": 5
    }
  ],
  "options": {
    "show_redirect_countdown": true
  },
  "diagnostics": {
    "redirect_owner": "claim-widget"
  }
}
```

---

# Redirect Phase

The redirect phase is informational.

It communicates:

```json
{
  "key": "redirect",
  "redirect_url": "https://example.com",
  "delay_seconds": 5
}
```

The frontend should not navigate directly to this URL.

Instead it uses the redirect endpoint.

---

# Security Boundary

## Never Redirect Directly

The success page must never perform:

```javascript
window.location = rider.redirect_url
```

or

```javascript
window.location = voucher.instructions.rider.url
```

The only allowed destination is:

```text
/x/claim/{code}/redirect
```

represented by:

```javascript
redirectEndpoint
```

This creates a single redirect gate.

Benefits:

- Future auditing
- Analytics
- Redirect validation
- Access control
- Expiration checks
- Safety controls

---

# Claim Redirect Controller

## Responsibility

The redirect controller is the final redirect authority.

Flow:

```text
Success Page
    ↓
redirectEndpoint
    ↓
ClaimRedirectController
    ↓
External URL
```

All external redirects must pass through this controller.

---

# Rider-First Flow

Example:

```text
Claim Start
    ↓
Rider Splash
    ↓
Form Flow
    ↓
Success Page
    ↓
Redirect Countdown
    ↓
ClaimRedirectController
```

The rider splash is considered consumed.

The form-flow splash should not re-display the same introduction.

---

# Splash Consumption

The compiler emits:

```json
{
  "options": {
    "skip_consumed_splash": true
  }
}
```

When enabled:

- Rider splash displays once.
- Form-flow splash is skipped.
- Duplicate introductions are avoided.

This behavior is covered by feature tests.

---

# Success Page Responsibilities

The success page receives:

```json
{
  "claim_experience": {},
  "redirect": {
    "show_countdown": true,
    "owner": "claim-widget",
    "delay_seconds": 5
  }
}
```

Responsibilities:

- Render claim outcome
- Render rider success content
- Render countdown when enabled
- Navigate only through redirectEndpoint

The page does not decide ownership.

Ownership comes from the compiler.

---

# Frontend Countdown Rendering

Rendering rule:

```javascript
redirect.show_countdown === true
```

When true:

```vue
<RiderCountdown />
```

is rendered.

When false:

No countdown UI appears.

The component performs navigation using:

```javascript
redirectEndpoint
```

never the raw rider URL.

---

# Testing Coverage

## Backend

### ClaimExperienceCompilerTest

Validates:

- Redirect ownership
- Redirect phase generation
- Countdown visibility
- No anonymous phases

### ClaimStartControllerTest

Validates:

- Experience persistence
- Splash suppression options
- Compiler output propagation

### ClaimFlowSkipsDuplicateSplashTest

Validates:

- Rider splash consumption
- Form-flow splash suppression
- Duplicate splash prevention

### ClaimSuccessPageControllerTest

Validates:

- Redirect metadata exposure
- Countdown configuration
- Ownership propagation

---

## Frontend

### useClaimSuccessRedirect.test.ts

Validates:

- Redirect timing
- Countdown state
- Redirect triggering

### Success.redirect-countdown.test.ts

Validates:

- Countdown rendering when enabled
- Countdown suppression when disabled
- Redirect metadata consumption

---

# Invariants

The following rules must always remain true:

1. Exactly one redirect owner exists.

2. Success.vue never redirects directly to an external URL.

3. ClaimRedirectController remains the sole redirect gate.

4. Countdown visibility is compiler-driven.

5. Rider splash consumption prevents duplicate splash rendering.

6. Redirect ownership is determined by the compiler, not the UI.

7. Frontend components consume redirect metadata but never compute redirect policy.

---

# Future Enhancements

Potential future work:

- Analytics before redirect
- Redirect audit logs
- User-cancelable countdowns
- Redirect confirmation dialogs
- Conditional redirects
- Multi-destination redirect strategies
- A/B tested claim experiences

These enhancements should be implemented inside the compiler or redirect controller without changing frontend ownership rules.
