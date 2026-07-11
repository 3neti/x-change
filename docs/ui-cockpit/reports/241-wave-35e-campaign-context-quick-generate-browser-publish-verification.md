# Cockpit Wave 35E — Campaign Context Quick Generate Browser / Publish Verification

## Status

Completed.

## Mission

Verify the campaign-context Quick Generate prefill in a browser and confirm published Cockpit assets match package source.

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

The Playwright smoke:

- Logs in as a local Cockpit operator.
- Opens `/x/cockpit/quick-generate` with campaign query parameters.
- Verifies the `Campaign context prefill` card is visible.
- Verifies template, amount, recipient, and purpose are prefilled.
- Intercepts only the POST to `/x/cockpit/quick-generate`.
- Verifies submitted metadata includes read-only campaign context.
- Verifies unsafe labels such as `campaign_payload`, `provider_payload`, and `wallet` are not submitted.

## Boundary

No real campaign mutation, campaign execution, bulk issuance, provider call, feedback delivery, or wallet mutation was performed. The POST was intercepted by Playwright.

## Next

Cockpit Wave 35F — Campaign Context Quick Generate Adoption Closure.
