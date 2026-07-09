# Host Integration Slice 2A — Journal Cockpit Hydration

## Status

Complete.

## Scope

Hydrate read-only x-journal evidence summaries into the existing Cockpit Voucher Detail audit surface.

## Implemented

- Voucher Detail now renders journal read-model entries when `read_model.journal.status` is `available`.
- Rendered journal entries use summary fields only:
  - `event_type` / `type` / `event`
  - `summary` / `description`
  - `occurred_at` / `timestamp` / `created_at`
  - journal payload policy
- Missing or unavailable journal read models keep the existing unavailable audit row.

## Boundaries

This slice does not:

- write journal entries
- query journal directly from the frontend
- expose raw journal payloads
- expose provider payloads
- expose wallet payloads
- execute actions
- send feedback
- call providers
- mutate vouchers
- move money

## Verification

```bash
npm run test:frontend -- tests/frontend/cockpit/CockpitVoucherDetailHydration.test.ts
```

Result:

```text
1 passed, 5 tests
```
