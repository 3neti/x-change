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

---

## 5. Rider Intro Rendering

Rider Intro Rendering is the first compiler-aware rendering region implemented within ClaimWidget.

Its purpose is to render visual content before the user begins interacting with the claim experience.

Conceptually:

```text
claimExperience.phases[]
        ↓
    rider_intro
        ↓
pre-claim-rider-region
```

This region serves as the migration replacement for legacy rider-preview reconstruction logic.

Compiled rendering is preferred whenever a valid compiled Rider Intro phase exists.

Legacy rider rendering remains available as a fallback.

---

### 5.1 Legacy Resolution

Before compiler-driven rendering existed, ClaimWidget reconstructed rider content directly from voucher preview metadata.

Conceptually:

```text
voucher preview
        ↓
legacy reconstruction
        ↓
rider display
```

This reconstruction process remains in place for compatibility.

Legacy rendering is only used when no usable compiled Rider Intro phase can be resolved.

This ensures that existing vouchers continue to render correctly while compiler adoption progresses.

---

### 5.2 Compiled Stages

Compiled Rider Intro rendering begins by locating:

```text
phase.type === "rider_intro"
```

Conceptually:

```text
claimExperience.phases[]
        ↓
 locate rider_intro
        ↓
     phase
        ↓
    stages[]
```

Only stages that survive visual-stage filtering participate in rendering.

Example:

```text
rider_intro
    └── stages[]
            ├── message
            ├── image
            └── link
```

The rendered output is driven entirely by the stage collection.

ClaimWidget does not generate Rider Intro content itself.

---

### 5.3 Legacy Stages

Legacy rider rendering continues to operate using voucher-preview rider structures.

Conceptually:

```text
voucher preview
        ↓
 legacy riders
        ↓
 rider display
```

The legacy path remains important because not all vouchers currently provide compiled Rider Intro phases.

Until migration is complete, both systems coexist.

---

### 5.4 Rendering Priority

Rendering priority follows a compiler-first model.

Conceptually:

```text
compiled rider_intro exists
        ↓
      yes
        ↓
 use compiled rendering

      otherwise

 use legacy rendering
```

This rule allows compiler-generated content to gradually replace reconstruction logic without introducing breaking changes.

The renderer never attempts to merge compiled and legacy rider content.

A single rendering strategy is selected.

---

### 5.5 Visual Region

Compiled Rider Intro stages are rendered into:

```text
pre-claim-rider-region
```

Conceptually:

```text
ClaimWidget
    └── pre-claim-rider-region
            ↓
         rider_intro
```

The region is intentionally isolated from runtime and redirect rendering.

This separation allows each phase family to evolve independently.

---

### 5.6 Rules

The Rider Intro renderer follows the following rules.

#### Compiled Wins

When a valid compiled Rider Intro phase exists, compiled rendering takes precedence.

```text
compiled rider_intro
        ↓
    render
```

Legacy reconstruction is bypassed.

---

#### Legacy Still Works

If no compiled Rider Intro phase exists, ClaimWidget falls back to legacy rider reconstruction.

```text
no compiled rider_intro
        ↓
 legacy rendering
```

This preserves compatibility with older vouchers.

---

#### Inactive Compiled Phases Are Ignored

A Rider Intro phase that is not active does not participate in rendering.

Conceptually:

```text
inactive phase
        ↓
      skip
```

Only active phases are eligible.

---

#### Non-Visual Compiled Stages Are Ignored

Operational stages are not rendered inside the Rider Intro region.

Examples include:

```text
navigation
execution
runtime metadata
```

Only visual stages are eligible for display.

---

#### Multiple Compiled Visual Stages Preserve Order

When multiple visual stages are present, display order follows the compiler-produced stage order.

Conceptually:

```text
stage[0]
stage[1]
stage[2]
```

No additional sorting is performed by ClaimWidget.

The compiler remains the source of truth for stage sequencing.

---

## 6. Runtime Rendering

Runtime Rendering is responsible for displaying compiler-produced content during claim execution.

Conceptually:

```text
claimExperience.phases[]
        ↓
      runtime
        ↓
claim-widget-runtime-region
```

Unlike Rider Intro rendering, which focuses on pre-claim experience content, Runtime rendering exists to support content that should be visible while the claim experience is actively executing.

Runtime rendering follows the same compiler-first philosophy used throughout ClaimWidget.

Compiled runtime phases take precedence whenever available.

Legacy behavior remains available as a fallback path.

---

### 6.1 Compiled Runtime Stages

Compiled Runtime rendering begins by locating:

```text
phase.type === "runtime"
```

Conceptually:

```text
claimExperience.phases[]
        ↓
   locate runtime
        ↓
      phase
        ↓
     stages[]
```

Visual-stage filtering is then applied.

Example:

```text
runtime
    └── stages[]
            ├── message
            ├── image
            ├── html
            └── link
```

Only renderable visual stages participate in Runtime rendering.

ClaimWidget does not generate runtime content itself.

The compiler remains the source of truth.

---

### 6.2 Legacy Runtime Stages

Legacy runtime behavior may still be derived from voucher-preview structures.

Conceptually:

```text
voucher preview
        ↓
 legacy runtime
        ↓
 runtime display
```

The legacy path exists solely for backward compatibility.

As compiler adoption increases, runtime rendering should increasingly originate from compiler-produced phases.

---

### 6.3 Rendering Priority

Runtime rendering follows a compiler-first selection model.

Conceptually:

```text
compiled runtime exists
        ↓
      yes
        ↓
 use compiled runtime

      otherwise

 use legacy runtime
```

The renderer never attempts to combine compiled and legacy runtime content.

A single rendering strategy is selected.

This keeps runtime behavior deterministic.

---

### 6.4 Runtime Region

Compiled runtime stages render into:

```text
claim-widget-runtime-region
```

Conceptually:

```text
ClaimWidget
    └── claim-widget-runtime-region
            ↓
           runtime
```

This region is isolated from:

```text
pre-claim-rider-region
claim-widget-redirect-region
```

Each rendering region owns a distinct experience surface.

---

### 6.5 Rules

Runtime rendering follows the following rules.

#### Compiled Runtime Wins

When a valid compiled Runtime phase exists, compiled rendering takes precedence.

```text
compiled runtime
        ↓
      render
```

Legacy reconstruction is bypassed.

---

#### Legacy Runtime Still Works

When no compiled Runtime phase exists, legacy runtime rendering remains available.

```text
no compiled runtime
        ↓
 legacy runtime
```

This preserves compatibility during migration.

---

#### Inactive Runtime Phases Are Ignored

Inactive runtime phases do not participate in rendering.

Conceptually:

```text
inactive runtime
        ↓
      skip
```

Only active phases are eligible.

---

#### Non-Visual Runtime Stages Are Ignored

Runtime phases may contain operational stages that are not intended for display.

Examples:

```text
execution
navigation
internal metadata
```

These stages are ignored by visual rendering logic.

Only renderable stages are displayed.

---

#### Multiple Runtime Stages Preserve Order

When multiple visual runtime stages exist, stage ordering follows compiler output.

Conceptually:

```text
stage[0]
stage[1]
stage[2]
```

ClaimWidget does not reorder runtime stages.

The compiler remains the authoritative sequencing source.

---

### 6.6 Debug Signals

Runtime rendering exposes several useful diagnostic signals during migration.

Conceptually:

```text
compiled runtime found
compiled runtime selected
legacy runtime selected
runtime stage count
renderable runtime stage count
```

These signals help verify:

```text
phase lookup
phase ownership
visual filtering
fallback behavior
```

The signals are intended to support migration validation and future test coverage.

They should not be interpreted as part of the user-facing runtime experience.

During migration, these diagnostics provide confidence that runtime rendering is being sourced from the correct ownership path.

---

## 7. Redirect Rendering

Redirect Rendering is responsible for displaying compiler-produced content associated with redirect experiences.

Conceptually:

```text
claimExperience.phases[]
        ↓
     redirect
        ↓
claim-widget-redirect-region
```

Redirect rendering is distinct from redirect execution.

The Redirect phase owns presentation.

Navigation behavior remains separately controlled and may be owned by other components.

This distinction is important during migration because visual redirect content and actual redirect actions do not necessarily occur at the same time.

---

### 7.1 Compiled Redirect Stages

Compiled Redirect rendering begins by locating:

```text
phase.type === "redirect"
```

Conceptually:

```text
claimExperience.phases[]
        ↓
   locate redirect
        ↓
      phase
        ↓
     stages[]
```

Visual-stage filtering is then applied.

Example:

```text
redirect
    └── stages[]
            ├── message
            ├── image
            ├── link
            └── html
```

Only visual stages participate in Redirect rendering.

ClaimWidget does not create redirect content.

All content originates from compiler-produced stages.

---

### 7.2 Legacy Redirect Stages

Legacy redirect behavior may still be reconstructed from voucher preview data.

Conceptually:

```text
voucher preview
        ↓
 legacy redirect
        ↓
 redirect display
```

The legacy path remains available for backward compatibility.

During migration, both compiled and legacy approaches coexist.

The compiler-first selection model determines which path is used.

---

### 7.3 Rendering Priority

Redirect rendering follows the same compiler-first model used by Rider Intro and Runtime rendering.

Conceptually:

```text
compiled redirect exists
        ↓
      yes
        ↓
 use compiled redirect

      otherwise

 use legacy redirect
```

Only one rendering strategy is selected.

Compiled and legacy redirect content are never merged.

This ensures deterministic rendering behavior.

---

### 7.4 Redirect Region

Compiled Redirect stages render into:

```text
claim-widget-redirect-region
```

Conceptually:

```text
ClaimWidget
    └── claim-widget-redirect-region
            ↓
          redirect
```

The region is independent from:

```text
pre-claim-rider-region
claim-widget-runtime-region
```

Each rendering region owns a distinct portion of the claim experience.

This separation allows redirect presentation to evolve independently from runtime and pre-claim content.

---

### 7.5 Rules

Redirect rendering follows the following rules.

#### Compiled Redirect Wins

When a valid compiled Redirect phase exists, compiled rendering takes precedence.

```text
compiled redirect
        ↓
      render
```

Legacy reconstruction is bypassed.

---

#### Legacy Redirect Still Works

If no compiled Redirect phase exists, legacy redirect rendering remains available.

```text
no compiled redirect
        ↓
 legacy redirect
```

This preserves compatibility with vouchers that have not yet been migrated.

---

#### Inactive Redirect Phases Are Ignored

Inactive Redirect phases do not participate in rendering.

Conceptually:

```text
inactive redirect
        ↓
      skip
```

Only active phases are eligible.

---

#### Non-Visual Redirect Stages Are Ignored

Redirect phases may contain operational instructions that are not intended for display.

Examples:

```text
navigation
execution
redirect metadata
```

These stages are ignored by visual rendering logic.

Only visual stages are rendered.

---

#### Redirect Does Not Mean Navigation

The existence of a Redirect phase does not automatically trigger navigation.

Conceptually:

```text
redirect phase
        ↓
 visual presentation
```

is distinct from:

```text
redirect execution
        ↓
 browser navigation
```

Rendering ownership and execution ownership remain separate concerns.

This distinction is particularly important during migration because Redirect rendering currently belongs to ClaimWidget while redirect execution remains outside the Redirect rendering region.

---

#### Multiple Redirect Stages Preserve Order

When multiple visual Redirect stages exist, stage ordering follows compiler output.

Conceptually:

```text
stage[0]
stage[1]
stage[2]
```

ClaimWidget does not reorder stages.

The compiler remains the authoritative sequencing source.

---

### 7.6 Debug Signals

Redirect rendering exposes diagnostic signals that help validate migration behavior.

Conceptually:

```text
compiled redirect found
compiled redirect selected
legacy redirect selected
redirect stage count
renderable redirect stage count
```

These diagnostics help verify:

```text
phase lookup
phase ownership
visual filtering
fallback behavior
```

The signals exist to support migration validation and future test coverage.

They are not intended to be user-facing features.

During migration, they provide confidence that Redirect rendering is being sourced from the correct ownership path.

---

### Redirect Rendering Summary

Redirect rendering completes the three primary ClaimWidget-owned rendering regions:

```text
rider_intro
runtime
redirect
```

All three regions share the same architectural principles:

```text
phase lookup
        ↓
visual filtering
        ↓
compiled-first selection
        ↓
region rendering
        ↓
legacy fallback
```

The next section introduces a different architectural concern:

```text
form_flow
```

Unlike Rider Intro, Runtime, and Redirect, Form Flow introduces ownership boundaries, renderer delegation, metadata normalization, and specialized rendering infrastructure.

For that reason, Form Flow is documented separately from the primary rendering regions.

---

# 8. Form Flow Ownership & Rendering

Form Flow introduces a different architectural concern from Rider Intro, Runtime, and Redirect rendering.

The previous rendering regions are primarily concerned with:

```text
phase lookup
        ↓
visual stage rendering
```

Form Flow introduces additional responsibilities:

```text
ownership detection
        ↓
boundary selection
        ↓
renderer delegation
        ↓
metadata normalization
        ↓
field rendering
```

For this reason, Form Flow is documented separately from the primary rendering regions.

The Form Flow architecture is intentionally structured so that ClaimWidget can eventually stop rendering form content directly and instead delegate responsibility to specialized rendering infrastructure.

Current implementation focuses on ownership detection and rendering delegation rather than full form execution.

---

## 8.1 Ownership Boundary

Form Flow rendering begins by determining ownership.

Unlike Rider Intro, Runtime, and Redirect, the question is not immediately:

```text
what should be rendered?
```

Instead, the first question is:

```text
who owns rendering?
```

The ownership boundary determines whether Form Flow participates in the current claim experience.

Conceptually:

```text
claimExperience.phases[]
        ↓
    form_flow
        ↓
 ownership boundary
        ↓
 renderer delegation
```

This boundary exists because Form Flow is expected to evolve into its own rendering system.

ClaimWidget currently acts as the orchestration layer rather than the long-term rendering owner.

---

### Compiled Form Flow Boundary

Compiled Form Flow ownership begins by locating:

```text
phase.type === "form_flow"
```

Conceptually:

```text
claimExperience.phases[]
        ↓
 locate form_flow
        ↓
 ownership boundary
```

The existence of a compiled Form Flow phase signals that Form Flow rendering infrastructure may participate.

Unlike Rider Intro, Runtime, and Redirect phases, locating the phase does not immediately trigger rendering.

Instead, the phase establishes eligibility for renderer delegation.

---

### Legacy Form Flow Boundary

Legacy Form Flow ownership may still originate from voucher preview structures.

Conceptually:

```text
voucher preview
        ↓
 legacy form flow
        ↓
 ownership boundary
```

This path remains available during migration.

Legacy compatibility allows existing vouchers to continue functioning while compiler-generated Form Flow phases are introduced incrementally.

---

### Boundary Rules

Ownership follows a compiler-first model.

Conceptually:

```text
compiled form_flow exists
        ↓
      yes
        ↓
 compiled ownership

      otherwise

 legacy ownership
```

Only one ownership path is selected.

ClaimWidget does not merge compiled and legacy ownership models.

The selected ownership path determines how renderer delegation proceeds.

---

### Why This Is Boundary-Only

At this stage of migration, ClaimWidget does not yet function as a complete form engine.

Current responsibilities are intentionally limited:

```text
phase discovery
ownership detection
renderer handoff
```

Responsibilities that remain outside current scope include:

```text
interactive form execution
validation
submission
workflow progression
```

These concerns are expected to evolve into specialized Form Flow infrastructure over time.

The ownership boundary exists specifically to allow that future separation.

---

### Ownership Region

Form Flow rendering is isolated from the primary visual rendering regions.

Conceptually:

```text
ClaimWidget
    ├── pre-claim-rider-region
    ├── claim-widget-runtime-region
    ├── claim-widget-redirect-region
    └── form-flow-boundary-region
```

The Form Flow region serves as the entry point into delegated rendering.

Unlike Rider Intro, Runtime, and Redirect, this region does not directly imply visual output.

Instead, it establishes the rendering context that specialized renderers consume.

---

### Ownership Debug Signals

Form Flow ownership exposes migration diagnostics.

Conceptually:

```text
compiled form_flow found
legacy form_flow found
compiled ownership selected
legacy ownership selected
renderer handoff initiated
```

These diagnostics help validate:

```text
ownership detection
boundary selection
fallback behavior
delegation flow
```

The signals are intended to support migration validation and future automated testing.

They are not part of the user-facing experience.

---

### Ownership Boundary Summary

The Form Flow ownership boundary introduces a new architectural pattern into ClaimWidget:

```text
phase lookup
        ↓
ownership determination
        ↓
renderer delegation
```

This differs from Rider Intro, Runtime, and Redirect rendering:

```text
phase lookup
        ↓
visual rendering
```

The distinction is intentional.

Form Flow is designed to become increasingly renderer-driven over time.

Ownership detection is therefore the first layer of that architecture.

The next section introduces the object responsible for selecting the active Form Flow ownership path:

```text
Boundary Selection Object
```

---

## 8.2 Boundary Selection Object

The ownership boundary determines whether Form Flow participates in the current claim experience.

The Boundary Selection Object determines which ownership path becomes active.

Conceptually:

```text
ownership boundary
        ↓
boundary selection object
        ↓
active ownership path
        ↓
renderer delegation
```

The Boundary Selection Object exists to centralize ownership decisions.

Without a dedicated selection object, ownership logic would become scattered across multiple rendering paths.

By concentrating ownership resolution into a single structure, ClaimWidget maintains a predictable migration path while allowing future Form Flow infrastructure to evolve independently.

---

### Compiled Mode

Compiled mode is activated when a valid compiled Form Flow phase is discovered.

Conceptually:

```text
claimExperience.phases[]
        ↓
 locate form_flow
        ↓
 phase exists
        ↓
 compiled mode
```

Compiled mode establishes that the compiler is now the source of truth for Form Flow ownership.

Once selected, all downstream renderer decisions originate from compiler-produced metadata.

The renderer does not attempt to reconstruct ownership from legacy voucher-preview structures.

---

### Legacy Mode

Legacy mode remains available for backward compatibility.

Conceptually:

```text
no compiled form_flow
        ↓
 legacy structures exist
        ↓
     legacy mode
```

Legacy mode preserves compatibility with vouchers created before compiler-driven Form Flow ownership was introduced.

The existence of legacy mode allows migration to proceed incrementally without requiring all voucher experiences to be upgraded simultaneously.

---

### Selection Logic

Boundary selection follows a deterministic compiler-first model.

Conceptually:

```text
compiled form_flow exists
        ↓
      yes
        ↓
 compiled mode

      otherwise

 legacy mode
```

Selection is intentionally simple.

The Boundary Selection Object does not perform ranking, scoring, merging, or reconciliation.

Only one ownership path becomes active.

This guarantees predictable renderer behavior.

---

### Active Boundary Contract

Once selection completes, the Boundary Selection Object produces an active ownership contract.

Conceptually:

```text
boundary selection
        ↓
 active contract
```

The active contract contains the information required for downstream renderer delegation.

Examples include:

```text
ownership source
selected mode
phase reference
rendering metadata
delegation eligibility
```

The exact structure may evolve over time.

However, the responsibility remains consistent:

```text
ownership decision
        ↓
 delegation contract
```

---

### Derived Signals

The Boundary Selection Object exposes several derived signals.

Conceptually:

```text
hasCompiledFormFlow
hasLegacyFormFlow
selectedMode
selectedPhase
delegationEligible
```

These signals simplify renderer logic.

Rather than repeatedly evaluating ownership rules, downstream rendering infrastructure consumes the already-selected contract.

This reduces duplication and keeps ownership concerns isolated.

---

### Why This Refactor Exists

Historically, Form Flow ownership decisions were intertwined with rendering logic.

Conceptually:

```text
ownership logic
        ↓
rendering logic
        ↓
field rendering
```

This coupling made migration difficult because ownership and rendering evolved together.

The Boundary Selection Object intentionally separates:

```text
ownership
```

from

```text
rendering
```

This separation allows:

```text
ownership evolution
renderer evolution
field renderer evolution
```

to proceed independently.

As Form Flow infrastructure matures, ownership logic can remain stable even while rendering capabilities expand.

---

### Boundary Lifecycle

The lifecycle of the Boundary Selection Object can be summarized as:

```text
claimExperience.phases[]
        ↓
 ownership detection
        ↓
 boundary selection
        ↓
 active contract
        ↓
 renderer delegation
```

At no point does the Boundary Selection Object render content.

Its sole responsibility is ownership resolution.

This distinction is important because future Form Flow infrastructure may replace renderers without affecting ownership selection.

---

### Selection Region

The Boundary Selection Object operates entirely within the Form Flow ownership region.

Conceptually:

```text
ClaimWidget
    └── form-flow-boundary-region
            ↓
     boundary selection
            ↓
      active contract
```

The resulting contract becomes the input for renderer delegation.

The boundary region therefore serves as the bridge between:

```text
ownership determination
```

and

```text
rendering execution
```

---

### Boundary Debug Signals

The Boundary Selection Object exposes migration diagnostics.

Conceptually:

```text
compiled mode selected
legacy mode selected
active phase identified
delegation contract created
delegation eligible
```

These diagnostics help validate:

```text
ownership selection
compiler participation
fallback behavior
delegation readiness
```

The signals are intended for migration validation and automated testing.

They are not user-facing features.

---

### Boundary Selection Summary

The Boundary Selection Object introduces a dedicated ownership-resolution layer into the Form Flow architecture.

Conceptually:

```text
ownership boundary
        ↓
boundary selection object
        ↓
active ownership contract
```

This layer exists specifically to isolate ownership decisions from rendering behavior.

The result is a more stable migration path and a cleaner separation of responsibilities.

The next section introduces the first rendering-oriented responsibility in the Form Flow architecture:

```text
Renderer Handoff
```

where the active ownership contract becomes a delegated rendering experience.

---

## 8.5 Placeholder Renderer Handoff

The Boundary Selection Object determines ownership.

Renderer Handoff determines execution.

Conceptually:

```text
ownership boundary
        ↓
boundary selection
        ↓
active contract
        ↓
renderer handoff
        ↓
FormFlowRenderer
```

This handoff represents the point where ClaimWidget transitions from ownership orchestration into delegated rendering.

The handoff architecture exists to allow Form Flow rendering to evolve independently from ClaimWidget.

ClaimWidget remains responsible for determining:

```text
ownership
phase eligibility
delegation readiness
```

while delegated renderers become responsible for:

```text
metadata interpretation
field rendering
preview rendering
future form execution
```

---

### Placeholder Renderer

Current implementation uses a placeholder renderer architecture.

Conceptually:

```text
ClaimWidget
        ↓
 FormFlowRenderer
        ↓
 placeholder output
```

The placeholder renderer exists to establish the rendering contract before introducing full interactive form capabilities.

The objective of the placeholder phase is validation.

Specifically:

```text
ownership detection
renderer delegation
metadata transport
field discovery
renderer registration
```

must all function correctly before interactive execution is introduced.

---

### Handoff Rule

Renderer handoff occurs only when ownership selection produces a valid active contract.

Conceptually:

```text
active contract exists
        ↓
      yes
        ↓
 delegate renderer
```

Otherwise:

```text
no active contract
        ↓
 no renderer handoff
```

The renderer never performs ownership detection itself.

Ownership decisions have already been resolved by the boundary layer.

This keeps responsibilities clean.

---

### Delegation Contract

The renderer receives a delegation contract produced by the Boundary Selection Object.

Conceptually:

```text
Boundary Selection Object
        ↓
 delegation contract
        ↓
 FormFlowRenderer
```

The delegation contract may contain:

```text
phase reference
ownership source
metadata
fields
renderer hints
```

The exact structure may evolve.

However, the architectural responsibility remains constant:

```text
ownership layer
        ↓
 rendering layer
```

The renderer consumes.

The ownership layer decides.

---

### Renderer Responsibilities

Once handoff occurs, renderer responsibilities begin.

Current responsibilities include:

```text
metadata rendering
field diagnostics
preview rows
renderer selection
```

Future responsibilities may include:

```text
field interaction
validation
submission
workflow progression
```

The placeholder architecture intentionally introduces responsibilities incrementally.

This reduces migration risk.

---

### Legacy Mode

Renderer handoff supports both ownership modes.

Conceptually:

```text
compiled ownership
        ↓
 renderer handoff

legacy ownership
        ↓
 renderer handoff
```

The renderer does not care whether ownership originated from:

```text
compiler
```

or

```text
legacy voucher preview
```

The renderer consumes the delegation contract.

Ownership provenance remains an ownership concern.

---

### Why Handoff Exists

Historically, ClaimWidget performed both ownership and rendering responsibilities.

Conceptually:

```text
ownership
        ↓
rendering
        ↓
field output
```

This created tight coupling.

The handoff architecture separates:

```text
ownership concerns
```

from

```text
rendering concerns
```

allowing each layer to evolve independently.

Benefits include:

```text
simpler migration
renderer specialization
ownership stability
future package extraction
```

The separation is foundational to the long-term architecture.

---

### Handoff Lifecycle

The renderer handoff lifecycle can be summarized as:

```text
claimExperience.phases[]
        ↓
 ownership boundary
        ↓
 boundary selection
        ↓
 active contract
        ↓
 renderer handoff
        ↓
 FormFlowRenderer
```

At this point ownership resolution is complete.

Rendering execution begins.

---

### Renderer Region

Renderer handoff occurs within the Form Flow ownership region.

Conceptually:

```text
ClaimWidget
    └── form-flow-boundary-region
            ↓
      renderer handoff
            ↓
      FormFlowRenderer
```

This establishes a dedicated rendering pathway separate from:

```text
rider_intro
runtime
redirect
```

The separation allows Form Flow rendering to mature without affecting other rendering regions.

---

### Handoff Debug Signals

Renderer handoff exposes migration diagnostics.

Conceptually:

```text
renderer selected
renderer delegated
renderer initialized
metadata received
fields received
```

These diagnostics help validate:

```text
ownership delegation
renderer activation
contract transport
rendering readiness
```

The signals support migration validation and future automated testing.

They are not user-facing features.

---

### Placeholder Handoff Summary

The Placeholder Renderer Handoff establishes the first dedicated rendering boundary within the Form Flow architecture.

Conceptually:

```text
ownership layer
        ↓
 delegation contract
        ↓
 rendering layer
```

This boundary is intentionally simple.

Its purpose is to validate:

```text
ownership
delegation
transport
renderer activation
```

before introducing richer rendering behavior.

The next section introduces the first renderer responsibility:

```text
Normalized Metadata Rendering
```

where compiler-produced metadata begins to appear inside delegated rendering output.

---

## 8.6 Normalized Metadata Rendering

Once renderer handoff completes, FormFlowRenderer becomes responsible for interpreting the delegated rendering contract.

The first rendering responsibility is metadata presentation.

Conceptually:

```text
delegation contract
        ↓
 FormFlowRenderer
        ↓
 normalized metadata
        ↓
 rendered output
```

Metadata rendering serves as the first visible proof that ownership detection, boundary selection, and renderer handoff have completed successfully.

The purpose of this phase is validation.

Before introducing interactive rendering, the system must first demonstrate that renderer delegation is functioning correctly.

---

### Metadata Normalization

The delegation contract may originate from:

```text
compiled ownership
```

or

```text
legacy ownership
```

Regardless of source, FormFlowRenderer consumes a normalized structure.

Conceptually:

```text
compiled metadata
        ↓
 normalization

legacy metadata
        ↓
 normalization

        ↓

 common structure
```

This ensures renderer behavior remains consistent regardless of ownership provenance.

Normalization eliminates renderer dependence on source-specific structures.

---

### Diagnostic Metadata

Current metadata rendering focuses on diagnostic visibility.

Conceptually:

```text
delegation source
renderer state
field count
ownership mode
phase identity
```

The objective is not user-facing presentation.

Instead, metadata rendering exists to validate:

```text
ownership selection
delegation transport
renderer activation
normalization
```

during migration.

---

### Rendered Metadata Region

Metadata is rendered inside the FormFlowRenderer region.

Conceptually:

```text
ClaimWidget
    └── FormFlowRenderer
            └── metadata region
```

This region exists independently from:

```text
field diagnostics
preview rows
field renderers
```

Metadata rendering serves as the foundation upon which all later renderer behavior is built.

---

### Current Rendering Shape

Current rendering behavior can be summarized as:

```text
delegation contract
        ↓
 normalized metadata
        ↓
 renderer output
```

Example conceptual output:

```text
Mode: Compiled
Phase: form_flow
Fields: 5
Renderer: FormFlowRenderer
```

The exact visual presentation may evolve.

The purpose remains unchanged:

```text
show renderer state
```

rather than

```text
execute form workflow
```

---

### What This Proves

Metadata rendering validates several architectural layers simultaneously.

Conceptually:

```text
ownership boundary
        ↓
 boundary selection
        ↓
 delegation contract
        ↓
 renderer activation
        ↓
 metadata rendering
```

Successful metadata rendering demonstrates that:

```text
ownership was resolved
contract was created
contract was delivered
renderer was activated
renderer consumed metadata
```

This makes metadata rendering one of the most important migration checkpoints.

---

### What This Does Not Yet Do

Metadata rendering intentionally avoids:

```text
interactive fields
validation
submission
workflow progression
business logic execution
```

Those concerns belong to later renderer phases.

The current objective is architectural verification rather than user interaction.

---

### Metadata Lifecycle

The metadata lifecycle can be summarized as:

```text
ownership boundary
        ↓
 boundary selection
        ↓
 active contract
        ↓
 renderer handoff
        ↓
 metadata normalization
        ↓
 rendered metadata
```

Every subsequent rendering feature builds upon this lifecycle.

If metadata rendering succeeds, the renderer infrastructure can safely evolve toward richer functionality.

---

### Why Metadata Comes First

The migration intentionally introduces metadata rendering before field rendering.

Historically, field rendering tends to expose architectural issues very late.

By rendering metadata first, the architecture validates:

```text
transport
normalization
delegation
activation
```

before introducing:

```text
fields
validation
submission
interaction
```

This significantly reduces migration risk.

---

### Metadata Debug Signals

Metadata rendering exposes migration diagnostics.

Conceptually:

```text
metadata normalized
metadata rendered
renderer active
contract received
ownership source detected
```

These diagnostics help validate:

```text
normalization
renderer readiness
transport integrity
ownership provenance
```

The signals are intended for migration validation and future automated testing.

They are not user-facing features.

---

### Metadata Rendering Summary

Normalized Metadata Rendering is the first rendering responsibility owned by FormFlowRenderer.

Conceptually:

```text
delegation contract
        ↓
 normalization
        ↓
 metadata rendering
```

The objective is not form execution.

The objective is verification.

Successful metadata rendering demonstrates that the entire ownership and delegation architecture is functioning correctly.

The next section introduces the first field-oriented renderer behavior:

```text
Field Diagnostics
```

where normalized metadata begins exposing individual field structures discovered within the delegated contract.

---

## 8.7 Field Diagnostics

Once metadata normalization succeeds, FormFlowRenderer can begin exposing field structures discovered within the delegated contract.

The first field-oriented rendering responsibility is diagnostic rendering.

Conceptually:

```text
delegation contract
        ↓
 field discovery
        ↓
 field diagnostics
        ↓
 rendered output
```

Field Diagnostics exist primarily to validate field transport and normalization.

The purpose is not user interaction.

The purpose is verification.

---

### Field Discovery

Field discovery begins by locating normalized field definitions.

Conceptually:

```text
delegation contract
        ↓
      fields[]
```

Example:

```text
fields[]
    ├── first_name
    ├── last_name
    ├── email
    ├── birth_date
    └── mobile
```

At this stage, FormFlowRenderer is not yet concerned with field behavior.

The renderer simply verifies that field definitions successfully survived transport and normalization.

---

### Rendered Field Markers

Field Diagnostics expose discovered fields as diagnostic markers.

Conceptually:

```text
field discovered
        ↓
 diagnostic marker
```

Example:

```text
Field: first_name
Type: text

Field: email
Type: email
```

The markers provide visibility into renderer state without introducing interactive behavior.

---

### Current Rendering Shape

Current field diagnostics can be summarized as:

```text
fields[]
        ↓
 diagnostic rows
        ↓
 renderer output
```

Example conceptual output:

```text
Field: first_name
Type: text

Field: email
Type: email

Field: birth_date
Type: date
```

The exact visual presentation may evolve.

The diagnostic purpose remains constant.

---

### What This Proves

Field Diagnostics validate several architectural layers simultaneously.

Conceptually:

```text
ownership
        ↓
delegation
        ↓
normalization
        ↓
field discovery
        ↓
field diagnostics
```

Successful rendering demonstrates that:

```text
fields were transported
fields were normalized
fields were discovered
renderer consumed fields
```

This makes Field Diagnostics an important migration checkpoint.

---

### What This Does Not Yet Do

Field Diagnostics intentionally avoid:

```text
user input
validation
submission
workflow execution
state mutation
```

The renderer is still operating in verification mode.

Interactive behavior will be introduced later through dedicated field renderers.

---

### Diagnostic Lifecycle

The lifecycle can be summarized as:

```text
delegation contract
        ↓
field discovery
        ↓
field diagnostics
        ↓
rendered output
```

This establishes the first renderer behavior operating directly on normalized field structures.

---

### Field Diagnostic Debug Signals

Field Diagnostics expose migration diagnostics.

Conceptually:

```text
field count
field discovered
field normalized
diagnostic rendered
```

These signals help validate:

```text
transport integrity
normalization
field discovery
renderer readiness
```

The diagnostics support migration validation and future automated testing.

They are not user-facing features.

---

### Field Diagnostics Summary

Field Diagnostics represent the first renderer feature that operates directly on field definitions.

Conceptually:

```text
normalized fields
        ↓
diagnostic rendering
```

The objective remains architectural verification.

The next renderer responsibility introduces field visibility that more closely resembles eventual user-facing rendering:

```text
Readonly Preview Rows
```

---

## 8.8 Readonly Preview Rows

Readonly Preview Rows provide a structured preview of normalized field definitions.

Unlike Field Diagnostics, which focus on field discovery, Preview Rows focus on field presentation.

Conceptually:

```text
normalized fields
        ↓
preview rows
        ↓
readonly display
```

Preview Rows serve as the bridge between diagnostics and true field rendering.

---

### Purpose

The purpose of Preview Rows is to demonstrate that the renderer can organize field information into a predictable presentation structure.

Conceptually:

```text
field discovery
        ↓
field presentation
```

This introduces renderer behavior that resembles real forms while avoiding interactive complexity.

---

### Rendered Preview Region

Preview Rows are rendered within FormFlowRenderer.

Conceptually:

```text
ClaimWidget
    └── FormFlowRenderer
            └── preview region
```

The preview region is distinct from:

```text
metadata region
diagnostic region
renderer registry
```

Each region validates a different layer of renderer behavior.

---

### Current Rendering Shape

Preview Rows render field information as readonly structures.

Example conceptual output:

```text
First Name
Text Field

Email Address
Email Field

Birth Date
Date Field
```

The purpose is to expose normalized field information in a layout that resembles eventual production rendering.

---

### What This Proves

Preview Rows validate:

```text
field ordering
field presentation
field normalization
renderer organization
```

Conceptually:

```text
normalized fields
        ↓
presentation structure
        ↓
readonly preview
```

Successful Preview Row rendering demonstrates that the renderer can move beyond diagnostics into presentation.

---

### Current Example

A conceptual rendering example:

```text
--------------------------------
First Name
Text Field
--------------------------------

Email Address
Email Field
--------------------------------

Birth Date
Date Field
--------------------------------
```

The exact visual implementation may evolve.

The architectural purpose remains unchanged.

---

### What This Does Not Yet Do

Preview Rows intentionally avoid:

```text
editable fields
validation
submission
workflow execution
input state
```

The rows remain readonly.

Their purpose is verification and presentation rather than interaction.

---

### Why Preview Rows Exist

Preview Rows reduce migration risk.

Historically, systems often move directly from diagnostics into interaction.

This can make ownership and renderer issues difficult to isolate.

Preview Rows intentionally introduce an intermediate phase:

```text
diagnostics
        ↓
preview
        ↓
interaction
```

This allows renderer presentation concerns to mature before interactive complexity is introduced.

---

### Preview Row Debug Signals

Preview Rows expose migration diagnostics.

Conceptually:

```text
preview rows generated
preview order established
preview rendered
```

These signals help validate:

```text
field presentation
renderer organization
ordering behavior
```

The diagnostics support migration validation and future automated testing.

---

### Preview Rows Summary

Readonly Preview Rows represent the first presentation-oriented renderer feature.

Conceptually:

```text
normalized fields
        ↓
readonly presentation
```

The renderer is still not executing forms.

However, it is now demonstrating its ability to organize field information into predictable display structures.

The next section introduces the renderer infrastructure responsible for determining how fields are rendered:

```text
Field Type Support Matrix
Renderer Registry
```

which collectively establish the delegation architecture used by specialized field renderers.

---

## 8.9 Field Type Support Matrix

Field Diagnostics and Preview Rows operate on normalized field definitions.

The next architectural concern is determining how those fields should be rendered.

Conceptually:

```text
normalized field
        ↓
field type
        ↓
support matrix
        ↓
renderer selection
```

The Field Type Support Matrix serves as the contract between normalized field definitions and renderer delegation.

Its purpose is to explicitly define which field types are understood by the rendering system.

---

### Supported Field Types

Current renderer infrastructure recognizes a limited set of field types.

Conceptually:

```text
text
email
date
number
select
textarea
```

These field types represent the initial renderer surface area.

Additional field types may be introduced in the future.

The support matrix allows the renderer system to evolve incrementally without requiring every field type to be implemented simultaneously.

---

### Type Guard

Field rendering begins by validating field type eligibility.

Conceptually:

```text
field
        ↓
type guard
        ↓
supported?
```

The type guard determines whether:

```text
field type
```

belongs to:

```text
supported renderer set
```

or

```text
unsupported renderer path
```

This validation prevents unknown field definitions from silently failing.

---

### Field Type Normalization

Field definitions may originate from multiple ownership paths.

Conceptually:

```text
compiled source
```

or

```text
legacy source
```

Regardless of origin, field types are normalized before renderer selection.

Conceptually:

```text
source field
        ↓
normalization
        ↓
normalized type
```

Examples:

```text
string
text
input
```

may normalize into:

```text
text
```

Likewise:

```text
email_input
email
```

may normalize into:

```text
email
```

The support matrix operates only on normalized field types.

---

### Renderer Behavior

The support matrix does not render fields.

Its responsibility is classification.

Conceptually:

```text
field
        ↓
support matrix
        ↓
renderer identity
```

Rendering responsibility remains delegated to specialized renderers.

The support matrix merely determines:

```text
who should render
```

rather than:

```text
how rendering occurs
```

---

### Why Unsupported Is Explicit

Unsupported field handling is intentionally explicit.

Historically, renderer systems often fail silently when encountering unknown field definitions.

The support matrix instead routes unknown field types through a dedicated unsupported path.

Conceptually:

```text
unknown type
        ↓
unsupported renderer
```

Benefits include:

```text
migration visibility
debuggability
future extensibility
```

Unsupported fields become visible architectural events rather than hidden failures.

---

### Matrix Lifecycle

The lifecycle of the support matrix can be summarized as:

```text
normalized field
        ↓
type normalization
        ↓
support matrix
        ↓
renderer identity
```

The resulting renderer identity becomes the input for renderer delegation.

---

### Support Matrix Debug Signals

The support matrix exposes migration diagnostics.

Conceptually:

```text
supported type
unsupported type
normalized type
renderer identified
```

These diagnostics help validate:

```text
field normalization
renderer eligibility
support coverage
```

The signals are intended for migration validation and future automated testing.

---

### Field Type Support Matrix Summary

The support matrix introduces a classification layer into Form Flow rendering.

Conceptually:

```text
field
        ↓
classification
        ↓
renderer identity
```

The matrix determines renderer ownership but does not perform rendering itself.

The next layer introduces the infrastructure responsible for delegating rendering responsibility:

```text
Renderer Registry
```

---

## 8.10 Renderer Registry

Once the support matrix identifies a renderer, delegation responsibility moves to the Renderer Registry.

Conceptually:

```text
field
        ↓
support matrix
        ↓
renderer identity
        ↓
renderer registry
        ↓
renderer instance
```

The Renderer Registry serves as the central delegation mechanism for field rendering.

It is responsible for locating the renderer associated with a normalized field type.

---

### Current Architecture

Current renderer architecture follows a registry-based delegation model.

Conceptually:

```text
FormFlowRenderer
        ↓
Renderer Registry
        ↓
Specialized Renderer
```

This prevents FormFlowRenderer from accumulating field-specific rendering logic.

Instead, responsibility is distributed across dedicated renderers.

---

### Renderer Registry

The registry maintains the mapping between:

```text
field type
```

and

```text
renderer implementation
```

Conceptually:

```text
text
    ↓
TextFieldRenderer

email
    ↓
EmailFieldRenderer

date
    ↓
DateFieldRenderer

number
    ↓
NumberFieldRenderer

select
    ↓
SelectFieldRenderer

textarea
    ↓
TextareaFieldRenderer
```

Unknown field types are mapped to:

```text
UnsupportedFieldRenderer
```

The registry therefore guarantees a rendering destination for every normalized field.

---

### Delegation Contract

Renderer delegation follows a simple contract.

Conceptually:

```text
field
        ↓
registry lookup
        ↓
renderer resolved
        ↓
render field
```

The registry does not interpret field metadata.

The registry does not render fields.

Its sole responsibility is renderer resolution.

---

### Registry Benefits

The registry architecture provides several benefits.

Conceptually:

```text
renderer isolation
incremental migration
extensibility
testability
```

New field types can be introduced without modifying FormFlowRenderer.

Instead:

```text
new field type
        ↓
new renderer
        ↓
registry registration
```

This keeps renderer growth manageable.

---

### Registry Lifecycle

The registry lifecycle can be summarized as:

```text
normalized field
        ↓
support matrix
        ↓
renderer identity
        ↓
registry lookup
        ↓
renderer delegation
```

This represents the final architectural step before field rendering actually occurs.

---

### Registry Debug Signals

The Renderer Registry exposes migration diagnostics.

Conceptually:

```text
renderer resolved
renderer delegated
renderer missing
unsupported fallback
```

These diagnostics help validate:

```text
registry coverage
delegation integrity
renderer availability
```

The signals support migration validation and future automated testing.

---

### Renderer Registry Summary

The Renderer Registry completes the delegation architecture.

Conceptually:

```text
field
        ↓
support matrix
        ↓
renderer identity
        ↓
registry lookup
        ↓
renderer delegation
```

At this point the rendering system is ready to execute specialized field renderers.

The next section documents those renderers individually:

```text
Supported Renderers
```

where rendering responsibility finally transitions from infrastructure into concrete field-specific implementations.

---

## 8.11 Supported Renderers

Once renderer delegation completes, rendering responsibility transitions from FormFlowRenderer infrastructure into specialized field renderers.

Conceptually:

```text
normalized field
        ↓
support matrix
        ↓
renderer registry
        ↓
specialized renderer
        ↓
field output
```

Each renderer owns a specific field type.

FormFlowRenderer no longer interprets field behavior directly.

Instead, it delegates rendering responsibility to the resolved renderer.

This separation allows renderer capabilities to evolve independently from FormFlowRenderer itself.

---

### TextFieldRenderer

TextFieldRenderer is responsible for rendering normalized text fields.

Conceptually:

```text
text
        ↓
TextFieldRenderer
```

Examples:

```text
first_name
last_name
middle_name
address_line
```

Current responsibilities include:

```text
field presentation
readonly rendering
diagnostic rendering
```

Future responsibilities may include:

```text
interactive input
validation
state management
```

The renderer owns text-specific presentation behavior.

---

### EmailFieldRenderer

EmailFieldRenderer is responsible for rendering normalized email fields.

Conceptually:

```text
email
        ↓
EmailFieldRenderer
```

Examples:

```text
email
contact_email
alternate_email
```

Current responsibilities include:

```text
field presentation
readonly rendering
diagnostic rendering
```

Future responsibilities may include:

```text
email validation
input assistance
email-specific interaction
```

Email-specific behavior remains isolated within the renderer.

---

### DateFieldRenderer

DateFieldRenderer is responsible for rendering normalized date fields.

Conceptually:

```text
date
        ↓
DateFieldRenderer
```

Examples:

```text
birth_date
issue_date
expiry_date
```

Current responsibilities include:

```text
field presentation
readonly rendering
diagnostic rendering
```

Future responsibilities may include:

```text
date pickers
date validation
format normalization
```

Date-specific concerns remain isolated from other renderer types.

---

### NumberFieldRenderer

NumberFieldRenderer is responsible for rendering normalized numeric fields.

Conceptually:

```text
number
        ↓
NumberFieldRenderer
```

Examples:

```text
amount
quantity
age
reference_number
```

Current responsibilities include:

```text
field presentation
readonly rendering
diagnostic rendering
```

Future responsibilities may include:

```text
numeric validation
formatting
input constraints
```

Numeric behavior remains renderer-specific.

---

### SelectFieldRenderer

SelectFieldRenderer is responsible for rendering normalized selection fields.

Conceptually:

```text
select
        ↓
SelectFieldRenderer
```

Examples:

```text
country
gender
civil_status
document_type
```

Current responsibilities include:

```text
option presentation
readonly rendering
diagnostic rendering
```

Future responsibilities may include:

```text
interactive selection
option validation
dynamic option loading
```

Selection-specific concerns remain isolated within the renderer.

---

### TextareaFieldRenderer

TextareaFieldRenderer is responsible for rendering normalized multi-line text fields.

Conceptually:

```text
textarea
        ↓
TextareaFieldRenderer
```

Examples:

```text
remarks
notes
description
comments
```

Current responsibilities include:

```text
field presentation
readonly rendering
diagnostic rendering
```

Future responsibilities may include:

```text
interactive editing
character limits
validation
```

Textarea-specific concerns remain isolated from other field types.

---

### Shared Renderer Principles

Although individual renderers own different field types, they share common architectural principles.

Conceptually:

```text
normalized field
        ↓
renderer delegation
        ↓
renderer output
```

Each renderer is expected to:

```text
consume normalized data
avoid ownership logic
avoid registry logic
focus on presentation
```

This separation keeps renderer responsibilities narrowly scoped.

---

### Renderer Lifecycle

The renderer lifecycle can be summarized as:

```text
field
        ↓
support matrix
        ↓
renderer registry
        ↓
renderer delegation
        ↓
renderer output
```

Once delegation occurs, renderer execution becomes independent of ownership selection.

The renderer simply consumes normalized field data.

---

### Renderer Debug Signals

Individual renderers expose migration diagnostics.

Conceptually:

```text
renderer activated
field received
field rendered
renderer completed
```

These diagnostics help validate:

```text
delegation integrity
renderer coverage
rendering readiness
```

The diagnostics support migration validation and future automated testing.

---

### Supported Renderer Summary

Supported renderers represent the first truly specialized rendering layer within Form Flow architecture.

Conceptually:

```text
field type
        ↓
specialized renderer
        ↓
field presentation
```

The renderer system remains intentionally modular.

New renderer types can be introduced without modifying ownership infrastructure, support matrices, or registry architecture.

---

## 8.12 Unsupported Renderer

Not every normalized field type is immediately supported.

The renderer architecture therefore includes an explicit fallback renderer.

Conceptually:

```text
unknown field type
        ↓
UnsupportedFieldRenderer
```

The purpose of the Unsupported Renderer is visibility.

Unknown field types should become observable migration events rather than silent failures.

---

### UnsupportedFieldRenderer

UnsupportedFieldRenderer is responsible for handling:

```text
unknown field types
unregistered field types
future field types
```

Conceptually:

```text
field
        ↓
no renderer found
        ↓
UnsupportedFieldRenderer
```

The fallback renderer guarantees that every normalized field has a rendering destination.

---

### Why Unsupported Exists

Historically, unsupported fields often fail silently.

Conceptually:

```text
unknown field
        ↓
nothing rendered
```

This behavior makes migration difficult because unsupported types remain invisible.

The explicit unsupported renderer instead produces:

```text
unknown field
        ↓
visible fallback
```

This dramatically improves migration visibility.

---

### Current Responsibilities

Current unsupported responsibilities include:

```text
diagnostic rendering
fallback presentation
migration visibility
```

Future responsibilities may include:

```text
developer guidance
renderer recommendations
migration tooling
```

The renderer exists primarily as an architectural safety mechanism.

---

### Unsupported Lifecycle

The unsupported lifecycle can be summarized as:

```text
field
        ↓
support matrix
        ↓
unsupported
        ↓
UnsupportedFieldRenderer
        ↓
fallback output
```

The lifecycle mirrors supported renderer delegation while producing a different rendering outcome.

---

### Unsupported Debug Signals

Unsupported rendering exposes migration diagnostics.

Conceptually:

```text
unsupported type detected
fallback activated
renderer missing
```

These diagnostics help validate:

```text
renderer coverage
registry completeness
migration readiness
```

The diagnostics are intended for migration validation and future automated testing.

---

### Unsupported Renderer Summary

UnsupportedFieldRenderer completes the renderer architecture.

Conceptually:

```text
supported field
        ↓
specialized renderer

unsupported field
        ↓
fallback renderer
```

Together, supported and unsupported renderers guarantee that every normalized field participates in a predictable rendering pathway.

This concludes the Form Flow Ownership & Rendering architecture.

The next major section transitions from renderer infrastructure back into ownership architecture:

```text
9. Success Rider Ownership Boundary
```

where rendering ownership leaves FormFlowRenderer and returns to broader ClaimWidget and Success experience concerns.

---

# 9. Success Rider Ownership Boundary

The Success experience represents the final rendering boundary within ClaimWidget.

Unlike Rider Intro, Runtime, Redirect, and Form Flow, Success rendering occurs after the claim experience has completed.

Conceptually:

```text
claim execution
        ↓
claim completed
        ↓
success experience
```

The primary architectural concern is ownership.

Specifically:

```text
who owns success rendering?
```

The answer determines whether success content is produced by:

```text
legacy success rendering
```

or

```text
compiler-produced success riders
```

The ownership model follows the same compiler-first philosophy used throughout ClaimWidget.

---

## 9.1 Ownership Rules

Success ownership is determined through compiler phase resolution.

Conceptually:

```text
claimExperience.phases[]
        ↓
 locate success
        ↓
 ownership decision
```

If a compiled Success phase exists, ownership belongs to the compiler.

Conceptually:

```text
compiled success phase
        ↓
 compiler owns success
```

Otherwise:

```text
no compiled success phase
        ↓
 legacy success ownership
```

The ownership decision is intentionally binary.

ClaimWidget does not merge:

```text
compiled success content
```

and

```text
legacy success content
```

Only one ownership path becomes active.

This keeps success rendering deterministic and predictable.

---

### Compiler-First Rule

Success rendering follows the same migration principle used throughout the claim experience.

Conceptually:

```text
compiled success exists
        ↓
      yes
        ↓
 use compiled ownership

      otherwise

 use legacy ownership
```

The compiler remains the preferred source of truth whenever available.

Legacy ownership exists solely to preserve backward compatibility.

---

### Ownership Scope

Success ownership controls:

```text
success messaging
success rider content
success presentation
```

Success ownership does not necessarily control:

```text
redirect execution
claim completion
backend settlement
```

These concerns remain outside the Success rendering boundary.

Ownership applies only to presentation.

---

## 9.2 Rendering Ownership

Once ownership is established, rendering responsibility follows ownership.

Conceptually:

```text
ownership selected
        ↓
 rendering selected
```

Compiled ownership produces:

```text
compiled success rendering
```

Legacy ownership produces:

```text
legacy success rendering
```

The renderer never combines both paths.

---

### Compiled Success Rendering

Compiled success rendering operates on compiler-produced stages.

Conceptually:

```text
success phase
        ↓
stages[]
        ↓
visual filtering
        ↓
rendered success content
```

Examples:

```text
message
image
html
link
```

Only visual stages participate in rendering.

Operational stages remain outside the rendering surface.

---

### Legacy Success Rendering

Legacy success rendering reconstructs success presentation from voucher preview structures.

Conceptually:

```text
voucher preview
        ↓
legacy success reconstruction
        ↓
success rendering
```

This path remains available during migration.

The long-term objective is for success experiences to originate entirely from compiler-produced phases.

---

### Success Rendering Region

Success content renders within the success experience region.

Conceptually:

```text
ClaimWidget
        ↓
success region
        ↓
success rendering
```

The success region is independent from:

```text
rider_intro
runtime
redirect
form_flow
```

This separation ensures that completion experiences can evolve independently from claim execution experiences.

---

### Success Rendering Lifecycle

The lifecycle can be summarized as:

```text
claim completed
        ↓
success ownership
        ↓
ownership selected
        ↓
rendering selected
        ↓
success content rendered
```

The lifecycle mirrors the compiler-first rendering pattern used throughout ClaimWidget.

---

## 9.3 Test Contract

The Success ownership boundary introduces a dedicated test contract.

The purpose of the contract is to guarantee predictable ownership selection during migration.

Conceptually:

```text
ownership
        ↓
rendering
        ↓
expected output
```

The contract validates both compiled and legacy behavior.

---

### Compiled Ownership Tests

Compiled ownership tests verify:

```text
success phase discovered
compiled ownership selected
compiled rendering activated
legacy rendering bypassed
```

These tests ensure that compiler-produced success experiences take precedence whenever available.

---

### Legacy Ownership Tests

Legacy ownership tests verify:

```text
no compiled success phase
legacy ownership selected
legacy rendering activated
```

These tests preserve backward compatibility.

---

### Rendering Tests

Rendering tests verify:

```text
success content rendered
visual stage filtering
rendering order preserved
```

The tests focus on presentation behavior rather than claim execution behavior.

---

### Ownership Tests

Ownership tests verify:

```text
compiler-first selection
single ownership path
fallback behavior
```

The renderer must never simultaneously activate:

```text
compiled ownership
```

and

```text
legacy ownership
```

Ownership selection must remain deterministic.

---

### Success Debug Signals

Success rendering exposes migration diagnostics.

Conceptually:

```text
compiled success selected
legacy success selected
success rendering activated
success stage count
```

These diagnostics support migration validation and automated testing.

They are not user-facing features.

---

### Success Ownership Summary

The Success Ownership Boundary completes the ownership model used throughout ClaimWidget.

Conceptually:

```text
rider_intro
runtime
redirect
form_flow
success
```

Each experience surface follows the same architectural principle:

```text
ownership determination
        ↓
compiler-first selection
        ↓
rendering activation
        ↓
legacy fallback
```

Success rendering therefore becomes the final ownership boundary in the claim experience lifecycle.

---

---

# 10. Test Coverage

The compiled rendering migration is protected by a layered testing strategy.

The objective is to verify:

```text
ownership selection
rendering activation
fallback behavior
renderer delegation
migration compatibility
```

The test suite validates both the new compiler-driven architecture and legacy compatibility paths.

---

## Ownership Coverage

Ownership tests verify that the correct rendering owner is selected.

Examples include:

```text
rider_intro ownership
runtime ownership
redirect ownership
form_flow ownership
success ownership
```

The tests ensure:

```text
compiled ownership wins
legacy fallback remains available
single ownership path is active
```

Ownership selection must remain deterministic.

---

## Rendering Coverage

Rendering tests verify that content is rendered from the expected source.

Examples include:

```text
compiled stage rendering
legacy stage rendering
visual filtering
rendering order
```

The tests validate that ClaimWidget renders only eligible visual content and preserves compiler-produced sequencing.

---

## Runtime Coverage

Runtime tests verify execution-related rendering behavior.

Examples include:

```text
runtime stage activation
runtime sequencing
duplicate execution prevention
countdown behavior
redirect timing
```

Runtime coverage ensures that rendering remains synchronized with execution state.

---

## Form Flow Coverage

Form Flow tests validate delegation infrastructure.

Examples include:

```text
ownership boundary
boundary selection
renderer handoff
metadata normalization
field discovery
renderer registry
```

The objective is to verify that rendering responsibility correctly transitions from ClaimWidget to FormFlowRenderer.

---

## Renderer Coverage

Renderer tests validate specialized field renderers.

Examples include:

```text
text renderer
email renderer
date renderer
number renderer
select renderer
textarea renderer
unsupported renderer
```

Renderer coverage ensures predictable rendering behavior across all supported field types.

---

## Compatibility Coverage

Compatibility tests verify coexistence between:

```text
compiled rendering
```

and

```text
legacy rendering
```

Examples include:

```text
compiler-first selection
legacy fallback activation
mixed migration environments
```

Compatibility coverage allows migration to proceed incrementally.

---

## Test Coverage Summary

The testing strategy validates:

```text
ownership
rendering
delegation
compatibility
migration
```

The objective is not merely correctness.

The objective is confidence that compiler-first rendering can replace legacy rendering without regressions.

---

# 11. Migration Status

The compiled rendering migration is currently operating in a hybrid state.

Both compiler-produced rendering and legacy rendering continue to coexist.

This coexistence is intentional.

---

## Completed

The following architectural components have been introduced:

```text
compiled phase lookup
visual stage filtering
rider_intro rendering
runtime rendering
redirect rendering
form_flow ownership boundary
boundary selection
renderer delegation
renderer registry
success ownership boundary
```

These components establish the foundation for compiler-first rendering.

---

## Partially Migrated

The following areas currently support both compiled and legacy paths:

```text
rider rendering
runtime rendering
redirect rendering
success rendering
form_flow rendering
```

The compiler-first selection model is active, but legacy compatibility remains available.

---

## Legacy Components Remaining

Several legacy paths remain intentionally available.

Examples include:

```text
legacy rider reconstruction
legacy runtime rendering
legacy redirect rendering
legacy success rendering
legacy form-flow reconstruction
```

These paths continue to provide backward compatibility for existing vouchers.

---

## Migration Strategy

The migration strategy follows:

```text
introduce compiled path
        ↓
verify ownership
        ↓
verify rendering
        ↓
maintain fallback
        ↓
expand coverage
        ↓
retire legacy path
```

This minimizes migration risk and allows incremental adoption.

---

## Exit Criteria

A legacy rendering path becomes eligible for removal when:

```text
compiled ownership exists
compiled rendering is validated
test coverage is complete
fallback usage approaches zero
```

Legacy removal should be treated as the final migration step rather than the first.

---

## Migration Status Summary

The system is currently operating as:

```text
compiler-first
with
legacy fallback
```

This state is intentional and represents the transitional architecture required to safely reach full compiler ownership.

---

# 12. Long-Term Direction

The long-term objective is a fully compiler-driven claim experience.

Conceptually:

```text
claimExperience
        ↓
compiled phases
        ↓
ownership
        ↓
rendering
```

The compiler becomes the single source of truth for experience construction.

---

## Compiler-Owned Experiences

The long-term architecture expects all major rendering regions to originate from compiler-produced phases.

Examples include:

```text
rider_intro
runtime
redirect
form_flow
success
```

ClaimWidget should increasingly become a rendering orchestrator rather than a rendering author.

---

## Renderer Specialization

Rendering responsibilities should continue moving toward specialized renderers.

Conceptually:

```text
ownership
        ↓
delegation
        ↓
specialized renderer
```

This allows individual rendering concerns to evolve independently.

---

## Form Flow Evolution

Form Flow is expected to become increasingly renderer-driven.

Future capabilities may include:

```text
interactive rendering
validation
submission
workflow progression
field-level execution
```

These capabilities build upon the ownership and delegation architecture already established.

---

## Legacy Retirement

The long-term objective is eventual removal of:

```text
legacy rider reconstruction
legacy runtime rendering
legacy redirect rendering
legacy success rendering
legacy form-flow rendering
```

Removal should occur only after:

```text
compiler adoption
coverage validation
migration completion
```

have been achieved.

---

## Simplified ClaimWidget

As ownership and rendering responsibilities become specialized, ClaimWidget should become simpler.

Conceptually:

```text
ClaimWidget
        ↓
ownership selection
        ↓
renderer orchestration
```

Rather than:

```text
ClaimWidget
        ↓
ownership
rendering
reconstruction
fallback logic
field rendering
```

This reduction in responsibility is a primary architectural goal.

---

## End-State Architecture

The intended end-state can be summarized as:

```text
claimExperience
        ↓
ownership resolution
        ↓
renderer delegation
        ↓
specialized rendering
```

Where:

```text
compiler
```

owns experience construction,

```text
ClaimWidget
```

owns orchestration,

and

```text
specialized renderers
```

own presentation.

This architecture provides a clear separation of responsibilities, predictable ownership boundaries, and a sustainable path for future claim experience evolution.

---
