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

Current wave: Wave 3 — x-feedback  
Current status: x-feedback Phase 3 Template Resolution Baseline complete  
Last updated: 2026-06-30

| Wave | Workstream | Role | Status | Compass |
|---|---|---|---|---|
| 1 | Execution Engine | Kernel / runtime | Completed through Slice 9 scaffold | [execution-engine/EXECUTION_ENGINE_COMPASS.md](execution-engine/EXECUTION_ENGINE_COMPASS.md) |
| 2A | x-journal | System log / audit trail | Complete through Phase 15 | `/Users/rli/PhpstormProjects/packages/x-journal/docs/architecture/x-journal/X_JOURNAL_COMPASS.md` |
| 2B | x-action | Workflow continuation / CTA layer | Phase 7 complete | `/Users/rli/PhpstormProjects/packages/x-action/docs/x-action-compass.md` |
| 3 | x-feedback | Notification / communication layer | Phase 3 complete | `/Users/rli/PhpstormProjects/packages/x-feedback/docs/architecture/x-feedback/X_FEEDBACK_COMPASS.md` |
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
- Completed Phase 5 Artifact Generation in x-journal:
  - artifact profile and result DTOs
  - artifact renderer contract
  - artifact generator registry
  - text receipt renderer baseline
  - text statement renderer baseline
  - package-consumer renderer registration seam
  - tests proving artifacts render canonical journal entries without becoming or mutating journal truth
  - green x-journal package suite: `49 passed, 145 assertions`
- Completed Phase 6 Verification and Integrity in x-journal:
  - integrity issue and verification result DTOs
  - read-side journal integrity verifier
  - clean SHA-256 hash-chain verification
  - canonical payload tamper detection
  - broken previous-hash continuity detection
  - missing-hash detection
  - unsigned baseline retained for future signature strategy
  - tests proving verification does not mutate journal entries
  - green x-journal package suite: `55 passed, 165 assertions`
- Completed Phase 7 Search and Retrieval in x-journal:
  - retrieval query and result DTOs
  - read-only journal entry retriever
  - reference-number lookup
  - actor and subject projection filtering
  - correlation, causation, execution ID, and event-type filtering
  - bounded limit/offset retrieval windows
  - deterministic ascending/descending ordering
  - tests proving retrieval does not mutate journal entries
  - green x-journal package suite: `62 passed, 189 assertions`
- Completed Phase 8 x-change Execution Integration in x-journal:
  - x-change execution outcome DTO
  - x-change execution journal recorder seam
  - execution outcome normalization from plain arrays
  - voucher `execution_id` mapping into journal references
  - successful and failed execution outcome recording
  - retrieval of recorded execution outcomes by execution ID
  - tests proving recording does not mutate supplied outcome data
  - no x-change or voucher call sites modified yet
  - green x-journal package suite: `68 passed, 232 assertions`
- Completed Phase 9 Provider Callback Integration in x-journal:
  - provider callback DTO
  - provider callback journal recorder seam
  - provider callback payload normalization
  - provider reference and execution reference preservation
  - raw provider payload preservation
  - failed callback recording without settlement/reconciliation/next-action decisions
  - retrieval of provider callbacks by execution and provider references
  - tests proving recording does not mutate supplied callback data
  - green x-journal package suite: `73 passed, 276 assertions`
- Completed Phase 10 Reconciliation Integration in x-journal:
  - reconciliation event DTO
  - reconciliation journal recorder seam
  - reconciliation comparison payload normalization
  - expected/actual/comparison fact preservation
  - discrepancy recording without correction, settlement, or next-action decisions
  - retrieval of reconciliation entries by execution and provider references
  - tests proving recording does not mutate supplied reconciliation data
  - green x-journal package suite: `78 passed, 315 assertions`
- Completed Phase 11 Operator Action Integration in x-journal:
  - operator action DTO
  - operator action journal recorder seam
  - `operator.*` journal transformer baseline
  - operator action payload normalization
  - actor/action/context and target reference preservation
  - blocked or denied action recording without workflow mutation, execution, money movement, or CTA completion
  - retrieval of operator actions by execution and causation references
  - tests proving recording does not mutate supplied operator action data
  - green x-journal package suite: `83 passed, 359 assertions`
- Completed Phase 12 Campaign Integration in x-journal:
  - campaign event DTO
  - campaign journal recorder seam
  - `campaign.*` journal transformer baseline
  - campaign event payload normalization
  - campaign, program, beneficiary-list, distribution, and voucher batch context preservation
  - campaign batch fact recording without voucher issuance, execution decisions, campaign state mutation, or distribution dispatch
  - retrieval of campaign entries by execution and program/causation references
  - tests proving recording does not mutate supplied campaign event data
  - green x-journal package suite: `88 passed, 410 assertions`
- Completed Phase 13 Cockpit Integration in x-journal:
  - Cockpit journal query DTO
  - Cockpit journal entry read model
  - Cockpit journal view result DTO
  - Cockpit journal reader service
  - Cockpit-facing journal query normalization
  - read-model projection over canonical journal entries
  - retrieval composed with `JournalVisibilityGate`
  - tests proving Cockpit reads expose journal facts without bypassing visibility, executing operator actions, or mutating journal entries
  - green x-journal package suite: `93 passed, 441 assertions`
- Completed Phase 14 Hardening in x-journal:
  - architecture hardening test suite
  - runtime Composer dependency boundary coverage for settlement domain packages
  - singleton infrastructure binding coverage
  - append-only model guard invariant coverage
  - explicit built-in transformer support coverage
  - fail-closed unsupported event coverage before persistence side effects
  - Cockpit post-retrieval visibility-window characterization
  - green x-journal package suite: `99 passed, 466 assertions`
- Completed Phase 15 Production Readiness in x-journal:
  - production readiness checklist
  - production deferrals ADR
  - release posture documentation
  - installation expectations documentation
  - validation command documentation
  - tests covering readiness documentation, Composer metadata, Laravel auto-discovery, config publishing, and migration availability
  - Wave 2A closure recommendation
  - green x-journal package suite: `102 passed, 496 assertions`

### Wave 2B — x-action

- Read the Wave 2B planning set under `/Users/rli/PhpstormProjects/x-change-sandbox/docs/todo/x-action`.
- Verified `/Users/rli/PhpstormProjects/packages/x-action` did not exist before this workstream.
- Created the independent package scaffold at `/Users/rli/PhpstormProjects/packages/x-action`.
- Established package identity:
  - Composer package: `3neti/x-action`
  - Namespace: `LBHurtado\XAction`
  - Compass: `/Users/rli/PhpstormProjects/packages/x-action/docs/x-action-compass.md`
- Completed x-action Phase 1 Core Grammar plus the authorized Phase 2 registry/resolver foundation:
  - `spatie/laravel-data` DTO foundation
  - `ActionData`
  - `ActionTargetData`
  - `ActionSubjectData`
  - `ActionContextData`
  - `WorkflowActionContract`
  - `ActionRegistryContract`
  - `ActionResolverContract`
  - `ActionRecorderContract`
  - in-memory/config-backed `ActionRegistry`
  - Laravel-container-backed `ActionResolver`
  - strict and non-strict invalid provider behavior
  - `NullActionRecorder`
  - package config and service provider bindings
  - architecture safety coverage proving no persistence, routes, controllers, connectors, models, or UI were scaffolded
  - green x-action package suite: `13 passed, 35 assertions`
- Completed x-action Phase 2B Resolution Rules and Capability Filtering:
  - dedicated `ActionEligibilityFilter`
  - `InvalidActionResolutionRuleException`
  - permission/capability-aware action filtering
  - feature profile condition filtering
  - subject predicate filtering via `ActionSubjectData::get()`
  - deterministic ordering after mixed config/runtime registration and filtering
  - malformed/unsupported rule fail-closed behavior in non-strict mode
  - malformed/unsupported rule exception behavior in strict mode
  - green x-action package suite: `19 passed, 41 assertions`
- Completed x-action Phase 2C Resolution Diagnostics and Explainability:
  - `ActionResolutionDiagnosticData`
  - `ActionResolutionReportData`
  - `ActionResolver::explain()`
  - diagnostics for included actions, invalid providers, unsupported actions, missing capabilities, feature profile mismatches, subject predicate mismatches, and malformed rules
  - preserved `resolve()` as the stable production API returning `ActionData[]`
  - green x-action package suite: `23 passed, 64 assertions`
- Completed x-action Phase 3 Action Recording and Analytics Baseline:
  - `ActionLifecycleEventData`
  - `InMemoryActionRecorder`
  - default `ActionRecorderContract` binding to the in-memory recorder
  - rendered/clicked/completed/failed lifecycle recording
  - action-key event filtering
  - recorder reset support
  - recording does not change action availability
  - no persistence, routes, connectors, or x-journal dependency introduced
  - green x-action package suite: `28 passed, 82 assertions`
- Completed x-action Phase 4 Action Routing and Redirect Baseline:
  - `ActionTargetResolverContract`
  - `ActionTargetResolutionData`
  - `ActionTargetResolver`
  - invalid and unsupported target exceptions
  - named route target resolution
  - signed route target resolution
  - external URL, mobile deep link, and API target resolution
  - redirect response creation for redirectable targets
  - non-redirectable interpretation for action-router and connector targets
  - no package routes, controllers, persistence, connectors, or UI introduced
  - green x-action package suite: `40 passed, 121 assertions`
- Completed x-action Phase 5 Action Run Identity and Correlation Baseline:
  - `ActionRunData`
  - `ActionRunFactory`
  - package-consumer binding for action run factory resolution
  - UUID-based non-persistent action run identity
  - correlation ID defaulting to run ID
  - caller-supplied correlation and causation ID preservation
  - run-aware rendered/clicked/completed/failed lifecycle recording
  - recorder filtering by run ID and correlation ID
  - legacy recorder calls remain uncorrelated unless a run is supplied
  - no persistence, routes, connectors, execution runtime, or x-journal dependency introduced
  - green x-action package suite: `47 passed, 161 assertions`
- Completed x-action Phase 6 Action Run Lifecycle and Journal Handoff Boundary:
  - `ActionRunLifecycle`
  - `InvalidActionRunTransitionException`
  - immutable action run status transitions
  - fail-closed terminal-state transition protection
  - lifecycle transitions remain separate from recorder side effects
  - `ActionRunJournalHandoffData`
  - `ActionRunJournalHandoffMapper`
  - plain journal-ready handoff payloads for action lifecycle events
  - uncorrelated legacy lifecycle event handoff preservation
  - no persistence, routes, models, execution runtime, or x-journal dependency introduced
  - green x-action package suite: `62 passed, 211 assertions`
- Completed x-action Phase 7 Host Integration Seams:
  - `ActionHostComposerContract`
  - `ActionHostComposer`
  - `ActionHostActionData`
  - `ActionHostResultData`
  - host-facing read-side bundles for resolved actions, target interpretations, action runs, rendered handoff payloads, and optional diagnostics
  - composition aligned with resolver filtering
  - composition remains separate from lifecycle recording side effects
  - no x-change, x-feedback, x-journal, persistence, routes, controllers, or connector dependency introduced
  - green x-action package suite: `68 passed, 240 assertions`

### Wave 3 — x-feedback

- Read the Wave 3 planning set under `/Users/rli/PhpstormProjects/x-change-sandbox/docs/todo/x-feedback`.
- Treated `x-feedback/x-feedbacl_codex_instructions.md` as the active Codex instruction file despite the filename typo.
- Verified `/Users/rli/PhpstormProjects/packages/x-feedback` did not exist before this workstream.
- Created the independent package scaffold at `/Users/rli/PhpstormProjects/packages/x-feedback`.
- Established package identity:
  - Composer package: `3neti/x-feedback`
  - Namespace: `LBHurtado\XFeedback`
  - Compass: `/Users/rli/PhpstormProjects/packages/x-feedback/docs/architecture/x-feedback/X_FEEDBACK_COMPASS.md`
- Completed x-feedback Phase 1 Core Feedback Grammar and Channel Contract Baseline:
  - `FeedbackIntentData`
  - `FeedbackRecipientData`
  - `FeedbackChannelData`
  - `FeedbackMessageData`
  - `FeedbackContextData`
  - `FeedbackDeliveryData`
  - explicit delivery status constants
  - `FeedbackChannelDriverContract`
  - `FeedbackChannelRegistryContract`
  - `FeedbackDispatcherContract`
  - `FeedbackTemplateResolverContract`
  - `FeedbackCredentialResolverContract`
  - `NullFeedbackChannelDriver`
  - `FeedbackChannelRegistry`
  - `FeedbackDispatcher`
  - package config and service provider bindings
  - no persistence, routes, controllers, models, provider SDKs, x-action, x-journal, or x-change dependency introduced
  - green x-feedback package suite: `9 passed, 38 assertions`
- Completed x-feedback Phase 2 Feedback Event Mapping Baseline:
  - `FeedbackEventData`
  - `FeedbackEventMapperContract`
  - `FeedbackEventMapperRegistryContract`
  - `FeedbackEventMapperRegistry`
  - `UnknownFeedbackEventMapperException`
  - package config mapper extension seam
  - runtime mapper registration
  - mapper class-string resolution through the Laravel container
  - registered feedback event to feedback intent mapping
  - unmapped event fail-closed behavior before delivery dispatch
  - no real provider delivery, persistence, queues, retries, routes, x-action, x-journal, or x-change dependency introduced
  - green x-feedback package suite: `15 passed, 71 assertions`
- Completed x-feedback Phase 3 Template Resolution Baseline:
  - `FeedbackTemplateData`
  - `FeedbackTemplateRegistryContract`
  - `FeedbackTemplateRegistry`
  - `FeedbackTemplateResolver`
  - `UnknownFeedbackTemplateException`
  - package config template extension seam
  - key, locale, profile, and channel-aware template resolution
  - key-level default template fallback
  - placeholder rendering from template defaults and intent variables
  - immutable intent resolution behavior
  - no real provider delivery, persistence, queues, retries, routes, or host coupling introduced
  - green x-feedback package suite: `22 passed, 99 assertions`

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
- x-journal artifact generation currently produces in-memory text/plain receipt and statement renderings only; persistence, public URLs, PDFs, and signatures are deferred.
- Artifact generation assumes callers have already scoped and authorized the entries being rendered.
- x-journal verification detects direct database tampering after the fact; it does not prevent direct database mutation.
- Scoped verification windows will need an explicit starting previous-hash strategy before partial-chain verification becomes production-facing.
- x-journal retrieval is intentionally separate from visibility; host APIs and operator screens must compose retrieval with `JournalVisibilityGate`.
- Offset-based retrieval is acceptable for the baseline but may need cursor pagination before high-volume Cockpit views.
- x-journal Phase 8 adds a recording seam only; live x-change execution call-site wiring remains deferred until call-site characterization tests are in place.
- Execution outcome journaling can duplicate entries if hosts call the recorder repeatedly for the same execution result; idempotency strategy remains open.
- Provider callback journaling can duplicate entries when providers retry webhooks; idempotency strategy remains open.
- Provider callback records preserve provider-supplied status but must not be treated as settlement or reconciliation truth without domain processing.
- Reconciliation journaling can duplicate entries if host reconciliation jobs are retried; idempotency strategy remains open.
- Reconciliation records may contain sensitive provider/bank file details; host APIs must compose retrieval with visibility/redaction controls.
- Operator action journaling can duplicate entries if host applications retry the same command/audit hook; idempotency strategy remains open.
- Operator action records may contain sensitive operator context, reasons, IP addresses, case details, and manual review notes; host APIs must compose retrieval with visibility/redaction controls.
- Host UIs and workflow layers must not treat operator journal records as action authorization, action execution, money movement, workflow mutation, or CTA completion.
- Campaign journaling can duplicate entries if host batch planners or distribution jobs retry journal recording; idempotency strategy remains open.
- Campaign records may contain sensitive beneficiary-list counts, targeting criteria, program details, distribution schedules, and voucher batch identifiers; host APIs must compose retrieval with visibility/redaction controls.
- Host campaign layers must not treat campaign journal records as voucher issuance, execution, distribution dispatch, or campaign lifecycle mutation.
- Cockpit read models can expose sensitive canonical payloads. Host Cockpit APIs must add presentation/redaction rules before broad operator exposure.
- Phase 13 Cockpit visibility filtering happens after the retrieval window. Production Cockpit pagination may need visibility-aware cursor/windowing.
- Cockpit journal read models must not be treated as command execution, action authorization, or lifecycle truth beyond the underlying journal facts.
- x-journal append-only behavior remains model-level; direct database mutation protection is still unresolved.
- x-journal idempotency remains unresolved for execution outcomes, provider callbacks, reconciliation records, operator actions, and campaign records.
- x-journal consumers should not assume every transformed entry has `metadata.domain`; the execution transformer currently records transformer class without a domain key.
- x-journal Wave 2A is package-ready as a foundation, but host-app production wiring remains deferred to explicit package-specific characterization/integration work.
- x-journal production deferrals are documented for idempotency, database-level immutability, signatures, artifact persistence, redaction, visibility-aware pagination, secondary-sink queueing, and live host wiring.
- x-action Phase 1 is package-ready as a workflow action grammar foundation, but it has no host integration, persistence, analytics storage, routing layer, connector runtime, UI, or claim compiler decoration yet.
- x-action must remain workflow continuation infrastructure. It must not become claim execution, money movement, compliance authority, voucher mutation, or journal truth.
- x-action invalid providers are ignored by default for rendering safety; strict mode exists for fail-closed environments and requires explicit host/package choice.
- x-action capability filtering is not authorization. Host applications must still enforce domain policy at the execution endpoint or workflow command.
- x-action non-strict malformed-rule behavior is safe for rendering because it hides malformed actions, but strict validation should be used in tests/CI to catch configuration mistakes.
- x-action diagnostics are explanatory and read-only. Host applications must not treat diagnostics as authorization, execution, money movement, voucher mutation, or lifecycle truth.
- x-action diagnostics can expose internal action keys, provider classes, and rule details; host-facing presentation should be scoped/redacted before broad Cockpit or Copilot use.
- x-action in-memory recording is process-local and not durable. It is an analytics seam and test baseline, not production storage or journal truth.
- Future durable action recording must not compete with x-journal and must not affect action availability.
- x-action target routing is target interpretation only. Host applications still own endpoint authorization, middleware, execution, and side effects.
- Redirectable target resolution may expose host URLs or external URLs; host renderers/surfaces must decide which targets are safe to display.
- x-action action run identity is a correlation seam only. Host applications must not treat an action run as proof of authorization, execution, completion, persistence, or journal truth.
- Future durable action runs must define idempotency and x-journal handoff boundaries before production use.
- x-action journal handoff payloads are descriptive only. They are not durable journal entries and do not make x-action depend on x-journal.
- Future host integrations must define redaction, idempotency, and visibility before exposing action handoff payloads broadly.
- x-action host bundles are read-side composition only. Host packages must still enforce authorization, redaction, command endpoint behavior, and side effects.
- Host composition can generate action run IDs repeatedly if called repeatedly; callers must not treat composition as durable run persistence.
- x-feedback Phase 1 is package-ready as a communication grammar foundation, but it has no real channel drivers, persistence, queues, retries, templates, provider callbacks, or host integration yet.
- x-feedback must not own lifecycle truth. It can communicate state supplied by upstream packages but must not decide what happened or what should happen.
- The null feedback driver is a baseline test seam and does not prove real provider delivery.
- x-feedback event mappers must translate upstream event facts into intents; they must not infer workflow truth from voucher, claim, or settlement internals.
- Unknown feedback events fail closed until hosts register explicit mappers.
- x-feedback template resolution prepares message content only. It must not be treated as provider delivery, delivery tracking, lifecycle truth, or action execution.
- Rendered template variables may contain sensitive beneficiary, claim, provider, or action context and will need redaction before broad operator preview.
- The primary x-journal Codex instruction file is empty; the addendum and functional specifications currently carry the actionable guidance.
- Concrete settlement-envelope and stored-value gateway bindings remain unresolved.
- Existing provider readiness, wallet mutation, claim submission, and reconciliation paths are sensitive; keep characterization tests around them.
- Each package has its own Pest/Testbench environment. Run tests from the relevant package root.
- The top-level roadmap spans multiple repositories. Do not mix unrelated package changes in one commit.

## Next Recommended Workstream

Continue Wave 3 — x-feedback with the next authorized slice.

Recommended actions:

1. Request approval for the next x-feedback slice before proceeding.
2. Recommended next slice: Phase 4 — Channel Driver Selection and Delivery Planning Baseline.
3. Add channel selection policy DTOs/contracts and delivery plan DTOs.
4. Avoid real provider delivery, persistence, queues, retries, routes, and host coupling until explicitly authorized.
5. Keep x-feedback communication-only; it must not own lifecycle truth, execute actions, or mutate journal truth.

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
