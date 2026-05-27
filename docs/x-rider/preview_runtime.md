# Rider Runtime Protocol
## Voucher Preview Projection Architecture

---

# Purpose

This document defines the canonical preview runtime architecture used by the x-change ecosystem.

It explains:

- how voucher preview data is produced
- how Rider experiences are projected
- how preview APIs are structured
- how frontend preview runtimes consume Rider data
- why RiderExperienceData exists
- why x-change and x-rider are intentionally separated

This document should be treated as the authoritative reference for:

```text id="fw5khn"
Rider runtime protocol behavior
```

throughout the ecosystem.

---

# High-Level Architecture

The preview runtime is intentionally layered:

```text id="jdhq7z"
Voucher
    ↓
Lifecycle Service
    ↓
Rider Experience Resolver
    ↓
RiderExperienceData
    ↓
API Projection
    ↓
Frontend Preview Runtime
```

Each layer owns a distinct responsibility.

---

# Architectural Philosophy

The preview runtime follows:

```text id="4d8bd9"
projection-based orchestration
```

instead of:

```text id="6u6v2v"
frontend-specific coupling
```

The backend defines:

- semantic experience structure
- stage meaning
- normalized runtime payloads

The frontend defines:

- rendering
- layout
- interaction
- animation
- accessibility
- presentation implementation

This separation is intentional.

---

# Runtime Layers

---

# Layer 1 — Voucher Lifecycle

Owned by:

```text id="u5n5ns"
x-change
```

Responsible for:

- voucher retrieval
- lifecycle orchestration
- issuance state
- claim state
- settlement state
- preview API exposure

---

## Example

```php id="3n3k7d"
VoucherLifecycleService::showByCode()
```

---

# Layer 2 — Rider Resolution

Owned by:

```text id="61bhju"
x-rider
```

Responsible for:

- Rider normalization
- stage interpretation
- preClaim normalization
- redirect normalization
- runtime payload shaping
- presentation semantics

---

## Example

```php id="kk7u6p"
RiderExperienceResolverContract
```

---

# Layer 3 — Projection

Owned by:

```text id="m0m3n0"
x-change
```

Responsible for:

- exposing RiderExperienceData
- transforming runtime objects into API-safe payloads
- preview-safe serialization

---

# Layer 4 — Frontend Preview Runtime

Owned by:

```text id="3r8qke"
frontend runtime
```

Examples:

- ClaimWidget.vue
- x-ray preview runtime
- kiosk runtime
- future mobile runtime

Responsible for:

- rendering
- layout
- interaction
- presentation mode handling
- runtime sequencing
- runtime action execution

---

# Canonical Runtime Flow

---

# 1. Voucher Retrieved

Example:

```php id="n00dhz"
$voucher = $service->showByCode($code);
```

---

# 2. Rider Experience Resolved

Example:

```php id="8itf1u"
$experience = $resolver->resolve($voucher);
```

---

# 3. RiderExperienceData Produced

Example:

```php id="v5isf9"
new RiderExperienceData(
    state: ...,
    subject: ...,
    preClaim: ...,
    success: ...,
    redirect: ...,
    stages: ...
)
```

---

# 4. API Response Projected

Example:

```json id="vvl2ju"
{
  "voucher": {
    "instructions": {},
    "rider": {
      "preClaim": {},
      "stages": {}
    }
  }
}
```

---

# 5. Frontend Consumes Projection

Examples:

```text id="jvswuh"
ClaimWidget.vue
VoucherPreview
x-ray runtime
```

---

# Canonical Preview API Contract

The preview runtime currently exposes:

---

# voucher.instructions

Raw instruction/runtime surface.

Example:

```json id="tf8piq"
voucher.instructions
```

Contains:

- cash instructions
- inputs
- validation
- rider stages
- legacy Rider configuration

---

# voucher.rider.preClaim

Canonical normalized pre-claim projection.

Example:

```json id="ml8v93"
voucher.rider.preClaim
```

Derived from:

```yaml id="6v2w6s"
type: splash
```

stages.

---

## Example Projection

```json id="b7xgxw"
{
  "enabled": true,
  "type": "markdown",
  "content": "Please review this before continuing.",
  "meta": {
    "stage_key": "pre-claim-demo",
    "timeout": 3
  }
}
```

---

# voucher.rider.stages

Canonical normalized Rider stage projection.

Example:

```json id="axgk9y"
voucher.rider.stages
```

Contains:

- splash stages
- image stages
- link stages
- future visual stages

---

## Example Projection

```json id="cfp68r"
{
  "stages": [
    {
      "type": "image",
      "key": "hero-banner",
      "payload": {
        "src": "https://example.com/banner.jpg"
      }
    }
  ]
}
```

---

## Stage Phase Filtering

The runtime lifecycle is now formally isolated.

Canonical runtime flow:

```text
pre_claim
    ↓
runtime
    ↓
form-flow
    ↓
success
    ↓
post_claim
    ↓
redirect
```

---

## Claim Preview Runtime

Claim preview surfaces (for example `ClaimWidget.vue`) render only:

| Allowed phases |
|---|
| `pre_claim` |
| `runtime` |

Claim preview MUST NOT:

- execute redirect runtime
- render success stages
- render post-claim stages

This prevents redirect leakage before redemption.

---

## Success Runtime

Success surfaces (for example `Success.vue`) render only:

| Allowed phases |
|---|
| `success` |
| `post_claim` |
| `redirect` |

Success surfaces MUST NOT:

- render pre-claim stages
- replay onboarding runtime
- replay pre-redemption splash content

This preserves lifecycle separation.

---

## Runtime Ownership

x-change owns:

- lifecycle orchestration
- phase projection
- payload transport

x-rider owns:

- runtime semantics
- stage meaning
- runtime action execution
- modal/fullscreen sequencing
- redirect runtime behavior

This separation is intentional.

---

# Why RiderExperienceData Exists

A major architectural principle is:

```text id="gmuhb6"
frontends should not interpret raw YAML directly
```

Raw Rider YAML:

- may contain shortcuts
- may evolve
- may contain legacy forms
- may contain normalization ambiguity

RiderExperienceData provides:

- canonical structure
- stable semantics
- normalized runtime behavior
- transport-safe projection

---

# Example

Raw YAML:

```yaml id="m9lq4s"
- type: splash
  content: Welcome.
```

Normalized runtime:

```php id="klh0jc"
RiderContentData(
    type: Markdown,
    content: 'Welcome.'
)
```

The frontend only consumes:

```text id="1ggg14"
normalized runtime contracts
```

---

# Why x-change Does NOT Normalize Rider Data

Because:

```text id="owzavm"
Rider semantics belong to x-rider
```

x-change intentionally avoids:

- stage interpretation
- stage normalization
- presentation semantics
- Rider payload shaping

Instead:

```text id="9l8shg"
x-change orchestrates and projects
```

This preserves package boundaries.

---

# Why Tests Mock RiderExperienceResolverContract

The preview runtime tests intentionally mock:

```php id="53lzsy"
RiderExperienceResolverContract
```

instead of testing x-rider internals.

This proves:

```text id="kg6v4m"
x-change only depends on the Rider contract
```

not implementation details.

---

# This Is Important

The tests verify:

```text id="9gvj6l"
projection contract correctness
```

NOT:

```text id="o4fy7u"
Rider normalization internals
```

This separation is critical for:

- package isolation
- future extraction
- independent evolution
- stable integration contracts

---

# Preview Runtime Rendering

Current frontend preview rendering lives primarily in:

```text id="6ddhys"
ClaimWidget.vue
```

which currently renders:

- preClaim splash
- Rider links
- Rider images
- Rider messages
- preview tabs

---

# Current Rendering Strategy

Current runtime rendering is phase-aware and presentation-aware.

---

## Inline Rendering

| Stage Type | Rendering |
|---|---|
| splash | inline content |
| message | informational content |
| image | inline media |
| link | inline CTA |
| cta | runtime-aware CTA |

---

## Presentation Modes

Stages may declare:

```yaml
presentation:
```

Examples:

```yaml
presentation: inline
presentation: modal
presentation: fullscreen
```

---

## Current Runtime Support

| Mode | State |
|---|---|
| inline | fully implemented |
| modal | implemented |
| fullscreen | implemented |

Blocking presentations are sequenced one at a time.

Example:

```text
modal → dismiss → fullscreen → dismiss
```

This sequencing is owned by the x-rider runtime sequencer.

---

# Future Direction

The preview runtime is intentionally evolving toward:

```text id="jlwmrg"
x-ray extraction
```

where:

- x-change owns orchestration
- x-rider owns semantics
- x-ray owns rendering/runtime visualization

---

# Runtime Actions

As of Phase 7, stages may declare runtime actions.

Examples:

- `redirect`
- `open_url`
- `copy_to_clipboard`
- `track_event`
- `delay`
- `show_stage`
- `close`

---

## Runtime Action Principle

Stages describe:

```text
what the user sees
```

Runtime actions describe:

```text
what the runtime does
```

This distinction is important.

---

## Example CTA Runtime

```yaml
- type: cta
  key: reward-cta
  phase: pre_claim
  presentation: inline
  payload:
    label: Open Reward
    url: https://example.com/reward

  actions:
    - type: open_url
      timing: on_click
      requires_user_gesture: true
      payload:
        url: https://example.com/reward
        target: _blank
```

---

## Redirect Runtime

Legacy redirect stages remain compatible.

Redirect execution is now runtime-driven where possible.

Redirect countdown behavior belongs to the runtime sequencer.

Claim preview must never execute redirect runtime.

---

# Planned Future Runtime Features

Examples:

- runtime analytics transport
- sponsor runtime campaigns
- kiosk runtime rendering
- mobile-native runtime
- immersive Rider runtime experiences
- runtime persistence
- multi-client runtime orchestration
- runtime replay

without changing backend contracts.

---

# Frontend Runtime Responsibilities

Frontend runtimes SHOULD:

- render safely
- sanitize HTML
- validate URLs
- support accessibility
- gracefully handle unknown stages
- gracefully handle missing payloads
- prevent unsafe runtime execution
- isolate runtime failures from redemption flow

---

## Runtime Safety

Runtime actions must never affect:

- claim correctness
- voucher redemption
- payout execution
- settlement
- ledger state

Runtime failures should degrade gracefully into:

```text
presentation failure
```

—not redemption failure.

---

# Runtime Compatibility Principle

A frontend runtime SHOULD assume:

```text id="v0tyd5"
backend contracts may evolve
```

and SHOULD avoid:

- tightly coupling to raw YAML
- relying on undocumented fields
- hardcoding stage assumptions

---

# Security Considerations

Preview runtime data may contain:

- markdown
- HTML
- URLs
- media assets

Frontend runtimes SHOULD:

- sanitize HTML
- validate URLs
- prevent XSS
- avoid arbitrary script execution

---

# Canonical Ownership Summary

| Concern | Owner |
|---|---|
| voucher lifecycle | x-change |
| Rider normalization | x-rider |
| preview projection | x-change |
| rendering semantics | x-rider |
| visual rendering | frontend runtime |
| future inspection runtime | x-ray |

---

# Lifecycle Isolation Tests

Phase 7 introduced formal lifecycle isolation tests.

These tests verify:

| Surface | Allowed Phases |
|---|---|
| Claim Preview | `pre_claim`, `runtime` |
| Success Runtime | `success`, `post_claim`, `redirect` |

The tests intentionally prove:

- redirect stages cannot leak into preview runtime
- pre-claim stages cannot leak into success runtime
- runtime semantics remain lifecycle-isolated

This converts lifecycle semantics from convention into executable protocol behavior.

---

# Guiding Principle

A useful heuristic:

| Question | Owner |
|---|---|
| "How does the voucher lifecycle behave?" | x-change |
| "What does this Rider stage mean?" | x-rider |
| "How should this render visually?" | frontend runtime |
| "How should this be inspected publicly?" | x-ray |

This principle should guide all future preview runtime evolution.
