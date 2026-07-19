# Pay Code Explorer Manual Acceptance — Slice 1 Checklist

Date: 2026-07-19

## Scope

This slice opens the Pay Code Explorer Manual Acceptance wave for:

- `/x/cockpit/pay-codes`

The goal is to verify that the current Pay Code Explorer is acceptable as a read-only operator inspection surface after the recent search, result-density, campaign-context, filter-builder, and mobile-row polish waves.

## Human Inspection Checklist

Inspect `/x/cockpit/pay-codes` in the browser and confirm:

- The page title and purpose are clear.
- Search and status filtering feel like read-only list navigation.
- The current search / filter summary is visible when query parameters are present.
- The filter details panel is compact by default and can be opened when needed.
- Result rows are scan-friendly on desktop.
- Result cards are scan-friendly on mobile or narrow widths.
- Each visible Pay Code row offers safe navigation to detail and distribution views.
- Disabled row actions are visibly disabled and do not look executable.
- Campaign context, when present, is shown as read-only filter context rather than mutation state.
- No raw payloads, provider payloads, wallet internals, secrets, tokens, OTP values, or execution payloads are visible.
- No visible runtime errors appear.

## Automated Browser Expectations

The existing authenticated Dusk smoke path should continue to verify:

- `/x/cockpit/pay-codes` renders.
- Search query and status filters are preserved in the URL.
- The current search summary renders.
- The page exposes read-only GET filtering copy.
- Unsafe engineering payload tokens remain hidden.
- No mutation controls such as `Save configuration` or `Enable handoffs` are visible.

## Decision Rule

Record one of:

- `Pass`
- `Pass with UI follow-up`
- `Blocked`

Do not record human `Pass` from automated tests alone.

## Boundary

This checklist changes no runtime behavior.

No routes, controllers, queries, read-model hydration, campaign context propagation, row action links, voucher lifecycle behavior, execution drivers, journal writes, action execution, feedback delivery, campaign mutation, provider calls, wallet behavior, Treasury behavior, public APIs, persistence, artifact generation, or money movement changed.

## Next Checkpoint

Pay Code Explorer Manual Acceptance Slice 2 — automated verification closure while pending human visual evidence.
