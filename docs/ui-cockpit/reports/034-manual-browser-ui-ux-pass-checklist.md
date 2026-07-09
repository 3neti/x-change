# Host Validation Checkpoint 2 — Manual Browser UI/UX Pass Checklist

Status: Complete

## Scope

Scaffold the manual browser UI/UX pass for read-only Cockpit validation against local scenario data.

This checkpoint does not perform browser automation or add browser-testing dependencies. It defines the operator-facing pass that should be executed in the host app after the read-only scenario validation harness.

## Host URL

```text
http://x-change-sandbox.test/x/cockpit
```

## Route Checklist

Inspect these existing read-only Cockpit routes:

| Surface | Named route | Path | Expected component |
| --- | --- | --- | --- |
| Dashboard | `x-change.cockpit.dashboard` | `/x/cockpit` | `x-change/cockpit/Dashboard` |
| Quick Generate | `x-change.cockpit.quick-generate` | `/x/cockpit/quick-generate` | `x-change/cockpit/QuickGenerate` |
| Pay Code Explorer | `x-change.cockpit.pay-codes.index` | `/x/cockpit/pay-codes` | `x-change/cockpit/PayCodeExplorer` |
| Voucher Detail | `x-change.cockpit.pay-codes.show` | `/x/cockpit/pay-codes/{code}` | `x-change/cockpit/VoucherDetail` |
| Distribution Workspace | `x-change.cockpit.pay-codes.distribution` | `/x/cockpit/pay-codes/{code}/distribution` | `x-change/cockpit/DistributionWorkspace` |

## Scenario Context

Use safe local scenario data from:

- `basic_cash`
- `divisible_open_three_slices_enforced_interval`

Prefer no-claim or non-provider scenario runs unless explicit provider execution is being validated separately.

## Acceptance Criteria

The browser pass is acceptable when:

- Dashboard loads without JavaScript errors.
- Pay Code Explorer loads without JavaScript errors.
- Voucher Detail loads without JavaScript errors for a local scenario Pay Code.
- Quick Generate remains read-only and does not expose a submit mutation.
- Distribution Workspace remains read-only and does not send feedback or generate distribution assets.
- Planned navigation links remain visibly disabled or “coming soon” unless a real route exists.
- Journal facts render only as evidence summaries.
- Action facts render only as disabled/presentation-only CTAs.
- Feedback facts render only as read-only delivery summaries.
- Campaign facts render only through the read-only dashboard/explorer adoption surfaces.
- The UI does not expose raw payloads, provider payloads, recipient addresses, OTP/approval secrets, wallet-private fields, exception internals, action target URLs, or provider credentials.

## Stop Conditions

Stop and report before proceeding if the browser pass reveals:

- a JavaScript error on any Cockpit route
- a dead enabled navigation link
- mutation controls that submit without an approved route
- raw payload or provider detail exposure
- a route that writes journal entries
- a route that executes actions
- a route that sends feedback
- a route that calls providers
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
php -d memory_limit=1G vendor/bin/pest tests/Unit/Architecture/CockpitManualBrowserUiUxPassChecklistTest.php
```

Expected result:

```text
1 passed
```

