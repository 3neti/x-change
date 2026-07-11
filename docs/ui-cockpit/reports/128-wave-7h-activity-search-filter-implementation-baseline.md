# Cockpit Mutation Wave 7H — Activity Search / Filter Implementation Baseline

    Status: Scaffolded / Baseline recorded
    Date: 2026-07-11

    ## Purpose

    Define the read-only search and filter implementation boundary for operator issuance activity.

    ## Production Control

    Activity search must remain read-only, bounded, authorized, tenant-scoped, and redaction-safe.

    ## Required Enforcement Shape

    - Allowed filters are operator, correlation, Pay Code, status, and occurred-at window after authorization scope is enforced.
- Search result limits must be bounded and deterministic to protect Cockpit performance.
- Search must not add mutation controls, retry controls, journal writes, feedback delivery, action execution, or provider calls.
- No new route or UI search form is introduced by this scaffold checkpoint.

    ## Boundary

    - Durable activity recording remains disabled by default.
    - This checkpoint adds no new Cockpit mutation control.
    - This checkpoint adds no journal write, action execution, feedback delivery, provider call, voucher mutation, wallet mutation, queue worker, purge command, or production-default persistence behavior.
    - No current Cockpit UI change is expected.

    ## Next Checkpoint

    7I — Projection / Queue Seam Implementation Baseline
