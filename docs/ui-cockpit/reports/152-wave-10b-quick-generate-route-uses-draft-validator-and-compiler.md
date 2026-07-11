# Cockpit Wave 10B — Quick Generate Route Uses Draft Validator and Compiler

## Status

Implemented.

## Purpose

Adopt the Wave 9/10A draft runtime inside the existing Cockpit Quick Generate mutation route while preserving the existing `GeneratePayCode` issuance handoff.

## Runtime Path

```text
GeneratePayCodeRequest::validated()
    ↓
CockpitQuickGenerateDraftFactoryContract
    ↓
CockpitIssuanceDraftValidatorContract
    ↓
CockpitIssuanceDraftCompilerContract
    ↓
GeneratePayCode
```

## Behavior

- The route still uses `GeneratePayCodeRequest` as the HTTP validation contract.
- The route now converts the validated form payload to `CockpitIssuanceDraftData`.
- Draft validation runs before issuance.
- Disabled or unknown templates fail with validation errors before `GeneratePayCode`.
- The compiled payload remains `GeneratePayCodeRequest` compatible.
- The existing response schema remains unchanged for client compatibility.
- Draft compiler metadata is additive and operator-safe.

## Boundary

This slice does not:

- Add new UI controls.
- Add campaign mutation.
- Add pricing or funding blocking.
- Write journal entries.
- Execute actions.
- Send feedback.
- Change the public Pay Code API route.
- Change provider behavior.
- Move money outside the existing `GeneratePayCode` path.

## Verification

Focused test:

```bash
php -d memory_limit=1G vendor/bin/pest tests/Feature/Cockpit/CockpitQuickGenerateRuntimeCompilerAdoptionTest.php
```

## Expected UI Effect

None beyond preserving the existing Quick Generate behavior. This slice changes the backend route path.

## Next Recommended Checkpoint

Cockpit Wave 10C — Template Profile Defaults Applied During Compilation.
