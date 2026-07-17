# Cockpit Funding Status Wave 1 — Header Balance Read Model Wiring

Date: 2026-07-17

## Scope

Introduce a read-only Cockpit header balance read-model seam so `/x/cockpit` no longer depends only on static header defaults.

## Changes

- Added `CockpitHeaderReadModelProviderContract`.
- Added `CockpitHeaderReadModelData`.
- Added `WalletCockpitHeaderReadModelProvider`.
- Bound the header read model provider in `XChangeServiceProvider`.
- Hydrated `cockpit_header_read_model` into dashboard page props.
- Updated the Dashboard Vue page to pass read-model balances into `CockpitLayout`.
- Added backend and frontend coverage.

## Runtime Behavior

- Internal balance reads the authenticated operator wallet through `WalletAccessContract` when resolvable.
- If no operator wallet can be resolved, the header safely displays `Internal balance not connected`.
- Provider balance remains explicitly `Provider balance not connected`.
- The provider balance is not fetched in this wave.

## Boundary

Read-only.

This wave does not top up wallets, debit wallets, reserve funds, transfer money, call provider balance APIs, call NetBank/Paynamics/GCash, mutate vouchers, approve or redeem claims, execute drivers, write journal entries, execute x-action continuations, send x-feedback deliveries, dispatch campaigns, change public APIs, or expose raw wallet/provider payloads.

## Verification

```bash
php -d memory_limit=1G vendor/bin/pest tests/Feature/Cockpit/CockpitHeaderBalanceReadModelTest.php
npm run test:frontend -- CockpitDashboardHydration.test.ts CockpitLayout.test.ts CockpitDashboardShell.test.ts
vendor/bin/pint --dirty --format agent
```

Results:

- Backend: 2 tests passed, 25 assertions.
- Frontend: 3 files passed, 36 tests passed.
- Pint: fixed provider import/spacing style.
