# Cockpit Wave 15A — Human Visual Confirmation Intake

## Status

Implemented.

## Purpose

Provide the human visual acceptance intake record for Wave 13/14 Cockpit presentation changes.

## Surfaces to Confirm

- `/x/cockpit/quick-generate`
- `/x/pay-codes/create`
- `/x/pay-codes`
- `/x/balances`

## Confirmation Options

### Pass

Use `Pass` when:

- Quick Generate is operator-focused.
- Diagnostics are behind `Show architecture history`.
- A small Pay Code can be generated through Quick Generate.
- The result panel shows generated Pay Code, pricing preflight, funding preflight, draft runtime, and activity runtime.
- Legacy pages show `Cockpit bridge` callouts.
- No raw payloads are visible.
- No unexpected mutation controls are visible.

### Blocked

Use `Blocked` when:

- Quick Generate still shows stale baseline panels as the primary path.
- Legacy bridge callouts are missing.
- A browser/Vite/Inertia error prevents visual confirmation.
- The UI exposes raw payloads, secrets, wallet internals, or provider payloads.
- Unexpected mutation controls appear outside the existing Quick Generate issuance handoff.

## Boundary

This record captures human visual confirmation only. It does not change code, issue Pay Codes, move money, call providers, write journals, execute actions, send feedback, or mutate campaigns.

## Next Recommended Checkpoint

Cockpit Wave 15B — Pass / Block Decision Criteria Record.
