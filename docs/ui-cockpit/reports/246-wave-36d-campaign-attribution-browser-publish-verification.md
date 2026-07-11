# Cockpit Wave 36D — Campaign Attribution Browser / Publish Verification

## Status

Completed.

## Mission

Verify campaign attribution result UI and campaign-aware return links in a browser, and confirm host-published Cockpit assets match package source.

## Commands

```bash
php artisan x-change:install --force
php artisan x-change:doctor --assets --json
npx playwright test tests/playwright/cockpit-quick-generate-campaign-context.spec.ts
```

## Results

- Asset doctor: checked 58, ok 58, stale 0, missing 0, extra 0.
- Playwright: 1 passed.

## Browser coverage

The Playwright smoke verifies:

- campaign context prefill card.
- campaign-prefilled form fields.
- safe campaign metadata submitted through Quick Generate.
- campaign attribution result card.
- `Return to Campaign Explorer` link.
- `Return to Campaign Dashboard` link.
- no `campaign_payload`, `provider_payload`, or `wallet` exposure.

## Boundary

The browser smoke intercepts only the POST response. No real campaign mutation, bulk issuance, feedback delivery, provider call, or wallet mutation is performed by this verification.

## Next

Cockpit Wave 36E — Campaign-Sourced Result Attribution Closure.
