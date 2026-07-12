# Cockpit Wave 56A — Manual Clipboard UX Acceptance Plan

## Status

Completed on 2026-07-12.

## Purpose

Define how a human operator should verify the manual beneficiary URL copy UX introduced in Wave 55.

## Pages to Inspect

- `/x/cockpit/pay-codes/{code}`
- `/x/cockpit/pay-codes/{code}/distribution`

Use a Pay Code that has a visible beneficiary URL card.

## Acceptance Checklist

For each page:

1. The `Beneficiary Pay Code URL` card is visible.
2. The full URL is visible.
3. The relative path is visible.
4. The panel says the surface is read-only or delivery disabled.
5. The `Copy beneficiary URL` control is visible.
6. Clicking the copy control changes the button or status text to a successful copied state.
7. The copied clipboard value matches the visible beneficiary URL.
8. No SMS, email, webhook, in-app notification, campaign dispatch, provider call, journal write, action execution, voucher mutation, or money movement is triggered.

## Browser Notes

Clipboard APIs may require a secure context and user gesture. If the browser blocks clipboard access, the acceptable result is a visible unavailable/failure state with no backend side effect.

## Required Evidence

Record:

- URL tested
- Pay Code tested
- browser used
- copied value
- pass/fail/block decision
- observed errors, if any

## Verification

- `php -d memory_limit=1G vendor/bin/pest tests/Unit/Architecture/CockpitWave56aManualClipboardUxAcceptancePlanTest.php`

## Next

Cockpit Wave 56B — Automated Clipboard UX Evidence Guard.
