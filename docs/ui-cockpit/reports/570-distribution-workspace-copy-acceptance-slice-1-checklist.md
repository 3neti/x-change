# Distribution Workspace Copy Acceptance — Slice 1 Checklist

Date: 2026-07-19

## Scope

This slice creates the acceptance checklist for the Distribution Workspace copy-polished secondary panels.

Target route:

- `/x/cockpit/pay-codes/{code}/distribution`

## Human Inspection Checklist

Use an existing Pay Code and confirm the page still shows the expected manual distribution flow.

Required visible copy:

- `Distribution Workspace`
- `Manual distribution summary`
- `Manual next step`
- `Copy claim URL`
- `Notification channels`
- `Message and follow-up readiness`
- `Why disabled`
- `Printable handout options`
- `Share options`
- `Copy, QR, and short-link readiness`
- `Status evidence`
- `Delivery and campaign signals`
- `Why this status appears`

Required safety confirmations:

- The beneficiary claim URL is visible when available.
- The copy button is browser-local only.
- Delivery remains disabled from Cockpit.
- QR, short-link, print-file, and artifact generation remain deferred.
- Follow-up actions remain disabled/read-only.
- No raw payloads, provider payloads, wallet internals, secrets, tokens, OTP values, or execution payloads are visible.
- No feedback delivery, campaign dispatch, journal write, provider call, voucher mutation, wallet mutation, Treasury mutation, or money movement is triggered by opening the page.

## Decision Rule

Record `Pass` only after human evidence confirms the copy is understandable and no runtime errors are visible.

If the page is functional but copy/layout still needs improvement, record `Pass with UI follow-up`.

If a visible runtime error, missing claim URL, broken navigation, unsafe data exposure, or unintended mutation appears, record `Blocked`.

## Boundary

This checklist does not change runtime behavior.

No routes, controllers, queries, read-model hydration, distribution dispatch, feedback delivery, action execution, journal write, campaign mutation, voucher mutation, claim execution, driver execution, provider call, artifact generation, wallet behavior, Treasury behavior, public API behavior, persistence, or money movement changed.

