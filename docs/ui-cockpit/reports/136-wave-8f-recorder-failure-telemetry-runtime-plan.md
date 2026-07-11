# Cockpit Mutation Wave 8F — Recorder Failure Telemetry Runtime Plan

    Status: Scaffolded / Runtime planning recorded
    Date: 2026-07-11

    ## Purpose

    Plan operational telemetry for durable activity recorder, repository, and handoff failures.

    ## Decision

    Recorder and handoff failures should emit safe diagnostics and logs before production default enablement.

    ## Required Controls

    - Quick Generate issuance must remain non-blocking when activity recording or handoff telemetry fails.
- Telemetry should distinguish recorder, repository, journal handoff, projector, action handoff, and feedback handoff failures.
- Diagnostics must expose correlation and safe reason codes without raw payloads or secrets.
- No external monitoring backend or alert channel is added by this scaffold checkpoint.

    ## Boundary

    - Durable activity production default remains disabled.
    - No current Cockpit UI change is expected.
    - No new journal write, action execution, feedback delivery, provider call, voucher mutation, wallet mutation, queue worker, purge command, route replacement, or money movement is introduced by this checkpoint.

    ## Next Checkpoint

    8G — Handoff Runtime Enablement Gate Plan
