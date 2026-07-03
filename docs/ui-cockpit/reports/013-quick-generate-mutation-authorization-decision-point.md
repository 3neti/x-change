# Cockpit Slice 26 — Quick Generate Mutation Authorization Decision Point

## Purpose

Cockpit Slice 26 records the explicit authorization decision before any Quick Generate mutation route can be scaffolded.

The decision point is intentionally read-only. It does not authorize a route, validate a submitted request, persist a payload, call issuance code, or perform any side effect.

## Decision

The Slice 26 decision is:

```text
not_authorized
```

Required approval:

```text
human-approval-required-before-route-scaffold
```

Next step:

```text
request-explicit-approval-or-continue-read-only-hardening
```

No Cockpit mutation route is authorized in Slice 26.

## Rationale

Mutation preconditions remain blocked; Cockpit must not register a write route until explicit human approval and a smaller mutation contract exist.

The currently blocked preconditions include:

- authorization readiness
- pricing readiness
- funding readiness
- idempotency readiness
- validation and redaction readiness
- existing issuance handoff readiness
- operator response contract readiness

## Read Model Shape

Slice 26 adds a read-only `mutation_authorization_decision` section to the Quick Generate read model:

```php
[
    'status' => 'blocked',
    'decision' => 'not_authorized',
    'required_approval' => 'human-approval-required-before-route-scaffold',
    'rationale' => 'Mutation preconditions remain blocked; Cockpit must not register a write route until explicit human approval and a smaller mutation contract exist.',
    'next_step' => 'request-explicit-approval-or-continue-read-only-hardening',
    'redactions' => [
        'payloads' => 'mutation-authorization-decision-only',
    ],
]
```

This shape is informational only.

## Explicit Non-Goals

No mutation endpoints, voucher issuance, request validation execution, payload persistence, provider call, wallet access, journal write, action run, feedback delivery, campaign behavior, or money movement is introduced in Slice 26.

Slice 26 does not introduce:

- Cockpit `POST`, `PUT`, `PATCH`, or `DELETE` routes
- `GeneratePayCode` invocation
- `GeneratePayCodeController` handoff
- request validation execution
- submitted payload storage
- idempotency key generation or persistence
- pricing calculation
- funding reservation
- wallet lookup
- provider invocation
- journal write
- action run
- feedback delivery
- campaign behavior
- money movement

## Operator Presentation

The Quick Generate page shows a visible Mutation Authorization Decision Point panel.

The panel displays:

- decision
- required approval
- rationale
- next step
- redaction policy

The panel contains no form and no submit action.

## Boundary

The disabled generate action remains disabled.

The Cockpit route surface remains GET-only.

The only safe follow-up is one of:

1. request explicit human approval to define a smallest possible mutation-route contract; or
2. continue read-only Cockpit hardening.

## Tests

The Slice 26 tests protect:

- `mutation_authorization_decision` serialization in the Quick Generate read model
- read-only route hydration for the decision point
- no broad mutation payload exposure
- no Cockpit mutation routes
- frontend rendering of the decision point
- no form/submission behavior in the decision panel
- report coverage for the authorization decision boundary
