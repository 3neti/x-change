# Cockpit Wave 68A — Manual Distribution External Evidence Validation Plan

## Status

Complete / Planning-only validation baseline.

## Purpose

Define the validation posture required before manual distribution external evidence intake can become runtime behavior.

This slice does not implement validation code. It records the request-shape, field, state, and boundary validation rules that must exist before Cockpit may accept operator-submitted external distribution evidence.

## Validation decision

External evidence intake must be reject-by-default.

Future runtime evidence intake may only accept allowlisted, operator-safe, scoped, redacted, and reviewable evidence facts.

## Required request shape

Future evidence intake must define a final request/response contract before any route is created.

Minimum allowed request concepts:

- Pay Code reference.
- Distribution workspace reference.
- Optional campaign reference.
- External workflow name.
- Evidence type.
- Evidence status.
- Redacted delivery reference.
- Redacted recipient reference.
- Operator note summary.
- Occurred-at timestamp.
- Correlation reference.
- Idempotency key.

## Required validation rules

| Field | Required validation |
|---|---|
| Pay Code reference | Required, scoped, existing, operator-authorized, non-empty string. |
| Distribution workspace reference | Required, scoped, operator-authorized, non-empty string. |
| Campaign reference | Optional, scoped, operator-authorized, non-empty string when present. |
| External workflow name | Required, allowlisted, non-secret string. |
| Evidence type | Required, allowlisted enum. |
| Evidence status | Required, allowlisted enum. |
| Delivery reference | Optional, redacted, non-secret, bounded string. |
| Recipient reference | Optional, masked or tokenized, bounded string. |
| Operator note summary | Optional, bounded text after redaction and secret rejection. |
| Occurred-at timestamp | Required, timezone-aware, not future-skewed beyond approved tolerance. |
| Correlation reference | Required, non-secret, bounded string. |
| Idempotency key | Required for mutations and replay protection. |

## Values that must be rejected

- Raw beneficiary claim URLs in free-form notes.
- Credentials.
- Access tokens.
- API keys.
- Bank account numbers.
- Wallet internals.
- Provider payloads.
- Webhook payloads.
- Full unmasked recipient contact details.
- HTML/script payloads.
- Binary attachments.
- Screenshots.
- Files.
- Unbounded text.

## State validation

Future runtime must define allowed state transitions before persistence.

Planning-only candidate states:

- `submitted`
- `accepted_for_review`
- `rejected`
- `needs_correction`
- `superseded`

No state may imply voucher lifecycle truth, x-feedback delivery truth, x-journal authority, x-action completion, campaign mutation, provider execution, wallet mutation, or money movement.

## Explicit denials

Until validation is implemented and approved, Cockpit must not add:

- Evidence request classes.
- Evidence routes.
- Evidence controllers.
- Evidence forms.
- Evidence persistence.
- Evidence files or uploads.
- Evidence state transition handlers.
- Journal writes.
- Feedback delivery mutation.
- Action completion.
- Campaign mutation.
- Provider calls.
- Voucher mutation.
- Wallet mutation.
- Money movement.

## Runtime implication

Validation planning alone is not sufficient to unblock runtime evidence intake. Retention, purge, review, handoff, attachment, incident, and rollback plans must also be complete.

## Next slice

```text
Cockpit Wave 68B — Manual Distribution External Evidence Retention Plan
```

