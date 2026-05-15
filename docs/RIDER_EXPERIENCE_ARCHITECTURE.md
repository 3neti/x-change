# x-change Rider Experience Architecture
## Companion Document to CLAIM_FLOW_MAP.md

**Version**: 1.0  
**Last Updated**: 2026-05-13  
**Audience**: Human developers + AI agents

---

# 1. Purpose

This document defines the Rider Experience architecture in x-change.

The rider is not merely:
- a redirect URL
- a thank-you message
- a splash page

The rider is:

> a programmable post-claim engagement surface attached to a Pay Code lifecycle.

It is intended to become:
- a monetization surface
- an advertising surface
- a merchant engagement surface
- a customer retention surface
- a programmable redemption UX layer

This document complements:

- `CLAIM_FLOW_MAP.md`
- `INSTALL_RESOURCE_MAP.md`
- `x-change Compass`
- redemption preparation architecture

---

# 2. Strategic Position

The rider sits between:

```text
successful claim acceptance
        ↓
external destination / merchant experience
```

It is the final programmable touchpoint owned by x-change before the claimant leaves the ecosystem.

---

# 3. Core Principle

## Redemption is financial.
## Rider is experiential.

These concerns must remain separate.

### Redemption layer responsibilities
- voucher validity
- KYC
- OTP
- payout execution
- disbursement
- settlement
- accounting

### Rider layer responsibilities
- branding
- messaging
- engagement
- onboarding
- advertising
- merchant continuity
- app deep links
- upsells
- behavioral routing

---

# 4. Rider Lifecycle Placement

The rider begins only after claim acceptance.

```text
claim submit
    ↓
redemption execution
    ↓
voucher redeemed
    ↓
post-redemption pipeline
    ↓
accepted outcome established
    ↓
RIDER EXPERIENCE BEGINS
```

---

# 5. Rider Outcome States

The rider must distinguish between:

| State | Meaning |
|---|---|
| accepted_success | claim accepted + disbursement succeeded |
| accepted_pending | claim accepted + disbursement pending |
| rejected_failure | claim rejected / rollback occurred |

---

## 5.1 accepted_success

Examples:
- Netbank disbursement succeeded
- withdrawal succeeded
- payout reference exists

Behavior:
- show success rider
- allow redirect
- allow ads
- allow merchant continuation

---

## 5.2 accepted_pending

Examples:
- gateway timeout
- async payout
- reconciliation pending

Behavior:
- redemption still stands
- success page still shown
- rider may still continue
- messaging must disclose pending state

Example:

```text
Your claim has been received.
Your disbursement is currently being processed.
```

---

## 5.3 rejected_failure

Examples:
- invalid voucher
- expired voucher
- rollback-triggering structural payout mismatch
- contract violation

Behavior:
- rider must NOT run
- no redirect
- no monetization surface
- render Error.vue instead

---

# 6. Rider Phases

The rider is composed of multiple programmable phases.

---

## 6.1 Pre-Claim Rider

Occurs before form-flow.

Examples:
- merchant splash
- campaign page
- onboarding teaser
- terms
- educational material

Current implementation:
- `Splash.vue`
- YAML driver splash step

---

## 6.2 In-Flow Rider

Occurs during form-flow.

Examples:
- contextual instructions
- ad insertions
- compliance education
- dynamic guidance

Future:
- programmable side panels
- rotating media
- contextual CTA

---

## 6.3 Success Rider

Occurs after accepted claim execution.

Examples:
- markdown message
- receipt
- app install prompt
- loyalty continuation
- promo code
- onboarding redirect

Current implementation:
- `claim/Success.vue`

---

## 6.4 Redirect Rider

Final transition phase.

Current implementation:
- `/x/claim/{code}/redirect`
- `ClaimRedirectController`

Responsibilities:
- redirect safety
- audit logging
- analytics
- campaign attribution
- future ad click tracking

---

# 7. Rider Contract Model

The rider must become a first-class DTO contract.

---

## Proposed DTO

```php
RiderExperienceData
```

---

## Example shape

```php
[
    'pre_claim' => [
        'enabled' => true,
        'type' => 'markdown',
        'content' => '...',
        'timeout' => 5,
    ],

    'success' => [
        'enabled' => true,
        'type' => 'markdown',
        'content' => '...',
    ],

    'redirect' => [
        'enabled' => true,
        'url' => 'https://merchant.example.com',
        'timeout' => 5,
    ],

    'ads' => [
        'enabled' => false,
    ],

    'campaign' => [
        'id' => 'summer-2026',
    ],
]
```

---

# 8. Rider Rendering Types

The rider system must support:

| Type | Description |
|---|---|
| markdown | rendered markdown |
| html | sanitized HTML |
| image | static image |
| svg | inline SVG |
| video | hosted video |
| url | external page |
| component | frontend component registry |
| iframe | sandboxed embedded content |
| deep_link | app continuation |

---

# 9. Redirect Safety Model

The redirect controller is a critical security boundary.

The frontend must NEVER directly redirect to:
- `rider.url`
- arbitrary merchant URLs

Instead:

```text
Success.vue
    ↓
/x/claim/{code}/redirect
    ↓
ClaimRedirectController
    ↓
safe validated redirect
```

---

## Responsibilities

The redirect controller must:
- validate scheme
- validate host
- enforce allowlists
- audit log redirect
- attach analytics metadata
- support deep-link fallback
- support mobile fallback URLs

---

# 10. Analytics & Monetization

The rider is a monetizable engagement surface.

---

## Future monetization capabilities

| Capability | Description |
|---|---|
| sponsored rider | paid merchant rider |
| ad insertion | rotating ads |
| campaign attribution | UTM analytics |
| app-install funnel | app onboarding |
| loyalty continuation | rewards |
| merchant upsell | cross-sell |
| affiliate redirect | partner monetization |
| QR continuation | continue transaction elsewhere |

---

# 11. Ad Architecture Direction

Ads must never be injected directly into redemption logic.

Instead:

```text
RiderExperienceResolver
    ↓
RiderContentPipeline
    ↓
AdInsertionStage
    ↓
Success.vue
```

This preserves:
- financial determinism
- redemption integrity
- auditability

---

# 12. Proposed Rider Services

## Contracts

```php
RiderExperienceResolverContract
SuccessRedirectResolverContract
RiderAnalyticsRecorderContract
RiderCampaignResolverContract
```

---

## Services

```php
DefaultRiderExperienceResolver
DefaultSuccessRedirectResolver
DefaultRiderAnalyticsRecorder
DefaultAdInsertionService
```

---

# 13. Future Capability: Dynamic Rider Selection

Future rider resolution may depend on:

- merchant
- issuer
- voucher type
- geography
- language
- campaign
- device
- settlement rail
- claim amount
- time window

Example:

```text
high-value claims
    → premium rider
```

---

# 14. Testing Strategy

The rider must be testable separately from redemption.

---

## Unit tests

- DTO normalization
- redirect validation
- rider resolution
- ad insertion
- campaign selection

---

## Feature tests

- success page props
- redirect controller
- fallback behavior
- pending-state messaging

---

## Browser tests

- countdown redirect
- markdown rendering
- mobile deep-link behavior
- ad rotation

---

# 15. Architectural Rule

The rider is downstream of redemption.

The rider:
- must never mutate redemption state
- must never affect payout correctness
- must never alter financial execution

The rider is:
- experiential
- programmable
- monetizable
- analytics-aware

But never authoritative over money movement.

---

# 16. Canonical Flow

```text
/x/claim
    ↓
form-flow
    ↓
claim submit
    ↓
redemption execution
    ↓
post-redemption pipeline
    ↓
accepted outcome
    ↓
Success.vue
    ↓
RiderExperienceData
    ↓
redirect controller
    ↓
merchant continuation
```

---

# 17. Final Guiding Principle

> Redemption establishes financial truth.  
> The rider establishes experiential continuity.

---

END OF DOCUMENT
