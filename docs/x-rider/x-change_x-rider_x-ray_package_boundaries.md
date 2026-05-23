# Package Boundaries
## Canonical Ownership Reference for x-change Ecosystem

---

# Purpose

This document defines the canonical architectural ownership boundaries between:

- x-change
- x-rider
- future x-ray
- the host application

Its purpose is to prevent:

- package responsibility drift
- duplicated runtime logic
- frontend/backend entanglement
- unclear ownership
- accidental cross-package coupling
- editing confusion between package and published assets

This document should be treated as the authoritative reference for determining:

```text
Which package owns what responsibility
```

throughout the x-change ecosystem.

---

# Architectural Philosophy

The ecosystem follows a layered runtime architecture:

```text
x-change
    ↓ orchestrates
x-rider
    ↓ normalizes
x-ray (future)
    ↓ renders/inspects
Host App
    ↓ composes/brands/deploys
```

Each layer owns a distinct responsibility.

---

# x-change Owns

Package:

```text
packages/x-change
```

Primary responsibility:

```text
Voucher lifecycle orchestration
```

x-change is the business and lifecycle engine.

It owns:

- lifecycle orchestration
- voucher APIs
- lifecycle services
- settlement flows
- claim flows
- preview orchestration
- RiderExperience projection integration

---

## Specifically, x-change Owns

### Voucher Lifecycle

- issue
- claim
- redeem
- cancel
- confirm
- settle
- transfer
- disburse

---

### API Surfaces

Examples:

```text
/api/x/v1/...
```

Including:

- voucher preview
- lifecycle routes
- inspection routes
- claim runtime routes

---

### Lifecycle Services

Examples:

```php
VoucherLifecycleService
ClaimVoucher
ConfirmVoucherPayment
CancelVoucher
```

---

### Preview Projection Orchestration

x-change is responsible for:

```text
obtaining RiderExperienceData
```

and projecting it into API responses.

However:

```text
x-change DOES NOT own Rider normalization logic
```

That belongs to x-rider.

---

## x-change Does NOT Own

x-change should NOT own:

- stage semantics
- stage rendering rules
- presentation runtime semantics
- splash normalization
- preClaim normalization
- Rider runtime orchestration internals

Those belong to x-rider.

---

# x-rider Owns

Package:

```text
packages/x-rider
```

Primary responsibility:

```text
Runtime experience normalization
```

x-rider is the runtime presentation orchestration layer.

It owns:

- stage normalization
- RiderExperienceData
- RiderStageData
- stage runtime semantics
- presentation mode semantics
- redirect normalization
- preClaim normalization

---

## Specifically, x-rider Owns

### Rider Runtime Concepts

Examples:

```php
RiderExperienceData
RiderStageData
RiderRedirectData
```

---

### Stage Resolution

Examples:

```yaml
rider:
  stages:
```

including:

- splash
- message
- image
- link
- redirect
- future runtime stages

---

### Runtime Presentation Semantics

Examples:

```yaml
presentation: inline
presentation: modal
presentation: fullscreen
```

x-rider defines:

```text
what those modes mean
```

not how every frontend renders them.

---

### Runtime Normalization

x-rider transforms:

```yaml
raw YAML/configuration
```

into:

```text
canonical runtime experience objects
```

This includes:

- defaults
- compatibility
- normalization
- payload shaping
- runtime consistency

---

## x-rider Does NOT Own

x-rider should NOT own:

- voucher lifecycle orchestration
- settlement logic
- business rules
- claim APIs
- frontend routing
- deployment composition

Those belong elsewhere.

---

# x-ray (Future) Will Own

Future package:

```text
packages/x-ray
```

Primary responsibility:

```text
Experience inspection and preview rendering runtime
```

x-ray is planned to become the dedicated:

- disclosure runtime
- inspection runtime
- preview UI runtime
- experience visualization
- safe public projection

layer.

---

## Planned x-ray Responsibilities

### Preview Rendering Runtime

Examples:

- voucher preview
- x-ray inspection
- disclosure surfaces
- pre-claim experiences
- safe public-facing inspection

---

### Experience Visualization

Examples:

- stage visualization
- preview runtime rendering
- fullscreen runtime
- kiosk runtime
- sponsor runtime

---

### Public Projection Safety

Examples:

- field masking
- payload filtering
- inspection authorization
- public-safe runtime exposure

---

## x-ray Will NOT Own

x-ray should NOT own:

- voucher lifecycle logic
- settlement orchestration
- Rider normalization
- business rules
- issuance logic

It is a rendering/runtime projection layer.

---

# Host Application Owns

Primary responsibility:

```text
Business composition and deployment
```

The host app composes packages together into a deployable product.

It owns:

- branding
- themes
- logos
- published assets
- deployment
- business composition

---

## Specifically, the Host App Owns

### Branding

Examples:

- logos
- app identity
- typography
- issuer identity
- marketing assets

---

### Themes

Examples:

- colors
- UI overrides
- Tailwind customization
- brand layouts

---

### Published Assets

Examples:

```text
resources/js/pages/x-change
resources/js/components/x-rider
```

These are:

```text
generated/published artifacts
```

not canonical sources.

---

### Deployment Composition

Examples:

- package installation
- environment configuration
- queue configuration
- infrastructure
- Vite build pipeline

---

### Business Composition

Examples:

- which drivers are enabled
- which providers are installed
- which lifecycle flows are exposed
- issuer-specific configuration

---

# Canonical Source Rule

## IMPORTANT

Published assets inside the host app are NOT canonical sources.

Canonical sources always live inside the packages.

Examples:

### Canonical x-change source

```text
packages/x-change/resources/js/...
```

### Canonical x-rider source

```text
packages/x-rider/resources/js/...
```

---

## Publish Flow

Host app copies are regenerated via:

```bash
php artisan x-change:install --force
```

followed by:

```bash
npm run build
```

Any direct edits to published host assets may be overwritten.

---

# Integration Philosophy

The ecosystem follows:

```text
contract-based orchestration
```

instead of direct package entanglement.

Examples:

```php
RiderExperienceResolverContract
```

instead of:

```text
hardcoded runtime dependencies
```

This allows:

- modular evolution
- future extraction
- independent package testing
- frontend/runtime decoupling
- alternative runtime implementations

---

# Long-Term Direction

The intended long-term architecture is:

```text
x-change
    ↓ business lifecycle
x-rider
    ↓ runtime normalization
x-ray
    ↓ runtime rendering/inspection
Host App
    ↓ branded deployment
```

This separation allows the ecosystem to scale cleanly without collapsing into a monolithic runtime.

---

# Guiding Principle

A useful heuristic:

| Question | Owning Layer |
|---|---|
| "How does the voucher lifecycle behave?" | x-change |
| "What does this stage mean?" | x-rider |
| "How should this experience be visualized?" | x-ray |
| "How should this deployment look and behave?" | Host App |

This principle should guide future architectural decisions.
