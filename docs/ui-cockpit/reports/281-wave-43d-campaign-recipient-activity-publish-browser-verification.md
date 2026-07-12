# Cockpit Wave 43D — Campaign Recipient Activity Publish / Browser Verification

## Status

Completed.

## Verification performed

- Published x-change Cockpit assets into the host app with `php artisan x-change:install --force`.
- Verified published Cockpit asset drift with `php artisan x-change:doctor --assets --json`.
- Ran browser coverage for the campaign-prefilled Quick Generate submission path.

## Results

```text
php artisan x-change:install --force
Result: passed

php artisan x-change:doctor --assets --json
Result: passed
Asset summary: checked 58, ok 58, stale 0, missing 0, extra 0

npm run test:browser -- tests/playwright/cockpit-quick-generate-campaign-context.spec.ts
Result: passed after escalation
Reason for escalation: Playwright setup runs Laravel tinker and browser processes that write user-level PsySH/browser state outside the sandbox.
```

## Expected UI result

The host mirror now contains the dashboard activity card changes from Wave 43C. When durable activity contains safe campaign-recipient attribution, `/x/cockpit` can show a read-only `Campaign attribution` section inside Operator Issuance Activity.

The browser smoke also confirms the upstream campaign-prefilled Quick Generate path still renders and submits safe campaign metadata.

## Boundaries preserved

- No campaign mutation.
- No campaign progress update.
- No bulk issuance.
- No delivery dispatch.
- No lifecycle truth ownership.
- No unsafe payload rendering.
- Existing `GeneratePayCode` remains the issuance owner.

## Next checkpoint

`Cockpit Wave 43E — Campaign Recipient Issuance Activity Attribution Closure`.
