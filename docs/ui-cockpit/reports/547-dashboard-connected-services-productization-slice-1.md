# Dashboard Connected Services Productization Slice 1

Date: 2026-07-19

## Scope

Promote `/x/cockpit` connected-service readiness into a scan-first operator section.

## Implemented

- Added a visible `Connected Services` overview above operator actions.
- Summarized six read-only service areas:
  - Audit Trail / x-journal
  - Follow-Up Actions / x-action
  - Notifications / x-feedback
  - Campaigns / x-campaign
  - Balances / Treasury posture
  - Execution Evidence
- Kept journal/action/feedback payload boundary details behind the existing optional system posture disclosure.
- Replaced old three-service dashboard status language with six-service readiness language.
- Preserved read-only boundaries: no journal writes, action execution, feedback sends, campaign mutation, provider calls, wallet behavior changes, Treasury behavior changes, persistence changes, public API changes, or money movement were added.

## Verification

- `npm run test:frontend -- tests/frontend/cockpit/CockpitDashboardHydration.test.ts`
  - 31 passed.
- `vendor/bin/pint --dirty --format agent`
  - Passed.

## Next

Publish the Cockpit assets to the host app, run drift checks, run authenticated browser smoke coverage, build the host frontend, then close the wave.
