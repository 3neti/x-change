# Cockpit Funding / Balance UI Wave — Slice 2 — Dashboard Panel

Date: 2026-07-17

## Outcome

Updated the `/x/cockpit` Funding Status panel to make balance semantics scan-friendly.

The panel now separates:

- Accounting — Internal Balance;
- Liability — Outstanding Pay Codes;
- Estimate — Usable Balance;
- External — Live Balance.

## UI Changes

- Replaced disconnected-state badge copy with `Bridge estimates` and `Treasury facts deferred`.
- Added a compact semantics strip above the metric cards.
- Updated helper copy for Outstanding Pay Codes and Usable Balance to explicitly say `Bridge estimate`.
- Removed old generic `Balance summary not connected` copy from the Funding Status panel.

## Boundary

This is presentation-only.

No wallet Treasury runtime dependency, provider balance refresh, wallet reservation, release, capture, repayment, reversal, refund, voucher lifecycle mutation, journal write, action execution, feedback delivery, campaign mutation, public API behavior, or money movement was added.

## Verification

Frontend dashboard widget coverage asserts:

- the bridge-estimate language is visible;
- Treasury facts are deferred;
- the four semantic labels render;
- the old disconnected copy is absent;
- the four metric cards still render.
