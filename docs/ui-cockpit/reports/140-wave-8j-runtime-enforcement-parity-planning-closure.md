# Cockpit Mutation Wave 8J — Runtime Enforcement / Parity Planning Closure

    Status: Scaffolded / Runtime planning recorded
    Date: 2026-07-11

    ## Purpose

    Close Wave 8 and set the next wave to Cockpit parity audit across dashboard, Pay Codes, and balances.

    ## Decision

    Wave 9 should start with a route/data/component parity audit for `/x/dashboard`, `/x/pay-codes`, and `/x/balances` before visible replacement work.

    ## Required Controls

    - Durable activity production default remains disabled until runtime enforcement is implemented.
- Cockpit parity work may start as audit/reporting without enabling new mutations.
- Visible parity implementation should follow the audit and should keep package assets as the source of truth.
- No current Cockpit UI change is expected from Wave 8.

    ## Boundary

    - Durable activity production default remains disabled.
    - No current Cockpit UI change is expected.
    - No new journal write, action execution, feedback delivery, provider call, voucher mutation, wallet mutation, queue worker, purge command, route replacement, or money movement is introduced by this checkpoint.

    ## Next Checkpoint

    Wave 9A — /x/dashboard, /x/pay-codes, and /x/balances Parity Audit
