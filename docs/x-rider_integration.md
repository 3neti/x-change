# x-rider Integration Baseline Contract
## x-change ↔ x-rider Runtime Architecture

Version: 1.0  
Status: Stabilized Baseline  
Scope: Rider Experience Resolution and Redirect Runtime

---

# 1. Purpose

This document defines the stabilized integration contract between:

- `3neti/x-change`
- `3neti/x-rider`

The purpose of this baseline is to:

- formalize the current rider runtime behavior
- stabilize the rendering and redirect flow
- define safe extension points
- establish a production-safe redirect model
- prepare the architecture for future driver runtime expansion

This document reflects the currently working implementation.

---

# 2. Core Philosophy

The rider is now a **first-class runtime experience layer**.

A voucher redemption is no longer merely:

```text
redeem → success
```

It is now:

```text
redeem
→ rider experience resolution
→ controlled rendering
→ safe redirect orchestration
→ analytics
→ optional campaign/ads/runtime extensions
```

The rider layer is intentionally:

- composable
- driver-based
- runtime-safe
- analytics-aware
- externally extensible

---

# 3. Runtime Flow

## Current Stable Runtime

```text
Voucher Redemption
    ↓
ClaimSubmitController
    ↓
ClaimSuccessPageController
    ↓
XChangeRiderSubjectFactory
    ↓
XChangeRiderOutcomeResolver
    ↓
RiderExperienceResolverContract
(DefaultRiderExperienceResolver)
    ↓
RiderExperienceData
    ↓
Success.vue
    ↓
RiderCountdown.vue
    ↓
/x/claim/{code}/redirect
    ↓
DefaultSuccessRedirectResolver
    ↓
External Destination
```

---

# 4. Stable Runtime Contract

## 4.1 Voucher Instructions Input

Current stable voucher rider shape:

```yaml
rider:
  message: "Thank you!"
  url: "https://merchant.example.com"
  redirect_timeout: 5

  splash:
    image: "https://..."
    timeout: 3
```

Legacy fields are intentionally supported.

---

# 4.2 Driver Runtime

Current runtime sources:

```text
resources/rider-drivers/*.yaml
```

The default runtime driver:

```text
resources/rider-drivers/default.yaml
```

The runtime is loaded through:

```php
RiderDriverLoader
```

---

# 4.3 Runtime Merge Strategy

The runtime merge order is:

```text
driver defaults
    overridden by
runtime voucher context
```

Implemented through:

```php
array_replace_recursive()
```

Runtime voucher context always wins.

---

# 5. Stable DTO Contract

## RiderSubjectData

Represents the runtime subject.

```php
RiderSubjectData(
    type,
    id,
    code,
    meta,
)
```

Reference generation:

```php
$subject->reference()
```

Produces:

```text
voucher:74:ABC123
```

---

## RiderExperienceData

Canonical resolved rider runtime.

```php
RiderExperienceData(
    state,
    subject,
    preClaim,
    success,
    redirect,
    campaign,
    ads,
    analytics,
    meta,
)
```

This is the primary runtime payload consumed by the frontend.

---

## RiderRedirectData

Canonical redirect configuration.

```php
RiderRedirectData(
    enabled,
    url,
    timeout,
    fallbackUrl,
    meta,
)
```

---

# 6. Frontend Contract

## Success.vue

Responsibilities:

- render rider success message
- render RiderCountdown
- render fallback actions
- avoid direct external redirects

Success.vue MUST NOT redirect directly to `rider.url`.

Instead:

```text
Success.vue
→ internal redirect endpoint
→ safe redirect resolver
→ external URL
```

---

## RiderCountdown.vue

Responsibilities:

- countdown UX
- manual continue action
- controlled redirect initiation
- frontend redirect orchestration

It redirects only to:

```text
/x/claim/{code}/redirect
```

Never directly to external URLs.

---

# 7. Safe Redirect Model

## Hard Rule

External rider URLs MUST NEVER be redirected directly from the success page.

All redirects MUST pass through:

```text
ClaimRedirectController
```

which uses:

```php
DefaultSuccessRedirectResolver
```

---

# 8. Redirect Safety

## Allowed Hosts

External redirects are restricted by:

```php
x-rider.redirects.allowed_hosts
```

Example:

```php
'allowed_hosts' => [
    'merchant.example.com',
    'open.spotify.com',
],
```

---

## Allow Any Host

Development-only override:

```env
X_RIDER_ALLOW_ANY_REDIRECT_HOST=true
```

Production use is discouraged.

---

## Unsafe URL Blocking

Blocked automatically:

```text
javascript:
data:
vbscript:
```

Fallback is used instead.

---

# 9. Fallback Redirect Behavior

If redirect resolution fails:

```text
fallbackUrl
```

is used.

Current default:

```text
/x/claim
```

---

# 10. Rider Outcome Resolution

Current stable outcomes:

```text
accepted_success
accepted_pending
rejected_failure
```

Resolved through:

```php
XChangeRiderOutcomeResolver
```

---

## Pending-as-Success Runtime

Configurable behavior:

```php
x-change.rider.outcomes.treat_pending_with_local_disbursement_as_success
```

Purpose:

Allow local disbursement flows to display success rider experiences even while backend settlement remains pending.

Default:

```php
true
```

---

# 11. Analytics Runtime

Current analytics event:

```text
rider.redirect.started
```

Payload:

```php
RiderAnalyticsEventData(
    event,
    reference,
    sourceType,
    sourceId,
    context,
    meta,
)
```

---

# 12. Current Stable Extension Points

## Contracts

```php
RiderExperienceResolverContract
RiderCampaignResolverContract
RiderRendererContract
SuccessRedirectResolverContract
RiderAnalyticsRecorderContract
```

---

# 13. Current Stable Configuration

## x-rider.php

Key sections:

```php
defaults
redirects
routes
driver
```

---

## x-change.php

Key sections:

```php
rider.outcomes
```

---

# 14. Current Stable Guarantees

The current runtime guarantees:

```text
✅ rider.url legacy compatibility
✅ redirect safety
✅ countdown UX
✅ internal redirect orchestration
✅ fallback redirect behavior
✅ analytics recording
✅ runtime driver loading
✅ YAML-based rider defaults
✅ external host allowlisting
```

---

# 15. Explicitly Deferred

The following are intentionally deferred:

```text
❌ ads runtime
❌ affiliate runtime
❌ surveys
❌ video runtime
❌ dynamic splash runtime
❌ OG meta runtime
❌ campaign scheduling
❌ multi-stage rider pipelines
❌ SSR rendering
❌ personalization
❌ A/B testing
```

---

# 16. Next Phase

## Phase 4 — Driver Runtime Expansion

Planned runtime types:

```text
splash
markdown
image
video
deep_link
survey
affiliate
ads
campaign
loyalty
```

These will evolve from the current stable baseline.

---

# 17. Architectural Position

x-rider is intentionally becoming:

```text
a runtime programmable post-transaction experience engine
```

not merely a success-page renderer.

This distinction is foundational to the future architecture.
