# Medicard Gate M4 — Cockpit Presentation

Date: 2026-10-09

## Disposition

Gate M4 is complete and green. The existing Campaigns workspace now derives a
sanitized product key and operator label from immutable campaign driver
configuration. AUI cards show demo policies ready; Medicard cards show demo
benefits ready. Both result codes are counted by the same read model.

No second dashboard, product-specific mutation endpoint, applicant data, or
provider payload was added.

## Verification

- Medicard read-model characterization: 1 test, 7 assertions.
- Campaigns Vue regression: 27 tests passed.
- The row remains compact and reuses the existing QR and monitoring controls.

## Next Gate

Gate M5 runs the combined synthetic lifecycle acceptance and fail-closed
regressions with fake payment and SMS boundaries.
