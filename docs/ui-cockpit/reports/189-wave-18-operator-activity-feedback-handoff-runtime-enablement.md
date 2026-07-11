# Cockpit Wave 18 — Operator Activity Feedback Handoff Runtime Enablement

## Status

Complete.

## Scope

Wave 18 enables Cockpit durable operator issuance activity to hand off read-only notification planning facts to x-feedback when explicitly configured.

The scope remains non-delivery:

- No provider notification is sent.
- No feedback delivery runtime is invoked.
- No feedback lifecycle truth is created.
- No wallet, voucher, provider, journal, or action behavior is changed by x-feedback handoff.

## Implemented slices

| Slice | Result |
| --- | --- |
| 18A | Added `XFeedbackCockpitOperatorIssuanceActivityFeedbackHandoff` to prepare x-feedback intent/delivery plan facts without dispatching delivery. |
| 18B | Added runtime profile key resolution through `feedback_handoff=x-feedback`; default remains null/not wired. |
| 18C | Added feedback handoff status projector contract, null projector, database projector, and safe metadata persistence. |
| 18D | Added feedback handoff invocation to the durable activity pipeline after journal/action handoffs with non-blocking failure behavior. |
| 18E | Hydrated persisted feedback handoff details into the durable Cockpit read model and presenter metadata. |
| 18F | Rendered feedback handoff summary in the Operator Issuance Activity panel and verified published asset drift guard. |
| 18G | Added a local diagnostic fixture option and Dusk smoke test for `feedback: planned`. |
| 18H | Closed the wave and recorded readiness for the next combined runtime verification wave. |

## Runtime opt-in keys

```env
XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_REPOSITORY=database
XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_RECORDER=database
XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_FEEDBACK_HANDOFF=x-feedback
XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_FEEDBACK_HANDOFF_STATUS_PROJECTOR=database
```

## UI impact

When x-feedback handoff details exist, the dashboard Operator Issuance Activity card can now show:

- `feedback: planned`
- Feedback intent
- Delivery plan
- Sends feedback: no
- Channel
- Planned deliveries
- Source / reason

This is visible only when durable activity metadata includes feedback handoff facts.

## Safety invariants

- Defaults remain safe/null.
- Feedback handoff is explicit opt-in.
- Feedback handoff prepares x-feedback data only.
- Feedback handoff does not call provider channel drivers.
- Feedback handoff does not send email, SMS, webhook, or in-app messages.
- Feedback handoff does not own lifecycle truth.
- Feedback handoff metadata is redacted before persistence and presentation.
- Pipeline failures are non-blocking.

## Verification

Commands run:

```bash
vendor/bin/pest tests/Feature/Cockpit/CockpitOperatorIssuanceActivityXFeedbackHandoffTest.php
vendor/bin/pest tests/Feature/Cockpit/CockpitOperatorIssuanceActivityRuntimeProfileResolutionTest.php tests/Feature/Cockpit/CockpitOperatorIssuanceActivityXFeedbackHandoffTest.php
vendor/bin/pest tests/Feature/Cockpit/CockpitOperatorIssuanceActivityFeedbackHandoffStatusPersistenceAdapterTest.php tests/Feature/Cockpit/CockpitOperatorIssuanceActivityRuntimeProfileResolutionTest.php
vendor/bin/pest tests/Feature/Cockpit/CockpitQuickGenerateXFeedbackRuntimeTest.php tests/Feature/Cockpit/CockpitOperatorIssuanceActivityFeedbackHandoffStatusPersistenceAdapterTest.php
vendor/bin/pest tests/Feature/Cockpit/CockpitOperatorIssuanceActivityXFeedbackReadModelTest.php tests/Unit/Cockpit/CockpitOperatorIssuanceActivityPresentationTest.php
npm run test:frontend -- CockpitDashboardHydration.test.ts
php artisan x-change:install --force
php artisan x-change:doctor --assets --json
vendor/bin/pest tests/Feature/Cockpit/CockpitDurableActivityDiagnosticFixtureCommandTest.php
php artisan dusk tests/Browser/CockpitDashboardFeedbackPlannedSmokeTest.php
```

Results:

- Focused x-change package backend tests passed.
- Frontend hydration test passed.
- Published asset drift guard passed: checked 56, ok 56, stale 0.
- Dusk smoke passed.

## Commits

- `7a57774 cockpit: add x-feedback activity handoff`
- `c2417f9 cockpit: resolve x-feedback activity runtime profile`
- `c4ff16d cockpit: persist x-feedback activity handoff status`
- `c3e7cd7 cockpit: invoke x-feedback activity handoff`
- `3913a07 cockpit: expose x-feedback activity read model`
- `eb055f7 cockpit: render x-feedback activity handoff`
- `0c5279f cockpit: add x-feedback activity dashboard smoke`

## Next recommended wave

Cockpit Wave 19 — Combined Operator Activity Runtime Profile Verification.

Purpose:

- Verify journal, action, and feedback handoffs together.
- Add one local fixture / Dusk smoke that shows `journal: recorded`, `action: composed`, and `feedback: planned` on the same activity card.
- Confirm combined runtime profile remains safe and non-blocking.
