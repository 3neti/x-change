# Cockpit Wave 34D — Quick Generate Post-Issuance UI Presentation

## Mission

Render the hydrated Quick Generate post-issuance navigation block in the successful result panel.

## Added UI

After a successful Quick Generate submit, the result card can now show:

- `Post-issuance handoff`;
- `Open Cockpit detail`;
- `Open Distribution workspace`;
- automatic redirect status;
- read-only destination status.

## Boundary

The links are navigation only. This slice does not auto-redirect, dispatch feedback, generate QR/short links, generate print artifacts, execute drivers, write journal entries, execute actions, mutate campaigns, call providers outside the existing issuance path, move money outside the existing issuance path, or expose unsafe payloads.

## Expected UI Result

After generating a Pay Code from `/x/cockpit/quick-generate`, the result panel should show a `Post-issuance handoff` card with Detail and Distribution links.

## Verification

- `npx vitest run tests/frontend/cockpit/CockpitQuickGenerateFoundation.test.ts`

## Next Slice

Cockpit Wave 34E — Browser / Publish Verification.
