# Phase 5.9 — Documentation + Package Boundary Cleanup
## Strategy and Scaffold Plan

---

# Goal

Formalize the architectural boundaries between:

- x-change
- x-rider
- future x-ray
- host application

while stabilizing the preview runtime and preventing future editing confusion.

This phase focuses on:

```text
clarity
ownership
boundaries
developer ergonomics
future extraction readiness
```

rather than new runtime capabilities.

---

# Primary Objectives

## 1. Document Package Responsibilities

Clearly define:

| Package | Responsibility |
|---|---|
| x-change | voucher lifecycle + orchestration |
| x-rider | runtime experience normalization |
| x-ray (future) | preview + disclosure rendering |
| host app | composition + branding + deployment |

---

## 2. Prevent Host-vs-Package Editing Mistakes

You repeatedly encountered:

```text
editing the published host asset
instead of the package source
```

This phase documents:

- authoritative sources
- publish flow
- overwrite behavior
- npm build requirements
- safe editing workflow

---

## 3. Stabilize Preview Runtime Architecture

Document:

- RiderExperienceData
- preClaim
- stage runtime
- stage payloads
- presentation modes
- projection flow

---

## 4. Prepare for x-ray Extraction

The preview runtime is now sufficiently mature to become its own package later.

This phase prepares:

```text
preview runtime
inspection runtime
x-ray rendering runtime
```

without yet extracting them.

---

# Proposed Documentation Additions

---

# A. x-change/docs/package_boundaries.md

## Purpose

Canonical package ownership reference.

## Content

### x-change Owns

- lifecycle orchestration
- voucher APIs
- lifecycle services
- settlement flows
- claim flows
- preview orchestration
- RiderExperience projection integration

### x-rider Owns

- stage normalization
- RiderExperienceData
- RiderStageData
- stage runtime semantics
- presentation mode semantics
- redirect normalization
- preClaim normalization

### x-ray (Future) Will Own

- disclosure runtime
- inspection runtime
- preview UI runtime
- experience visualization
- safe public projection

### Host App Owns

- branding
- themes
- logos
- published assets
- deployment
- business composition

---

# B. x-change/docs/developer_workflow.md

## Purpose

Prevent future IDE/package confusion.

## Content

### Canonical Rule

```text
Edit package sources first.
Never edit published host assets directly.
```

---

## Correct Workflow

### x-change package source

```text
packages/x-change/resources/js/...
```

### x-rider package source

```text
/Users/rli/PhpstormProjects/packages/x-rider/resources/js/...
```

---

## Publish Workflow

```bash
php artisan x-change:install --force
npm run build
```

---

## Why npm run build Matters

Because Vite bundles the published host assets.

Without rebuilding:

```text
browser still serves stale compiled JS
```

---

## Common Failure Modes

### Wrong File Edited

Symptoms:

- changes disappear
- git status empty
- UI unchanged

Cause:

```text
edited published host asset instead of package source
```

---

### Publish Overwrites Changes

Cause:

```bash
php artisan x-change:install --force
```

overwrites host copies.

---

# C. x-rider/docs/presentation_modes.md

## Purpose

Formalize runtime presentation semantics.

## Content

### Supported Modes

```yaml
presentation: inline
presentation: modal
presentation: fullscreen
```

---

## inline

Rendered inside existing page flow.

Used for:

- notices
- warnings
- splash cards
- embedded media

---

## modal

Rendered as blocking overlay.

Used for:

- confirmations
- disclosures
- onboarding

---

## fullscreen

Takes over entire viewport.

Used for:

- campaigns
- sponsor experiences
- branded onboarding
- immersive rider experiences

---

## Future Modes

Document reserved possibilities:

```yaml
presentation: kiosk
presentation: terminal
presentation: overlay
presentation: interstitial
```

---

# D. x-rider/docs/stage_payload_contracts.md

## Purpose

Define stable runtime payload shapes.

## Sections

### splash

```yaml
- type: splash
  content:
  content_type:
  presentation:
```

---

### message

```yaml
- type: message
  content:
```

---

### link

```yaml
- type: link
  payload:
    label:
    url:
```

---

### image

```yaml
- type: image
  payload:
    src:
    alt:
```

---

## Runtime Guarantee

Payloads are:

```text
transported faithfully
not reinterpreted by x-change
```

---

# E. x-change/docs/preview_runtime.md

## Purpose

Document the entire preview projection architecture.

## Sections

### Lifecycle Projection

```text
Voucher
→ Lifecycle Service
→ Rider Resolver
→ RiderExperienceData
→ API Resource
→ Preview Runtime
```

---

### Preview API Contract

Explain:

```json
voucher.instructions
voucher.rider.preClaim
voucher.rider.stages
```

---

### Why RiderExperienceData Exists

Explain normalization rationale.

---

### Why Tests Mock RiderExperienceResolverContract

To preserve package boundaries.

---

# F. x-change/tests/README_preview_runtime.md

## Purpose

Document test philosophy.

## Explain

### Contract Tests

```text
x-change tests projection contract
NOT x-rider internals
```

### Why Mocking Is Correct

Because x-rider owns runtime resolution.

x-change owns API exposure.

---

# Recommended Cleanup Work

---

## 1. Add Comments to Shared Types

Example:

```ts
/**
 * Canonical runtime stage payload
 * projected from RiderExperienceData.
 */
```

---

## 2. Add Source Ownership Comments

Example at top of published assets:

```ts
/**
 * SOURCE OF TRUTH:
 * packages/x-change/resources/js/...
 *
 * Published into host app via:
 * php artisan x-change:install --force
 */
```

This will save enormous confusion later.

---

## 3. Add Runtime Version Metadata

Possible future addition:

```json
"rider_runtime_version": "1.0"
```

for compatibility tracking.

---

# Recommended Git Commit Structure

## x-rider

```bash
git add docs/presentation_modes.md
git add docs/stage_payload_contracts.md
git add docs/stage_runtime.md
git add resources/js/components/x-rider/types.ts
```

Commit:

```text
docs(x-rider): formalize stage runtime and presentation contracts
```

---

## x-change

```bash
git add docs/package_boundaries.md
git add docs/developer_workflow.md
git add docs/preview_runtime.md
git add tests/README_preview_runtime.md
```

Commit:

```text
docs(x-change): formalize preview runtime and package boundaries
```

---

# Expected Outcome

After Phase 5.9:

- package responsibilities become explicit
- future contributors avoid editing confusion
- preview runtime architecture becomes canonical
- x-ray extraction becomes safer
- runtime semantics become stable
- frontend/runtime separation becomes documented

This phase is less visible than splash/image rendering work, but architecturally one of the most important stabilizing phases completed so far.
