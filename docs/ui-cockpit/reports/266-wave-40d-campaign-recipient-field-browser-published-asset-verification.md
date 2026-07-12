# Cockpit Wave 40D — Campaign Recipient Field Browser / Published Asset Verification

## Status

Completed.

## Scope

Verify the published Cockpit UI and browser Campaign → Quick Generate source-link path after Wave 40 recipient-field propagation.

No Vue component changes were made in this slice.

## Commands

```bash
php artisan x-change:install --force
```

Result: passed.

```bash
php artisan x-change:doctor --assets --json
```

Result:

```text
asset doctor: passed
checked 58, ok 58, stale 0, missing 0, extra 0
```

```bash
npx playwright test tests/playwright/cockpit-campaign-source-link.spec.ts
```

Result:

```text
Playwright: 1 passed
```

## Verified behavior

- Campaign Cockpit Adoption still renders `Open Quick Generate`.
- The link opens `/x/cockpit/quick-generate`.
- Quick Generate still renders `Campaign context prefill`.
- The browser path remains compatible with existing explicit campaign query URLs.

## UI effect

No new component.

Operators should observe the same Campaign source-link flow. The internal improvement is that adapter metadata can now normalize recipient/payout fields before the link is built.

## Next checkpoint

Cockpit Wave 40E — Campaign Recipient-to-Issuance Draft Field Mapping Closure.
