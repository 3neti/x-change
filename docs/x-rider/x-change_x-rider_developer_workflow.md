# Developer Workflow
## Canonical Workflow for x-change and x-rider Development

---

# Purpose

This document exists to prevent one of the most common and costly development mistakes in the x-change ecosystem:

```text
editing published host assets instead of package sources
```

This mistake causes:

- disappearing changes
- empty git diffs
- overwritten files
- stale frontend builds
- inconsistent runtime behavior
- confusion inside IDEs

This document defines the canonical development workflow for working with:

- x-change
- x-rider
- published assets
- Vite frontend runtime

---

# Canonical Rule

## ALWAYS EDIT PACKAGE SOURCES FIRST

```text
Edit package sources first.
Never edit published host assets directly.
```

Published assets inside the host app are generated artifacts.

They are NOT the source of truth.

---

# Canonical Source Locations

---

# x-change Package Source

Canonical source:

```text
packages/x-change/resources/js/...
```

Examples:

```text
packages/x-change/resources/js/components/x-change/ClaimWidget.vue
packages/x-change/resources/js/composables/useVoucherPreview.ts
packages/x-change/resources/js/pages/x-change/claim/Success.vue
```

These are the authoritative frontend sources for x-change.

---

# x-rider Package Source

Canonical source:

```text
/Users/rli/PhpstormProjects/packages/x-rider/resources/js/...
```

Examples:

```text
/Users/rli/PhpstormProjects/packages/x-rider/resources/js/components/x-rider/RiderRenderer.vue
/Users/rli/PhpstormProjects/packages/x-rider/resources/js/components/x-rider/RiderStagePresenter.vue
```

These are the authoritative frontend runtime sources for x-rider.

---

# Published Host Assets

Examples:

```text
resources/js/components/x-rider
resources/js/pages/x-change
resources/js/components/x-change
```

These are generated copies.

They may be overwritten at any time.

---

# Correct Development Workflow

---

# Step 1 — Edit Package Sources

Examples:

```text
packages/x-change/resources/js/...
```

or:

```text
/Users/rli/PhpstormProjects/packages/x-rider/resources/js/...
```

---

# Step 2 — Publish Assets

Run:

```bash
php artisan x-change:install --force
```

This republishes package frontend assets into the host app.

---

# Step 3 — Rebuild Frontend Assets

Run:

```bash
npm run build
```

or during development:

```bash
npm run dev
```

---

# Why npm run build Matters

The frontend is served through:

```text
Vite
```

Vite bundles the published host assets.

Even if package files are correct:

```text
browser may still serve stale compiled JavaScript
```

until the frontend build pipeline is refreshed.

---

# IMPORTANT

After publishing assets:

```bash
php artisan x-change:install --force
```

you should assume:

```text
compiled frontend assets are stale
```

until rebuilding.

---

# Common Failure Modes

---

# Failure Mode #1
## Wrong File Edited

### Symptoms

- changes disappear
- git status empty
- UI unchanged
- package commits missing expected diffs
- functionality appears reverted

---

## Cause

You edited:

```text
published host assets
```

instead of:

```text
package source files
```

---

## Example

WRONG:

```text
resources/js/components/x-change/ClaimWidget.vue
```

RIGHT:

```text
packages/x-change/resources/js/components/x-change/ClaimWidget.vue
```

---

# Failure Mode #2
## Publish Overwrites Changes

### Symptoms

- edits suddenly disappear
- changes revert after install
- package behavior appears inconsistent

---

## Cause

Running:

```bash
php artisan x-change:install --force
```

overwrites published host copies.

This is expected behavior.

Published assets are disposable/generated artifacts.

---

# Failure Mode #3
## Browser Still Shows Old UI

### Symptoms

- code changes exist
- published assets updated
- UI still unchanged
- old runtime behavior persists

---

## Cause

Frontend assets were not rebuilt.

---

## Fix

Run:

```bash
npm run build
```

or:

```bash
npm run dev
```

---

# Failure Mode #4
## Git Diff Appears Empty

### Symptoms

```bash
git status
```

does not show expected changes.

---

## Cause

You edited generated host copies excluded from source control or later overwritten.

---

# Recommended IDE Practices

---

# Recommended Project Awareness

Be consciously aware of:

```text
PACKAGE SOURCE
vs
PUBLISHED HOST COPY
```

especially when multiple projects are open simultaneously.

---

# Recommended Package Editing Habit

When editing frontend runtime behavior:

ask first:

```text
Which package actually owns this responsibility?
```

Examples:

| Responsibility | Package |
|---|---|
| claim flow UI | x-change |
| stage rendering | x-rider |
| preview runtime | x-change |
| stage semantics | x-rider |

---

# Recommended Git Workflow

Commit package changes separately.

Example:

## x-rider

```bash
cd /Users/rli/PhpstormProjects/packages/x-rider
git status
git add ...
git commit
```

---

## x-change

```bash
cd /Users/rli/PhpstormProjects/x-change-sandbox/packages/x-change
git status
git add ...
git commit
```

This preserves clean package boundaries.

---

# Source Ownership Principle

A useful heuristic:

```text
If the file can be regenerated,
it is probably NOT the source of truth.
```

Published frontend assets are generated artifacts.

Canonical sources always live inside the packages.

---

# Long-Term Architectural Direction

The ecosystem intentionally separates:

| Layer | Responsibility |
|---|---|
| x-change | lifecycle orchestration |
| x-rider | runtime normalization |
| x-ray (future) | runtime rendering/inspection |
| host app | branded deployment |

This workflow preserves those package boundaries during development.

---

# Recommended Future Improvement

Potential future enhancement:

Add header comments to published assets:

```ts
/**
 * GENERATED FILE
 *
 * Source of truth:
 * packages/x-change/resources/js/...
 *
 * Republished via:
 * php artisan x-change:install --force
 */
```

This would significantly reduce accidental editing confusion.

---

# Final Guiding Principle

The safest mental model is:

```text
Packages are authored.
Host assets are generated.
```

Always edit authored sources first.
