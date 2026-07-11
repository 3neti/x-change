# Cockpit Wave 27A — Operator Activity Filter Multi-Select Decision

## Status

Complete.

## Decision

Keep the Operator Issuance Activity filter UI as single-select for now.

Do not add visible multi-select controls in Wave 27.

## Context

The Cockpit dashboard route already normalizes `activity_status` and `activity_handoff_status` as string lists. That backend shape can support future multi-select query parameters.

The current operator UI, however, only exposes one status and one handoff status at a time.

## Rationale

Single-select remains the better operator default while the activity volume is still low and local/manual validation is still underway.

The next useful UX hardening is not multi-select. It is:

- clearer active-filter summaries;
- clear-per-filter links;
- preserved read-only query semantics;
- browser-smokeable behavior with no mutation controls.

## Deferred Multi-Select Criteria

Revisit multi-select when at least one of these is true:

- operators need to inspect multiple terminal statuses at once;
- handoff triage requires combining `recorded`, `planned`, `composed`, and `not_wired` states;
- activity volume makes repeated single-filter visits inefficient;
- saved filter presets are authorized.

## Explicit Boundaries

Wave 27A does not add:

- visible multi-select controls;
- saved filters;
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

Cockpit Wave 27B — Operator Activity Compact Active Filter Summary.
