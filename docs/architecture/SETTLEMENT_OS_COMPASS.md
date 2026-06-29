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
Current status: Wave 2A Phase 4 complete in x-journal  
Last updated: 2026-06-29

| Wave | Workstream | Role | Status | Compass |
|---|---|---|---|---|
| 1 | Execution Engine | Kernel / runtime | Completed through Slice 9 scaffold | [execution-engine/EXECUTION_ENGINE_COMPASS.md](execution-engine/EXECUTION_ENGINE_COMPASS.md) |
| 2A | x-journal | System log / audit trail | Phase 4 complete | `/Users/rli/PhpstormProjects/packages/x-journal/docs/architecture/x-journal/X_JOURNAL_COMPASS.md` |
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

### Wave 2A — x-journal

- Read the Wave 2A planning set under `/Users/rli/PhpstormProjects/x-change-sandbox/docs/todo/x-journal`.
- Verified the expected package path `/Users/rli/PhpstormProjects/packages/x-journal` does not currently exist.
- Verified `docs/todo/x-journal/x-journal_codex_instructions.md` is empty.
- The addendum and functional specifications are present and coherent enough to establish the workstream intent, Compass rules, phase roadmap, and architectural invariants.
- Stopped before package scaffolding or implementation because a required package is missing.
- Created the independent package scaffold at `/Users/rli/PhpstormProjects/packages/x-journal` after explicit approval.
- Established the namespace convention `LBHurtado\XJournal`.
- Added the x-journal workstream Compass at `/Users/rli/PhpstormProjects/packages/x-journal/docs/architecture/x-journal/X_JOURNAL_COMPASS.md`.
- Completed Phase 1A Core Journal Foundation in x-journal:
  - `ExecutionJournalEntry` model and migration
  - Spatie Laravel Data DTOs for actor, subject, money, references, integrity, and journal entry payloads
  - `ExecutionReferenceNumberGenerator`
  - `ExecutionJournalRecorder`
  - default `DatabaseJournalSink`
  - append-only update/delete guards
  - green x-journal package suite: `13 passed, 25 assertions`
- Completed Phase 1B Core Journal Foundation hardening in x-journal:
  - counter-backed ERN sequencing by prefix/year
  - scalar projection columns for actor, subject, correlation, causation, and execution IDs
  - deterministic SHA-256 integrity hashing
  - previous-hash chaining across persisted journal entries
  - tested package-consumer sink replacement via `JournalSinkContract`
  - green x-journal package suite: `19 passed, 49 assertions`
- Completed Phase 2 Event Transformation Layer in x-journal:
  - generic `JournalEventData`
  - event transformer contract and registry
  - `JournalEventRecorder`
  - execution-result transformer baseline for `execution.*` events
  - unsupported-event failure behavior
  - package-consumer transformer registration seam
  - green x-journal package suite: `25 passed, 71 assertions`
- Completed Phase 2B Domain Event Transformer Baselines in x-journal:
  - claim lifecycle transformer baseline
  - provider callback transformer baseline
  - reconciliation transformer baseline
  - tests proving domain event normalization without business decisions
  - green x-journal package suite: `31 passed, 98 assertions`
- Completed Phase 3 Sink Architecture in x-journal:
  - canonical database sink retained
  - `JournalSinkDispatcher`
  - `SecondaryJournalSinkContract`
  - secondary sink fan-out for projections/exports
  - tests proving secondary sinks do not become canonical journal truth
  - green x-journal package suite: `36 passed, 113 assertions`
- Completed Phase 4 Visibility and Authorization in x-journal:
  - access actor and access decision DTOs
  - visibility policy contract
  - visibility gate
  - default actor-or-subject visibility policy
  - explicit `x-journal.view` permission support
  - tests proving visibility does not alter journal truth
  - green x-journal package suite: `43 passed, 126 assertions`

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
- x-journal append-only enforcement is currently model-event based; direct database mutation protection is not yet addressed.
- x-journal integrity hashes are deterministic but unsigned; signature strategy remains open for future verification phases.
- x-journal event transformers are package-local and intentionally not wired to voucher or x-change runtime events yet.
- x-journal domain transformers preserve payloads and do not interpret claim outcomes, provider success, or reconciliation resolution.
- x-journal secondary sinks are currently synchronous after canonical database persistence.
- x-journal visibility is currently package-local and programmatically composed.
- The primary x-journal Codex instruction file is empty; the addendum and functional specifications currently carry the actionable guidance.
- Concrete settlement-envelope and stored-value gateway bindings remain unresolved.
- Existing provider readiness, wallet mutation, claim submission, and reconciliation paths are sensitive; keep characterization tests around them.
- Each package has its own Pest/Testbench environment. Run tests from the relevant package root.
- The top-level roadmap spans multiple repositories. Do not mix unrelated package changes in one commit.

## Next Recommended Workstream

Begin Phase 5 — Artifact Generation.

Recommended actions:

1. Add artifact generation contracts.
2. Add statement/receipt rendering baseline.
3. Add artifact profile DTOs.
4. Prove artifacts are renderings of journal truth, not journal truth itself.

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

- Should the new x-journal package be wired into the host app path repositories now, or only after Phase 1 core behavior exists?
- Should the empty `x-journal_codex_instructions.md` remain empty, or should the addendum be promoted/merged into the primary instruction file?
- Should Phase 1 use `spatie/laravel-data` DTOs immediately or plain immutable PHP data objects first?
- What is the initial ERN format and uniqueness scope?
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
