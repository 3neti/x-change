# Cockpit Wave 20 — Operator Activity Runtime Configuration UX / Local Operations Handoff

## Status

Complete.

## Scope

Wave 20 adds package-owned local operations visibility for the Cockpit operator issuance activity runtime profile.

It does not change runtime defaults and does not auto-enable:

- durable activity storage
- x-journal handoff
- x-action handoff
- x-feedback handoff
- provider calls
- wallet mutation
- voucher mutation
- lifecycle truth ownership

## Implementation

Wave 20 introduced:

- `CockpitOperatorIssuanceActivityRuntimeProfileData`
- `CockpitOperatorIssuanceActivityRuntimeProfileInspector`
- `x-change:cockpit:operator-activity-runtime-profile`
- `x-change:doctor --operator-activity-runtime`

The runtime profile reports:

- configured operator activity components
- resolved runtime classes
- fallback/null classes
- runtime status:
  - `not_wired`
  - `partially_wired`
  - `combined_runtime_ready`
- safety flags proving defaults remain explicit-opt-in and non-money-moving

## CLI Verification

Local operators can inspect the profile with:

```bash
php artisan x-change:cockpit:operator-activity-runtime-profile --json --pretty
```

or through doctor:

```bash
php artisan x-change:doctor --operator-activity-runtime --json
```

In the current local sandbox, both commands return `partially_wired` because durable activity repository/recorder are locally enabled while journal/action/feedback handoffs remain null.

## Test Coverage

Focused coverage verifies:

- default runtime profile is `not_wired`
- null/fallback services are reported by default
- explicit component configuration is reflected as `partially_wired`
- combined runtime profile can be inspected
- doctor exposes the same profile behind an explicit option

Commands run:

```bash
vendor/bin/pest tests/Feature/Cockpit/CockpitOperatorIssuanceActivityRuntimeProfileInspectorTest.php tests/Feature/Cockpit/CockpitOperatorIssuanceActivityRuntimeProfileCommandTest.php tests/Feature/Console/DoctorXChangeCommandTest.php
php artisan x-change:cockpit:operator-activity-runtime-profile --json
php artisan x-change:doctor --operator-activity-runtime --json
```

Result:

```text
8 passed, 131 assertions
both Artisan commands exited successfully
```

## UI Effect

No current Cockpit UI change is expected from Wave 20.

The visible Cockpit dashboard and Quick Generate screens continue to show runtime activity cards based on actual durable activity read models. Wave 20 only adds local operations visibility through Artisan/doctor commands.

## Safety Decision

Defaults remain safe:

- `requires_explicit_opt_in=true`
- `moves_money=false`
- `calls_provider=false`
- `executes_action=false`
- `sends_feedback=false`
- `owns_lifecycle_truth=false`

`writes_journal` is true only when the journal handoff component is explicitly configured.

## Next Recommended Wave

Cockpit Wave 21 — Runtime Profile UI Surface Decision.

Recommended decision:

- either keep runtime profile inspection as CLI/doctor-only operational tooling;
- or add a read-only Cockpit diagnostics panel that renders the runtime profile for operators/admins.

Do not enable any runtime mutation expansion as part of that decision.
