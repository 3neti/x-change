# Cockpit Wave 69 — Manual Distribution External Evidence Review / Handoff Plan

## Status

Complete / Planning-only review and handoff baseline.

## Purpose

Define the review workflow and package handoff posture required before manual distribution external evidence intake can become runtime behavior.

This slice does not implement review queues, routes, controllers, state machines, journal writers, feedback writers, action completion, campaign mutation, or evidence persistence.

## Review decision

Manual distribution external evidence must be reviewable before it can influence any operator-facing status.

External evidence remains:

```text
operator-submitted review evidence
```

It is not:

```text
voucher lifecycle truth
x-feedback delivery truth
x-journal authority
x-action completion
x-campaign state
provider settlement truth
wallet truth
money movement truth
```

## Required review workflow

Future runtime evidence intake must define these review states before persistence:

- `submitted`
- `accepted_for_review`
- `accepted`
- `rejected`
- `needs_correction`
- `superseded`
- `purged`

Future runtime evidence intake must define who may:

- Submit evidence.
- View redacted evidence.
- Review evidence.
- Reject evidence.
- Request correction.
- Supersede evidence.
- Purge evidence.
- Escalate suspicious evidence.

## Required handoff boundaries

| Layer | Future handoff role | Boundary |
|---|---|---|
| x-journal | Record approved evidence events if authorized. | Cockpit must not become journal truth. |
| x-feedback | Correlate evidence to delivery attempts if authorized. | Cockpit must not mutate delivery truth directly. |
| x-action | Expose follow-up CTAs if authorized. | Cockpit must not complete actions directly. |
| x-campaign | Attribute evidence to campaign contexts if authorized. | Cockpit must not mutate campaign state directly. |
| voucher | Link evidence to Pay Code context if authorized. | Cockpit must not mutate voucher lifecycle. |
| wallet/provider | No handoff. | Evidence must not move money or call providers. |

## Required escalation workflow

Future runtime evidence intake must define escalation handling for:

- Mistaken recipient disclosure.
- Suspicious distribution evidence.
- Conflicting evidence.
- Duplicate evidence.
- Evidence containing secrets.
- Evidence referencing an unscoped Pay Code.
- Evidence submitted by an unauthorized operator.
- Evidence tied to a sensitive beneficiary URL.

## Explicit denials

Until review and handoff are implemented and approved, Cockpit must not add:

- Evidence review routes.
- Evidence review controllers.
- Evidence review queues.
- Evidence state machines.
- Evidence approval handlers.
- Evidence rejection handlers.
- Evidence correction handlers.
- Evidence journal writers.
- Evidence feedback writers.
- Evidence action completion handlers.
- Evidence campaign mutation handlers.
- Provider calls.
- Voucher mutation.
- Wallet mutation.
- Money movement.

## Runtime implication

Review and handoff planning does not authorize runtime evidence intake. Attachment/storage, malware scanning, readiness closure, and runtime implementation decision remain required.

## Next checkpoint

```text
Cockpit Wave 70 — Manual Distribution External Evidence Attachment / Storage Decision
```

