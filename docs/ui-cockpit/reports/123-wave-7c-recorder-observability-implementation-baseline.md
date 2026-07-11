# Cockpit Mutation Wave 7C — Recorder Failure Observability Implementation Baseline

    Status: Scaffolded / Baseline recorded
    Date: 2026-07-11

    ## Purpose

    Define the minimum observable failure facts for durable activity recorder and handoff failures.

    ## Production Control

    Recorder and handoff failures must be visible as safe diagnostics without blocking issuance.

    ## Required Enforcement Shape

    - Recorder failures must remain non-blocking for the existing Quick Generate issuance path.
- Recorder failure diagnostics must be safe, redacted, and correlated to the activity or request when possible.
- Operational counters/log events should distinguish recorder, repository, journal handoff, projector, action handoff, and feedback handoff failures.
- No host monitoring exporter, queue, retry, or alerting integration is enabled by this scaffold checkpoint.

    ## Boundary

    - Durable activity recording remains disabled by default.
    - This checkpoint adds no new Cockpit mutation control.
    - This checkpoint adds no journal write, action execution, feedback delivery, provider call, voucher mutation, wallet mutation, queue worker, purge command, or production-default persistence behavior.
    - No current Cockpit UI change is expected.

    ## Next Checkpoint

    7D — Journal Handoff Hardening Baseline
