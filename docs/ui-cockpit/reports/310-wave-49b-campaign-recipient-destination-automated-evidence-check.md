# Cockpit Wave 49B — Campaign Recipient Destination Automated Evidence Check

## Status

Completed.

## Evidence source

Playwright verifies the campaign-attributed activity navigation path:

```text
/x/cockpit
    → Open Pay Code
    → Pay Code Detail campaign context card
    → return links
    → Open Distribution workspace
    → Distribution Workspace campaign context card
    → return links
```

## Verified facts

- Dashboard activity cards preserve safe campaign-recipient context.
- Pay Code Detail shows `Opened from campaign activity`.
- Distribution Workspace shows `Inspecting distribution from campaign activity`.
- Destination return links use `Back to ... · read-only` copy.
- Campaign-recipient query context remains present.
- Unsafe payload labels remain absent from rendered destination context cards.

## Command

```bash
npm run test:browser -- tests/playwright/cockpit-campaign-activity-navigation.spec.ts
```

## Result

Playwright: 1 passed.

## Boundary

The automated evidence check does not add mutation behavior.

No campaign mutation, distribution dispatch, bulk issuance, feedback delivery, journal writes, provider calls, wallet movement, lifecycle truth ownership, or unsafe payload exposure was added.

## Next slice

`Cockpit Wave 49C — Campaign Recipient Destination Human Acceptance Record Template`
