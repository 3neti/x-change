# Cockpit Mutation Wave 7B — Durable Activity Retention / Purge Enforcement Baseline

    Status: Scaffolded / Baseline recorded
    Date: 2026-07-11

    ## Purpose

    Convert the Wave 6 retention decision into a purge/read-side exclusion implementation-control baseline.

    ## Production Control

    Expired durable activity records must be purged or excluded before production default enablement.

    ## Required Enforcement Shape

    - Repository reads must not expose records past their retention boundary once enforcement is enabled.
- A future purge command must be explicit, observable, idempotent, and safe to run repeatedly.
- Retention enforcement must preserve append-only journal truth; Cockpit activity cleanup must not delete x-journal entries.
- No purge scheduler or destructive cleanup is enabled by this scaffold checkpoint.

    ## Boundary

    - Durable activity recording remains disabled by default.
    - This checkpoint adds no new Cockpit mutation control.
    - This checkpoint adds no journal write, action execution, feedback delivery, provider call, voucher mutation, wallet mutation, queue worker, purge command, or production-default persistence behavior.
    - No current Cockpit UI change is expected.

    ## Next Checkpoint

    7C — Recorder Failure Observability Implementation Baseline
