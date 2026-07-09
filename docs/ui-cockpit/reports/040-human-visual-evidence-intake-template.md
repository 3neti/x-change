# Host Validation Checkpoint 8 — Human Visual Evidence Intake Template

Status: Evidence intake scaffolded; human visual browser confirmation still pending

## Purpose

Provide a single evidence intake record for resolving the read-only Cockpit validation gate.

This checkpoint does not claim that the visual browser pass has been completed. It gives the operator a precise place to record browser evidence before the gate can move from `Blocked` to `Pass`, `Fail`, or `Blocked — accepted by human`.

## Required Starting URL

```text
http://x-change-sandbox.test/x/cockpit
```

## Gate Status

Read-only Cockpit validation gate: BLOCKED

The gate remains blocked until this evidence intake records a human result and the same result is propagated to:

- `reports/035-manual-browser-ui-ux-pass-execution-record.md`
- `reports/037-human-visual-confirmation-handoff.md`
- `reports/038-read-only-validation-gate-status.md`
- `reports/039-blocked-gate-audit-allowed-work-boundary.md`
- `COMPASS.md`
- `../architecture/SETTLEMENT_OS_COMPASS.md`

## Evidence Intake Form

| Field | Value |
| --- | --- |
| Human reviewer | Pending |
| Review date/time | Pending |
| Browser | Pending |
| Vite/build mode | Pending |
| Authenticated operator | Pending |
| Scenario context | Pending |
| Pay Code used for detail routes | Pending |
| Console status | Pending |
| Screenshot/video references | Pending |
| Overall result | Pending |

Allowed overall result values:

- `Pass`
- `Fail`
- `Blocked`
- `Blocked — accepted by human`

## Surface Results

| Surface | URL / Route | Result | Required evidence |
| --- | --- | --- | --- |
| Dashboard | `/x/cockpit` | Pending | Integration summary cards render; no unsafe payloads; no console errors. |
| Quick Generate | `/x/cockpit/quick-generate` | Pending | Generate controls remain non-mutating; pricing/funding gates are presentation-only. |
| Pay Code Explorer | `/x/cockpit/pay-codes` | Pending | Rows remain sanitized; planned navigation is disabled or routed to real read-only surfaces. |
| Voucher Detail | `/x/cockpit/pay-codes/{code}` | Pending | Voucher, journal, action, and feedback facts are summary-only and redacted. |
| Distribution Workspace | `/x/cockpit/pay-codes/{code}/distribution` | Pending | Distribution panels remain planning/presentation-only; no delivery occurs. |
| Planned Navigation | Sidebar/header links | Pending | Planned links are visibly disabled or route to existing read-only endpoints. |

## Stop Conditions

Stop and mark `Fail` if any inspected Cockpit route shows:

- JavaScript errors
- enabled dead navigation
- mutation-capable controls
- raw payload exposure
- provider payload exposure
- recipient address exposure
- credential, OTP, or approval secret exposure
- action target URL exposure
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
- routes
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

```bash
php -d memory_limit=1G vendor/bin/pest tests/Unit/Architecture/CockpitHumanVisualEvidenceIntakeTest.php
```
