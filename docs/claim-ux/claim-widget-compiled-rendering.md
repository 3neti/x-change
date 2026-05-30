# ClaimWidget Compiled Rendering

## Purpose

This document defines the first compiled rendering contract inside:

```text
resources/js/components/x-change/ClaimWidget.vue
```

`ClaimWidget.vue` is being migrated from legacy interpretation toward compiler-driven rendering.

This document currently covers the compiler-driven rendering paths implemented inside ClaimWidget.

At present, these include:

- rider_intro
- runtime

The goal is not yet to remove legacy behavior. The goal is to make compiled behavior win when available, while preserving legacy fallback during migration.

---

# Current Scope

ClaimWidget now contains compiler-aware behavior for:

- rider_intro
- runtime
- redirect
- form_flow (ownership boundary only)

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
```

If no usable compiled `rider_intro` phase exists, ClaimWidget falls back to the existing legacy rider preview logic.

---

# Source Code Dissection

## Props

ClaimWidget accepts:

```ts
interface Props {
    initialCode?: string | null;
    claimExperience?: Record<string, unknown> | null;
}
```

The important prop for compiled rendering is:

```ts
claimExperience
```

This is the compiled claim UX contract passed from the backend.

It may contain:

```ts
claimExperience.phases
```

where each phase may describe a segment of the claim journey.

---

# Voucher Preview Source

ClaimWidget still uses:

```ts
useVoucherPreview({ debounceMs: 500, minCodeLength: 4 })
```

This provides the legacy voucher preview state:

```ts
code
loading
error
voucherData
showPreview
```

`voucherData` remains important because ClaimWidget still renders:

- voucher instructions
- metadata
- preview messages
- legacy rider splash fallback
- non-active voucher state

The compiled rendering slice does not remove this dependency.

---

# Stage Extraction

ClaimWidget normalizes stage arrays through:

```ts
function extractStages(value: unknown): RawRiderStage[]
```

This helper accepts either:

```ts
[
    // direct stage array
]
```

or:

```ts
{
    stages: [
        // nested stage array
    ]
}
```

and returns a normalized `RawRiderStage[]`.

This is used by both compiled and legacy rendering paths.

---

# Compiled Phase Lookup

Compiled phase lookup is handled by:

```ts
function compiledPhase(key: string): Record<string, any> | null
```

It reads:

```ts
props.claimExperience?.phases
```

and returns the matching phase only when:

```ts
phase.key === key
```

and:

```ts
(phase.status ?? 'active') === 'active'
```

This means inactive phases are ignored.

Examples of ignored phases:

```ts
status: 'skipped'
status: 'inactive'
status: 'disabled'
```

Only active phases are eligible for rendering.

---

# Compiled Phase Stages

Compiled phase stages are read through:

```ts
function compiledPhaseStages(key: string): RawRiderStage[]
```

For rider intro, ClaimWidget uses:

```ts
compiledPhaseStages('rider_intro')
```

This provides the raw compiled stages for the pre-claim rider intro slot.

---

# Visual Stage Filtering

ClaimWidget only renders visual preview stages in the pre-claim region.

Visual stages are currently:

```ts
splash
message
image
link
cta
```

The helper is:

```ts
function isVisualPreviewStage(stage: RawRiderStage): boolean
```

Non-visual/action stages are ignored for the pre-claim visual preview region.

Example ignored stage:

```ts
{
    key: 'compiled-action-stage',
    type: 'redirect',
    phase: 'pre_claim'
}
```

---

# Legacy Rider Stage Resolution

Legacy rider stages are still reconstructed from `voucherData`.

The legacy path uses:

```ts
riderStages
```

which combines:

```text
voucherData.rider.stages
voucherData.instructions.rider.stages
voucherData.instructions.rider.splash
```

The legacy instruction splash is synthesized by:

```ts
instructionSplashStage(data)
```

This creates a stage with:

```ts
key: 'legacy-splash'
type: 'splash'
phase: 'pre_claim'
presentation: 'fullscreen'
```

Legacy splash metadata is hydrated through:

```ts
hydrateInstructionSplashMeta(...)
```

Resolved and raw rider stages are merged through:

```ts
mergeStageWithRaw(...)
```

and deduplicated through:

```ts
uniqueStages(...)
```

This is the old compatibility path.

It remains active as a fallback.

---

# Legacy Splash Preference

When legacy preview is used, ClaimWidget prefers voucher instruction splash.

This is done by:

```ts
preferVoucherInstructionSplash(stages)
```

Current legacy priority is:

```text
legacy voucher instruction splash
before
legacy rider intro stage
```

This is why the fallback test expects:

```text
legacy-splash
```

rather than:

```text
legacy-rider-intro
```

---

# Compiled Pre-Claim Visual Stages

Compiled rider intro stages are derived here:

```ts
const compiledPreClaimVisualStages = computed<RawRiderStage[]>(() =>
    compiledPhaseStages('rider_intro')
        .filter((stage) =>
            stage.enabled !== false
            && isVisualPreviewStage(stage)
        )
);
```

A compiled stage must satisfy:

```text
phase belongs to active rider_intro
stage.enabled !== false
stage type is visual
```

Only then is it eligible for pre-claim rendering.

---

# Legacy Pre-Claim Visual Stages

Legacy fallback stages are derived here:

```ts
const legacyPreClaimVisualStages = computed<RawRiderStage[]>(() => {
    const stages = riderStages.value.filter((stage) =>
        stage.enabled !== false
        && isPreClaimStage(stage)
        && isVisualPreviewStage(stage)
    );

    return preferVoucherInstructionSplash(stages);
});
```

Legacy stages must satisfy:

```text
stage.enabled !== false
stage is in pre_claim phase
stage type is visual
```

Then voucher instruction splash is preferred if present.

---

# Rendering Priority

The key migration decision is here:

```ts
const preClaimVisualStages = computed<RawRiderStage[]>(() =>
    compiledPreClaimVisualStages.value.length > 0
        ? compiledPreClaimVisualStages.value
        : legacyPreClaimVisualStages.value
);
```

This defines the contract:

```text
compiled rider_intro wins
else legacy pre-claim preview
```

This is the core of this slice.

---

# Visual Region

The pre-claim rider intro preview renders when:

```ts
hasPreClaimContent
```

which is:

```ts
preClaimVisualStages.value.length > 0
```

The visual slot is marked by:

```text
data-testid="pre-claim-rider-region"
```

Current template shape:

```vue
<Card
    v-if="hasPreClaimContent"
    data-testid="pre-claim-rider-region"
    class="mb-4 border-primary/10 bg-primary/5"
>
    <CardContent class="pt-4 pb-4">
        <RiderRuntimeSequencer :stages="preClaimVisualStages" />
    </CardContent>
</Card>
```

This region may contain either:

```text
compiled rider_intro stages
```

or:

```text
legacy rider preview stages
```

The region name describes the visual slot, not the source of the stages.

---

# Rules

## Compiled Wins

If a usable compiled `rider_intro` exists:

```text
render compiled rider_intro
do not render legacy splash
do not render legacy rider intro
```

A usable compiled rider intro means:

```text
active rider_intro phase
+
at least one enabled visual stage
```

---

## Legacy Still Works

If no usable compiled `rider_intro` exists:

```text
render legacy rider splash / rider intro
```

Current legacy fallback prefers:

```text
legacy-splash
```

over:

```text
legacy-rider-intro
```

---

## Inactive Compiled Phases Are Ignored

If compiled `rider_intro` exists but is inactive:

```text
status != active
```

ClaimWidget ignores it and falls back to legacy.

---

## Non-Visual Compiled Stages Are Ignored

If compiled `rider_intro` contains only non-visual/action stages:

```text
type = redirect
type = action
```

ClaimWidget does not render them in the pre-claim visual preview slot.

It falls back to legacy visual stages when available.

---

## Multiple Compiled Visual Stages Preserve Order

When the compiler emits multiple visual stages:

```text
compiled-intro-image
compiled-intro-message
```

ClaimWidget passes them to `RiderRuntimeSequencer` in the same order.

The component does not reorder compiled visual stages.

---

# Runtime Stage Compiled Rendering

ClaimWidget now supports compiler-first rendering for runtime stages, following the same migration pattern used for `rider_intro`.

Runtime stages are conceptually separate from both the pre-claim rider intro region and redirect stages.

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

The three regions serve different purposes:

```text
pre-claim-rider-region
    → content shown before claim interaction

claim-widget-runtime-region
    → content shown during claim widget runtime

claim-widget-redirect-region
    → redirect-adjacent experience content
```

---

# Compiled Runtime Stages

Compiled runtime stages are derived from the active runtime phase:

```ts
const compiledRuntimeStages = computed<RawRiderStage[]>(() =>
    compiledPhaseStages('runtime')
        .filter((stage) =>
            stage.enabled !== false
            && isVisualPreviewStage(stage)
        )
);
```

A compiled runtime stage must satisfy:

```text
belongs to active runtime phase
enabled !== false
visual stage type
```

Only then is it eligible for runtime rendering.

---

# Legacy Runtime Stages

Legacy runtime stages remain available as a compatibility fallback.

They are derived from legacy rider stages:

```ts
const legacyRuntimeStages = computed<RawRiderStage[]>(() =>
    riderStages.value.filter((stage) =>
        stage.enabled !== false
        && stageIsInPhase(stage, 'runtime')
        && isVisualPreviewStage(stage)
    )
);
```

---

# Runtime Rendering Priority

```ts
const runtimeStages = computed<RawRiderStage[]>(() =>
    compiledRuntimeStages.value.length > 0
        ? compiledRuntimeStages.value
        : legacyRuntimeStages.value
);
```

Contract:

```text
compiled runtime wins
else legacy runtime fallback
```

---

# Runtime Visual Region

Runtime stages render inside:

```text
data-testid="claim-widget-runtime-region"
```

```vue
<div
    v-if="runtimeStages.length > 0"
    data-testid="claim-widget-runtime-region"
>
    <RiderRuntimeSequencer :stages="runtimeStages" />
</div>
```

---

# Runtime Rules

## Compiled Runtime Wins

```text
render compiled runtime stages
do not render legacy runtime stages
```

---

## Legacy Runtime Still Works

```text
render legacy runtime stages
```

when no usable compiled runtime phase exists.

---

## Inactive Runtime Phases Are Ignored

```text
status != active
```

causes the runtime phase to be ignored.

---

## Non-Visual Runtime Stages Are Ignored

Only visual stages participate in runtime rendering.

Examples ignored in this region:

```text
redirect
action
submit
```

---

# Runtime Debug Signal

ClaimWidget exposes:

```ts
uses_compiled_runtime: compiledRuntimeStages.value.length > 0
```

for migration diagnostics.

---

# Redirect Stage Compiled Rendering

ClaimWidget now supports compiler-first rendering for redirect stages.

Redirect stages are treated as a separate ownership concern from runtime stages.

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

---

# Compiled Redirect Stages

Compiled redirect stages are derived from:

```ts
compiledPhaseStages('redirect')
```

and filtered through:

```ts
stage.enabled !== false
isVisualPreviewStage(stage)
```

Only active visual redirect stages participate in rendering.

---

# Legacy Redirect Stages

Legacy redirect stages are reconstructed from legacy rider stages:

```ts
const legacyRedirectStages = computed(() =>
    riderStages.value.filter((stage) =>
        stage.enabled !== false
        && stageIsInPhase(stage, 'redirect')
        && isVisualPreviewStage(stage)
    )
);
```

This preserves compatibility with existing vouchers.

---

# Redirect Rendering Priority

```ts
const redirectStages = computed(() =>
    compiledRedirectStages.value.length > 0
        ? compiledRedirectStages.value
        : legacyRedirectStages.value
);
```

Contract:

```text
compiled redirect wins
else legacy redirect fallback
```

---

# Redirect Visual Region

Redirect stages render inside:

```text
data-testid="claim-widget-redirect-region"
```

```vue
<div
    v-if="redirectStages.length > 0"
    data-testid="claim-widget-redirect-region"
>
    <RiderRuntimeSequencer :stages="redirectStages" />
</div>
```

This region is intentionally separate from runtime rendering.

---

# Redirect Rules

## Compiled Redirect Wins

```text
render compiled redirect stages
do not render legacy redirect stages
```

---

## Legacy Redirect Still Works

```text
render legacy redirect stages
```

when no usable compiled redirect phase exists.

---

## Inactive Redirect Phases Are Ignored

```text
status != active
```

causes the redirect phase to be ignored.

---

## Redirect Does Not Mean Navigation

This rendering slice only governs visual redirect-stage presentation.

Actual redirect execution remains governed by the redirect gate and success-page redirect contract.

---

# Redirect Debug Signal

ClaimWidget exposes:

```ts
uses_compiled_redirect: compiledRedirectStages.value.length > 0
```

for migration diagnostics.

---

# Form Flow Ownership Boundary

ClaimWidget now recognizes whether `form_flow` is compiler-owned, but it does not render compiled form stages directly yet.

This is an intentional migration boundary.

```text
claimExperience.phases.form_flow
        ↓
ClaimWidget
        ↓
boundary detection only
```

---

## Compiled Form Flow Boundary

When an active compiled `form_flow` phase exists:

```text
phase.key = form_flow
phase.status = active
```

ClaimWidget exposes:

```text
data-testid="compiled-form-flow-boundary"
```

This means:

```text
the compiler owns the form-flow contract
```

but it does not yet mean:

```text
ClaimWidget renders compiled form stages directly
```

Compiled form stage content must not appear directly in ClaimWidget yet.

---

## Legacy Form Flow Boundary

When no active compiled `form_flow` phase exists, ClaimWidget exposes:

```text
data-testid="legacy-form-flow-boundary"
```

This means:

```text
legacy voucher-instruction form flow remains the active path
```

The legacy path remains available during migration.

---

## Current Rule

```text
active compiled form_flow
        ↓
compiled-form-flow-boundary

no active compiled form_flow
        ↓
legacy-form-flow-boundary
```

Inactive compiled phases behave the same as absent phases:

```text
status != active
        ↓
legacy-form-flow-boundary
```

---

## Why This Is Boundary-Only

Form flow is more complex than rider visual stages because it involves:

- input fields
- validation
- persistence
- submission behavior
- form-flow package ownership
- redirect back into the claim pipeline

So this slice intentionally avoids rendering compiled form stages directly.

The current purpose is only to make ownership detectable and testable.

---

## Test Coverage

Covered by:

```text
tests/frontend/ClaimWidget.form-flow-rendering.test.ts
```

Assertions include:

```text
active compiled form_flow exposes compiled-form-flow-boundary

inactive compiled form_flow exposes legacy-form-flow-boundary

absent compiled form_flow exposes legacy-form-flow-boundary

compiled form stage content is not rendered directly
```

---

## Future Direction

The next migration step is not to render raw compiled form stages inside ClaimWidget.

The likely target is:

```text
claimExperience.phases.form_flow
        ↓
FormFlowRenderer / form-flow package
        ↓
ClaimWidget shell
```

ClaimWidget should remain a shell and coordinator, not become a form engine.

# Success Rider Ownership Boundary

ClaimWidget intentionally does **not** render compiled `success_rider` phases.

This is a deliberate architectural boundary.

```text
ClaimWidget
    owns:
        rider_intro
        runtime
        redirect

Success.vue
    owns:
        success_rider
        redirect_countdown
        redirect_execution
```

The existence of a compiled `success_rider` phase does not imply that ClaimWidget should render it.

---

# Why Success Rider Is Different

The purpose of ClaimWidget is to support the claim journey before successful completion.

Current ClaimWidget responsibilities are:

```text
voucher preview

pre-claim rider content

runtime rider content

redirect-adjacent content

claim interaction
```

Success experiences occur after claim completion.

Those experiences belong to:

```text
resources/js/pages/Claim/Success.vue
```

rather than:

```text
resources/js/components/x-change/ClaimWidget.vue
```

---

# Success Rider Rendering Ownership

Compiled success rider phases are intended for:

```text
claimExperience.phases.success_rider
```

but the renderer is:

```text
Success.vue
```

not:

```text
ClaimWidget.vue
```

Rendering path:

```text
ClaimExperienceCompiler
        ↓
ClaimExperienceData
        ↓
success_rider phase
        ↓
Success.vue
```

ClaimWidget is intentionally excluded from this path.

---

# Current Rule

Even when a valid compiled success rider phase exists:

```text
phase.key = success_rider
status = active
```

ClaimWidget must not render it.

Example:

```text
compiled-success-rider-stage
```

must not appear inside:

```text
pre-claim-rider-region
claim-widget-runtime-region
claim-widget-redirect-region
```

---

# Why This Separation Exists

Without this boundary, success content could appear:

```text
before claim completion
```

or:

```text
during claim interaction
```

which creates ambiguity around ownership.

The separation keeps the journey explicit:

```text
ClaimWidget
        ↓
claim completes
        ↓
Success.vue
```

This matches the existing redirect ownership model.

---

# Future Direction

Success rider rendering will eventually become compiler-driven.

However, the target architecture is:

```text
ClaimExperienceCompiler
        ↓
ClaimExperienceData
        ↓
Success.vue
```

not:

```text
ClaimExperienceCompiler
        ↓
ClaimExperienceData
        ↓
ClaimWidget.vue
```

The migration work for success rider rendering therefore belongs to the Success page, not to ClaimWidget.

---

# Test Contract

Tests may provide compiled success rider phases to ClaimWidget.

The expected behavior is:

```text
ClaimWidget detects nothing
ClaimWidget renders nothing
ClaimWidget ignores success_rider
```

A passing test therefore looks like:

```ts
expect(wrapper.text())
    .not.toContain('compiled-success-rider-stage');
```

The purpose of this assertion is to document ownership boundaries, not compiled-phase detection.

---

# Migration Status

Current ownership map:

```text
ClaimWidget
    ✅ rider_intro
    ✅ runtime
    ✅ redirect

Success.vue
    ⏳ success_rider
    ⏳ redirect countdown
    ⏳ redirect execution
```

This boundary is intentional and should remain until success rendering is migrated into Success.vue.

---

# Test Coverage

Covered by:

```text
tests/frontend/ClaimWidget.compiled-rendering.test.ts
```

Current coverage includes:

```text
compiled rider_intro precedence
legacy rider fallback
inactive rider phase handling
non-visual rider stage handling
multi-stage rider ordering
pre-claim rider region rendering

compiled runtime precedence
legacy runtime fallback
inactive runtime handling
runtime region rendering

compiled redirect precedence
legacy redirect fallback
inactive redirect handling
redirect region rendering
```

---

# Migration Status

Completed:

```text
pre-claim rider_intro compiled rendering
runtime compiled rendering
redirect compiled rendering

compiler-first stage selection

legacy rider fallback
legacy runtime fallback
legacy redirect fallback

inactive phase guards

dedicated pre-claim rider region
dedicated runtime region
dedicated redirect region

runtime debug instrumentation
redirect debug instrumentation
```

```text
Still pending:

form_flow renderer handoff

success rider phase rendering

redirect execution ownership cleanup

legacy stage reconstruction removal

debug instrumentation cleanup
```

---

# Long-Term Direction

The long-term target remains:

```text
ClaimExperienceCompiler
        ↓
ClaimExperienceData
        ↓
ClaimWidget.vue
        ↓
Renderer-only behavior
```

Eventually, ClaimWidget should stop reconstructing the claim journey from raw voucher instructions.

For now, this migration is intentionally incremental:

```text
compiled path first
legacy fallback preserved
tests guard both
```
