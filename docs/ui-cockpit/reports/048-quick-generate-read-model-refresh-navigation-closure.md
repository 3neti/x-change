# Cockpit Mutation Wave 1F — Read Model Refresh / Navigation Closure

## Status

Implemented.

## Scope

This slice closes the first Quick Generate mutation path by adding operator-controlled post-submit affordances:

- generated Pay Code result display;
- Cockpit Voucher Detail navigation link;
- manual Quick Generate read-model refresh.

## Implemented Behavior

- The Cockpit Quick Generate mutation response now includes:
  - `result.code`
  - `result.amount`
  - `result.currency`
  - `result.links.redeem`
  - `result.links.redeem_path`
  - `result.links.cockpit_detail`
- The Quick Generate submit panel stores only the operator-safe response body.
- After a successful submit, the panel shows:
  - generated code;
  - “Open Cockpit detail” link;
  - “Refresh read model” button.
- The refresh button calls Inertia `router.reload()` with:
  - `only: ['quick_generate_read_model']`
  - `preserveScroll: true`

## Explicit Non-Goals

This slice does not add:

- automatic redirect;
- optimistic read-model mutation;
- Pay Code Explorer mutation;
- Voucher Detail mutation;
- journal writes;
- action execution;
- feedback delivery;
- campaign mutation;
- direct provider calls;
- direct wallet access;
- direct money movement.

## Boundary Decision

Cockpit may guide the operator to the generated Pay Code detail after issuance, but the operator remains in control. The UI does not silently redirect or mutate read models client-side.

## Verification

Covered by:

- frontend Quick Generate submit-panel tests;
- Cockpit mutation route response tests;
- architecture documentation guard.

## Next Recommended Slice

Draft the next mutation plan before adding more write behavior. Candidate:

```text
Cockpit Mutation Wave 2 — Operator-visible issuance activity and audit handoff
```

