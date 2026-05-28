# Claim UI/UX Compiler Strategy & Implementation Plan

## 1. Purpose

This plan defines a safe, methodical refactor for the claim UI/UX flow.

The current flow works, but it is fragile because multiple layers are independently interpreting the same claim journey:

- voucher instructions
- YAML form-flow driver
- `ClaimWidget.vue`
- x-rider runtime
- form-flow pages
- success redirect behavior

This creates regressions such as:

- duplicate splash screens
- inconsistent fullscreen layout
- unclear redirect ownership
- missing countdown timers
- orphaned rider stages
- frontend logic becoming the accidental source of truth

The goal is to introduce a **Claim Experience Compiler** that becomes the single holder of truth for the claim journey.

---

## 2. Core Idea

The claim journey should be compiled before it is rendered.

```text
Voucher Instructions
        +
YAML Driver
        ↓
ClaimExperienceCompiler
        ↓
ClaimExperienceData
        ↓
ClaimWidget.vue
```

The compiler does not replace voucher instructions or the YAML driver.

Instead:

```text
Voucher instructions declare intent.
YAML driver declares possible form-flow steps.
Compiler resolves the actual journey.
Frontend renders the compiled journey.
```

---

## 3. Guiding Principle

```text
No frontend component should independently interpret both voucher instructions and the YAML driver.

Only the compiler may combine them.
```

This prevents the same splash, redirect, rider message, or form-flow step from being interpreted more than once.

---

## 4. Division of Labor

### 4.1 Voucher Instructions

Responsible for declaring the Pay Code contract.

They answer:

```text
What does this voucher require?
What rider content exists?
What redirect URL exists?
What validation or input requirements exist?
What settlement or payout behavior exists?
```

Examples:

```text
rider splash
rider message
rider image
rider redirect URL
required inputs
validation rules
cash/disbursement instructions
```

Voucher instructions should not directly control component rendering.

---

### 4.2 YAML Driver

Responsible for declaring the form-flow structure.

It answers:

```text
What form-flow steps are possible?
In what order may they appear?
What conditions activate each step?
What callback is used on completion?
What reference ID format is used?
```

Examples:

```text
splash
wallet/bank account form
bio form
OTP
KYC
location
selfie
signature
completion callback
```

The YAML driver is a procedural template, not the final UI truth.

---

### 4.3 ClaimExperienceCompiler

Responsible for reconciling voucher instructions and YAML output.

It answers:

```text
What is the actual claim journey?
Which phases are active?
Which phases are skipped?
Which component owns each phase?
Was a splash already consumed?
Who owns redirect?
Should countdown be shown?
What should ClaimWidget render?
```

The compiler is the single judgment layer.

---

### 4.4 ClaimWidget.vue

Responsible only for rendering the compiled experience.

It should eventually stop doing:

```text
direct voucher instruction parsing
rider splash detection
ad hoc stage merging
redirect inference
form-flow splash decisions
success-stage construction
```

It should only do:

```text
render current phase
submit claim/pre-claim action
hand rider phases to x-rider
hand form-flow phases to form-flow
execute compiled redirect behavior
```

---

### 4.5 x-rider

Responsible for rider runtime only.

It should:

```text
render rider stages
execute rider actions
handle runtime sequencing
emit completion events
```

It should not know voucher semantics.

It should not decide whether a splash belongs before claim, inside form-flow, or after success.

---

### 4.6 form-flow

Responsible for collecting claimant data.

It should:

```text
render compiled/allowed form steps
collect required fields
submit collected data
show confirmation
trigger completion callback
```

It should not render a splash if the compiler has already marked the rider intro splash as consumed.

---

## 5. Proposed Compiled Shape

The compiler should emit a normalized payload similar to:

```php
[
    'version' => 1,

    'entry' => [
        'mode' => 'rider_first',
        'initial_phase' => 'rider_intro',
    ],

    'phases' => [
        [
            'key' => 'rider_intro',
            'owner' => 'x-rider',
            'source' => 'voucher.instructions.rider.splash',
            'status' => 'active',
            'stages' => [
                // rider splash stage
            ],
        ],
        [
            'key' => 'pre_claim',
            'owner' => 'claim-widget',
            'source' => 'claim.route',
            'status' => 'active',
            'action_url' => '/claim',
        ],
        [
            'key' => 'form_flow',
            'owner' => 'form-flow',
            'source' => 'voucher-redemption.yaml',
            'status' => 'active',
            'url' => '/form-flow/{flow_id}',
            'skip_stages' => [
                'splash',
            ],
        ],
        [
            'key' => 'confirmation',
            'owner' => 'form-flow',
            'source' => 'form-flow',
            'status' => 'active',
        ],
        [
            'key' => 'success_rider',
            'owner' => 'x-rider',
            'source' => 'voucher.instructions.rider.message',
            'status' => 'active',
            'stages' => [
                // success message/image/action stages
            ],
        ],
        [
            'key' => 'redirect',
            'owner' => 'claim-widget',
            'source' => 'voucher.instructions.rider.redirect_url',
            'status' => 'active',
            'url' => 'https://example.com',
            'delay_seconds' => 5,
            'show_countdown' => true,
        ],
    ],

    'consumed' => [
        'splash' => true,
    ],

    'diagnostics' => [
        'duplicate_splash_prevented' => true,
        'redirect_owner' => 'claim-widget',
    ],
]
```

Every phase must have:

```text
key
owner
source
status
```

This prevents anonymous, orphan-generating slices.

---

## 6. Non-Negotiable Invariants

### 6.1 One Splash Owner

```text
A splash may appear before claim or inside form-flow, but not both.
```

If voucher rider splash is rendered before claim:

```text
form-flow splash must be skipped.
```

---

### 6.2 One Redirect Owner

```text
Only one layer may execute the final redirect.
```

Allowed owners:

```text
claim-widget
x-rider
success-page
```

But never more than one for the same journey.

---

### 6.3 One Fullscreen Shell

```text
Only one component should own fullscreen chrome for a rider phase.
```

Recommended rule:

```text
RiderRuntimeSequencer owns fullscreen/modal layout.
RiderStagePresenter renders content only.
```

---

### 6.4 Frontend Must Not Recompile

```text
ClaimWidget.vue must not reconstruct the claim journey from raw voucher instructions once compiler output exists.
```

It may keep a temporary legacy fallback during migration, but the target state is renderer-only.

---

### 6.5 YAML Remains a Contract Artifact

The YAML driver must remain part of the claim UX contract.

The compiler should inspect it or consume its transformed output instead of bypassing it.

---

## 7. Implementation Phases

## Phase 0 — Baseline and Freeze Current Behavior

Before refactoring, document the current observed journey.

Current baseline:

```text
1. Rider splash
2. Pre-claim form
3. Form-flow splash
4. Form-flow wallet/bank form
5. Confirmation
6. Redemption waiting
7. Success rider message
8. Redirect to rider URL
```

Create a short markdown fixture documenting this as the current behavior.

Suggested file:

```text
docs/claim-ux/current-claim-journey.md
```

Purpose:

```text
We need to know what behavior exists before deciding what to preserve or intentionally change.
```

---

## Phase 1 — Add Compiler as Shadow Payload

Create backend compiler classes, but do not change the UI yet.

Suggested classes:

```php
ResolveClaimExperience
ClaimExperienceCompiler
ClaimExperienceData
ClaimPhaseData
ClaimRiderPhaseData
ClaimFormFlowPhaseData
ClaimRedirectData
ClaimExperienceDiagnosticsData
```

Initial goal:

```text
Compiler observes and reports the claim journey.
It does not control rendering yet.
```

Controller payload should include both:

```php
[
    'claim_experience' => $compiledExperience,
    'legacy_claim_payload' => $existingPayload,
]
```

No deletion yet.

No frontend behavior change yet.

---

## Phase 2 — Build Compiler Tests

Add tests for representative voucher scenarios.

Required scenarios:

```text
voucher with rider splash
voucher with no rider
voucher with rider success message only
voucher with form-flow splash
voucher with redirect URL
voucher with redirect delay
voucher with required bank/mobile fields
voucher with bio fields
voucher with OTP/KYC/location/selfie/signature requirements
```

Critical assertions:

```text
no duplicate splash unless explicitly allowed
exactly one redirect owner
form-flow splash is skipped if rider intro consumed splash
success rider stage still appears after redemption
countdown metadata exists when redirect delay exists
all phases have key, owner, source, status
```

Suggested test names:

```php
it('compiles a rider-first claim experience without duplicating splash')
it('keeps form-flow splash when no rider intro splash exists')
it('assigns exactly one redirect owner')
it('preserves success rider message after confirmation')
it('marks consumed stages in diagnostics')
it('does not emit anonymous phases')
```

---

## Phase 3 — Add ClaimWidget Fallback Consumption

Update `ClaimWidget.vue` to prefer compiler output but retain legacy fallback.

Target pattern:

```js
const experience = computed(() => {
    return props.claim_experience ?? buildLegacyExperienceFromProps(props)
})
```

At this phase:

```text
New payload is preferred.
Old payload still works.
```

This avoids a hard cutover.

---

## Phase 4 — Move Stage Resolution Out of ClaimWidget

Gradually move these responsibilities into the compiler:

```text
instructionSplashStage()
preClaimVisualStages
success rider stage assembly
redirect countdown derivation
form-flow splash skip decision
legacy rider stage merging
```

After each move:

```text
run tests
verify claim flow manually
do not delete fallback yet
```

---

## Phase 5 — Normalize Phase Ownership

Adopt explicit phase ownership.

Canonical owners:

```text
rider_intro      → x-rider
pre_claim        → claim-widget
form_flow        → form-flow
confirmation     → form-flow
redemption_wait  → claim-widget or form-flow
success_rider    → x-rider
redirect         → claim-widget or x-rider, but exactly one
```

The compiler should emit this directly.

Example:

```php
[
    'key' => 'form_flow',
    'owner' => 'form-flow',
    'source' => 'voucher-redemption.yaml',
    'skip_stages' => ['splash'],
]
```

---

## Phase 6 — Fix Fullscreen Shell Collision

Audit x-rider rendering.

Likely problem:

```text
RiderRuntimeSequencer has fullscreen wrapper
RiderStagePresenter also has fullscreen wrapper
```

Refactor toward:

```text
RiderRuntimeSequencer owns layout shell.
RiderStagePresenter owns inner content.
```

Possible prop:

```ts
presentationMode: 'fullscreen' | 'embedded' | 'bare'
```

Recommended target:

```text
RuntimeSequencer passes presentationMode="bare" to StagePresenter when runtime owns chrome.
```

Add frontend tests for:

```text
fullscreen class applied once
stage content remains centered
continue button remains centered
second splash does not stretch unexpectedly
```

---

## Phase 7 — Remove Legacy Interpretation

Only after tests are green and manual flow is stable:

Remove from `ClaimWidget.vue`:

```text
direct voucher instruction parsing
instructionSplashStage()
ad hoc rider stage merging
duplicate redirect inference
direct splash/form-flow conflict resolution
```

ClaimWidget should become a renderer of `ClaimExperienceData`.

Final target:

```text
ClaimWidget.vue receives compiled phases.
ClaimWidget.vue renders phases.
ClaimWidget.vue does not decide claim structure.
```

---

## 8. Collision Risk Assessment

### 8.1 Splash Collision

Current likely issue:

```text
voucher rider.splash
+
YAML splash step
=
same splash appears twice
```

Mitigation:

```text
compiler emits consumed.splash = true
compiler emits form_flow.skip_stages = ['splash']
```

---

### 8.2 Redirect Collision

Possible issue:

```text
success rider action redirect
+
success page redirect timer
+
ClaimWidget redirect
=
countdown missing or duplicate redirect
```

Mitigation:

```text
compiler assigns exactly one redirect owner
compiler emits show_countdown explicitly
```

---

### 8.3 Layout Collision

Likely issue:

```text
fullscreen runtime wrapper
+
fullscreen presenter wrapper
=
inconsistent rendering
```

Mitigation:

```text
single fullscreen shell owner
presenter supports bare/embedded mode
```

---

### 8.4 Orphaned Stage Slices

Risk:

```text
extracting partial rider logic without source tracking
```

Mitigation:

```text
all phases require key, owner, source, status
diagnostics must report skipped/consumed/ignored stages
```

---

### 8.5 YAML Drift

Risk:

```text
compiler hardcodes behavior that already exists in voucher-redemption.yaml
```

Mitigation:

```text
compiler reads YAML or transformed driver output
tests use the actual driver fixture
```

---

## 9. Development Discipline

Use shadowing, not deletion.

```text
1. Observe old behavior
2. Compile equivalent behavior
3. Snapshot compiled behavior
4. Switch renderer to compiled behavior
5. Remove old interpretation
```

Never do:

```text
delete old ClaimWidget logic first
then attempt to recreate behavior
```

That will create regressions.

---

## 10. Suggested First Scaffold

Start with backend-only classes.

### Data Objects

```php
ClaimExperienceData
ClaimPhaseData
ClaimRiderStageData
ClaimRedirectData
ClaimExperienceDiagnosticsData
```

### Action

```php
ResolveClaimExperience
```

### Service

```php
ClaimExperienceCompiler
```

### Tests

```php
ResolveClaimExperienceTest
ClaimExperienceCompilerTest
```

### Temporary Controller Payload

```php
return Inertia::render('Claim/Show', [
    // existing props
    'voucher' => $voucherPayload,
    'instructions' => $instructionsPayload,
    'rider' => $riderPayload,

    // new shadow prop
    'claim_experience' => ResolveClaimExperience::run($voucher),
]);
```

---

## 11. Suggested Folder Placement

If inside x-change:

```text
src/Actions/Claim/ResolveClaimExperience.php
src/Services/Claim/ClaimExperienceCompiler.php
src/Data/Claim/ClaimExperienceData.php
src/Data/Claim/ClaimPhaseData.php
src/Data/Claim/ClaimRedirectData.php
src/Data/Claim/ClaimExperienceDiagnosticsData.php
```

If host-app first:

```text
app/Actions/Claim/ResolveClaimExperience.php
app/Services/Claim/ClaimExperienceCompiler.php
app/Data/Claim/ClaimExperienceData.php
```

Recommended approach:

```text
Host-app first.
Package extraction later.
```

Reason:

```text
The actual claim UI behavior is still being discovered.
We should stabilize it in the host app before freezing it into the package boundary.
```

---

## 12. Success Criteria

This refactor is successful when:

```text
ClaimWidget no longer decides splash duplication.
ClaimWidget no longer directly interprets voucher instructions.
ClaimWidget renders compiled phases.
x-rider renders rider stages consistently.
form-flow does not duplicate rider splash.
success redirect countdown is explicit and stable.
tests prove one splash owner and one redirect owner.
manual claim flow feels coherent from start to finish.
```

---

## 13. Final Target Architecture

```text
Voucher Instructions
    ↓
YAML Driver / FormFlow Driver Transform
    ↓
ClaimExperienceCompiler
    ↓
ClaimExperienceData
    ↓
ClaimWidget.vue
    ├─ x-rider for rider phases
    ├─ form-flow for data collection phases
    └─ redirect/countdown according to compiled instruction
```

---

## 14. Final Rule

```text
The compiler owns truth.
ClaimWidget owns rendering.
x-rider owns rider runtime.
form-flow owns data collection.
voucher instructions own requirements.
YAML owns possible form steps.
```

That division of labor is the guardrail against future claim UI regressions.
