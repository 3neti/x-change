# Host Validation Checkpoint 5 — Human Visual Confirmation Handoff Packet

Status: Handoff scaffolded; human visual browser confirmation still pending

## Purpose

Provide the exact handoff packet for completing human visual browser confirmation of the read-only Cockpit.

This checkpoint does not claim visual confirmation. It defines the pass/fail form that must be filled after a person inspects the host app in a browser.

## Required URL

```text
http://x-change-sandbox.test/x/cockpit
```

## Prerequisites

Before beginning the visual pass:

1. Ensure Vite is connected, or use a production build.
2. Confirm the operator is authenticated.
3. Prepare local scenario context for:
   - `basic_cash`
   - `divisible_open_three_slices_enforced_interval`
4. Keep the browser console visible.
5. Keep this report, `reports/034-manual-browser-ui-ux-pass-checklist.md`, and `reports/035-manual-browser-ui-ux-pass-execution-record.md` open for comparison.

## Visual Confirmation Form

Fill this table after browser inspection.

| Surface | URL / Route | Result | Evidence / Notes |
| --- | --- | --- | --- |
| Dashboard | `/x/cockpit` | Pending | Confirm summary cards, integration cards, no console errors, no unsafe payloads. |
| Quick Generate | `/x/cockpit/quick-generate` | Pending | Confirm read-only drafting, no generate mutation, no wallet/provider access. |
| Pay Code Explorer | `/x/cockpit/pay-codes` | Pending | Confirm sanitized rows, disabled controls, integration badges. |
| Voucher Detail | `/x/cockpit/pay-codes/{code}` | Pending | Confirm sanitized voucher facts, journal/action/feedback summaries only. |
| Distribution Workspace | `/x/cockpit/pay-codes/{code}/distribution` | Pending | Confirm distribution planning only, no send/delivery/provider action. |
| Planned Navigation | Cockpit sidebar/header | Pending | Confirm planned IA links are disabled/coming soon unless real routes exist. |

Allowed result values:

- `Pass`
- `Fail`
- `Blocked`

## Required Evidence

Record:

- browser URL
- scenario or Pay Code used
- console status
- visible error status
- screenshot reference if available
- any deviation from the checklist

## Pass Criteria

The checkpoint can be marked complete only when:

- all inspected routes are `Pass`
- no Cockpit route has JavaScript errors
- no enabled dead navigation exists
- no mutation-capable control appears
- no unsafe payload/provider/wallet/credential/OTP/recipient/detail appears
- no evidence suggests journal writes, action execution, feedback delivery, provider calls, voucher mutation, wallet access, or money movement

## Blocked Criteria

Mark the pass `Blocked` when:

- Vite is disconnected and a production build is unavailable
- no authenticated operator session is available
- no local scenario Pay Code is available for Voucher Detail / Distribution Workspace
- browser console cannot be inspected

## Fail Criteria

Mark the pass `Fail` and stop when any Cockpit route shows:

- JavaScript errors
- unsafe payload exposure
- mutation controls
- provider calls
- journal writes
- action execution
- feedback delivery
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

## After Completion

When the visual pass is performed, update:

- this report’s `Status`
- the Visual Confirmation Form
- `reports/035-manual-browser-ui-ux-pass-execution-record.md`
- `COMPASS.md`
- `../architecture/SETTLEMENT_OS_COMPASS.md`

Do not proceed to mutation-capable Cockpit planning until this visual confirmation is either `Pass` or explicitly accepted as `Blocked` by a human.

## Verification

Command:

```bash
php -d memory_limit=1G vendor/bin/pest tests/Unit/Architecture/CockpitHumanVisualConfirmationHandoffTest.php
```

