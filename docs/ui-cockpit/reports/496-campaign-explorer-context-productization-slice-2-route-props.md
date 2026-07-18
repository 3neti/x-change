# Campaign Explorer Context Productization — Slice 2 — Route Props

Date: 2026-07-18

## Scope

Preserve full campaign navigation context through the Pay Code Explorer route.

## Result

- `CockpitPayCodeExplorerPageController` now accepts campaign id, audience id, and recipient id query parameters.
- `CockpitReadOnlyPageProps::toPayCodeExplorerArray()` now forwards those ids into the read-only campaign navigation context.
- The route acceptance test now verifies the Explorer receives planning key, execution id, campaign id, audience id, recipient id, source, destination, mutation-disabled metadata, and redaction boundaries.
- The stale dashboard campaign expectation was aligned with the current installed/read-only x-campaign package-presence behavior.

## Verification

Commands:

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/pest tests/Feature/Cockpit/CockpitReadOnlyRoutesTest.php
```

Results:

- Pint passed
- 46 tests passed
- 781 assertions passed

## Boundary

No campaign route, campaign controller, campaign mutation, campaign dispatch, Pay Code issuance through campaign, journal write, action execution, feedback delivery, provider call, wallet behavior, Treasury behavior, public API behavior, or money movement changed.
