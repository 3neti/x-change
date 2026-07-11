# Cockpit Mutation Wave 7I — Projection / Queue Seam Implementation Baseline

    Status: Scaffolded / Baseline recorded
    Date: 2026-07-11

    ## Purpose

    Define when durable activity handoff/status projection may move from synchronous local execution to queued projection.

    ## Production Control

    Projection queues must be idempotent, observable, retry-safe, and non-authoritative for issuance truth.

    ## Required Enforcement Shape

    - Queued projection must not become required for successful Quick Generate issuance.
- Projection jobs must carry correlation and idempotency metadata and must be safe to retry.
- Projection failures must surface as operational diagnostics instead of hidden silent loss.
- No queue jobs, scheduler, worker dependency, or asynchronous behavior is introduced by this scaffold checkpoint.

    ## Boundary

    - Durable activity recording remains disabled by default.
    - This checkpoint adds no new Cockpit mutation control.
    - This checkpoint adds no journal write, action execution, feedback delivery, provider call, voucher mutation, wallet mutation, queue worker, purge command, or production-default persistence behavior.
    - No current Cockpit UI change is expected.

    ## Next Checkpoint

    7J — Production Hardening Controls Closure
