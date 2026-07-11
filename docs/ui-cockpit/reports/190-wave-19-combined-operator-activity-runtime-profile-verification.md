# Cockpit Wave 19 — Combined Operator Activity Runtime Profile Verification

## Status

Complete.

## Scope

Wave 19 verifies that the Cockpit durable operator issuance activity runtime can show journal, action, and feedback handoff facts together on the same activity record.

This wave is verification-focused. It does not introduce new runtime side effects beyond the existing opt-in handoff paths from Waves 16–18.

## Implemented slices

| Slice | Result |
| --- | --- |
| 19A | Added package-level combined diagnostic fixture coverage for one row with `journal: recorded`, `action: composed`, and `feedback: planned`. |
| 19B | Added Quick Generate combined runtime test proving one generated activity can flow through x-journal, x-action, and x-feedback when all explicit runtime keys are enabled. |
| 19C | Added Dusk dashboard smoke proving the existing Operator Issuance Activity card renders combined journal/action/feedback facts. |
| 19D | Closed the wave and updated the Cockpit and Settlement OS compasses. |

## Verified combined state

Expected activity card facts:

```text
journal: recorded
action: composed
feedback: planned
```

Expected safe detail facts:

- Writes journal: yes
- Action hint: `cockpit.pay-code.open`
- Executes action: no
- Feedback intent
- Delivery plan
- Sends feedback: no
- Channel: `in_app`

## Safety invariants

- Combined runtime remains explicit opt-in.
- Defaults remain null/not-wired.
- x-journal writes only when journal handoff is explicitly configured.
- x-action composes presentation-only action hints; it does not execute actions.
- x-feedback prepares notification-planning facts; it does not dispatch provider delivery.
- Pipeline handoff failures remain non-blocking.
- Operator presentation remains redaction-safe.

## Verification

Commands run:

```bash
vendor/bin/pest tests/Feature/Cockpit/CockpitDurableActivityDiagnosticFixtureCommandTest.php
vendor/bin/pest tests/Feature/Cockpit/CockpitQuickGenerateCombinedRuntimeTest.php
php artisan dusk tests/Browser/CockpitDashboardCombinedRuntimeSmokeTest.php
vendor/bin/pest tests/Feature/Cockpit/CockpitDurableActivityDiagnosticFixtureCommandTest.php tests/Feature/Cockpit/CockpitQuickGenerateCombinedRuntimeTest.php tests/Feature/Cockpit/CockpitQuickGenerateXJournalRuntimeTest.php tests/Feature/Cockpit/CockpitQuickGenerateXActionRuntimeTest.php tests/Feature/Cockpit/CockpitQuickGenerateXFeedbackRuntimeTest.php
php artisan dusk tests/Browser/CockpitDashboardCombinedRuntimeSmokeTest.php
```

Results:

- Package focused backend runtime suite passed.
- Dusk combined dashboard smoke passed.

## Commits

- `4798d1c cockpit: verify combined activity fixture`
- `1b439b5 cockpit: verify combined quick generate runtime`
- `555022d cockpit: add combined runtime dashboard smoke`

## Next recommended wave

Cockpit Wave 20 — Operator Activity Runtime Configuration UX / Local Operations Handoff.

Purpose:

- Document/operator-surface how to enable the runtime profile locally.
- Add a local verification command or report that confirms active runtime keys.
- Avoid enabling journal/action/feedback handoffs by default.
