# Cockpit Wave 62B — Manual Distribution Next Capability Decision Matrix

## Status

Scaffolded / Next capability decision recorded.

## Purpose

This checkpoint evaluates the next reasonable capability after manual copy has been accepted.

The decision keeps Cockpit aligned with the Settlement Operating System boundaries: Cockpit can present and operate existing system truth, but it must not invent delivery, telemetry, artifact generation, campaign dispatch, or money movement behavior.

## Capability Options

| Candidate | Status | Reason |
|---|---|---|
| Manual copy operational hardening | Recommended | Builds on accepted manual copy and guidance without adding side effects. |
| Copy event telemetry | Deferred | Requires explicit persistence, redaction, retention, and journal strategy. |
| x-feedback delivery from Cockpit | Deferred | Requires feedback runtime authorization, recipient routing, provider credentials, retries, and delivery records. |
| Campaign dispatch from Cockpit | Deferred | Requires x-campaign mutation readiness and distribution orchestration. |
| Short-link generation | Deferred | Requires route ownership, expiration, revocation, redaction, and audit policy. |
| QR asset generation | Deferred | Requires representation format, artifact storage, revocation semantics, and print/export policy. |
| Print artifact generation | Deferred | Requires artifact generation, template approval, storage/export policy, and operator authorization. |

## Decision

Proceed next with:

`Manual copy operational hardening`

## Recommended Scope

The next wave should strengthen the current manual-copy surface without introducing new side effects:

- Verify the accepted guidance remains visible after asset publishing.
- Keep copy controls browser-local.
- Keep copy attempts non-persistent.
- Keep delivery disabled.
- Keep QR, short links, and print artifacts disabled.
- Add operator readiness documentation for safe manual distribution workflows.
- Add regression guards that prevent accidental backend endpoint calls from copy UI.

## Explicitly Not Approved

The decision does not approve:

- Sending feedback from Cockpit.
- Dispatching campaigns.
- Persisting copy telemetry.
- Writing journal entries.
- Executing actions.
- Calling providers.
- Mutating vouchers.
- Mutating wallets.
- Creating short links.
- Generating QR assets.
- Generating print artifacts.
- Moving money.

## Next Checkpoint

Cockpit Wave 62C — Manual Distribution Operational Readiness Closure.
