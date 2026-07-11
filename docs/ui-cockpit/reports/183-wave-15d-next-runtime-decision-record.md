# Cockpit Wave 15D — Next Runtime Decision Record

## Status

Implemented.

## Decision

```text
conditional-go
```

Planning may proceed for the next runtime wave, but implementation of new mutation expansion should wait until human visual acceptance is marked Pass.

## Rationale

The current state is technically ready for acceptance:

- asset drift guard is green
- host mirrors are synchronized
- route smoke records are complete
- frontend tests are green
- PHP feature/architecture tests are green
- available browser logs do not show a new blocking Wave 15 exception

However, this wave is explicitly about browser-confirmed visual acceptance. The agent cannot truthfully mark final visual acceptance without human confirmation.

## Runtime Boundary

No new mutation expansion is authorized by this decision.

Quick Generate remains the only Cockpit mutation path, and it still hands off to the existing `GeneratePayCode` runtime.

## Candidate Next Runtime Wave

After human Pass:

```text
Cockpit Wave 16 — Operator Activity Journal Handoff Runtime Enablement
```

Candidate first checkpoint:

```text
Wave 16A — Journal Handoff Runtime Preconditions and Local Opt-In Decision
```

## Explicit Non-Goals

- Do not enable campaign mutation.
- Do not add provider calls from Cockpit.
- Do not bypass `GeneratePayCode`.
- Do not expose raw payloads.
- Do not make journal/action/feedback handoffs mandatory for issuance success.

## Next Recommended Checkpoint

Cockpit Wave 15E — Wave 15 Closure / Human Acceptance Pending Record.
