# Cockpit Mutation Wave 8G — Handoff Runtime Enablement Gate Plan

    Status: Scaffolded / Runtime planning recorded
    Date: 2026-07-11

    ## Purpose

    Plan the explicit runtime gates for journal, action, and feedback handoffs before any production use.

    ## Decision

    Journal/action/feedback handoffs remain disabled by default and require independent explicit enablement gates.

    ## Required Controls

    - Journal handoff enablement must require configured adapter, projector, idempotency, and safe payload mapping.
- Action handoff enablement must remain non-executing unless a later mutation slice explicitly authorizes action execution.
- Feedback handoff enablement must remain non-delivery unless a later slice explicitly authorizes provider dispatch.
- No handoff default changes are introduced by this scaffold checkpoint.

    ## Boundary

    - Durable activity production default remains disabled.
    - No current Cockpit UI change is expected.
    - No new journal write, action execution, feedback delivery, provider call, voucher mutation, wallet mutation, queue worker, purge command, route replacement, or money movement is introduced by this checkpoint.

    ## Next Checkpoint

    8H — Parity Surface Inventory Plan
