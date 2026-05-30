# ClaimWidget Compiled Rendering

## Purpose

This document defines the first compiled rendering contract inside:

```text
resources/js/components/x-change/ClaimWidget.vue
```

`ClaimWidget.vue` is being migrated from legacy interpretation toward compiler-driven rendering.

This slice covers the **pre-claim rider intro** area only.

The goal is not yet to remove legacy behavior. The goal is to make compiled behavior win when available, while preserving legacy fallback during migration.

---

# Current Scope

ClaimWidget now prefers compiled `rider_intro` phases when available.

```text
claimExperience.phases[]
        ↓
rider_intro
        ↓
pre-claim-rider-region
        ↓
RiderRuntimeSequencer
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

# Runtime Stages Are Separate

ClaimWidget also has a separate runtime-stage path:

```ts
const runtimeStages = computed<RawRiderStage[]>(() =>
    riderStages.value.filter((stage) =>
        stage.enabled !== false
        && stageIsInPhase(stage, 'runtime')
        && isVisualPreviewStage(stage)
    )
);
```

This is rendered separately:

```vue
<RiderRuntimeSequencer
    v-if="runtimeStages.length > 0"
    :stages="runtimeStages"
/>
```

This slice does not migrate runtime stages to compiler-driven rendering yet.

Only the pre-claim `rider_intro` visual slot is covered.

---

# Debug Signal

ClaimWidget currently exposes a development debug signal:

```ts
uses_compiled_rider_intro: compiledPreClaimVisualStages.value.length > 0
```

This is useful during migration because it shows whether the compiled path is actually being used.

Example:

```text
[x-change] claim experience {
  uses_compiled_rider_intro: true
}
```

This should be treated as temporary migration instrumentation.

---

# Test Coverage

Covered by:

```text
tests/frontend/ClaimWidget.compiled-rendering.test.ts
```

Assertions include:

```text
prefers compiled rider intro stages over legacy rider splash stages

falls back to legacy voucher instruction splash when compiled rider intro is absent

renders compiled rider intro even when voucher preview has no legacy rider stages

ignores inactive compiled rider intro and falls back to legacy splash

ignores non-visual compiled rider intro stages and falls back to legacy splash

renders multiple compiled rider intro visual stages in order

renders rider intro preview inside a dedicated pre-claim rider region
```

---

# Test Harness Notes

The frontend test uses a controllable voucher preview fixture.

This matters because the test suite must distinguish between:

```text
compiled wins over legacy
```

and:

```text
compiled works without legacy data
```

Those are different guarantees.

A fixed mock containing legacy data cannot prove that compiled rendering works without legacy data.

The fixture must be overridable per test.

---

# Migration Status

Completed in this slice:

```text
pre-claim rider_intro compiled rendering
compiled-over-legacy priority
legacy fallback preservation
inactive compiled phase guard
non-visual compiled stage guard
multi-stage ordering
pre-claim rider visual region marker
```

Not yet migrated:

```text
pre_claim form action ownership
form_flow phase rendering
success rider phase rendering
redirect phase rendering inside ClaimWidget
runtime stage ownership
legacy stage cleanup
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
