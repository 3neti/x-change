# Cockpit Wave 14D — Browser Visual Handoff Checklist

## Status

Implemented.

## Purpose

Provide the exact browser checklist for human visual verification of Wave 13 and Wave 14 publish state.

## Browser Log Note

Recent browser log inspection only showed historical Vite reconnect messages. No new Wave 14 browser exception was identified from the available log entries.

## Checklist

### Quick Generate

Open:

```text
http://x-change-sandbox.test/x/cockpit/quick-generate
```

Confirm:

- The page header says `Quick Generate Runtime`.
- The primary page shows template selection, runtime guidance, and the Quick Generate form.
- Historical panels are not the primary operator path.
- The historical panels are under `Diagnostics` / `Show architecture history`.
- Generate a small Pay Code and confirm the result panel shows:
  - generated Pay Code
  - pricing preflight
  - funding preflight
  - draft runtime
  - activity runtime

### Legacy Create Page

Open:

```text
http://x-change-sandbox.test/x/pay-codes/create
```

Confirm:

- The page still behaves as the legacy advanced Pay Code generation page.
- A `Cockpit bridge` callout is visible.
- The callout links to Cockpit Quick Generate.

### Legacy Pay Code List

Open:

```text
http://x-change-sandbox.test/x/pay-codes
```

Confirm:

- The page still behaves as the legacy Pay Code list/search page.
- A `Cockpit bridge` callout is visible.
- The callout links to Cockpit Pay Code Explorer.

### Legacy Balances Page

Open:

```text
http://x-change-sandbox.test/x/balances
```

Confirm:

- The page still behaves as the legacy balance/reconciliation page.
- A `Cockpit bridge` callout is visible.
- The callout links to Cockpit dashboard/balance readiness.

## Boundary

This checklist does not authorize new mutation behavior. It verifies visual presentation, route availability, and bridge clarity only.

## Next Recommended Checkpoint

Cockpit Wave 14E — Wave 14 Closure / Next Planning Record.
