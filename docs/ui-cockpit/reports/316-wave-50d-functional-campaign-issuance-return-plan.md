# Cockpit Wave 50D — Functional Campaign Issuance Return Plan

## Status

Completed.

## Decision

Return the next implementation direction to functional campaign issuance.

The immediate goal is not more destination-copy polish. The useful next path is to connect campaign/template ideas to the existing Quick Generate handoff in a controlled, non-bulk way.

## Recommended next wave

`Cockpit Wave 51 — Campaign Template Quick Generate Functional Bridge`

## Recommended Wave 51 scope

- Audit current campaign context prefill and Quick Generate draft factory behavior.
- Define a single-recipient campaign issuance draft contract.
- Confirm the draft can produce a `GeneratePayCodeRequest`-compatible payload.
- Keep `GeneratePayCode` as the issuance owner.
- Keep x-campaign state read-only.
- Keep bulk issuance blocked.
- Keep provider, wallet, feedback, journal, and action side effects behind existing x-change issuance boundaries.

## Explicit non-goals

- No campaign mutation.
- No campaign batch execution.
- No campaign recipient status updates.
- No direct distribution dispatch.
- No new provider calls from Cockpit.
- No direct wallet movement from Cockpit.
- No feedback delivery from Cockpit.
- No journal writes from Cockpit.

## Rationale

The user goal is functional parity: generate Pay Codes using the new template and campaign ideas.

Destination navigation is stable enough from automated evidence. Human acceptance remains pending, but it does not block non-mutating functional campaign issuance planning.

## Next slice

`Cockpit Wave 50E — Campaign Destination Acceptance Intake Closure`
