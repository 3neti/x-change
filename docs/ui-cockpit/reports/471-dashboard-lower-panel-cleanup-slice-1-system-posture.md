# Dashboard Lower-Panel Cleanup — Slice 1 — System Posture Disclosure

Date: 2026-07-18

## Outcome

The Dashboard lower status panels are now grouped behind a single optional `System posture` disclosure.

## UI Changes

- Added an optional `System posture` disclosure after the main operator activity sections.
- Moved these lower panels into that disclosure:
  - Connected Services;
  - Funding readiness;
  - Claim lifecycle summary;
  - Items that may need attention;
  - Campaign context.
- Preserved all read-only facts, cards, and connection-detail toggles.

## Boundary

This is a presentation-only Cockpit Dashboard change.

No route behavior, read-model behavior, wallet behavior, Treasury behavior, voucher lifecycle mutation, claim approval, driver execution, journal write, x-action execution, x-feedback delivery, provider call, campaign mutation, public API behavior, or money movement changed.

## Verification

```bash
npm run test:frontend -- CockpitDashboardFoundation.test.ts CockpitDashboardHydration.test.ts
```

Result: 2 files passed, 35 tests passed.

## Next

Publish host assets, verify drift, run focused Dashboard frontend tests again, run the host production build, then close this wave.
