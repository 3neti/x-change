# Cockpit Wave 60C — Manual Guidance Acceptance Decision Policy

## Status

Scaffolded / Pending human guidance intake.

## Purpose

This checkpoint defines how to classify human evidence for manual distribution guidance on Voucher Detail and Distribution Workspace.

The policy prevents the Cockpit guidance acceptance from accidentally authorizing delivery, telemetry, campaign dispatch, QR assets, short links, journal writes, action execution, provider calls, voucher mutation, wallet mutation, or money movement.

## Required Evidence

A decision record must identify:

- Reviewer.
- Review date.
- Environment and browser.
- Pay Code inspected.
- Voucher Detail URL opened.
- Distribution Workspace URL opened.
- Whether the manual distribution guidance is visible on both surfaces.
- Whether the guidance is clear and operator-safe.
- Whether any errors or side effects were observed.

## Pass

Use `Pass` only when:

- Voucher Detail was inspected.
- Distribution Workspace was inspected.
- Both pages showed visible manual distribution guidance.
- The guidance was comprehensible without engineering context.
- The guidance stated manual distribution only.
- The guidance stated approved external workflow.
- The guidance stated recipient verification.
- The guidance did not imply Cockpit-delivered SMS, email, webhook, in-app, or campaign distribution.
- The guidance did not imply copy telemetry persistence.
- The guidance did not imply short-link or QR asset generation.
- The guidance did not imply journal writes, action execution, provider calls, voucher mutation, wallet mutation, or money movement.

## Blocked

Use `Blocked` when:

- No usable Pay Code is available.
- Either page is inaccessible.
- The reviewer cannot inspect the UI.
- Host-published Cockpit assets are stale.
- Browser/runtime issues prevent inspection.
- The reviewer cannot determine whether the guidance is visible and clear.

## Fail

Use `Fail` when:

- Guidance is missing from either surface.
- Guidance is wrong, misleading, or unsafe.
- Guidance implies Cockpit delivery by SMS, email, webhook, in-app notification, or campaign dispatch.
- Guidance implies copy telemetry persistence.
- Guidance implies short-link or QR asset generation.
- Guidance implies journal writes, action execution, provider calls, voucher mutation, wallet mutation, or money movement.
- The reviewer observes an unexpected side effect while only inspecting the guidance.

## No Evidence Rule

If no completed human evidence record exists, the only valid status is:

`pending-human-guidance-intake`

Do not mark Pass, Blocked, or Fail without evidence from a reviewer.

## Next Checkpoint

Cockpit Wave 60D — Manual Guidance Pending Acceptance Status / Closure.
