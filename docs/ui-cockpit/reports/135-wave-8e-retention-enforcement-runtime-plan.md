# Cockpit Mutation Wave 8E — Retention Enforcement Runtime Plan

    Status: Scaffolded / Runtime planning recorded
    Date: 2026-07-11

    ## Purpose

    Plan runtime retention enforcement after authorization/scope seams are defined.

    ## Decision

    Retention should be enforced both at read time and by an explicit purge command before production default enablement.

    ## Required Controls

    - Read models should exclude expired durable activity records once retention enforcement is active.
- A future purge command should be explicit, idempotent, bounded, observable, and local/production-safe by option.
- Retention cleanup must not delete or mutate x-journal entries.
- No purge command, scheduler, or destructive database operation is added by this scaffold checkpoint.

    ## Boundary

    - Durable activity production default remains disabled.
    - No current Cockpit UI change is expected.
    - No new journal write, action execution, feedback delivery, provider call, voucher mutation, wallet mutation, queue worker, purge command, route replacement, or money movement is introduced by this checkpoint.

    ## Next Checkpoint

    8F — Recorder Failure Telemetry Runtime Plan
