# Cockpit Wave 28B — Operator Activity Filter Browser Acceptance

## Status

Complete.

## Objective

Record browser acceptance for the Wave 27 Operator Issuance Activity filter refinement.

## Accepted Browser Surface

```text
/x/cockpit?activity_search=PC-DUSK-FILTER&activity_status=issued&activity_handoff_status=recorded
```

## Browser-Accepted Behavior

The focused Dusk smoke confirms:

- the Cockpit dashboard route accepts the filter query;
- the search input preserves `activity_search`;
- the status select preserves `activity_status`;
- the handoff select preserves `activity_handoff_status`;
- the active-filter count renders;
- read-only safety copy renders;
- the compact summary renders;
- `Clear search`, `Clear status`, and `Clear handoff` links render;
- a matching durable activity card renders;
- journal handoff evidence renders as read-only display state;
- runtime mutation/configuration controls remain absent;
- raw/provider payload labels remain absent.

## Verification

Command run:

```bash
php artisan dusk tests/Browser/CockpitDashboardActivityFilterSmokeTest.php
```

Result:

```text
Dusk activity filter smoke: 1 passed, 27 assertions
```

## Human Browser Checklist

Open:

```text
http://x-change-sandbox.test/x/cockpit?activity_search=PC-DUSK-FILTER&activity_status=issued&activity_handoff_status=recorded
```

Confirm:

- `SEARCH ACTIVITY`, `STATUS`, and `HANDOFF` controls appear;
- `3 active filters` appears when all three filters are active;
- `Filters: search “PC-DUSK-FILTER” · status issued · handoff recorded` appears;
- `Clear search`, `Clear status`, and `Clear handoff` appear;
- clearing one filter keeps the other filters in the URL;
- no `Enable handoffs`, `Save configuration`, retry, resend, rerun, or execute controls appear.

## Explicit Boundaries

Wave 28B does not add:

- visible multi-select controls;
- saved filter presets;
- filter persistence;
- POST/PUT/PATCH/DELETE filter routes;
- runtime configuration mutation UI;
- handoff enablement toggles;
- retry, resend, rerun, or execute controls;
- provider calls;
- wallet mutation;
- voucher execution mutation;
- journal/action/feedback write expansion;
- campaign mutation;
- raw payload display.

## Next Checkpoint

Cockpit Wave 28C — Operator Activity Next Runtime Decision.
