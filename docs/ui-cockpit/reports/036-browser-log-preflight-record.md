# Host Validation Checkpoint 4 — Browser Log Preflight Record

Status: Browser-log preflight recorded; human visual browser confirmation still pending

## Scope

Record the browser-log preflight immediately before human visual confirmation of read-only Cockpit.

This checkpoint follows:

- `reports/033-read-only-ui-ux-scenario-validation.md`
- `reports/034-manual-browser-ui-ux-pass-checklist.md`
- `reports/035-manual-browser-ui-ux-pass-execution-record.md`

## Browser Entry Point

```text
http://x-change-sandbox.test/x/cockpit
```

## Browser Log Findings

Recent browser logs include:

- `/x/cockpit` Vite debug entries: `server connection lost. Polling for restart...`
- `/x/balances` Vue warnings for extraneous non-props attributes on `Index`

Interpretation:

- The `/x/cockpit` browser log finding is a dev-server connectivity/debug condition, not a confirmed Cockpit runtime UI failure.
- The `/x/balances` Vue warnings are outside the Cockpit route and should not be treated as Cockpit UI validation failures.
- Human visual confirmation is still required for Dashboard, Quick Generate, Pay Code Explorer, Voucher Detail, and Distribution Workspace.

## Required Human Follow-Up

Before closing the visual checkpoint, open the host app and verify:

1. `/x/cockpit` loads after Vite is connected or after a production build is served.
2. No JavaScript error appears on Dashboard.
3. No JavaScript error appears on Quick Generate.
4. No JavaScript error appears on Pay Code Explorer.
5. No JavaScript error appears on Voucher Detail for a local scenario Pay Code.
6. No JavaScript error appears on Distribution Workspace for a local scenario Pay Code.
7. Planned navigation remains disabled unless a route exists.
8. No unsafe payload details are visible.
9. No mutation-capable controls are enabled.

## Stop Conditions

Stop and report if any Cockpit route shows:

- JavaScript errors after Vite/build is healthy
- enabled dead navigation
- mutation-capable controls
- raw payload exposure
- provider payload exposure
- recipient address exposure
- OTP/approval secret exposure
- wallet-private field exposure
- exception internals
- action target URLs
- provider credentials
- journal writes
- action execution
- feedback delivery
- provider calls
- voucher mutation
- wallet access
- money movement

## Boundary

This checkpoint did not add:

- browser automation dependencies
- browser snapshots
- new routes
- mutation endpoints
- lifecycle scenario execution
- claim submission
- provider calls
- journal writes
- action execution
- feedback delivery
- wallet access
- money movement

## Verification

Command:

```bash
php -d memory_limit=1G vendor/bin/pest tests/Unit/Architecture/CockpitBrowserLogPreflightRecordTest.php
```

