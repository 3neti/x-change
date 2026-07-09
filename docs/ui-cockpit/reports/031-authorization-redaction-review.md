# Host Integration Slice 2H — Authorization / Redaction Review

Status: Complete

## Scope

Review the read-only Cockpit presentation surfaces that consume Journal, Action, and Feedback read models:

- Dashboard integration summary cards
- Voucher Detail integration summary cards
- Pay Code Explorer integration badges

## Result

The existing presentation boundary remains valid:

- Dashboard, Voucher Detail, and Pay Code Explorer display only summary-safe integration facts.
- Payload policies and safe unavailable/readiness reasons may be displayed.
- Exception classes, exception messages, raw payloads, provider payloads, recipient addresses, action target URLs, non-durable run IDs, credentials, and internal routes remain hidden.
- Action affordances remain disabled/presentation-only.
- Feedback delivery remains read-only.
- Journal data remains evidence/read-only.

## Boundary

This slice did not add:

- mutation routes
- authorization policy execution
- role/permission persistence
- action execution
- feedback delivery
- feedback retry execution
- journal writes
- provider calls
- voucher mutation
- wallet access
- money movement
- raw payload exposure

## Verification

Command:

```bash
npm run test:frontend -- tests/frontend/cockpit/CockpitDashboardHydration.test.ts tests/frontend/cockpit/CockpitVoucherDetailHydration.test.ts tests/frontend/cockpit/CockpitPayCodeExplorerHydration.test.ts
```

Result:

```text
3 passed, 29 tests
```

