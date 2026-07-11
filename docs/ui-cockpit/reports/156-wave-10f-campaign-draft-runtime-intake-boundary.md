# Cockpit Wave 10F — Campaign Draft Runtime Intake Boundary

## Status

Implemented.

## Purpose

Allow Quick Generate runtime draft compilation to carry campaign context metadata into the existing issuance handoff without mutating campaigns.

## Behavior

- `GeneratePayCodeRequest` now allows safe `metadata.campaign` keys.
- `DefaultCockpitQuickGenerateDraftFactory` creates `CockpitIssuanceCampaignContextData` when campaign context is present.
- The existing compiler passes campaign context under `metadata.campaign`.
- The route still calls only `GeneratePayCode`.

## Boundary

This slice does not:

- Create campaigns.
- Update campaigns.
- Attach recipients.
- Enqueue campaign jobs.
- Generate campaign batches.
- Deliver feedback.
- Write campaign journal entries.

## Verification

Focused tests:

```bash
php -d memory_limit=1G vendor/bin/pest \
  tests/Unit/Cockpit/DefaultCockpitQuickGenerateDraftFactoryTest.php \
  tests/Feature/Cockpit/CockpitQuickGenerateCampaignDraftRuntimeIntakeTest.php
```

## Expected UI Effect

None. This is a backend intake boundary for future campaign-originated drafts.

## Next Recommended Checkpoint

Cockpit Wave 10G — Operator-safe Response / Activity Metadata Alignment.
