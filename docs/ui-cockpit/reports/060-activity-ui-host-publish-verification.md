# Cockpit Mutation Wave 2J — Activity UI Host Publish Verification

Status: Implemented
Date: 2026-07-10

## Scope

This checkpoint verifies that the package-owned Cockpit activity UI from Wave 2H can be published into the host app through the official x-change install workflow and that the published Cockpit mirrors match package source afterward.

This slice intentionally allows host-published Cockpit asset changes because the verification target is the publish workflow itself. The source of truth remains `packages/x-change/resources/js/cockpit`.

## Commands Executed

From the host app root:

```bash
php artisan x-change:install --force
php artisan x-change:doctor --assets --json
npm run build
```

From the x-change package root:

```bash
npm run test:frontend
```

## Results

- `php artisan x-change:install --force` passed.
- `php artisan x-change:doctor --assets --json` passed.
- Published Cockpit assets: passed.
- Asset summary:
  - checked: 55
  - ok: 55
  - stale: 0
  - missing: 0
  - extra: 0
- `npm run build` passed from the host app root, proving the published Cockpit import graph resolves.
- `npm run test:frontend` passed from the x-change package root: `74 passed, 476 tests`.

## Published Activity UI Evidence

The asset doctor confirmed the host mirror now contains and matches:

- `components/CockpitOperatorIssuanceActivityPanel.vue`
- `components/CockpitQuickGenerateSubmitPanel.vue`
- `pages/Dashboard.vue`
- `pages/QuickGenerate.vue`
- `types.ts`

## Type Check Observation

`npm run types:check` was also attempted from the host app root and failed due to broad existing host-wide TypeScript issues. One Cockpit-owned issue was discovered in `CockpitQuickGenerateSubmitPanel.vue`: `router.reload()` rejected the `preserveScroll` option under the installed Inertia v3 types.

Resolution:

- Removed the non-essential `preserveScroll` option from the package source partial reload.
- Republished with `php artisan x-change:install --force`.
- Updated the package frontend expectation.
- Re-ran package frontend tests successfully.

The remaining host `types:check` failures are outside this Cockpit publish verification slice.

## Boundary Confirmation

This slice did not add:

- no manual host mirror edits
- new Cockpit routes
- new Cockpit controllers
- new public APIs
- no journal writes
- no action execution
- no feedback delivery
- persistence
- migrations
- queues
- provider calls
- wallet access
- voucher execution changes
- lifecycle truth ownership
- raw payload exposure
- no money movement

## Decision

The package publish workflow is valid for Cockpit activity UI adoption. After `php artisan x-change:install --force`, the host-published Cockpit mirrors are clean according to the drift guard and the host build can compile the published UI.

## Next Recommended Slice

Cockpit Mutation Wave 2K — Activity UI Manual Browser Verification.

Use this next slice to manually verify the rendered dashboard activity panel in the host app after the clean publish checkpoint.
