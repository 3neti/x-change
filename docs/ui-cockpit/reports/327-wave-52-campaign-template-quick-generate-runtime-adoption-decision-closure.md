# Cockpit Wave 52 — Campaign Template Quick Generate Runtime Adoption Decision Closure

## Status

Completed.

## Completed Slices

- Wave 52A — Campaign Template Quick Generate Runtime Adoption Audit
- Wave 52B — Runtime Adoption Decision Existing Payload Path
- Wave 52C — Runtime Payload Compatibility Guard
- Wave 52D — Backend Runtime Intake Guard

## Final Decision

Campaign Template Quick Generate runtime adoption uses the existing x-change Quick Generate issuance handoff:

```text
Quick Generate campaign context
    ↓
GeneratePayCodeRequest-compatible payload
    ↓
CockpitQuickGenerateDraftFactoryContract
    ↓
CockpitIssuanceDraftValidatorContract
    ↓
CockpitIssuanceDraftCompilerContract
    ↓
GeneratePayCode
```

The campaign draft adapter remains a read-model/source-link preparation seam and is not injected directly into the runtime mutation route.

## Runtime Compatibility

Campaign-prefilled submits now carry:

```text
cash.validation.mobile
inputs.fields[] = mobile
feedback.mobile
metadata.campaign.*
```

This matches the compiler-compatible request shape characterized in Wave 51.

## Publish / Drift Evidence

Host assets were republished with:

```bash
php artisan x-change:install --force
```

Published asset drift was checked with:

```bash
php artisan x-change:doctor --assets --json
```

Result:

```text
checked 58, ok 58, stale 0, missing 0, extra 0
```

## Tests

- `npm run test:frontend -- tests/frontend/cockpit/CockpitQuickGenerateFoundation.test.ts`
- `php -d memory_limit=1G vendor/bin/pest tests/Feature/Cockpit/CockpitQuickGenerateCampaignRuntimeAdoptionTest.php`
- `php -d memory_limit=1G vendor/bin/pest tests/Unit/Architecture/CockpitWave52aCampaignTemplateQuickGenerateRuntimeAdoptionAuditTest.php tests/Unit/Architecture/CockpitWave52bRuntimeAdoptionDecisionExistingPayloadPathTest.php tests/Unit/Architecture/CockpitWave52cRuntimePayloadCompatibilityGuardTest.php tests/Unit/Architecture/CockpitWave52dBackendRuntimeIntakeGuardTest.php tests/Unit/Architecture/CockpitWave52CampaignTemplateQuickGenerateRuntimeAdoptionDecisionClosureTest.php`

## Expected UI Result

No new card was added.

The existing Campaign context prefill card remains the operator-visible entry point. The functional change is behind the submit button: generated campaign-prefilled requests now include mobile validation semantics before handing off to existing issuance.

## Remaining Boundaries

- No campaign mutation.
- No bulk issuance.
- No provider call from Cockpit.
- No direct wallet movement from Cockpit.
- No direct journal/action/feedback side effect from Cockpit.
- No second issuance runtime.

## Next Recommended Wave

Cockpit Wave 53 — Campaign Quick Generate Full URL / Distribution Link Readiness.

Suggested focus:

- expose generated full Pay Code URL/redeem URL in the operator-safe result panel;
- keep route generation owned by existing x-change link/result services;
- preserve campaign attribution and post-issuance navigation;
- avoid delivery, dispatch, provider calls, and campaign mutation.
