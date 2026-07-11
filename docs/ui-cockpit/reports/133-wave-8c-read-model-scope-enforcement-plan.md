# Cockpit Mutation Wave 8C — Read Model Scope Enforcement Plan

    Status: Scaffolded / Runtime planning recorded
    Date: 2026-07-11

    ## Purpose

    Plan how existing Cockpit read-model providers will apply authorization and scope before exposing durable activity records.

    ## Decision

    Scope enforcement should sit at read-model/provider boundaries before presentation hydration.

    ## Required Controls

    - Durable activity read models should request a scope decision before querying or before returning records.
- Denied scope should return safe empty read models with explicit unavailable/denied reasons.
- Presentation components must not receive raw unauthorized durable activity rows.
- Dashboard, Pay Code detail, and future parity surfaces must share the same enforcement contract.

    ## Boundary

    - Durable activity production default remains disabled.
    - No current Cockpit UI change is expected.
    - No new journal write, action execution, feedback delivery, provider call, voucher mutation, wallet mutation, queue worker, purge command, route replacement, or money movement is introduced by this checkpoint.

    ## Next Checkpoint

    8D — Repository Query Scope Enforcement Plan
