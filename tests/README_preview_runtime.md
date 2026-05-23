# Preview Runtime Test Suite
## x-change/tests/README_preview_runtime.md

---

# Purpose

This test suite validates the canonical preview runtime integration between:

- x-change
- x-rider
- lifecycle services
- Rider projection APIs
- frontend preview consumers

The goal of these tests is NOT to test frontend rendering.

The goal is to prove:

```text
x-change correctly projects normalized Rider runtime data
```

through stable API contracts.

---

# What Is Being Tested

The preview runtime tests verify:

| Concern | Verified |
|---|---|
| voucher preview lifecycle route | ✓ |
| RiderExperience projection | ✓ |
| preClaim projection | ✓ |
| Rider visual stage projection | ✓ |
| Rider payload preservation | ✓ |
| lifecycle orchestration | ✓ |
| package boundary integrity | ✓ |
| Rider resolver integration | ✓ |

---

# Canonical Test File

Primary runtime integration test:

```text
tests/Feature/Api/Lifecycle/Vouchers/ShowVoucherByCodeLifecycleRouteTest.php
```

This file serves as the canonical preview runtime integration suite.

---

# Architectural Goal

The tests intentionally validate:

```text
projection contracts
```

instead of:

```text
frontend implementation details
```

This distinction is extremely important.

---

# Key Architectural Principle

x-change owns:

- lifecycle orchestration
- preview APIs
- Rider projection

x-rider owns:

- Rider normalization
- stage semantics
- RiderExperienceData

The tests prove that x-change integrates correctly with x-rider WITHOUT owning Rider internals.

---

# Why This Matters

This architecture allows:

- future x-ray extraction
- independent package evolution
- alternative frontend runtimes
- kiosk runtimes
- mobile runtimes
- sponsor runtimes

without rewriting lifecycle orchestration.

---

# What The Tests DO NOT Validate

The tests intentionally DO NOT validate:

- Vue rendering
- CSS
- animations
- browser interaction
- modal behavior
- fullscreen runtime behavior
- Vite bundling
- DOM structure

Those belong to frontend/UI tests.

---

# Runtime Flow Being Validated

The tests validate this canonical runtime chain:

```text
VoucherLifecycleService
    ↓
RiderExperienceResolverContract
    ↓
RiderExperienceData
    ↓
API Projection
    ↓
JSON Runtime Surface
```

---

# Why RiderExperienceResolverContract Is Mocked

Most preview runtime tests mock:

```php
RiderExperienceResolverContract
```

instead of invoking the actual x-rider implementation.

This is intentional.

---

# What This Proves

This proves:

```text
x-change depends on Rider contracts,
not Rider implementation details
```

This preserves package boundaries.

---

# Important Architectural Separation

The tests verify:

```text
integration correctness
```

NOT:

```text
Rider normalization correctness
```

Normalization belongs to x-rider's own test suite.

---

# Current Preview Runtime Features Covered

---

# 1. Voucher Preview Route

Verified:

```text
GET /api/x/v1/vouchers/code/{code}
```

returns:

- voucher information
- instruction payloads
- Rider runtime projections

---

# 2. Rider preClaim Projection

Verified:

```json
voucher.rider.preClaim
```

contains normalized splash-stage runtime content.

---

## Example Assertion

```php
->assertJsonPath(
    'data.voucher.rider.preClaim.content',
    'Pre-claim splash content.'
)
```

---

# 3. Rider Visual Stage Projection

Verified:

```json
voucher.rider.stages
```

contains normalized runtime stages.

---

## Covered Stage Types

Currently tested:

- splash
- image
- link

---

# 4. Payload Preservation

Verified:

```text
payload fields survive projection unchanged
```

Examples:

```php
->assertJsonPath(
    'data.voucher.rider.stages.stages.1.payload.label',
    'Learn more'
)
```

---

# 5. API Stability

The tests verify stable JSON projection paths.

This is important because:

```text
frontend runtimes depend on these contracts
```

---

# Canonical Projection Contracts

Currently validated:

---

# voucher.rider.preClaim

Example:

```json
{
  "enabled": true,
  "type": "markdown",
  "content": "Pre-claim splash content.",
  "meta": {
    "stage_key": "pre-claim-test",
    "timeout": 3
  }
}
```

---

# voucher.rider.stages

Example:

```json
{
  "stages": [
    {
      "type": "image",
      "key": "preview-image",
      "payload": {
        "src": "https://placehold.co/600x240",
        "alt": "Preview image"
      }
    }
  ]
}
```

---

# Why Stable Projection Matters

Frontend runtimes SHOULD consume:

```text
normalized runtime projections
```

instead of raw YAML.

This prevents:

- frontend normalization duplication
- runtime ambiguity
- compatibility drift
- YAML interpretation inconsistencies

---

# Preview Runtime Philosophy

The preview runtime intentionally follows:

```text
backend semantics
frontend rendering
```

instead of:

```text
backend rendering logic
```

This separation enables:

- reusable runtimes
- portable experiences
- future x-ray extraction
- multi-platform rendering

---

# Current Runtime Consumers

Current consumers include:

- ClaimWidget.vue
- voucher preview UI
- Rider pre-claim rendering
- inline Rider rendering

Future consumers may include:

- x-ray runtime
- kiosk runtime
- sponsor runtime
- mobile runtime

---

# Future Runtime Evolution

Planned future runtime capabilities include:

- modal runtime
- fullscreen runtime
- stage sequencing
- sponsor campaigns
- interactive stages
- richer media stages
- disclosure runtime extraction

The current tests establish the stable API foundation required for that evolution.

---

# Why These Tests Are Important

These tests protect against:

- accidental API contract breakage
- Rider projection regressions
- lifecycle/runtime coupling
- normalization duplication
- frontend/backend semantic drift

They act as:

```text
architectural contract tests
```

for the preview runtime.

---

# Recommended Future Tests

Future runtime tests may include:

- modal stage projection
- fullscreen stage projection
- analytics projection
- redirect projection
- stage sequencing
- stage filtering
- sponsor runtime payloads
- x-ray disclosure runtime

---

# Relationship to x-rider Tests

x-change tests validate:

```text
projection integration
```

x-rider tests validate:

```text
runtime normalization semantics
```

Both layers are intentionally separate.

---

# Guiding Principle

A useful heuristic:

| Concern | Test Suite |
|---|---|
| "Did the Rider normalize correctly?" | x-rider |
| "Did x-change expose the Rider correctly?" | x-change |
| "Did the UI render correctly?" | frontend/UI tests |

This separation should be preserved as the runtime evolves.

---

# Final Architectural Summary

The preview runtime test suite proves:

```text
x-change can safely orchestrate Rider runtime experiences
without owning Rider runtime semantics.
```

This is one of the most important architectural guarantees in the ecosystem.
