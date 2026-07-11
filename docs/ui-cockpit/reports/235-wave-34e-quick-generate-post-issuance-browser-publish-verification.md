# Cockpit Wave 34E — Quick Generate Post-Issuance Browser / Publish Verification

## Mission

Verify that package-owned Quick Generate post-issuance navigation is published to the host and browser-rendered without performing real issuance.

## Verification

- `php artisan x-change:install --force`
- `php artisan x-change:doctor --assets --json`
- `npx playwright test tests/playwright/cockpit-quick-generate-post-issuance.spec.ts`

## Browser Strategy

The Playwright smoke logs into the local host app, opens `/x/cockpit/quick-generate`, intercepts only the POST to `/x/cockpit/quick-generate`, and returns an operator-safe fixture response.

This verifies the real browser-rendered result panel without creating a real Pay Code, calling providers, using wallets, writing journal entries, sending feedback, executing actions, or mutating campaigns.

## Expected UI Result

The browser-rendered Quick Generate result panel shows:

- `Generated Pay Code: PC-PLAYWRIGHT-34`;
- `Post-issuance handoff`;
- `Open Cockpit detail`;
- `Open Distribution workspace`;
- `Automatic redirect: disabled`;
- `read-only` destination labels.

## Boundaries

No real issuance was performed by the browser verification. No feedback dispatch, QR/short-link generation, print artifact generation, provider call, campaign mutation, extra money movement, or unsafe payload exposure was added.

## Next Slice

Cockpit Wave 34F — Post-Issuance Navigation Closure.
