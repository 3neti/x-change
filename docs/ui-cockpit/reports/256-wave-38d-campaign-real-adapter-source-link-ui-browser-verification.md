# Cockpit Wave 38D — Campaign Real Adapter Source Link UI / Browser Verification

## Status

Completed.

## Scope

Verify the Campaign → Quick Generate source-link UI path after Wave 38's real-adapter source-context hardening.

This slice does not add new UI. It verifies the existing Campaign Cockpit Adoption `Open Quick Generate` link after the package assets are published to the host mirrors.

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

## Browser assertions

The browser smoke verified:

- `/x/cockpit` renders Campaign Cockpit Adoption;
- `Open Quick Generate` is visible;
- the link targets `/x/cockpit/quick-generate`;
- campaign context query values are preserved in the link;
- clicking the link opens Quick Generate;
- Quick Generate renders the `Campaign context prefill` panel;
- the form is prefilled with campaign template, amount, recipient, and purpose values.

## UI effect

No new component.

Operators should continue seeing the same Campaign Cockpit Adoption `Open Quick Generate` link. The verified behavior is that the link remains browser-safe after x-change added real x-campaign DTO source-context support.

## Boundaries

Still blocked:

- campaign mutation
- bulk issuance
- delivery dispatch
- provider calls
- direct wallet access or movement
- journal/action/feedback mutation
- bypassing existing `GeneratePayCode` ownership

## Next checkpoint

Cockpit Wave 38E — Campaign Workspace Entry Point Real Adapter Adoption Closure.
