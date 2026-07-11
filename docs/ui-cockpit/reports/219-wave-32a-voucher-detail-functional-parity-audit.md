# Cockpit Wave 32A — Voucher Detail Functional Parity Audit

## Mission

Audit the Cockpit Voucher Detail destination reached from Wave 31 row links and define the next functional parity target.

## Current State

`/x/cockpit/pay-codes/{code}` already renders a sanitized Voucher Detail read model:

- Pay Code;
- display status;
- amount;
- claim state;
- availability window;
- redaction policy;
- dependent read-model status for execution, journal, action, and feedback;
- evidence, distribution, timeline, and audit panels.

The page is read-only and does not mutate vouchers, execute drivers, call providers, write journal entries, send feedback, or move money.

## Parity Gap

The destination is useful, but still reads like a foundation surface. After Wave 31, operators can navigate there from Explorer rows, so Voucher Detail should expose clearer operator-safe evidence facts:

- lifecycle summary facts;
- execution readiness/evidence status;
- claim/approval evidence status;
- journal evidence status;
- action/CTA status;
- feedback/distribution status;
- explicit redaction and mutation boundary facts.

## Authorized Wave 32 Scope

Wave 32 may add read-only evidence surface hardening:

1. a typed evidence summary contract;
2. provider hydration for voucher detail evidence summaries;
3. UI presentation of those evidence facts;
4. browser verification from Explorer row to Voucher Detail;
5. closure and next planning.

## Explicit Boundaries

Wave 32 must not add:

- claim approval;
- redemption execution;
- execution-driver invocation;
- provider calls;
- wallet mutation;
- journal writes;
- x-action execution;
- x-feedback delivery;
- campaign mutation;
- raw provider payload display;
- raw claim payload display;
- raw recipient payload display;
- raw wallet/internal balance display.

## Planned Slices

| Slice | Purpose | Expected UI effect |
|---|---|---|
| 32A | Voucher Detail parity audit | No UI change |
| 32B | Evidence summary read-model contract | No visible UI until adopted |
| 32C | Provider evidence summary hydration | No visible UI until adopted |
| 32D | Evidence summary UI presentation | Voucher Detail shows an operator evidence summary |
| 32E | Browser/publish verification | No new UI beyond 32D |
| 32F | Closure / next planning | No UI change |

## Next Slice

Cockpit Wave 32B — Voucher Detail Evidence Summary Read Model Contract.
