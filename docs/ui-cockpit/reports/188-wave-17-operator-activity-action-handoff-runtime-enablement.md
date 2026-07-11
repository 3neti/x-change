# Cockpit Wave 17 — Operator Activity Action Handoff Runtime Enablement

Date: 2026-07-11

## Objective

Enable and verify the Cockpit operator issuance activity x-action handoff path so Quick Generate durable activity can expose presentation-only operator action hints when explicitly configured.

## Boundary

Wave 17 does not execute workflow actions.

The default remains safe:

```text
repository: null
recorder: null
action_handoff: null
action_handoff_status_projector: null
```

Action handoff facts are composed only when runtime configuration opts in.

## Completed Slices

### Wave 17A — Real x-action Adapter

Added `XActionCockpitOperatorIssuanceActivityActionHandoff`.

Verified:

- it composes against `cockpit.operator_issuance_activity.recorded`;
- it maps Cockpit activity to x-action subject/context data;
- it returns presentation-only action hints;
- it does not execute actions, authorize actions, move money, expose provider payloads, or persist lifecycle truth.

### Wave 17B — Runtime Profile Key Resolution

Added runtime profile-key resolution for:

```text
action_handoff=x-action
```

Direct class-name configuration remains supported.

### Wave 17C — Action Handoff Status Persistence

Added:

- `CockpitOperatorIssuanceActivityActionHandoffStatusProjectorContract`
- `CockpitOperatorIssuanceActivityActionHandoffStatusProjectionData`
- `NullCockpitOperatorIssuanceActivityActionHandoffStatusProjector`
- `DatabaseCockpitOperatorIssuanceActivityActionHandoffStatusProjector`

The database projector persists:

```text
action_handoff_status=composed
action_hint_id
action_run_id
executes_action=false
safe action metadata
```

### Wave 17D — Quick Generate Runtime Invocation

Updated `CockpitOperatorIssuanceActivityHandoffPipeline` so Quick Generate durable activity can invoke x-action handoff and persist action status when configured.

Verified:

- existing issuance handoff still uses `GeneratePayCode`;
- journal remains separately gated;
- x-action handoff composes action hints only;
- persisted action metadata is redacted and operator-safe.

### Wave 17E — Dashboard Read Model Exposure

Updated durable activity read-model hydration and presentation data so dashboard props expose:

```text
handoffs.action = composed
action_hint_id = cockpit.pay-code.open
action_run_id = ...
executes_action = false
```

### Wave 17F — Dashboard UI Rendering

Updated the existing Operator Issuance Activity panel to render a read-only action handoff summary.

Visible facts include:

```text
action: composed
Action hint: cockpit.pay-code.open
Action run: ...
Executes action: no
Suggested action: Open Pay Code
```

### Wave 17G — Dusk Dashboard Smoke

Extended the local diagnostic fixture command with:

```text
--with-action
```

Added host Dusk coverage proving the dashboard renders action-composed fixture facts.

## Runtime Configuration

For local action-enabled activity runtime:

```env
XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_REPOSITORY=database
XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_RECORDER=database
XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_ACTION_HANDOFF=x-action
XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_ACTION_HANDOFF_STATUS_PROJECTOR=database
```

Class names are still accepted for backward compatibility.

## Test Results

Focused package tests:

```text
CockpitOperatorIssuanceActivityXActionHandoffTest.php
CockpitOperatorIssuanceActivityRuntimeProfileResolutionTest.php
CockpitOperatorIssuanceActivityActionHandoffStatusPersistenceAdapterTest.php
CockpitQuickGenerateXActionRuntimeTest.php
CockpitOperatorIssuanceActivityXActionReadModelTest.php
```

Frontend test:

```text
CockpitDashboardHydration.test.ts
```

Host browser test:

```text
CockpitDashboardActionComposedSmokeTest.php
```

All focused checks passed during the wave.

## UI Impact

The existing dashboard Operator Issuance Activity cards can now show x-action composition evidence when durable activity storage and action handoff metadata are present.

No action execution button was added.

## Remaining Boundaries

- Action handoff remains opt-in.
- Action runs are presentation-only and non-durable.
- Cockpit does not execute x-action workflows.
- Cockpit does not authorize x-action workflows.
- Feedback handoff remains `not_wired`.
- No provider calls are added.
- No wallet mutation behavior is changed.
- No voucher execution behavior is changed.
- No campaign mutation is added.

## Next Recommended Wave

```text
Cockpit Wave 18 — Operator Activity Feedback Handoff Runtime Enablement
```

The next logical state transition is:

```text
journal: recorded
action: composed
feedback: not_wired → feedback: planned / queued / handed_off
```
