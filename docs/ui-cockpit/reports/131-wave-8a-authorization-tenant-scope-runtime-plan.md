# Cockpit Mutation Wave 8A — Authorization / Tenant Scope Runtime Plan

    Status: Scaffolded / Runtime planning recorded
    Date: 2026-07-11

    ## Purpose

    Choose the first runtime enforcement branch for durable Cockpit activity: authorization and tenant scope before broader production enablement.

    ## Decision

    Proceed with runtime authorization and tenant-scope enforcement before enabling durable activity recording by default.

    ## Required Controls

    - The runtime plan must introduce a package-owned authorization/scope seam before any broad Cockpit durable activity reads.
- The seam must degrade denied or ambiguous operators to empty/redacted read models.
- The seam must not depend on host-specific User models, tenant models, or roles directly.
- No UI change is expected until a later slice exposes authorized/denied read states intentionally.

    ## Boundary

    - Durable activity production default remains disabled.
    - No current Cockpit UI change is expected.
    - No new journal write, action execution, feedback delivery, provider call, voucher mutation, wallet mutation, queue worker, purge command, route replacement, or money movement is introduced by this checkpoint.

    ## Next Checkpoint

    8B — Durable Activity Scope DTO / Decision Contract Plan
