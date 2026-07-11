# Cockpit Mutation Wave 8B — Durable Activity Scope DTO / Decision Contract Plan

    Status: Scaffolded / Runtime planning recorded
    Date: 2026-07-11

    ## Purpose

    Define the contract shape needed to represent operator scope and authorization decisions without coupling Cockpit to host auth internals.

    ## Decision

    Introduce future package-local scope and access decision DTOs before repository/query enforcement.

    ## Required Controls

    - Scope data should carry operator identifier, tenant/workspace identifier when available, abilities, and redaction mode.
- Decision data should carry allowed/denied, reason, visible scope, and safe diagnostics.
- The default/null decision must fail closed for production-style reads and may remain permissive only in explicitly local tests.
- No route middleware, policies, or host model dependencies are added by this scaffold checkpoint.

    ## Boundary

    - Durable activity production default remains disabled.
    - No current Cockpit UI change is expected.
    - No new journal write, action execution, feedback delivery, provider call, voucher mutation, wallet mutation, queue worker, purge command, route replacement, or money movement is introduced by this checkpoint.

    ## Next Checkpoint

    8C — Read Model Scope Enforcement Plan
