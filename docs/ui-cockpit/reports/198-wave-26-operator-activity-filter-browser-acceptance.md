# Cockpit Wave 26 — Operator Activity Filter Browser Acceptance / Query UX Hardening

## Status

Complete.

## Objective

Harden the read-only Operator Issuance Activity filter experience so browser/manual acceptance can distinguish three states:

- durable activity not wired;
- recent durable activity available with no active filters;
- active filters applied with matching or no matching activity.

## Implemented Slices

### Wave 26A — Result Summary Hardening

`CockpitOperatorIssuanceActivityPanel` now renders a result summary for durable operator activity.

Expected copy:

```text
Showing 1 matching activity for the current read-only filters.
Showing 2 recent activities.
Activity filters become available when durable activity storage is wired.
```

This keeps the filter state visible without implying mutation, provider calls, wallet access, journal writes, action execution, feedback delivery, or lifecycle truth ownership.

### Wave 26B — Filtered No-Match Empty State

When filters are active and no activity matches, the panel now renders:

```text
No activity matches current filters
Clear filters or adjust the search/status criteria to inspect durable operator issuance activity.
```

The filtered no-match state no longer falls back to the runtime-not-wired empty copy.

### Wave 26C — Browser / Manual Acceptance Record

The browser acceptance surface is:

```text
/x/cockpit?activity_search=PC-DUSK-FILTER&activity_status=issued&activity_handoff_status=recorded
```

The accepted read-only behavior is:

- the Operator Issuance Activity panel renders the filter bar;
- the search input preserves the query string value;
- the status and handoff selects preserve the query string values;
- active filter chips summarize the current filters;
- the result summary states the matching count;
- the filtered no-match state renders the no-match copy when no activity matches;
- the clear link returns operators to `/x/cockpit`;
- no mutation controls are exposed.

Controls and data that must remain absent:

- `Enable handoffs`;
- `Save configuration`;
- retry, resend, rerun, or execute controls;
- `provider_payload`;
- `wallet_payload`;
- raw provider payloads;
- raw wallet payloads;
- raw idempotency keys.

## Verification

Commands run:

```bash
npx vitest run tests/frontend/cockpit/CockpitDashboardHydration.test.ts
php artisan x-change:install --force
php artisan x-change:doctor --assets --json
```

Previously established browser smoke for this same filter surface:

```text
tests/Browser/CockpitDashboardActivityFilterSmokeTest.php
```

Results:

```text
Frontend hydration: 18 passed
Dusk activity filter smoke: 1 passed, 23 assertions
Asset drift doctor: checked 58, ok 58, stale 0, missing 0, extra 0
```

## Human Acceptance Checklist

Use a browser with the local app running and open:

```text
http://x-change-sandbox.test/x/cockpit?activity_search=PC-DUSK-FILTER&activity_status=issued&activity_handoff_status=recorded
```

Pass criteria:

- The page renders without a Vite or console-blocking error.
- The Operator Issuance Activity panel shows `SEARCH ACTIVITY`, `STATUS`, and `HANDOFF`.
- The filter inputs reflect the URL query values.
- The panel shows the result summary.
- If the current local data has no match, the panel shows `No activity matches current filters`.
- The panel remains read-only and exposes no mutation controls.

## Explicit Boundaries

Wave 26 does not add:

- multi-select filters;
- saved filter presets;
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

## Decision

Wave 26 is accepted as a read-only query UX hardening pass.

URL-only query state remains acceptable for the current operator activity filter surface.

Multi-select filters are deferred until real operator usage shows that single status and single handoff filters are insufficient.

## UI Impact

Operators should now see a clearer summary below the activity filter bar.

If filters return no records, operators should see the explicit no-match message instead of stale not-wired copy.

## Next Recommended Wave

Cockpit Wave 27 — Operator Activity Filter UX Refinement / Multi-Select Decision.
