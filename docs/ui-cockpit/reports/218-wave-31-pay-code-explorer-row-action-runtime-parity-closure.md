# Cockpit Wave 31 — Pay Code Explorer Row Action Runtime Parity Closure

## Status

Completed.

## Completed Slices

| Slice | Result |
|---|---|
| 31A | Row action parity audit recorded. |
| 31B | Row action read-model contract added. |
| 31C | Provider row action hydration added. |
| 31D | Results table renders enabled read-only row links and disabled future actions. |
| 31E | Playwright browser smoke and asset doctor verified the published host UI. |

## As-Built Runtime

Pay Code Explorer rows now carry and render operator-safe row actions:

- `View details` → `/x/cockpit/pay-codes/{code}`;
- `Distribution` → `/x/cockpit/pay-codes/{code}/distribution`;
- `Open timeline` remains disabled;
- `Notify recipient` remains disabled.

## Verification

Focused verification covered:

- package DTO contract tests;
- provider parity tests;
- frontend hydration tests;
- Playwright browser smoke tests;
- published asset drift doctor;
- Pint formatting.

## Boundary Confirmation

Wave 31 added read-only navigation only. It did not add:

- voucher mutation;
- claim approval;
- redemption execution;
- execution-driver invocation;
- provider calls;
- wallet mutation;
- journal writes;
- x-action execution;
- x-feedback delivery;
- campaign mutation;
- unsafe payload exposure.

## UI Result

Operators can now move from `/x/cockpit/pay-codes` list rows into read-only Cockpit detail and distribution surfaces using row-level links.

## Next Recommended Wave

Cockpit Wave 32 — Voucher Detail Functional Parity / Evidence Surface Hardening.

Recommended first slice:

```text
Cockpit Wave 32A — Voucher Detail Functional Parity Audit
```

Rationale:

The Explorer now links into Voucher Detail. The next pragmatic step is ensuring the destination surface has the functional facts operators need, without introducing mutation.
