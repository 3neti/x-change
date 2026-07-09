# Host Integration Slice 2B — Action Cockpit Hydration

## Status

Complete.

## Scope

Hydrate x-action read-model CTA summaries into the existing Cockpit Voucher Detail operator action surface.

## Implemented

- Voucher Detail maps available `read_model.actions.actions` entries into disabled operator action controls.
- Action labels are visible to operators as presentation context.
- Disabled reasons are visible, not only present as title attributes.
- Action redaction policy is displayed as part of the reason.
- Static fallback actions remain in place when the x-action read model is unavailable or empty.

## Boundaries

This slice does not:

- execute actions
- authorize workflow actions
- record action lifecycle
- expose raw diagnostics
- expose target URLs
- mutate vouchers
- write journal entries
- send feedback
- call providers
- move money

## Verification

```bash
npm run test:frontend -- tests/frontend/cockpit/CockpitVoucherDetailHydration.test.ts tests/frontend/cockpit/CockpitVoucherDetailFoundation.test.ts
```

Result:

```text
2 passed, 11 tests
```
