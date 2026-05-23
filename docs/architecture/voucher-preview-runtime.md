# Voucher Preview Runtime Architecture
## Rider Projection, Experience Normalization, and Preview Contract Design

---

# 1. Introduction

The voucher preview feature in x-change has evolved from a simple voucher inspection endpoint into a full runtime projection layer capable of exposing normalized presentation experiences across multiple channels.

This document describes:

- what was implemented
- what was proven by the tests
- the architectural boundaries established
- why the Rider Experience projection matters
- and how this work prepares the extraction of preview/x-ray functionality into a standalone package.

---

# 2. Original State

Originally, the voucher preview endpoint behaved primarily as a voucher lookup surface:

```text
GET /api/x/v1/vouchers/code/{code}
```

The response mainly exposed:

- voucher metadata
- instructions
- amounts
- validation data
- expiration state

The rider section was effectively passive metadata:

```yaml
rider:
  message:
  splash:
  redirect:
```

The frontend interpreted these fields directly.

This created several problems:

- frontend/backend coupling
- inconsistent rendering behavior
- inability to normalize presentation runtime
- difficulty supporting multiple presentation modes
- no formal stage orchestration
- no reusable runtime experience model

---

# 3. The New Runtime Architecture

The system now introduces a normalized Rider Experience runtime.

## New Flow

```text
Voucher
    ↓
VoucherLifecycleService
    ↓
RiderExperienceResolver
    ↓
RiderExperienceData
    ↓
VoucherDetailResource
    ↓
Preview API JSON
    ↓
Frontend Runtime Renderer
```

This transforms voucher preview into an experience orchestration layer.

---

# 4. RiderExperienceData

The central abstraction is now:

```php
RiderExperienceData
```

This object normalizes:

- preClaim content
- success content
- redirect behavior
- visual stages
- analytics
- campaigns
- metadata

The frontend no longer interprets raw YAML directly.

Instead:

```text
YAML
→ Resolver
→ Normalized Runtime Experience
→ Presentation Runtime
```

---

# 5. What Was Proven by the Tests

The tests in:

```text
ShowVoucherByCodeLifecycleRouteTest.php
```

proved several important architectural guarantees.

---

# 6. Proven Guarantee #1
## Voucher Preview Is a Stable Public API

The preview endpoint is now an official runtime API surface.

The route no longer simply returns voucher information.

It now exposes:

```text
Voucher Preview + Rider Experience Projection API
```

This allows:

- x/claim pages
- x-ray pages
- mobile apps
- kiosk apps
- merchant terminals
- external preview consumers

to consume the same normalized runtime payload.

The tests prove this surface remains stable.

---

# 7. Proven Guarantee #2
## x-change Is Decoupled from x-rider Internals

The tests intentionally mock:

```php
RiderExperienceResolverContract
```

instead of booting the entire x-rider runtime.

This proves:

```text
x-change depends only on the Rider contract
NOT on Rider implementation details
```

This is one of the most important architectural outcomes.

It establishes:

- loose coupling
- runtime substitution
- contract-based orchestration
- package independence

instead of hidden runtime entanglement.

---

# 8. Proven Guarantee #3
## Rider Stages Are First-Class Runtime Entities

The system now treats stages as formal runtime presentation instructions.

Example:

```yaml
rider:
  stages:
    - type: splash
    - type: image
    - type: link
```

Stages are no longer passive configuration.

They are runtime-renderable orchestration units.

The tests prove that stages survive the entire lifecycle:

```text
Voucher
→ Lifecycle service
→ Rider resolver
→ RiderExperienceData
→ API projection
→ Frontend runtime
```

without being lost or mutated.

---

# 9. Proven Guarantee #4
## preClaim Is Canonical

The tests establish:

```text
preClaim is now an official runtime concept
```

instead of a frontend-only implementation detail.

This is significant because:

- splash screens
- onboarding disclosures
- warnings
- disclaimers
- fullscreen experiences
- regulatory notices

can now be normalized consistently.

Example:

```php
$experience->preClaim
```

This creates a stable backend representation independent of UI implementation.

---

# 10. Proven Guarantee #5
## Stage Payload Integrity

The tests validate that payloads survive projection faithfully.

Example assertions:

```php
payload.label
payload.url
payload.alt
```

This proves:

```text
x-change does not reinterpret stage payloads
```

It simply exposes them consistently.

This is essential for future consumers:

- React Native
- Flutter
- Electron
- kiosk apps
- merchant terminals
- external portals

which may all render stages differently.

---

# 11. Proven Guarantee #6
## Presentation Modes Are Runtime-Driven

The system now supports:

```yaml
presentation: inline
presentation: modal
presentation: fullscreen
```

This is important because presentation becomes declarative.

The backend defines:

```text
WHAT the experience is
```

while the frontend decides:

```text
HOW the experience is rendered
```

This separation is foundational for future extensibility.

---

# 12. Runtime Projection vs Raw Configuration

Before:

```text
Frontend renders YAML directly
```

After:

```text
Frontend renders normalized runtime experience
```

This distinction is critical.

The runtime projection layer allows:

- validation
- defaults
- compatibility
- normalization
- orchestration
- future migrations

without breaking frontend consumers.

---

# 13. Why This Matters for x-ray Extraction

The x-ray concept is no longer simply:

```text
show voucher information
```

It is now:

```text
render voucher experience safely
```

That distinction is major.

x-ray can now evolve into:

- preview runtime
- disclosure runtime
- onboarding runtime
- inspection runtime
- issuer-controlled projection runtime

using the same RiderExperience projection model.

---

# 14. Architectural Direction

The architecture now resembles modern orchestration systems used by:

- Stripe Checkout
- Square Terminal
- Shopify Extensions
- Toast POS
- Adyen Checkout Flows

where:

```text
Backend defines experience contract
Frontend renders runtime experience
```

instead of embedding presentation logic directly into UI pages.

---

# 15. Recommended Future Direction

## Suggested Extraction

Future package candidate:

```text
3neti/x-ray
```

Responsibilities:

- voucher preview runtime
- rider projection
- pre-claim runtime
- stage orchestration
- presentation normalization
- disclosure rendering
- inspection APIs
- preview authorization
- experience visibility policies

---

# 16. Recommended Future Runtime Concepts

Potential future additions:

## Stage Scheduling

```yaml
delay: 3
```

## Conditional Stages

```yaml
when:
  redeemed: false
```

## Multi-Step Presentation

```yaml
sequence:
```

## Analytics Events

```yaml
track:
```

## Channel-Specific Rendering

```yaml
channels:
  web:
  mobile:
  kiosk:
```

---

# 17. Conclusion

The work completed in this phase quietly transformed voucher preview into a runtime experience orchestration architecture.

The key achievement is not merely displaying rider content.

The key achievement is establishing:

```text
Normalized Experience Projection
```

as a formal system boundary.

This enables:

- decoupled presentation runtimes
- reusable preview infrastructure
- future x-ray extraction
- multi-channel rendering
- stable frontend contracts
- scalable rider orchestration

while preserving package modularity and lifecycle integrity.

This is now a foundational subsystem of the x-change ecosystem.
