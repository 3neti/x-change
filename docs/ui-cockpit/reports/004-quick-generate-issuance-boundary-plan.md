# 004 — Cockpit Slice 17 Quick Generate Issuance Boundary Plan

Slice 17 documents the boundary for a future Quick Generate issuance handoff.

It does not authorize mutation wiring.

## Boundary Position

Quick Generate is an operator Cockpit surface. It may prepare a future issuance request, but it must not own voucher issuance semantics, voucher execution, wallet mutation, provider execution, journal writes, feedback delivery, or action execution.

## Existing issuance owner

Actual Pay Code generation already belongs to the existing x-change issuance path:

- `LBHurtado\XChange\Actions\PayCode\GeneratePayCode`
- `LBHurtado\XChange\Http\Controllers\PayCode\GeneratePayCodeController`
- existing API route surface for Pay Code generation

Cockpit must reuse that approved path when a future mutation slice is explicitly authorized.

## Required gates before generation can be enabled

Before a Cockpit generate button can become active, the future mutation slice must define and test:

- Authorization — operator role, tenant, and permission checks.
- Pricing — existing pricing estimate path and stale-estimate handling.
- Funding — wallet/provider funding readiness without exposing balances broadly.
- Idempotency — idempotency key generation, replay behavior, and conflict behavior.
- Redaction — request, response, and error payload redaction for operator-visible facts.

## Current Slice 17 constraints

No Cockpit mutation route is registered in Slice 17.

No changes are authorized for:

- voucher issuance
- execution
- journal writes
- action execution
- feedback delivery
- provider calls
- wallet lookup, reservation, debit, or credit
- campaign behavior
- claim UX

## Future handoff shape

A later approved slice may introduce a thin Cockpit issuance adapter only after the gates above are designed.

The expected direction is:

```text
Cockpit Quick Generate
    ↓
authorized issuance request
    ↓
existing GeneratePayCodeController / GeneratePayCode action
    ↓
existing pricing, funding, idempotency, and generation behavior
```

Cockpit should not create a parallel issuance runtime.
