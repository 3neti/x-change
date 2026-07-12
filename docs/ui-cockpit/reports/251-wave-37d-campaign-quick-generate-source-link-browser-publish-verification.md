# Cockpit Wave 37D — Campaign Quick Generate Source Link Browser / Publish Verification

## Status

Completed.

## Mission

Verify the campaign source link in the browser after publishing x-change package assets to the host app.

## Verification

- Published package assets with `php artisan x-change:install --force`.
- Confirmed published Cockpit assets match package source.
- Added Playwright coverage for the operator path:
  - open `/x/cockpit` with campaign context query parameters.
  - see `Open Quick Generate` in the Campaign Cockpit Adoption panel.
  - click the source link.
  - land on `/x/cockpit/quick-generate`.
  - see `Campaign context prefill`.
  - verify template, amount, recipient reference, and purpose are prefilled.

## Commands

```bash
php artisan x-change:install --force
php artisan x-change:doctor --assets --json
npx playwright test tests/playwright/cockpit-campaign-source-link.spec.ts
```

Results:

- asset doctor: passed, 58 checked, 58 ok, 0 stale.
- Playwright: 1 passed.

## Expected UI result

`/x/cockpit?...campaign_*` can now show an `Open Quick Generate` source link in Campaign Cockpit Adoption. Clicking it opens Quick Generate with the Campaign context prefill card and prefilled form values.

## Next

Cockpit Wave 37E — Campaign Context Source Link Generation Closure.
