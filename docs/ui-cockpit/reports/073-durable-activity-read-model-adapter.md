# Cockpit Mutation Wave 3K — Durable Activity Read Model Adapter

Status: Implemented

Date: 2026-07-10

## Scope

This checkpoint exposes configured durable operator issuance activity records through the existing Cockpit operator activity read model.

It does not add or change UI components.

## Implemented

- Added `DurableCockpitOperatorIssuanceActivityReadModelProvider`.
- Wired `VoucherLifecycleCockpitReadModelProvider::forOperatorIssuanceActivity()` to use the durable adapter.
- Preserved the default null/not-wired read model when durable activity persistence is disabled.
- Hydrated durable records into `CockpitOperatorIssuanceActivityItemData`.
- Hydrated durable records into existing presentation DTOs through `CockpitOperatorIssuanceActivityPresenterContract`.
- Used stored handoff statuses as display facts only.

## Boundary

The adapter reads from:

```text
CockpitOperatorIssuanceActivityRepositoryContract
```

It does not:

- write activity records
- write journal entries
- execute x-action actions
- send x-feedback deliveries
- call providers
- access wallets
- mutate vouchers
- own lifecycle truth
- move money

## UI Impact

No package Vue components, pages, routes, or TypeScript contracts were changed.

Existing Cockpit dashboard rendering can display durable activity if:

1. durable activity repository is explicitly configured, and
2. durable records exist.

When persistence is disabled, the existing not-wired empty state remains unchanged.

## Tests

- Red baseline:
  - `php -d memory_limit=1G vendor/bin/pest tests/Feature/Cockpit/CockpitOperatorIssuanceActivityDurableReadModelAdapterTest.php`
  - Result: `1 failed, 1 passed, 5 assertions`
- Focused implementation:
  - `php -d memory_limit=1G vendor/bin/pest tests/Feature/Cockpit/CockpitOperatorIssuanceActivityDurableReadModelAdapterTest.php`
  - Result: `2 passed, 18 assertions`

## Next Recommended Checkpoint

Cockpit Mutation Wave 3L — Durable Activity Dashboard Verification

Recommended scope:

- verify the existing dashboard props carry durable activity read-model data when persistence is configured
- keep Vue/UI source unchanged unless a stale prop mismatch is discovered
- prove default dashboard props remain not-wired when persistence is disabled
- preserve read-only presentation semantics
