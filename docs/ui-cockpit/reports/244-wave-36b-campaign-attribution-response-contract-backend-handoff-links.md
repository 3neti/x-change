# Cockpit Wave 36B — Campaign Attribution Response Contract / Backend Handoff Links

## Status

Completed.

## Mission

Carry safe campaign attribution into successful Quick Generate responses and add campaign-aware read-only return links to the post-issuance handoff.

## Added

- `campaign_attribution` response block.
- `Return to Campaign Explorer` post-issuance handoff item.
- `Return to Campaign Dashboard` post-issuance handoff item.
- Feature coverage for attribution, link availability, and response redaction.

## Behavior

When Quick Generate receives `metadata.campaign`, the response now exposes only safe attribution keys:

- planning key
- execution ID
- campaign ID
- audience ID
- recipient ID
- source
- generated Pay Code

The response also builds campaign-aware URLs with existing Cockpit query parameters.

## Boundary

This slice does not mutate campaigns, execute campaign jobs, generate bulk Pay Codes, deliver feedback, call campaign services, or expose raw campaign/provider/wallet payloads.

## Verification

```bash
php -d memory_limit=1G vendor/bin/pest tests/Feature/Cockpit/CockpitQuickGenerateCampaignAttributionResponseTest.php
```

## Next

Cockpit Wave 36C — Campaign Attribution Result UI Presentation.
