# x-rider Integration Baseline Contract
## x-change ↔ x-rider Runtime Architecture

Version: 2.0  
Status: Stabilized Baseline Runtime  
Scope: Rider Experience Resolution, Stage Runtime, Redirect Orchestration, Frontend Rendering

---

# 1. Purpose

This document defines the stabilized integration contract between:

- `3neti/x-change`
- `3neti/x-rider`

The purpose of this baseline is to:

- formalize the rider runtime behavior
- stabilize the rendering and redirect orchestration flow
- define canonical runtime DTOs
- define safe extension points
- establish production-safe redirect behavior
- stabilize the stage runtime architecture
- preserve backward compatibility while enabling future expansion

This document reflects the current working implementation.

---

# 2. Architectural Philosophy

The rider is now a:

```text
programmable post-transaction experience layer
```

A voucher redemption is no longer merely:

```text
redeem → success
```

It is now:

```text
redeem
→ rider experience resolution
→ stage normalization
→ controlled frontend rendering
→ safe redirect orchestration
→ analytics
→ optional campaign/runtime expansion
```

The rider layer is intentionally:

```text
composable
driver-based
runtime-safe
analytics-aware
stage-driven
externally extensible
backward compatible
```

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
DefaultRiderStageResolver
    ↓
RiderStageCollectionData
    ↓
RiderExperienceData
    ↓
Success.vue
    ↓
RiderStageRenderer.vue
    ↓
RiderCountdown.vue
    ↓
/x/claim/{code}/redirect
    ↓
ClaimRedirectController
    ↓
DefaultSuccessRedirectResolver
    ↓
External Destination
```

---

# 4. Runtime Layering

The runtime is intentionally layered.

```text
stages
    ↓
canonical RiderExperienceData
    ↓
frontend orchestration
```

This allows:

```text
x-rider to evolve independently
x-change to remain stable
```

Current rule:

```text
stages are the programmable source
canonical RiderExperienceData fields are the stable app contract
```

---

# 5. Stable Rider Input Contract

## 5.1 Legacy Rider Shape

The following remains fully supported:

```yaml
rider:
  message: "Thank you!"
  url: "https://merchant.example.com"
  redirect_timeout: 5

  splash:
    image: "https://..."
    timeout: 3
```

Legacy compatibility is intentional.

---

## 5.2 Stage Runtime Shape

Current canonical stage format:

```yaml
rider:
  stages:
    - type: message
      content: "Thank you."

    - type: redirect
      url: "https://merchant.example.com"
      timeout: 5

    - type: splash
      content: "Welcome."
      timeout: 2
```

---

# 6. Runtime Driver Loading

Current runtime sources:

```text
resources/rider-drivers/*.yaml
```

Default driver:

```text
resources/rider-drivers/default.yaml
```

Loaded through:

```php
RiderDriverLoader
```

---

# 7. Runtime Merge Strategy

Merge order:

```text
driver defaults
    overridden by
runtime voucher context
```

Implemented through:

```php
array_replace_recursive()
```

Runtime context always wins.

---

# 8. Stage Runtime

## 8.1 RiderStageData

Canonical stage DTO:

```php
RiderStageData(
    type,
    enabled,
    key,
    payload,
    meta,
)
```

Serialized example:

```json
{
  "type": "message",
  "enabled": true,
  "key": "thank-you-message",
  "payload": {
    "content": "Thank you.",
    "content_type": "markdown"
  },
  "meta": {}
}
```

---

## 8.2 RiderStageCollectionData

Canonical stage collection:

```php
RiderStageCollectionData
```

Helper methods:

```php
firstOfType()
renderable()
redirectLike()
```

---

## 8.3 Supported Stage Types

Current supported types:

```text
message
redirect
splash
image
link
```

Implemented stage drivers:

```text
MessageStageDriver
RedirectStageDriver
SplashStageDriver
```

---

# 9. Stable DTO Contract

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
    stages,
    campaign,
    ads,
    analytics,
    meta,
)
```

This is the primary runtime payload consumed by x-change.

---

## RiderContentData

Canonical content payload.

```php
RiderContentData(
    enabled,
    type,
    content,
    meta,
)
```

Used for:

```text
success messages
pre-claim splash content
future ads/campaign content
```

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

# 10. Stage Normalization

Stages normalize into canonical runtime fields.

---

## 10.1 Message Stage

```yaml
- type: message
  content: "Thank you."
```

Normalizes into:

```php
RiderExperienceData::success
```

---

## 10.2 Redirect Stage

```yaml
- type: redirect
  url: "https://merchant.example.com"
```

Normalizes into:

```php
RiderExperienceData::redirect
```

---

## 10.3 Splash Stage

```yaml
- type: splash
  content: "Welcome."
```

Normalizes into:

```php
RiderExperienceData::preClaim
```

---

# 11. Runtime Precedence Rules

The runtime follows strict precedence.

General model:

```text
runtime/context legacy fields
    >
explicit/latest stage
    >
driver defaults
    >
config fallback
```

---

## Message Precedence

```text
rider.message
    >
latest enabled message stage
    >
rider.success.content
    >
x-rider.defaults.success_message
```

---

## Redirect URL Precedence

```text
rider.url
    >
latest enabled redirect stage payload.url
    >
rider.redirect.url
```

---

## Redirect Timeout Precedence

```text
rider.redirect_timeout
    >
latest enabled redirect stage payload.timeout
    >
rider.redirect.timeout
    >
x-rider.defaults.redirect_timeout
```

---

## Splash / PreClaim Precedence

```text
rider.pre_claim
    >
latest enabled splash stage
    >
null
```

---

# 12. Frontend Contract

## Success.vue

Responsibilities:

```text
render rider success content
render stage renderer
render redirect countdown
render fallback actions
avoid direct external redirects
```

The page MUST NOT redirect directly to external URLs.

Instead:

```text
Success.vue
→ internal redirect endpoint
→ safe redirect resolver
→ external URL
```

---

## RiderStageRenderer.vue

Responsibilities:

```text
render message stages
render splash stages
render link stages
```

Redirect stages are intentionally excluded.

---

## RiderCountdown.vue

Responsibilities:

```text
countdown UX
manual continue action
controlled redirect initiation
frontend redirect orchestration
```

It redirects only to:

```text
/x/claim/{code}/redirect
```

Never directly to external URLs.

---

# 13. Safe Redirect Model

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

# 14. Redirect Safety

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

## Development Override

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

Fallback redirect is used instead.

---

# 15. Fallback Redirect Behavior

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

# 16. Rider Outcome Resolution

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

# 17. Analytics Runtime

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

Analytics are emitted only at orchestration boundaries.

Stage drivers themselves remain side-effect free.

---

# 18. Current Stable Extension Points

## Contracts

```php
RiderExperienceResolverContract
RiderCampaignResolverContract
RiderRendererContract
SuccessRedirectResolverContract
RiderAnalyticsRecorderContract
RiderStageDriverContract
RiderStageResolverContract
```

---

# 19. Stable Configuration

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

# 20. Package Default Rule

Package default rider drivers must remain:

```text
safe
neutral
non-marketing
non-invasive
```

Recommended default:

```yaml
rider:
  stages: []
```

Package defaults should not inject:

```text
ads
campaigns
forced redirects
merchant-specific messaging
```

Demonstration content belongs in:

```text
tests
fixtures
sandbox drivers
demo environments
```

---

# 21. Current Stable Guarantees

The runtime currently guarantees:

```text
✅ rider.url legacy compatibility
✅ stage runtime normalization
✅ redirect safety
✅ countdown UX
✅ internal redirect orchestration
✅ fallback redirect behavior
✅ analytics recording
✅ runtime driver loading
✅ YAML-based rider defaults
✅ external host allowlisting
✅ canonical RiderExperienceData contract
✅ frontend/backend separation
✅ stage-driven orchestration
```

---

# 22. Explicitly Deferred

The following remain intentionally deferred:

```text
❌ ads runtime
❌ affiliate runtime
❌ surveys
❌ video runtime
❌ OG meta runtime
❌ campaign scheduling
❌ multi-step stage pipelines
❌ SSR rendering
❌ personalization
❌ A/B testing
❌ async analytics
❌ loyalty runtime
```

---

# 23. Architectural Position

x-rider is intentionally evolving into:

```text
a programmable post-transaction experience engine
```

not merely:

```text
a success-page renderer
```

This distinction is foundational to the long-term architecture.
