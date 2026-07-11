# Cockpit Wave 15B — Pass / Block Decision Criteria Record

## Status

Implemented.

## Purpose

Define exact go/no-go rules for deciding whether Cockpit can move from visual acceptance into the next runtime planning slice.

## Go Criteria

Proceed to runtime decision when all are true:

- Quick Generate uses the Existing GeneratePayCode handoff.
- Quick Generate result shows operator-safe generated Pay Code details.
- Pricing preflight is visible after a successful generation.
- Funding preflight is visible after a successful generation.
- Draft runtime and activity runtime facts are visible.
- Historical architecture panels are behind diagnostics.
- Legacy pages show Cockpit bridge callouts.
- No raw payloads, wallet internals, provider payloads, secrets, or hidden mutation controls are visible.
- journal/action/feedback handoffs remain gated.
- campaign mutation remains gated.

## No-Go Criteria

Do not proceed when any are true:

- Quick Generate cannot generate through the existing handoff.
- Result panel omits pricing/funding/draft/activity runtime facts.
- Historical panels still dominate the operator path.
- Legacy bridge callouts are absent.
- Browser errors prevent confidence in the rendered UI.
- The UI exposes raw payloads, wallet internals, provider payloads, or secrets.
- Any new mutation path appears outside the existing GeneratePayCode handoff.

## Decision Output

If Go: proceed to `Cockpit Wave 15D — Next Runtime Decision Record`.

If No-Go: record blockers and return to targeted UI/runtime stabilization.

## Boundary

This criteria record does not change runtime behavior.

## Next Recommended Checkpoint

Cockpit Wave 15C — Browser Evidence / Log Snapshot Record.
