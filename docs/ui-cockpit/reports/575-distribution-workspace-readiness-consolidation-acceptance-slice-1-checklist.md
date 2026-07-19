# Distribution Workspace Readiness Consolidation Acceptance — Slice 1 Checklist

Date: 2026-07-19

## Scope

This slice creates the acceptance checklist for the consolidated Distribution Workspace readiness layout.

Target route:

- `/x/cockpit/pay-codes/{code}/distribution`

## Human Inspection Checklist

Use an existing Pay Code and confirm the page still supports safe manual distribution inspection.

Required visible copy:

- `Distribution Workspace`
- `Manual distribution summary`
- `Connected context`
- `Detailed readiness panels`
- `Notification channels`
- `Message and follow-up readiness`
- `Printable handout options`
- `Status evidence`
- `Share options`
- `Copy, QR, and short-link readiness`

Required absence:

- The old `Channel and artifact readiness` repeated metric grid should no longer appear.

Required safety confirmations:

- The beneficiary claim URL is visible when available.
- Copy remains browser-local/manual only.
- Delivery remains disabled from Cockpit.
- QR, short-link, print-file, and artifact generation remain deferred.
- Follow-up actions remain disabled/read-only.
- No raw payloads, provider payloads, wallet internals, secrets, tokens, OTP values, or execution payloads are visible.
- No feedback delivery, campaign dispatch, journal write, provider call, voucher mutation, wallet mutation, Treasury mutation, or money movement is triggered by opening the page.

## Decision Rule

Record `Pass` only after human evidence confirms the page is less repetitive and still understandable.

If the page is functionally correct but still needs UI refinement, record `Pass with UI follow-up`.

If the old duplicate readiness grid still appears, if claim URL/copy behavior regresses, or if unsafe data/mutation appears, record `Blocked`.

## Boundary

This checklist does not change runtime behavior.

No routes, controllers, queries, read-model hydration, distribution dispatch, feedback delivery, action execution, journal write, campaign mutation, voucher mutation, claim execution, driver execution, provider call, artifact generation, wallet behavior, Treasury behavior, public API behavior, persistence, or money movement changed.

