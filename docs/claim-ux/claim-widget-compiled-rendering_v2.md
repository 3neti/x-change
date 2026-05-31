# ClaimWidget Compiled Rendering

## 1. Purpose

This document defines the compiled rendering contract implemented inside:

```text
resources/js/components/x-change/ClaimWidget.vue
```

`ClaimWidget.vue` is being migrated from legacy interpretation toward compiler-driven rendering.

The goal of this migration is not to immediately remove legacy behavior.

Instead, the goal is to introduce compiler-driven rendering paths that take precedence when available while preserving legacy fallback behavior throughout the migration.

This document describes the rendering ownership model, rendering regions, phase selection rules, form-flow ownership boundaries, renderer handoff contracts, and migration architecture currently implemented within ClaimWidget.

The document serves as the authoritative specification for ClaimWidget's compiler-aware rendering behavior.

---

## 2. Scope

ClaimWidget currently contains compiler-aware behavior for:

```text
rider_intro
runtime
redirect
form_flow (ownership boundary only)
```

These rendering paths coexist with legacy voucher-preview behavior.

Current rendering ownership can be summarized as:

```text
claimExperience.phases[]
        ↓
    rider_intro
        ↓
pre-claim-rider-region

claimExperience.phases[]
        ↓
      runtime
        ↓
claim-widget-runtime-region

claimExperience.phases[]
        ↓
     redirect
        ↓
claim-widget-redirect-region
```

Form flow currently participates only through ownership detection and renderer handoff.

ClaimWidget does not yet function as a compiled form engine.

If no usable compiled phase exists for a supported rendering region, ClaimWidget falls back to the existing legacy rider-preview implementation.

---

## 3. Architectural Overview

### 3.1 Ownership Map

Current ownership boundaries are:

```text
ClaimWidget
    owns:
        rider_intro
        runtime
        redirect
        form_flow boundary detection
        form_flow renderer handoff

Success.vue
    owns:
        success_rider
        redirect_countdown
        redirect_execution
```

Compiled phase existence does not imply rendering ownership.

Rendering ownership remains explicitly defined by component boundaries.

---

### 3.2 Rendering Regions

ClaimWidget currently exposes three compiler-aware rendering regions:

```text
ClaimWidget
    ├── pre-claim-rider-region
    │       ↓
    │   rider_intro
    │
    ├── claim-widget-runtime-region
    │       ↓
    │   runtime
    │
    └── claim-widget-redirect-region
            ↓
         redirect
```

The three regions serve distinct purposes:

```text
pre-claim-rider-region
    → content shown before claim interaction

claim-widget-runtime-region
    → content shown during claim widget runtime

claim-widget-redirect-region
    → redirect-adjacent experience content
```

Form-flow rendering ownership is handled separately through the form-flow boundary region and renderer handoff contract described later in this document.

---

## 4. Core Rendering Infrastructure

The compiled rendering system begins by extracting compiler-produced phase information from the voucher preview payload.

Every compiler-aware rendering path described later in this document ultimately depends on this infrastructure.

The infrastructure is responsible for:

```text
1. locating the voucher preview payload
2. locating claimExperience
3. extracting claimExperience.phases
4. locating phases by type
5. filtering visual stages
6. selecting rendering candidates
```

All higher-level rendering decisions are built on top of these primitives.

---

### 4.1 Props

ClaimWidget receives a voucher preview payload through its component props.

The voucher preview acts as the bridge between:

```text
Backend Compiler
        ↓
Voucher Preview Payload
        ↓
ClaimWidget
```

Compiled rendering is therefore driven entirely from payload data.

ClaimWidget does not construct compiled phases itself.

Instead, it consumes the phases already produced by the backend compiler.

---

### 4.2 Voucher Preview Source

Compiled rendering begins by locating:

```text
voucher.claimExperience
```

The presence of `claimExperience` is treated as the entry point into compiler-driven rendering.

Conceptually:

```text
voucher
    └── claimExperience
            └── phases[]
```

If `claimExperience` is absent, compiled rendering cannot participate and legacy behavior remains active.

This allows compiled and legacy systems to coexist during migration.

---

### 4.3 Stage Extraction

ClaimWidget extracts phase information from:

```text
claimExperience.phases
```

Conceptually:

```text
claimExperience
    └── phases[]
            ├── rider_intro
            ├── runtime
            ├── redirect
            └── ...
```

Each phase represents a compiler-produced rendering instruction.

ClaimWidget does not assume that every phase is renderable.

Phase interpretation occurs through dedicated selection logic.

---

### 4.4 Compiled Phase Lookup

Compiled rendering relies on locating phases by type.

Conceptually:

```text
find phase
    where phase.type === desiredType
```

Examples:

```text
rider_intro
runtime
redirect
form_flow
```

The lookup process is intentionally narrow.

ClaimWidget only searches for phase types that it explicitly owns.

Unknown phase types are ignored.

This prevents accidental rendering of compiler features that belong to other components.

---

### 4.5 Compiled Phase Stages

A located phase may contain one or more stages.

Conceptually:

```text
phase
    └── stages[]
```

Stages represent concrete rendering instructions.

Example:

```text
runtime
    └── stages[]
            ├── message
            ├── image
            └── link
```

Rendering logic operates on stages rather than directly on phases.

This allows a single phase to produce multiple visual artifacts.

---

### 4.6 Visual Stage Filtering

Not every stage is visual.

Before rendering, ClaimWidget filters stages into renderable candidates.

Conceptually:

```text
phase.stages[]
        ↓
visual stage filter
        ↓
renderable stages
```

Examples of visual stages include:

```text
message
image
link
html
```

Examples of non-visual stages may include:

```text
navigation
execution
runtime-only metadata
```

Non-visual stages are ignored by visual rendering regions.

This separation allows the compiler to express operational behavior without requiring every stage to be rendered.

Visual stage filtering is the final step before region-specific rendering begins.

The next sections describe how Rider Intro, Runtime, and Redirect regions consume the filtered stage set.
