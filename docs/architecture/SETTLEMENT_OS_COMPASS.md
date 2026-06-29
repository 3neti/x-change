# Settlement Operating System Compass

## Mission

Operationalize x-change as a Settlement Operating System for Pay Codes / Portable Settlement Contracts.

The operating model is:

```text
Portable Settlement Contract / Pay Code
    ↓
Validation
    ↓
Authorization
    ↓
Routing
    ↓
Execution
    ↓
Journal
    ↓
Action / CTA
    ↓
Feedback
    ↓
Cockpit
    ↓
Campaign / Program Scale
```

This Compass is the program-level memory. Future workstream compasses should be summarized here when a slice begins, completes, reveals a significant risk, or changes a package boundary.

## Current Position

Current wave: Wave 2A — x-journal  
Current status: Ready to begin x-journal documentation/bootstrap and Slice 0-style characterization  
Last updated: 2026-06-29

| Wave | Workstream | Role | Status | Compass |
|---|---|---|---|---|
| 1 | Execution Engine | Kernel / runtime | Completed through Slice 9 scaffold | [execution-engine/EXECUTION_ENGINE_COMPASS.md](execution-engine/EXECUTION_ENGINE_COMPASS.md) |
| 2A | x-journal | System log / audit trail | Next | Pending |
| 2B | x-action | Workflow continuation / CTA layer | Not started | Pending |
| 3 | x-feedback | Notification / communication layer | Not started | Pending |
| 4 | x-change Cockpit | Operator shell | Not started | Pending |
| 5 | x-campaign | Program / bulk distribution layer | Not started | Pending |

## Package Map

| Layer | Primary package path | Boundary |
|---|---|---|
| x-change | `/Users/rli/PhpstormProjects/x-change-sandbox/packages/x-change` | Settlement OS orchestration, product experience, APIs, provider coordination, analytics/reporting |
| voucher | `/Users/rli/PhpstormProjects/packages/voucher` | Voucher lifecycle and execution semantics |
| x-journal | `/Users/rli/PhpstormProjects/packages/x-journal` | Durable system log and audit trail |
| x-action | `/Users/rli/PhpstormProjects/packages/x-action` | Workflow continuation and CTA state |
| x-feedback | `/Users/rli/PhpstormProjects/packages/x-feedback` | Notifications and communication tracking |
| x-campaign | `/Users/rli/PhpstormProjects/packages/x-campaign` | Bulk/program distribution |
| settlement-envelope | `/Users/rli/PhpstormProjects/packages/settlement-envelope` | Settlement readiness / authorization participant |
| wallet / cash / contact / form-flow / emi-core | `/Users/rli/PhpstormProjects/packages/*` | Domain capabilities consumed through explicit seams |

## Completed Work

### Wave 1 — Execution Engine

- Execution Engine workstream completed through Slice 9.
- Voucher now owns execution semantics behind a registry-driven runtime.
- x-change remains the product/orchestration adapter and does not own voucher execution semantics.
- Current voucher execution path is:

```text
RedeemVoucher
    ↓
ExecutionEngine
    ↓
ExecutionDriverRegistry
    ↓
ExecutionDriverContract
```

- Built-in drivers currently registered in voucher:
  - `default`
  - `settlement_envelope`
  - `stored_value`
- Execution results include durable `execution_id` correlation.
- Driver-composed pipeline runtime exists as opt-in infrastructure; existing drivers were not rewritten into pipeline steps.
- Last known green suites:
  - voucher: `381 passed, 28 skipped`
  - x-change package: `970 passed, 5 skipped`
- Latest relevant commits:
  - voucher: `d7abbe6 execution-engine: add driver composed runtime`
  - x-change: `ca6a91d docs(execution-engine): record slice 9 composed runtime`

## Current Architectural Decisions

- x-change is the Settlement Operating System / orchestration layer.
- Voucher owns voucher execution semantics.
- Claim UX is not execution.
- x-journal records what happened; it must not decide what should happen.
- x-action describes available/required/blocked/completed workflow actions; it must not execute money movement directly.
- x-feedback communicates lifecycle state; it must not own lifecycle truth.
- Cockpit displays and operates real state through existing APIs/actions/execution paths.
- x-campaign coordinates program scale and must not reinvent issuance, execution, feedback, or reporting.
- Execution Engine is journal-ready but not journal-dependent.
- Settlement Envelope is a participant/readiness gate/authorization structure, not the execution engine.
- Stored Value is driver behavior, not a new voucher species.
- Driver-composed runtime is opt-in driver infrastructure, not a replacement for the central `ExecutionEngine`.

## Program Risks

- Package boundaries can blur as integration pressure increases. Record boundary decisions before implementation.
- Execution result persistence is still deferred; x-journal must be designed without forcing voucher to depend on journal storage.
- Concrete settlement-envelope and stored-value gateway bindings remain unresolved.
- Existing provider readiness, wallet mutation, claim submission, and reconciliation paths are sensitive; keep characterization tests around them.
- Each package has its own Pest/Testbench environment. Run tests from the relevant package root.
- The top-level roadmap spans multiple repositories. Do not mix unrelated package changes in one commit.

## Next Recommended Workstream

Begin Wave 2A — x-journal.

First actions:

1. Read the x-journal planning docs under `/Users/rli/PhpstormProjects/x-change-sandbox/docs/todo/x-journal`.
2. Inspect the actual x-journal package at `/Users/rli/PhpstormProjects/packages/x-journal`.
3. Create or update an x-journal workstream Compass in the relevant package/docs path.
4. Characterize existing x-journal behavior before changing architecture.
5. Add baseline tests for journal append/read/query invariants.
6. Report discrepancies before production changes.

## x-journal Initial Intent

x-journal should become the append-only system memory for:

- execution events
- claim lifecycle events
- authorization events
- settlement events
- campaign events
- operator actions
- provider callbacks
- exceptions
- reconciliation events

It should capture:

- who
- what
- when
- where
- why/context
- before/after
- correlation IDs
- causation IDs

It must not enforce business policy or decide workflow outcomes.

## Open Questions

- Where should the x-journal Compass live if the x-journal package already has a preferred docs structure?
- What is the existing x-journal storage model, if any?
- Should execution results be journaled by x-change adapters, voucher event listeners, or a future integration package?
- What is the canonical correlation mapping from voucher `execution_id` to journal correlation/causation IDs?
- Should provider callbacks and reconciliation events use the same journal entry schema as execution events?
- How should program-level campaign events later connect to journal entries without making x-campaign depend on journal internals?

## Update Rules

Update this top-level Compass whenever:

- a workstream begins
- a workstream slice completes
- a significant package boundary changes
- a cross-workstream dependency is introduced
- a major risk is discovered
- test coverage status changes
- a workstream Compass is created or moved
- the recommended next workstream changes

Workstream compasses remain the source of detailed slice history. This Compass should summarize program status and link to the detailed Compass rather than duplicating every slice.
