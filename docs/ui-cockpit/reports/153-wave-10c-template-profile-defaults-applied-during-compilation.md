# Cockpit Wave 10C — Template Profile Defaults Applied During Compilation

## Status

Implemented.

## Purpose

Allow the Cockpit issuance draft compiler to apply template profile defaults from `CockpitIssuanceTemplateRegistryContract`.

## Behavior

- Template default validation is merged into `cash.validation`.
- Template default input fields are merged into `inputs.fields`.
- Template default feedback is merged into `feedback`.
- Template default rider data is merged into `rider`.
- Explicit draft values override template defaults.
- Template metadata is exposed under `metadata.template`.

## Boundary

This slice does not:

- Add new templates from the UI.
- Enable disabled templates.
- Add campaign mutation.
- Add persistence.
- Call providers.
- Move money outside the existing `GeneratePayCode` path.

## Verification

Focused test:

```bash
php -d memory_limit=1G vendor/bin/pest tests/Unit/Cockpit/DefaultCockpitIssuanceDraftCompilerTest.php
```

## Expected UI Effect

Existing Quick Generate submissions with blank message may receive the template default rider message in the generated Pay Code payload.

## Next Recommended Checkpoint

Cockpit Wave 10D — Pricing Estimate Preflight for Compiled Drafts.
