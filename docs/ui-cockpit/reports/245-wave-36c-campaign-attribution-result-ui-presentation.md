# Cockpit Wave 36C — Campaign Attribution Result UI Presentation

## Status

Completed.

## Mission

Render campaign attribution on successful campaign-sourced Quick Generate responses and expose campaign-aware return links in the existing post-issuance handoff panel.

## Added

- `CockpitQuickGenerateCampaignAttribution` TypeScript type.
- `Campaign attribution` result card.
- Frontend coverage for campaign attribution and campaign-aware return links.

## Expected UI

After a campaign-prefilled Quick Generate succeeds, the result panel can show:

- `Campaign attribution`.
- planning key.
- execution ID.
- campaign ID.
- generated Pay Code.
- `Return to Campaign Explorer`.
- `Return to Campaign Dashboard`.

## Boundary

The panel is read-only. It does not mutate campaign state, execute campaign jobs, deliver feedback, call providers, or expose raw payloads.

## Verification

```bash
npx vitest run tests/frontend/cockpit/CockpitQuickGenerateFoundation.test.ts
```

## Next

Cockpit Wave 36D — Campaign Attribution Browser / Publish Verification.
