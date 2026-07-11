# Cockpit Mutation Wave 7G — Rollback / Disable Operational Baseline

    Status: Scaffolded / Baseline recorded
    Date: 2026-07-11

    ## Purpose

    Define operational rollback controls for disabling durable activity repository, recorder, and handoffs.

    ## Production Control

    Operators must be able to disable durable activity recording and handoffs without breaking Quick Generate issuance.

    ## Required Enforcement Shape

    - Null repository, recorder, journal handoff, action handoff, feedback handoff, and projector remain the safe fallback implementations.
- Rollback must not require deleting durable activity tables or changing voucher issuance behavior.
- Dashboard read models must degrade to not-wired/empty states when durable activity is disabled.
- No rollback command or production config mutation is enabled by this scaffold checkpoint.

    ## Boundary

    - Durable activity recording remains disabled by default.
    - This checkpoint adds no new Cockpit mutation control.
    - This checkpoint adds no journal write, action execution, feedback delivery, provider call, voucher mutation, wallet mutation, queue worker, purge command, or production-default persistence behavior.
    - No current Cockpit UI change is expected.

    ## Next Checkpoint

    7H — Activity Search / Filter Implementation Baseline
