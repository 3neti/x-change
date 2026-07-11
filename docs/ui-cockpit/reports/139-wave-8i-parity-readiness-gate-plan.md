# Cockpit Mutation Wave 8I — Parity Readiness Gate Plan

    Status: Scaffolded / Runtime planning recorded
    Date: 2026-07-11

    ## Purpose

    Define the gate between runtime hardening and the visible Cockpit parity wave.

    ## Decision

    Dashboard/Pay Code/Balance parity may begin after the audit identifies read-only-safe facts and mutation-gated facts separately.

    ## Required Controls

    - Parity implementation should start with read-only facts and navigation before adding new mutation controls.
- Balance parity must not introduce wallet mutation, top-up, provider calls, or money movement without separate approval.
- Pay Code parity must not change voucher redemption, issuance, or lifecycle semantics.
- The next wave should be a parity audit, not direct UI replacement.

    ## Boundary

    - Durable activity production default remains disabled.
    - No current Cockpit UI change is expected.
    - No new journal write, action execution, feedback delivery, provider call, voucher mutation, wallet mutation, queue worker, purge command, route replacement, or money movement is introduced by this checkpoint.

    ## Next Checkpoint

    8J — Runtime Enforcement / Parity Planning Closure
