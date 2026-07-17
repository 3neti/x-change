# Cockpit Funding / Balance UI Wave — Slice 3 — Host Publish / Closure

Date: 2026-07-17

## Outcome

Closed the Cockpit Funding / Balance UI Wave by publishing package-owned Cockpit assets into the host mirror and verifying the published state.

## Published Assets

`php artisan x-change:install --force --no-interaction` published the package source changes into:

- `resources/js/cockpit/components/CockpitLiquidityHero.vue`;
- `resources/js/cockpit/dashboardDefaults.ts`.

## Verification

Commands run:

```bash
php artisan x-change:install --force --no-interaction
php artisan x-change:doctor --assets --json
npm --prefix packages/x-change run test:frontend -- tests/frontend/cockpit/CockpitDashboardFoundation.test.ts
npm run build
```

Results:

- Published asset drift: `checked 60, ok 60, stale 0, missing 0, extra 0`.
- Focused frontend dashboard test: `1 passed`, `5 tests`.
- Host production build: passed.
- Non-blocking Rolldown annotation warnings remain from third-party `node_modules/reka-ui/node_modules/@vueuse/core`.

## Boundary

This closure is host-publish and verification only.

No wallet Treasury runtime dependency, provider balance refresh, wallet reservation, release, capture, repayment, reversal, refund, voucher lifecycle mutation, journal write, action execution, feedback delivery, campaign mutation, public API behavior, lifecycle mutation, or money movement was added.

## Manual UI Check

On `/x/cockpit`, expect the Funding Status panel to show:

- `Bridge estimates`;
- `Treasury facts deferred`;
- Accounting / Internal Balance;
- Liability / Outstanding Pay Codes;
- Estimate / Usable Balance;
- External / Live Balance.
