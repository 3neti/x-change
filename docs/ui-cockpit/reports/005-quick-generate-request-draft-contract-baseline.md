# 005 — Cockpit Slice 18 Quick Generate Request Draft Contract Baseline

Cockpit Slice 18 defines the local request draft shape for a future Quick Generate issuance request.

It does not authorize persistence, mutation routing, voucher issuance, pricing calculation, funding reservation, provider calls, journal writes, action execution, feedback delivery, campaign behavior, claim UX changes, or money movement.

## Draft schema

The frontend/backend-neutral draft schema is:

```text
x-change.cockpit.quick-generate-draft.v1
```

## Draft fields

The draft contract contains only planning fields:

- `schema`
- `status`
- `template_key`
- `amount`
- `currency`
- `recipient_reference`
- `purpose`
- `idempotency_key`
- `redactions`

## Current baseline values

```yaml
schema: x-change.cockpit.quick-generate-draft.v1
status: draft_only
template_key: money-changer
amount: null
currency: PHP
recipient_reference: null
purpose: null
idempotency_key: null
redactions:
  payloads: draft-shape-only
```

## Boundary constraints

Drafts are local and read-only in Slice 18.

No draft persistence or mutation route is registered in Slice 18.

The draft contract is not an issuance command. It is not passed to `GeneratePayCode`, does not generate vouchers, and does not reserve funds.

## Future handoff

A later approved mutation slice may translate an authorized draft into the existing issuance path:

```text
Quick Generate draft
    ↓
authorization, pricing, funding, idempotency, redaction gates
    ↓
existing GeneratePayCodeController / GeneratePayCode action
```

Until that slice is approved, the draft remains a read-only contract shape.
