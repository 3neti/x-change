# Host Integration Slice 2G — Integration Error / Unavailable States

## Status

Complete.

## Scope

Harden Cockpit presentation for unavailable Journal / Action / Feedback read models.

## Implemented

- Dashboard integration summary cards display safe unavailable reasons from `redactions.reason`.
- Voucher Detail integration summary cards display safe unavailable reasons from `redactions.reason`.
- Exception classes and exception messages remain hidden from the operator UI.

## Boundaries

This slice does not:

- expose exception messages
- expose exception classes
- retry failed adapters
- add queues
- add observability exporters
- write journal entries
- execute actions
- send feedback
- call providers
- mutate vouchers
- move money

## Verification

```bash
npm run test:frontend -- tests/frontend/cockpit/CockpitDashboardHydration.test.ts tests/frontend/cockpit/CockpitVoucherDetailHydration.test.ts
```

Result:

```text
2 passed, 20 tests
```
