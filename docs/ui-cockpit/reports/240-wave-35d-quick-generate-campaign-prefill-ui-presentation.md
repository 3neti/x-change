# Wave 35D — Quick Generate Campaign Prefill UI Presentation

## Status

Completed.

## Mission

Render hydrated campaign context in Quick Generate and use its draft values to prefill the operator form.

## Added

- Campaign context TypeScript types.
- Campaign context prop handoff from the Quick Generate page to the submit panel.
- Visible `Campaign context prefill` card.
- Form prefill from the read-only campaign draft.
- Submission metadata that marks campaign context as read-only and non-mutating.

## Boundary

The UI still submits through the existing Quick Generate issuance handoff. Campaign context is metadata/prefill only. It does not mutate campaigns, execute bulk issuance, deliver feedback, call providers, reserve funds, or expose raw campaign/provider/wallet payloads.

## Expected UI

When `/x/cockpit/quick-generate` is opened with campaign query parameters, operators should see a `Campaign context prefill` card and the form fields should be prefilled from the campaign draft.

## Verification

```bash
npx vitest run tests/frontend/cockpit/CockpitQuickGenerateFoundation.test.ts
```

## Next

Cockpit Wave 35E — Campaign Context Quick Generate Browser / Publish Verification.
