# Cockpit Mutation Wave 8H — Parity Surface Inventory Plan

    Status: Scaffolded / Runtime planning recorded
    Date: 2026-07-11

    ## Purpose

    Record the legacy/current x-change surfaces that Cockpit parity must audit after runtime safety gates are planned.

    ## Decision

    The parity audit must include `/x/dashboard`, `/x/pay-codes`, and `/x/balances`, not only the Cockpit dashboard.

    ## Required Controls

    - `/dashboard` is a redirect and should be treated as compatibility routing to `/x/dashboard`.
- `/x/dashboard` is the current dashboard parity baseline.
- `/x/pay-codes` is the Pay Code list/explorer parity baseline.
- `/x/balances` is the balance/funding/liquidity parity baseline.

    ## Boundary

    - Durable activity production default remains disabled.
    - No current Cockpit UI change is expected.
    - No new journal write, action execution, feedback delivery, provider call, voucher mutation, wallet mutation, queue worker, purge command, route replacement, or money movement is introduced by this checkpoint.

    ## Next Checkpoint

    8I — Parity Readiness Gate Plan
