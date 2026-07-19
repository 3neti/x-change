# Quick Generate Form Density Slice 1

Date: 2026-07-19

## Scope

Reduce pre-submit visual density on `/x/cockpit/quick-generate`.

## Implemented

- Kept the Contract Builder Checklist visible as the primary operator scan map.
- Moved `VoucherInstruction DTO coverage` into a collapsed disclosure.
- Preserved the DTO coverage map for engineering inspection without forcing operators to read it before the actual builder.
- Preserved all form controls, payload construction, validation behavior, template defaults, claim inputs, validation, rider, feedback, slices, settlement, execution, metadata, and engineering preview behavior.

## Boundaries

No routes, controllers, request payloads, issuance behavior, validation behavior, provider calls, wallet behavior, Treasury behavior, journal writes, action execution, feedback sends, campaign mutation, persistence changes, public API changes, or money movement were added.

## Verification

- `npm run test:frontend -- tests/frontend/cockpit/CockpitQuickGenerateFoundation.test.ts tests/frontend/cockpit/CockpitQuickGenerateHydration.test.ts`
  - 32 passed.
- `vendor/bin/pint --dirty --format agent`
  - Passed.

## Next

Publish package Cockpit assets to the host app, verify drift, run browser smoke coverage, build the frontend, and close the wave.
