# Cockpit Wave 41C — Campaign Recipient Source-Link UI Presentation

## Status

Completed.

## What changed

- The Campaign Cockpit Adoption panel now renders `recipient_quick_generate_links`.
- The panel displays a `Recipient Quick Generate entry points` section when safe recipient links are available.
- Each recipient entry point links to the existing Quick Generate handoff with campaign recipient context.

## UI behavior

Operators can now see recipient-specific source links such as:

- `Generate for Ana`
- `Generate for Ben`

Each link remains a read-only prefill path. The operator must still submit the Quick Generate form before a Pay Code can be issued.

## Safety constraints

- No campaign mutation button was added.
- No bulk issuance was added.
- No automatic generation occurs on link render.
- Unsafe payload fields remain unrendered.
- Quick Generate remains the only issuance handoff.

## Test coverage

- `CockpitDashboardHydration.test.ts` verifies recipient source-link rendering, href propagation, recipient reference display, and unsafe payload suppression.

## Expected UI result

`/x/cockpit` can now show a `Recipient Quick Generate entry points` card under Campaign Cockpit Adoption when the campaign read model includes safe recipient source links.

## Next checkpoint

`Cockpit Wave 41D — Campaign Recipient Source-Link Publish / Browser Verification`.
