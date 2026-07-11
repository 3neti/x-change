# Cockpit Mutation Wave 8D — Repository Query Scope Enforcement Plan

    Status: Scaffolded / Runtime planning recorded
    Date: 2026-07-11

    ## Purpose

    Plan repository-level query constraints so durable activity storage cannot accidentally return records outside the authorized operator scope.

    ## Decision

    Repository reads should accept explicit query/scope constraints before production use.

    ## Required Controls

    - Repository recent/find methods should have a scoped query path before production default enablement.
- Scope filtering must support operator ID and future tenant/workspace identifiers.
- Unscoped reads should remain test/local-only or guarded by explicit internal use cases.
- No database schema change or destructive migration is introduced by this scaffold checkpoint.

    ## Boundary

    - Durable activity production default remains disabled.
    - No current Cockpit UI change is expected.
    - No new journal write, action execution, feedback delivery, provider call, voucher mutation, wallet mutation, queue worker, purge command, route replacement, or money movement is introduced by this checkpoint.

    ## Next Checkpoint

    8E — Retention Enforcement Runtime Plan
