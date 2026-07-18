# Real Integration Wiring Wave — Slice 1 — Dashboard Integration Bundle

Date: 2026-07-18

## Scope

Wire dashboard-level Connected Services to the existing optional integration adapters when journal, action, and feedback summaries are requested without a specific Pay Code.

## Changes

- `VoucherLifecycleCockpitReadModelProvider::forVoucher()` now hydrates journal/action/feedback summaries when:
  - no Pay Code is supplied;
  - the query explicitly includes one of `journal`, `actions`, or `feedback`;
  - optional integration adapters are available.
- Voucher and execution facts remain fallback/not-wired without a Pay Code.
- Added backend characterization proving dashboard-level summaries can hydrate safely without exposing raw payloads.

## Boundary

This is read-model wiring only. It does not write journal entries, execute x-action actions, send x-feedback deliveries, dispatch campaigns, mutate vouchers, execute drivers, generate artifacts, call providers, change wallet behavior, change Treasury behavior, alter public APIs, or move money.

## Verification

- `vendor/bin/pest tests/Unit/Cockpit/CockpitReadModelBaselineTest.php`
  - Result: 20 tests passed, 244 assertions.
- `vendor/bin/pint --dirty --format agent packages/x-change/src/Services/Cockpit/VoucherLifecycleCockpitReadModelProvider.php packages/x-change/tests/Unit/Cockpit/CockpitReadModelBaselineTest.php`
  - Result: passed.
