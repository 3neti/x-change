# Cockpit Wave 37C — Campaign Quick Generate Source Link UI Presentation

## Status

Completed.

## Mission

Render the campaign Quick Generate source link in the Cockpit dashboard campaign adoption panel.

## Implementation

- Added the frontend `CockpitCampaignQuickGenerateLink` type.
- Extended `CockpitCampaignAdoptionPanel` to render an enabled read-only `Open Quick Generate` source link when `campaign_read_model.quick_generate_link` is available.
- Kept the link as normal navigation into the existing Quick Generate page.
- Kept campaign mutation controls absent.
- Kept raw campaign, recipient, provider, wallet, balance, and generation payloads out of the UI.

## Expected UI result

On `/x/cockpit` with campaign query context, the Campaign Cockpit Adoption panel can show:

- `Open Quick Generate`.
- `Prefills the existing Quick Generate handoff`.
- read-only campaign context metadata.

Clicking the link should open `/x/cockpit/quick-generate` with the campaign prefill query parameters.

## Tests

```bash
npx vitest run tests/frontend/cockpit/CockpitDashboardHydration.test.ts
```

Result: 1 file passed, 19 tests passed.

## Next

Cockpit Wave 37D — Campaign Quick Generate Source Link Browser / Publish Verification.
