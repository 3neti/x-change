# Cockpit Funding Status Wave 2 — Provider Balance Summary Adapter

Date: 2026-07-17

## Scope

Add an opt-in provider balance summary path for the `/x/cockpit` header balance read model.

## Changes

- Added `x-change.cockpit.header_provider_balance.enabled`.
- `WalletCockpitHeaderReadModelProvider` can now read provider-side balance summary data from `BuildBalanceOverview`.
- Provider balance hydration uses `syncIfStale: false`.
- If the flag is disabled, no operator is available, no safe provider balance exists, or resolution fails, the header remains `Provider balance not connected`.
- Added coverage for the enabled provider summary path.

## Runtime Behavior

- Default behavior remains disconnected.
- The provider summary path is read-only and opt-in.
- The adapter selects provider-like balances such as `provider_wallet`, `netbank_source_account`, or `provider_*` authority records from `BuildBalanceOverview`.
- Raw wallet/provider payloads are not exposed.

## Boundary

Read-only.

This wave does not top up wallets, debit wallets, reserve funds, transfer money, refresh provider balances, call provider sync paths, mutate vouchers, approve or redeem claims, execute drivers, write journal entries, execute x-action continuations, send x-feedback deliveries, dispatch campaigns, change public APIs, or expose raw wallet/provider payloads.

## Verification

```bash
php -d memory_limit=1G vendor/bin/pest tests/Feature/Cockpit/CockpitHeaderBalanceReadModelTest.php
npm run test:frontend -- CockpitDashboardHydration.test.ts CockpitLayout.test.ts CockpitDashboardShell.test.ts
vendor/bin/pint --dirty --format agent
```

Results:

- Backend: 3 tests passed, 32 assertions.
- Frontend: 3 files passed, 36 tests passed.
- Pint: passed.
