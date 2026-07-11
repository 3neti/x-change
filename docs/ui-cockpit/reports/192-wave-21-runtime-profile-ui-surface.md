# Cockpit Wave 21 — Runtime Profile UI Surface Decision

## Status

Complete.

## Decision

Runtime profile inspection now has a read-only Cockpit diagnostics surface.

The CLI/doctor surfaces from Wave 20 remain available, and the new UI does not replace them.

## New Cockpit URL

```text
http://x-change-sandbox.test/x/cockpit/diagnostics/runtime-profile
```

## Implementation

Wave 21 added:

- backend page-prop contract for `runtime_profile_read_model`
- read-only route:
  - `GET /x/cockpit/diagnostics/runtime-profile`
  - route name `x-change.cockpit.diagnostics.runtime-profile`
- Inertia page:
  - `x-change/cockpit/RuntimeProfile`
- Cockpit navigation item:
  - `Runtime Profile`
- runtime diagnostics UI showing:
  - runtime status
  - repository/recorder/journal/action/feedback handoff state
  - resolved component classes
  - fallback classes
  - page safety facts
  - runtime safety facts

## UI Effect

Operators can now open the Runtime Profile page from the Cockpit sidebar.

The page displays read-only runtime configuration. It has no buttons or forms for changing configuration.

Expected visible copy includes:

- `Operator Activity Runtime Profile`
- `Runtime components`
- `Explicit configuration and fallbacks`
- `This diagnostics surface is read-only`
- `Runtime capabilities remain explicit opt-in`

## Safety Boundary

Wave 21 does not:

- mutate configuration
- enable handoffs
- write journal entries
- execute actions
- send feedback
- call providers
- mutate vouchers
- move money
- own lifecycle truth

The page renders class names and safe runtime flags only.

## Verification

Commands run:

```bash
vendor/bin/pest tests/Feature/Cockpit/CockpitRuntimeProfilePagePropsTest.php
vendor/bin/pest tests/Feature/Cockpit/CockpitReadOnlyRoutesTest.php tests/Feature/Cockpit/CockpitRuntimeProfilePagePropsTest.php
npm run test:frontend
php artisan x-change:install --force
php artisan route:list --name=x-change.cockpit --path=x/cockpit
php artisan x-change:doctor --assets --json
```

Results:

```text
CockpitRuntimeProfilePagePropsTest: 2 passed, 50 assertions
Cockpit read-only focused suite: 42 passed, 679 assertions
Package frontend suite: 76 files passed, 482 tests passed
Route list: 7 Cockpit routes, including diagnostics/runtime-profile
Asset drift doctor: checked 58, ok 58, stale 0, missing 0, extra 0
```

Additional check:

```bash
npm run types:check
```

Result:

```text
blocked by pre-existing host TypeScript issues outside the Cockpit runtime-profile slice
```

No reported type errors referenced the new runtime profile page or types.

## Host Publish State

`php artisan x-change:install --force` published the Cockpit package UI into host mirrors.

The asset drift guard confirmed that the published Cockpit assets match package source.

## Next Recommended Wave

Cockpit Wave 22 — Runtime Profile Browser Verification / Operator Acceptance.

Recommended scope:

- open `/x/cockpit/diagnostics/runtime-profile`
- verify navigation works from the sidebar
- verify local `partially_wired` state matches CLI/doctor output
- verify no mutation controls are visible
- record human Pass/Blocked decision
