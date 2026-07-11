# Cockpit Wave 23A — Runtime Profile Operator Acceptance Closure

## Status

Accepted.

## Accepted Surface

```text
/x/cockpit/diagnostics/runtime-profile
```

## Closure Decision

The Runtime Profile diagnostics page is accepted as a read-only Cockpit operator diagnostics surface.

This closes the browser/operator acceptance loop opened in Wave 22.

## Evidence Accepted

- Dusk smoke test verified `/x/cockpit/diagnostics/runtime-profile`.
- The page renders `Operator Activity Runtime Profile`.
- The page renders runtime status, configured/fallback components, and safety flags.
- The page explicitly states the diagnostics surface is read-only.
- The page exposes no runtime configuration mutation controls.
- The page exposes no provider payloads, raw payloads, wallet payloads, or mutation endpoints.

## Verification Baseline

```text
php artisan dusk tests/Browser/CockpitRuntimeProfileDiagnosticsSmokeTest.php
```

```text
1 passed, 19 assertions
```

Supporting Wave 22 verification:

```text
Cockpit read-only focused suite: 42 passed, 679 assertions
Package frontend suite: 76 files passed, 482 tests passed
Asset drift doctor: checked 58, ok 58, stale 0, missing 0, extra 0
```

## Accepted Local Runtime State

The local runtime profile may report:

```text
partially_wired
```

This is acceptable when durable activity repository/recorder are locally enabled while journal, action, or feedback handoffs remain configured as null/fallback components.

The page remains a diagnostics view. It does not enable runtime capabilities.

## Architectural Decisions

- Runtime Profile remains read-only.
- Runtime configuration mutation is not authorized from Cockpit.
- CLI and doctor commands remain the operational source for runtime inspection.
- Runtime capability enablement remains explicit opt-in through configuration.
- Journal, action, and feedback handoff expansion remains separately authorized work.
- No provider, wallet, voucher, or money movement behavior changes are introduced by this closure.

## Must Remain Absent

- `Enable handoffs`
- `Save configuration`
- runtime configuration write forms
- provider payloads
- raw wallet payloads
- journal/action/feedback mutation controls

## Remaining Risks

- Operators may need guidance on interpreting `partially_wired` versus `fully_wired`.
- Future runtime changes must keep diagnostics separate from runtime mutation controls.
- Local environment state can differ from another operator's machine; CLI/doctor comparison remains the expected verification path.

## Result

Wave 23A closes Runtime Profile operator acceptance as a pass.

## Next Checkpoint

Cockpit Wave 23B — Next Runtime Decision Record.
