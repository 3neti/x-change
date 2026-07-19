# Distribution Workspace Manual Acceptance — Slice 1 Checklist

Date: 2026-07-19

## Status

`pending-human-visual-acceptance`

## Scope

This slice defines what a human reviewer should inspect on `/x/cockpit/pay-codes/{code}/distribution` after the secondary panel cleanup.

It does not mark the page as accepted. It creates the acceptance checklist and keeps the implementation boundary explicit.

## Manual Inspection URL

Use an existing Pay Code from the local host app:

```text
/x/cockpit/pay-codes/{code}/distribution
```

Known example from earlier local evidence:

```text
/x/cockpit/pay-codes/6LGM/distribution
```

## Acceptance Checklist

Pass requires all of these to be true from the human browser view:

- The primary Distribution Workspace summary is visible and readable.
- The beneficiary claim URL card is visible when the read model provides a claim URL.
- The copy button copies the beneficiary URL locally in the browser.
- The manual distribution checklist is understandable.
- The secondary panels are visually compact:
  - Delivery channels;
  - Print Templates;
  - Operational evidence;
  - Share Assets.
- Secondary panels do not dominate the page when collapsed.
- Opening a secondary panel reveals details without enabling mutation controls.
- Buttons for dispatch, print asset generation, QR creation, and campaign creation remain disabled or non-mutating.
- No page copy implies SMS, email, webhook, in-app notification, campaign dispatch, journal write, provider call, voucher mutation, wallet mutation, or money movement.
- No console or visible runtime error appears.

## Human Decision Values

Allowed values:

- `Pass`
- `Blocked`
- `Fail`

Do not record `Pass` until explicit human evidence is supplied.

## Boundary

This slice is documentation and verification planning only. It does not change routes, controllers, queries, read-model hydration, distribution links, voucher lifecycle behavior, execution drivers, journal writes, action execution, feedback delivery, provider calls, wallet behavior, Treasury behavior, public APIs, persistence, or money movement.

## Verification

- `vendor/bin/pest packages/x-change/tests/Unit/Architecture/DistributionWorkspaceManualAcceptanceTest.php`

## Next Recommended Checkpoint

Distribution Workspace Manual Acceptance Slice 2 — automated browser evidence and pending-human closure record.
