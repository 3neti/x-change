# 006 — Cockpit Slice 19 Quick Generate Authorization Gate Baseline

Cockpit Slice 19 defines read-only authorization gate facts for the future Quick Generate issuance handoff.

It does not authorize mutation routing, voucher issuance, draft persistence, pricing calculation, funding reservation, provider calls, journal writes, action execution, feedback delivery, campaign behavior, claim UX changes, or money movement.

## Gate status model

Authorization gates are read-only facts in Slice 19.

The current aggregate status is:

```text
blocked
```

This means the operator can view and plan in Cockpit, but generation remains disabled.

## Gate facts

The baseline gate facts are:

- `operator-authenticated` — passed.
- `can-view-cockpit` — passed.
- `can-generate-pay-code` — blocked.
- `can-call-providers` — blocked.
- `can-move-money` — blocked.

## Redaction

The authorization read model exposes only gate labels, statuses, and safe reasons.

It does not expose:

- raw roles
- raw permissions
- policy payloads
- tenant payloads
- provider payloads
- raw payloads

## Boundary constraints

No authorization gate enables generation in Slice 19.

No Cockpit mutation route is registered in Slice 19.

No gate result is treated as a command. The gate read model is only a readiness explanation for operators and future implementation slices.

## Future handoff

A later approved mutation slice may use real policy and tenant checks before calling the existing issuance path:

```text
Quick Generate authorization gates
    ↓
all required gates pass
    ↓
existing GeneratePayCodeController / GeneratePayCode action
```

Until then, the authorization gate model remains a read-only planning surface.
