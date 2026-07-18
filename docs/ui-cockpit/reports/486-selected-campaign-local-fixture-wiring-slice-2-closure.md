# Selected Campaign Local Fixture Wiring — Slice 2 Closure

Date: 2026-07-18

## Result

Pass.

The selected campaign local fixture wiring wave is closed.

## Operator Effect

When local fixtures are enabled and the operator opens:

`/x/cockpit?campaign_planning_key=plan-local&campaign_execution_id=exec-local`

the dashboard campaign panel can now show a selected local campaign from the real optional x-campaign adapter instead of only the generic “no campaign selected” empty state.

Expected visible effect:

- Campaign package connected.
- Selected campaign name: `Local Cockpit Campaign`.
- Selected planning key: `plan-local`.
- Selected execution id: `exec-local`.
- Read-only Quick Generate prefill availability.
- Campaign changes remain disabled.

## Verification

From the host root:

```bash
php artisan x-change:install --force --no-interaction
php artisan x-change:doctor --assets --no-interaction
```

Result: published Cockpit assets match package source.

From `packages/x-change`:

```bash
npm run test:frontend -- tests/frontend/cockpit/CockpitDashboardHydration.test.ts tests/frontend/cockpit/CockpitDashboardFoundation.test.ts
```

Result: 2 files passed, 36 tests passed.

From the host root:

```bash
npm run build
```

Result: passed.

Known non-blocking warning: Rolldown reports invalid pure-annotation comments from `node_modules/reka-ui/node_modules/@vueuse/core/dist/index.js`. This warning pre-exists this slice and does not block the build.

## Boundary Confirmation

This closure does not add durable persistence, database migrations, routes, controllers, campaign mutation, campaign dispatch, Pay Code generation through campaign, feedback sends, journal writes, action execution, provider calls, wallet behavior, Treasury behavior, public API changes, or money movement.

## Next Recommended Wave

Campaign Selected Context UI Productization:

1. Make the selected campaign panel more useful when campaign context is present.
2. Add clearer “Generate from this campaign” navigation to Quick Generate.
3. Keep the action as prefill-only; do not mutate campaign state.
