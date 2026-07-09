# Host Integration Slice 2D — Dashboard Integration Summary

## Status

Complete.

## Scope

Render a read-only Journal / Action / Feedback integration summary on the Cockpit Dashboard from the existing read-model bundle.

## Implemented

- Dashboard accepts the existing `read_model` bundle prop.
- Dashboard renders summary cards for:
  - Journal Evidence
  - Action CTAs
  - Feedback Deliveries
- Each card shows status, count, and payload policy only.

## Boundaries

This slice does not:

- query integration packages from the frontend
- add new routes
- write journal entries
- execute actions
- send feedback
- retry delivery
- call providers
- expose raw payloads
- mutate vouchers
- move money

## Verification

```bash
npm run test:frontend -- tests/frontend/cockpit/CockpitDashboardHydration.test.ts tests/frontend/cockpit/CockpitDashboardFoundation.test.ts
```

Result:

```text
2 passed, 14 tests
```
