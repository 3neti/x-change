# Cockpit Wave 22 — Runtime Profile Browser Verification / Operator Acceptance

## Status

Browser smoke verified. Human operator acceptance pending.

## URL

```text
http://x-change-sandbox.test/x/cockpit/diagnostics/runtime-profile
```

## What to Confirm

Open the Runtime Profile page from Cockpit or directly by URL and confirm:

- the page loads without browser errors;
- the sidebar shows `Runtime Profile`;
- the page title is `Operator Activity Runtime Profile`;
- the summary cards show:
  - `RUNTIME STATUS`
  - `REPOSITORY`
  - `JOURNAL HANDOFF`
  - `ACTION / FEEDBACK`
- the runtime components section shows:
  - `repository`
  - `recorder`
  - `journal_handoff`
  - `action_handoff`
  - `feedback_handoff`
- the safety panels show:
  - `This diagnostics surface is read-only`
  - `Runtime capabilities remain explicit opt-in`
- no mutation controls are visible.

## Must Not Appear

The page must not show:

- `Enable handoffs`
- `Save configuration`
- provider payloads
- raw payloads
- wallet payloads
- mutation endpoints

## Expected Local State

In the current local sandbox, the runtime profile may show:

```text
partially_wired
```

That is expected when durable activity repository/recorder are locally enabled while journal/action/feedback handoffs remain null.

The value should match:

```bash
php artisan x-change:cockpit:operator-activity-runtime-profile --json
php artisan x-change:doctor --operator-activity-runtime --json
```

## Automated Browser Verification

Added Dusk smoke test:

```text
tests/Browser/CockpitRuntimeProfileDiagnosticsSmokeTest.php
```

Command:

```bash
php artisan dusk tests/Browser/CockpitRuntimeProfileDiagnosticsSmokeTest.php
```

Result:

```text
1 passed, 19 assertions
```

## Supporting Verification

Commands run:

```bash
vendor/bin/pest tests/Feature/Cockpit/CockpitReadOnlyRoutesTest.php tests/Feature/Cockpit/CockpitRuntimeProfilePagePropsTest.php
npm run test:frontend
php artisan x-change:doctor --assets --json
```

Results:

```text
Cockpit read-only focused suite: 42 passed, 679 assertions
Package frontend suite: 76 files passed, 482 tests passed
Asset drift doctor: checked 58, ok 58, stale 0, missing 0, extra 0
```

## Human Decision

Pass when:

- the page visibly matches the checklist above;
- the local status matches CLI/doctor output;
- no mutation controls are visible;
- no browser error blocks use.

Blocked when:

- the route fails to load;
- the runtime profile is missing or stale;
- mutation controls appear;
- browser errors prevent inspection.

## Next Recommended Wave

Cockpit Wave 23 — Runtime Profile Operator Acceptance Closure / Next Runtime Decision.

Recommended scope:

- record human Pass/Blocked outcome;
- if Pass, decide whether the next wave should improve operator activity search/filtering or move to the next functional Cockpit parity surface;
- do not expand mutation authority automatically.
