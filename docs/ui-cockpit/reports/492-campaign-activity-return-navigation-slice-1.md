# Campaign Activity Return Navigation Productization — Slice 1

Date: 2026-07-18

## Result

Pass.

Quick Generate now surfaces campaign return links as a dedicated operator panel after a campaign-attributed generation.

## What Changed

- Added a `Campaign return navigation` panel to the Quick Generate result area.
- Shows campaign-scoped return links separately from generic post-issuance destinations.
- Preserves the existing post-issuance navigation contract and generic link list.
- Keeps all campaign links read-only.

## Boundary Confirmation

This slice is UI-only. It does not add routes, controllers, campaign mutation, campaign dispatch, Pay Code generation through campaign, feedback sends, journal writes, action execution, provider calls, wallet behavior, Treasury behavior, public API changes, persistence, or money movement.

## Verification

From the host root:

```bash
vendor/bin/pint --dirty --format agent
```

Result: passed.

From `packages/x-change`:

```bash
npm run test:frontend -- tests/frontend/cockpit/CockpitQuickGenerateFoundation.test.ts
```

Result: 1 file passed, 28 tests passed.

## Next Slice

Campaign Activity Return Navigation Productization Slice 2 — dashboard activity campaign-return card polish.
