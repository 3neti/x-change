# Host Validation Checkpoint 1 — Read-Only Cockpit UI/UX Scenario Validation

Status: Complete

## Scope

Scaffold the read-only Cockpit UI/UX validation checkpoint against local scenario-shaped data.

The validation payloads model the operator facts expected from:

- `basic_cash`
- `divisible_open_three_slices_enforced_interval`

This checkpoint validates Cockpit presentation behavior only.

## Validated Surfaces

- Dashboard scenario metrics, activity, and integration summary cards
- Pay Code Explorer scenario list rows and integration badges
- Voucher Detail scenario voucher summary, journal evidence, action CTA summaries, feedback delivery summaries, and integration summary cards

## Operator Expectations

The UI should show:

- scenario names
- sanitized Pay Code codes
- sanitized amount/status/timestamp facts
- journal evidence summaries
- disabled action/CTA labels
- read-only feedback delivery statuses
- payload policy labels

The UI must not show:

- raw payloads
- provider payloads
- recipient addresses
- OTP or approval secrets
- wallet-private fields
- exception classes
- exception messages
- action target URLs
- provider credentials

## Boundary

This checkpoint did not run lifecycle scenarios, submit claims, call providers, issue vouchers, mutate vouchers, write journal entries, execute actions, send feedback, retry deliveries, access wallets, or move money.

## Manual UI/UX Follow-Up

Use the host app to validate the actual browser experience:

1. Run or prepare safe local scenarios using no-claim or non-provider options where appropriate.
2. Open `/x/cockpit`.
3. Inspect Dashboard, Pay Code Explorer, and Voucher Detail surfaces.
4. Confirm planned navigation remains visibly disabled unless a real route exists.
5. Confirm action and feedback affordances remain read-only.
6. Confirm no unsafe payload or provider details appear in the UI.

## Verification

Command:

```bash
npm run test:frontend -- tests/frontend/cockpit/CockpitReadOnlyScenarioValidation.test.ts
```

Result:

```text
1 file passed, 3 tests
```

