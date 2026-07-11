# Cockpit Mutation Wave 7F — PII / Redaction Enforcement Baseline

    Status: Scaffolded / Baseline recorded
    Date: 2026-07-11

    ## Purpose

    Define enforceable redaction controls for durable activity records and Cockpit read models.

    ## Production Control

    Durable activity storage and display must not expose raw payloads, credentials, wallet data, tokens, OTPs, or recipient secrets.

    ## Required Enforcement Shape

    - Repository writes must pass through the redaction policy before persistence.
- Read model presenters must consume safe fields only and must not display raw metadata bags blindly.
- Redaction flags must remain explicit and testable so unsafe exposure cannot be hidden in nested metadata.
- No new UI controls or data exposure are introduced by this scaffold checkpoint.

    ## Boundary

    - Durable activity recording remains disabled by default.
    - This checkpoint adds no new Cockpit mutation control.
    - This checkpoint adds no journal write, action execution, feedback delivery, provider call, voucher mutation, wallet mutation, queue worker, purge command, or production-default persistence behavior.
    - No current Cockpit UI change is expected.

    ## Next Checkpoint

    7G — Rollback / Disable Operational Baseline
