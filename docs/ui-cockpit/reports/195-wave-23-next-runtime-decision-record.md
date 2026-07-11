# Cockpit Wave 23B — Next Runtime Decision Record

## Status

Decision recorded.

## Context

Runtime Profile diagnostics are now accepted as a read-only Cockpit surface.

The next decision is whether to:

- add runtime mutation controls;
- deepen runtime diagnostics;
- improve operator activity discovery;
- shift to another functional Cockpit parity surface.

## Decision

Proceed next with:

```text
Cockpit Wave 24 — Operator Activity Search / Filter Runtime Readiness
```

## Rationale

Operator activity is now durably recorded and visible, but operators still need read-only discovery tools before additional mutation scope is expanded.

Search and filtering improve operational usefulness without changing execution, journal, action, feedback, provider, wallet, or voucher behavior.

## Authorized Direction for Wave 24

Wave 24 may plan and scaffold read-only operator activity discovery capabilities:

- search contract baselines;
- filter contract baselines;
- redaction-aware query criteria;
- read model result shape;
- UI readiness planning;
- diagnostics proving search/filtering is read-only.

## Explicitly Deferred

- runtime configuration mutation UI;
- enabling/disabling journal/action/feedback handoffs from Cockpit;
- retry, resend, or re-run controls;
- provider calls;
- wallet mutation;
- voucher execution mutation;
- journal/action/feedback write expansion;
- campaign mutation;
- raw payload display.

## Required Boundaries

- Durable operator activity remains the source for operator activity cards.
- Search/filtering must respect existing redaction and operator visibility rules.
- Search/filtering must not expose raw provider, wallet, or submitted payload internals.
- Search/filtering must not reinterpret lifecycle truth.
- Search/filtering must not write journal entries, action runs, feedback deliveries, vouchers, wallets, or provider records.

## Expected UI Impact

Wave 23B has no UI impact.

The future Wave 24 UI impact, if authorized, should be limited to read-only operator activity discovery affordances such as search fields, filters, status chips, or empty states.

## Next Runtime Capability Gate

Runtime mutation remains blocked until a separate human-approved wave explicitly authorizes it.

## Next Checkpoint

Cockpit Wave 23C — Compass Closure.
