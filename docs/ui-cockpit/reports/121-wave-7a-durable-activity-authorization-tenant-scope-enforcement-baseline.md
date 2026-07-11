# Cockpit Mutation Wave 7A — Durable Activity Authorization / Tenant Scope Enforcement Baseline

    Status: Scaffolded / Baseline recorded
    Date: 2026-07-11

    ## Purpose

    Convert the Wave 6 authorization decision into an implementation-control baseline for durable activity reads and future writes.

    ## Production Control

    Durable activity access must be authorized and tenant-scoped before production default enablement.

    ## Required Enforcement Shape

    - Every durable activity read model adapter must have an explicit operator authorization decision before exposing activity facts.
- Every durable activity query must carry tenant, workspace, or equivalent host scope before multi-tenant production enablement.
- Denied, missing, or ambiguous scope must fail closed to an empty/redacted read model instead of exposing activity rows.
- Quick Generate durable activity recording remains local opt-in and not production-default until this control has runtime enforcement.

    ## Boundary

    - Durable activity recording remains disabled by default.
    - This checkpoint adds no new Cockpit mutation control.
    - This checkpoint adds no journal write, action execution, feedback delivery, provider call, voucher mutation, wallet mutation, queue worker, purge command, or production-default persistence behavior.
    - No current Cockpit UI change is expected.

    ## Next Checkpoint

    7B — Durable Activity Retention / Purge Enforcement Baseline
