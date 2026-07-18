# Campaign Drilldown Context Copy Polish Slice 1

Date: 2026-07-18

## Scope

Polish Voucher Detail campaign context copy so operator-visible labels do not expose raw contract tokens.

## Outcome

- Voucher Detail still preserves campaign query context in links and page props.
- The campaign context card now renders friendly labels for source, destination, safety, and payload visibility.
- Raw contract tokens remain internal to the read-only navigation contract and URLs.

## Verification

- `npm run test:frontend -- tests/frontend/cockpit/CockpitVoucherDetailFoundation.test.ts`

## Boundary

This slice did not add routes, controllers, campaign mutation, campaign dispatch, Pay Code generation through campaign, feedback sends, journal writes, action execution, provider calls, wallet behavior, Treasury behavior, public API changes, persistence, or money movement.

## Next

Campaign Drilldown Context Copy Polish Slice 2 — apply the same operator-label treatment to Distribution Workspace.
