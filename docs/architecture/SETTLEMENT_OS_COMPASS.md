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

Current wave: Host Integration Readiness
Current status: Wave 5 — x-campaign complete through Phase 15; x-change Host Integration Slice 1 read-only Campaign Cockpit adoption complete through Slice 1I; Cockpit navigation hardening complete
Last updated: 2026-07-09

| Wave | Workstream | Role | Status | Compass |
|---|---|---|---|---|
| 1 | Execution Engine | Kernel / runtime | Completed through Slice 9 scaffold | [execution-engine/EXECUTION_ENGINE_COMPASS.md](execution-engine/EXECUTION_ENGINE_COMPASS.md) |
| 2A | x-journal | System log / audit trail | Complete through Phase 15 | `/Users/rli/PhpstormProjects/packages/x-journal/docs/architecture/x-journal/X_JOURNAL_COMPASS.md` |
| 2B | x-action | Workflow continuation / CTA layer | Phase 7 complete | `/Users/rli/PhpstormProjects/packages/x-action/docs/x-action-compass.md` |
| 3 | x-feedback | Notification / communication layer | Phase 23 complete | `/Users/rli/PhpstormProjects/packages/x-feedback/docs/architecture/x-feedback/X_FEEDBACK_COMPASS.md` |
| 4 | x-change Cockpit | Operator shell | Slice 27 + navigation hardening complete | [../ui-cockpit/COMPASS.md](../ui-cockpit/COMPASS.md) |
| 5 | x-campaign | Program / bulk distribution layer | Complete through Phase 15 host adoption / parity report | `/Users/rli/PhpstormProjects/packages/x-campaign/docs/X_CAMPAIGN_COMPASS.md` |

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
- Completed x-feedback Phase 4 Channel Driver Selection and Delivery Planning Baseline:
  - `FeedbackChannelSelectionPolicyData`
  - `FeedbackDeliveryPlanData`
  - `FeedbackDeliveryPlanItemData`
  - `FeedbackChannelSelectorContract`
  - `FeedbackDeliveryPlannerContract`
  - `FeedbackChannelSelector`
  - `FeedbackDeliveryPlanner`
  - package-consumer selector and planner bindings
  - allowed, disabled, enabled, required, preferred, and fallback channel policy behavior
  - dry delivery plan generation per recipient and selected channel
  - proof that planning does not resolve or invoke channel drivers
  - no real provider delivery, persistence, queues, retries, routes, or host coupling introduced
  - green focused Phase 4 suite: `7 passed, 37 assertions`
  - green x-feedback package suite: `29 passed, 136 assertions`
- Completed x-feedback Phase 5 Delivery Dispatch Preparation and Receipt Handoff Baseline:
  - `FeedbackDispatchPreparationData`
  - `FeedbackProviderReceiptData`
  - `FeedbackDispatchPreparerContract`
  - `FeedbackReceiptHandoffMapperContract`
  - `FeedbackDispatchPreparer`
  - `FeedbackReceiptHandoffMapper`
  - package-consumer dispatch preparation and receipt handoff bindings
  - template resolution plus delivery planning composition
  - delivery-result to provider-receipt handoff mapping
  - proof that dispatch preparation does not invoke provider delivery
  - no real provider delivery, persistence, queues, retries, routes, or host coupling introduced
  - green focused Phase 5 suite: `6 passed, 44 assertions`
  - green x-feedback package suite: `35 passed, 180 assertions`
- Completed x-feedback Phase 6 Delivery Attempt Runtime Baseline:
  - `FeedbackDeliveryAttemptData`
  - `FeedbackDeliveryAttemptRuntimeContract`
  - `FeedbackDeliveryAttemptRuntime`
  - package-consumer delivery attempt runtime binding
  - prepared delivery plan execution through registered channel drivers
  - default null-driver delivery attempt coverage
  - delivery-result to provider-receipt handoff generation
  - unknown planned channel fail-closed behavior before later side effects
  - no durable delivery records, queues, retries, routes, provider SDKs, or host coupling introduced
  - green focused Phase 6 suite: `5 passed, 28 assertions`
  - green x-feedback package suite: `40 passed, 208 assertions`
- Completed x-feedback Phase 7 Delivery Recording Strategy Baseline:
  - `FeedbackDeliveryRecordData`
  - `FeedbackDeliveryAttemptRecorderContract`
  - `InMemoryFeedbackDeliveryAttemptRecorder`
  - package-consumer non-persistent recorder binding
  - delivery attempt recording from receipt handoff payloads
  - in-memory lookup by correlation ID and intent key
  - recorder reset for test and short-lived baselines
  - no database persistence, x-journal dependency, queues, routes, jobs, provider SDKs, or host coupling introduced
  - green focused Phase 7 suite: `6 passed, 31 assertions`
  - green x-feedback package suite: `46 passed, 239 assertions`
- Completed x-feedback Phase 8 Journal Receipt Handoff Baseline:
  - `FeedbackJournalReceiptData`
  - `FeedbackJournalReceiptMapperContract`
  - `FeedbackJournalReceiptMapper`
  - package-consumer journal receipt mapper binding
  - delivery record to x-journal-ready feedback receipt fact mapping
  - provider receipt to x-journal-ready feedback receipt fact mapping
  - batch delivery record handoff mapping
  - no x-journal dependency, database persistence, queues, routes, jobs, provider SDKs, or host coupling introduced
  - green focused Phase 8 suite: `6 passed, 41 assertions`
  - green x-feedback package suite: `52 passed, 280 assertions`
- Completed x-feedback Phase 9 Provider Callback Feedback Mapping Baseline:
  - `FeedbackProviderCallbackData`
  - `FeedbackProviderCallbackMapperContract`
  - `FeedbackProviderCallbackMapper`
  - package-consumer provider callback mapper binding
  - provider callback to provider receipt mapping
  - provider callback to feedback event mapping
  - provider callback status normalization
  - no webhook routes, provider SDKs, persistence, queues, retries, or host coupling introduced
  - green focused Phase 9 suite: `6 passed, 44 assertions`
  - green x-feedback package suite: `58 passed, 324 assertions`
- Completed x-feedback Phase 10 Retry and Freshness Policy Baseline:
  - `FeedbackRetryPolicyData`
  - `FeedbackRetryDecisionData`
  - `FeedbackRetryFreshnessEvaluatorContract`
  - `FeedbackRetryFreshnessEvaluator`
  - package-consumer retry/freshness evaluator binding
  - retryable/final/expired/exhausted/pending delivery classification
  - next retry timestamp calculation from backoff policy
  - no queued retries, persistence, provider SDKs, routes, jobs, or host coupling introduced
  - green focused Phase 10 suite: `7 passed, 33 assertions`
  - green x-feedback package suite: `65 passed, 357 assertions`
- Completed x-feedback Phase 11 Channel Driver Architecture Backfill:
  - reconciled implementation with the original x-feedback todo Phase 2 driver architecture expectations
  - `FeedbackChannelHealthData`
  - expanded `FeedbackChannelDriverContract` to `send`, `supports`, and `health`
  - safe baseline drivers for `null`, `log`, `in_app`, `mail`, and `webhook`
  - baseline driver config registration
  - known-driver registry resolution coverage
  - unknown-driver fail-closed coverage
  - driver health and supports/capability coverage
  - package-local handoff facts without provider side effects
  - no provider SDKs, outbound webhook calls, SMTP assumptions, queues, routes, persistence, or host coupling introduced
  - green focused Phase 11 suite: `18 passed, 86 assertions`
  - green x-feedback package suite: `83 passed, 443 assertions`
- Completed x-feedback Phase 12 Transport Driver Baseline:
  - `EmailFeedbackChannelDriver`
  - `SmsFeedbackChannelDriver`
  - `FeedbackEmailMessage`
  - `FeedbackWebhookMessageData`
  - `FeedbackWebhookSendResultData`
  - `FeedbackWebhookSenderContract`
  - `SpatieFeedbackWebhookSender`
  - explicit `email`, `sms`, and `webhook` transport channels
  - `lbhurtado/sms:^2.4.2`
  - `spatie/laravel-webhook-server:^3.10`
  - webhook remains an x-feedback channel while Spatie is isolated behind the sender seam
  - green focused Phase 12 suite: `9 passed, 50 assertions`
  - green x-feedback package suite: `91 passed, 485 assertions`
- Completed x-feedback Phase 13 Preference and Suppression Policy Baseline:
  - `FeedbackNotificationPreferenceData`
  - `FeedbackQuietHoursData`
  - `FeedbackSuppressionPolicyData`
  - `FeedbackSuppressionDecisionData`
  - `FeedbackSuppressionEvaluatorContract`
  - `FeedbackSuppressionEvaluator`
  - disabled preference, opt-out, disabled channel, quiet-hours, expired-intent, stale-intent, and required-channel advisory decisions
  - no persistence, routes, host package dependency, workflow execution, lifecycle truth ownership, or journal truth mutation introduced
  - green focused Phase 13 suite: `8 passed, 40 assertions`
  - green x-feedback package suite: `99 passed, 525 assertions`
- Completed x-feedback Phase 14 Notification Route Baseline:
  - `FeedbackNotificationRouteData`
  - `FeedbackNotificationRouteResolverContract`
  - `FeedbackNotificationRouteResolver`
  - config seam at `x-feedback.notification_routes`
  - recipient route data normalization
  - config-backed route resolution without a database route book
  - deterministic route ordering by primary flag, verification, priority, and address
  - delivery planning composition with normalized route metadata
  - legacy recipient field fallback while hosts migrate to `NotificationRoute`
  - no database route book, contact package dependency, package routes, provider delivery changes, host package dependency, workflow execution, lifecycle truth ownership, or journal truth mutation introduced
  - green focused Phase 14 suite: `8 passed, 36 assertions`
  - green x-feedback package suite: `107 passed, 561 assertions`
- Completed x-feedback Phase 15 Feature Profile and Template Policy Baseline:
  - `FeedbackFeatureProfileData`
  - `FeedbackTemplateResolutionPolicyData`
  - `FeedbackTemplatePolicyResolverContract`
  - `FeedbackTemplatePolicyResolver`
  - config seam at `x-feedback.template_policy`
  - feature-profile variables and fallback candidates
  - explicit profile and channel fallback policy
  - fail-closed mismatched profile/channel template selection
  - resolved message metadata for selected feature profile, template profile, and template channel
  - no template persistence, template authoring UI, approval/version workflow, host package dependency, workflow execution, lifecycle truth ownership, or journal truth mutation introduced
  - green focused Phase 15 suite: `8 passed, 31 assertions`
  - green x-feedback package suite: `115 passed, 592 assertions`
- Completed x-feedback Phase 16 Action and Artifact Rendering Policy Baseline:
  - `FeedbackActionRenderingPolicyData`
  - `FeedbackArtifactRenderingPolicyData`
  - `FeedbackRenderedActionData`
  - `FeedbackRenderedArtifactData`
  - `FeedbackRenderingDecisionData`
  - `FeedbackActionArtifactRendererContract`
  - `FeedbackActionArtifactRenderer`
  - config seam at `x-feedback.rendering`
  - package-consumer binding for supplied action/artifact rendering
  - per-channel action presentation defaults for SMS links, webhook payloads, metadata-only channels, and rich-channel buttons
  - per-channel artifact rendering strategies: `preview`, `link`, `hide`, and `attach`
  - attachments disabled by default unless an explicit rendering policy enables them
  - no x-action dependency, artifact storage, file generation, workflow execution, lifecycle truth ownership, host package dependency, or journal truth mutation introduced
  - green focused Phase 16 suite: `8 passed, 51 assertions`
  - green x-feedback package suite: `123 passed, 643 assertions`
- Completed x-feedback Phase 17 Durable Delivery Records Baseline:
  - `feedback_delivery_records` migration
  - `FeedbackDeliveryRecord` model
  - extended `FeedbackDeliveryRecordData`
  - `DatabaseFeedbackDeliveryAttemptRecorder`
  - package migration loading through `XFeedbackServiceProvider`
  - package Testbench database isolation with SQLite + `RefreshDatabase`
  - durable provider receipt preservation as communication delivery state
  - provider response, provider message ID, provider status, correlation, causation, expiry, max-attempt, and attempt-count preservation
  - idempotent repeated-attempt update semantics by receipt idempotency key
  - no x-journal dependency, routes, controllers, queues, Cockpit pages, campaign orchestration, lifecycle mutation, or audit truth ownership introduced
  - green focused Phase 17 suite: `8 passed, 45 assertions`
  - green x-feedback package suite: `131 passed, 688 assertions`
- Completed x-feedback Phase 18 In-App Notification Baseline:
  - in-app notification state columns on `feedback_delivery_records`
  - `FeedbackInAppNotificationData`
  - `FeedbackInAppNotificationStateManagerContract`
  - `FeedbackInAppNotificationStateManager`
  - package-consumer binding for in-app notification state transitions
  - default `unread` state for `in_app` delivery records
  - read, unread, archived, and dismissed states
  - mark-read, mark-unread, archive, dismiss, bulk mark-read, and recipient notification listing seams
  - archived and dismissed records hidden from recipient listings by default
  - no Cockpit pages, frontend components, HTTP routes/controllers, workflow mutation, lifecycle truth ownership, host package dependency, or journal truth mutation introduced
  - green focused Phase 18 suite: `9 passed, 38 assertions`
  - green x-feedback package suite: `140 passed, 726 assertions`

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
- x-feedback delivery planning prepares intended recipient/channel combinations only. It must not be treated as provider delivery, queued delivery, durable delivery tracking, lifecycle truth, or action execution.
- Delivery planning intentionally does not resolve channel drivers; provider capability validation remains deferred until real dispatch/provider phases.
- x-feedback dispatch preparation composes message rendering and delivery planning only. It must not be treated as provider delivery, queued delivery, durable delivery tracking, lifecycle truth, or action execution.
- x-feedback provider receipt handoff payloads are portable facts only. They are not durable journal entries or delivery-record storage.
- x-feedback delivery attempt runtime can invoke registered channel drivers, but it is not durable delivery tracking, queueing, retry policy, lifecycle truth, or journal truth.
- Unknown planned delivery channels fail closed before later planned items are executed.
- x-feedback Phase 7 delivery records are process-local and non-canonical. They must not be treated as durable audit history or a replacement for x-journal.
- x-feedback Phase 8 journal receipt handoffs are portable facts only. They are not durable journal entries and do not make x-feedback depend on x-journal.
- x-feedback Phase 9 provider callback mapping normalizes facts only. It must not be treated as webhook handling, provider verification, reconciliation, settlement, or lifecycle truth.
- x-feedback Phase 10 retry/freshness decisions are advisory only. They do not queue retries, persist retry state, call providers, or mutate delivery records.
- x-feedback Phase 11 baseline drivers are safe handoff drivers only. `mail` and `webhook` do not prove SMTP, HTTP, provider acceptance, or delivery confirmation.
- x-feedback Phase 12 transport dispatch proves attempted dispatch only. It must not be treated as beneficiary receipt, provider confirmation, settlement truth, workflow completion, or journal truth.
- x-feedback Phase 12 wraps Spatie Webhook Server behind `FeedbackWebhookSenderContract`; Spatie must not leak into higher-level x-feedback APIs.
- x-feedback remaining work is now prioritized against `/Users/rli/PhpstormProjects/x-change-sandbox/docs/todo/x-feedback/x-feedback_functional_specifications.md`.
- x-feedback functional specification gaps after Phase 12 include notification preferences, notification routes, feature-profile hardening, action/artifact rendering policy, durable delivery records, in-app notification state, operational monitoring, delivery console APIs, credential resolution, journal event handoff strengthening, and reusable UI components.
- x-feedback Phase 13 closes the initial notification preference/suppression baseline; remaining functional specification gaps now begin with notification routes.
- x-feedback Phase 14 closes the initial notification route baseline; remaining functional specification gaps now begin with feature-profile/template policy hardening.
- x-feedback Phase 15 closes the initial feature-profile/template policy baseline; remaining functional specification gaps now begin with action and artifact rendering policy.
- x-feedback Phase 16 closes the initial action/artifact rendering policy baseline; remaining functional specification gaps now begin with durable delivery records.
- x-feedback Phase 17 closes the initial durable delivery records baseline; remaining functional specification gaps now begin with in-app notification state.
- x-feedback Phase 18 closes the initial in-app notification state baseline; remaining functional specification gaps now begin with operational monitoring.
- x-feedback Phase 19 closes the initial operational monitoring baseline; remaining functional specification gaps now begin with delivery console APIs.
- x-feedback operational monitoring is read-only visibility over driver health, delivery failures, and retry backlog. It must not queue retries, call delivery providers, mutate lifecycle state, own Cockpit pages/widgets, or become journal truth.
- x-feedback Phase 20 closes the initial delivery console API baseline; remaining functional specification gaps now begin with credential resolution.
- x-feedback delivery console APIs expose communication delivery status, delivery history, redacted provider responses, and retry request handoff facts only. They must not own Cockpit pages, HTTP route exposure, retry execution, workflow authorization, lifecycle truth, or journal truth.
- x-feedback Phase 21 closes the initial credential resolution baseline; remaining functional specification gaps now begin with journal event emission/handoff integration.
- x-feedback credential resolution is a typed lookup seam over owner/provider/channel context. It must not become credential storage, tenant ownership, encryption-at-rest policy, credential rotation, provider execution, rendered-message content, delivery-record content, provider-response content, or journal payload content.
- x-feedback Phase 22 closes the initial journal event emission/handoff baseline; remaining functional specification gaps now begin with reusable UI component baseline.
- x-feedback journal event handoffs expose `feedback.created`, `feedback.sent`, `feedback.failed`, and `feedback.expired` communication facts only. They must not persist to x-journal, dispatch Laravel events/listeners, queue jobs, own audit history, own lifecycle truth, or mutate delivery records.
- x-feedback Phase 23 closes the initial reusable UI component baseline; Wave 3 x-feedback baseline coverage is complete through the functional-specification slices currently planned.
- x-feedback UI component view models expose communication presentation facts only. They must not own Cockpit pages, navigation, authorization, Vue/Inertia/Blade rendering, frontend assets, workflow execution, lifecycle truth, or host package behavior.
- x-feedback in-app notification state is recipient presentation state only. It must not be treated as delivery truth, workflow truth, lifecycle truth, or journal truth.
- x-feedback rendered actions are presentation of upstream CTA/action payloads only. They must not be treated as x-action capability resolution, workflow availability, authorization, or execution.
- x-feedback rendered artifacts are presentation of upstream artifact references only. They must not become artifact storage, artifact lifecycle, artifact permissioning, or file generation.
- x-feedback artifact references and action targets can expose sensitive URLs/context. Future API, operator, journal, and UI surfaces must apply redaction and authorization.
- x-feedback durable delivery records are communication delivery state only. x-journal remains audit/system truth.
- x-feedback delivery-record idempotency currently uses explicit receipt keys, provider message IDs, or deterministic fallback keys. Provider callback adapters should supply stronger provider-specific idempotency keys when available.
- x-feedback delivery console APIs and UI components must expose communication facts only. Cockpit owns pages and broader operator workflows.
- x-feedback credential resolution introduces secret-handling risk. Credentials must not leak into rendered messages, logs, delivery records, provider responses, or journal handoff payloads.
- x-feedback suppression decisions are advisory pre-delivery facts. They must not be treated as workflow authorization, claim status, settlement state, or audit truth.
- x-feedback notification routes may expose sensitive addresses and verification metadata. Future operator, journal, and UI surfaces must apply redaction.
- x-feedback feature profiles are institutional presentation context, not languages, lifecycle state, workflow meaning, authorization, or campaign segmentation.
- x-feedback template fallback policies can leak wrong institutional wording if configured too broadly; hosts must keep fallback chains explicit and narrow.
- x-change Cockpit Slice 0 is complete as a discovery/documentation slice under `packages/x-change/docs/ui-cockpit`.
- Cockpit starts from the current x-change resources and tests. `redeem-x` is prior art only and must not override the current x-change Claim UX.
- Cockpit must preserve the Claim Journey, Paynamics OTP approval UX, rider message UX, splash/redirect UX, and existing frontend-tested claim flow.
- Cockpit can later consume Execution Engine, x-journal, x-action, and x-feedback read-side seams, but it must not invent execution, journal, action, feedback, settlement, or campaign behavior.
- x-change Cockpit Slice 1 establishes the frontend Cockpit namespace and shell primitives under `resources/js/cockpit` without adding routes, controllers, backend integration, or domain side effects.
- Cockpit Slice 1 includes a global header, sidebar navigation, balance HUD placeholder, layout, dashboard shell, navigation descriptors, and focused Vitest coverage.
- x-change Cockpit Slice 2 adds read-only dashboard foundation widgets for liquidity, balance cards, redemption pipeline, risk/expiry, and recent activity without backend integration or domain side effects.
- x-change Cockpit Slice 3 adds a disabled/read-only Quick Generate foundation with template selector, runtime input placeholders, pricing/funding summary placeholders, and generate-action placeholder without issuance or money movement.
- x-change Cockpit Slice 4 adds a read-only Pay Code Explorer foundation with search, filter, results-table, and disabled row-action placeholders without host queries or domain mutations.
- x-change Cockpit Slice 5 adds a read-only Voucher Detail foundation with overview, timeline, evidence, distribution, audit, and disabled operator-action placeholders without voucher mutation, execution, journal writes, feedback delivery, provider calls, or money movement.
- x-change Cockpit Slice 6 adds a read-only Distribution Workspace foundation with digital distribution planning, print template, share/QR, and operational analytics placeholders without campaign behavior, distribution dispatch, feedback delivery, voucher mutation, execution, journal writes, provider calls, or money movement.
- x-change Cockpit Slice 7 exposes the existing Cockpit pages through authenticated GET-only Inertia routes under `/x/cockpit` without adding separate JSON read APIs, mutation routes, host read-model calls, execution, journal writes, feedback delivery, provider calls, campaign behavior, or money movement.
- x-change Cockpit Slice 8 adds an operator authorization/redaction baseline with explicit read-only `can` props, redaction metadata, a default redactor contract, and route context propagation without exposing journal, action, feedback, provider, voucher, or execution payloads.
- x-change Cockpit Slice 9 adds host-facing read-model DTO and provider contract baselines for voucher, execution, journal, action, and feedback views. The default provider is null/not-wired and does not call voucher, x-journal, x-action, x-feedback, providers, wallets, persistence, or external services.
- x-change Cockpit Slice 10 composes the null/not-wired read-model bundle into authenticated Inertia page props without adding live package adapters, JSON APIs, mutation endpoints, execution, journal writes, feedback delivery, provider calls, wallet access, raw payload exposure, or money movement.
- x-change Cockpit Slice 20 adds read-only Quick Generate pricing gate facts and a visible pricing gate panel without calculating prices, exposing pricing breakdowns, selecting funding sources, reserving funds, calling providers, adding mutation routes, or moving money.
- x-change Cockpit Slice 21 adds read-only Quick Generate funding gate facts and a visible funding gate panel without resolving wallets, reading balances, evaluating sufficient funds, reserving funds, debiting balances, calling providers, adding mutation routes, or moving money.
- x-change Cockpit Slice 22 adds read-only Quick Generate idempotency gate facts and a visible idempotency gate panel without persisting keys, hashing payloads, reading replay records, evaluating conflicts, reading TTL policy, adding mutation routes, or enabling generation.
- x-change Cockpit Slice 23 adds read-only Quick Generate validation/redaction gate facts and a visible validation/redaction gate panel without validating requests, persisting payloads, exposing submitted PII, building sanitized previews, returning validation errors, adding mutation routes, or enabling generation.
- x-change Cockpit Slice 24 adds a read-only Quick Generate mutation handoff boundary plan and visible handoff panel without registering mutation routes, calling `GeneratePayCode`, calling `GeneratePayCodeController`, submitting payloads, generating vouchers, calling providers, accessing wallets, writing journal entries, running actions, sending feedback, or moving money.
- x-change Cockpit Slice 25 adds a read-only Quick Generate mutation preconditions review and visible review panel without approving mutation wiring, registering mutation routes, validating requests, issuing vouchers, calling providers, accessing wallets, writing journal entries, running actions, sending feedback, or moving money.
- x-change Cockpit Slice 26 adds a read-only Quick Generate mutation authorization decision point and visible decision panel. The decision is `not_authorized`; it does not approve mutation wiring, register mutation routes, validate requests, persist payloads, issue vouchers, call providers, access wallets, write journal entries, run actions, send feedback, create campaigns, or move money.
- x-change Cockpit Slice 27 adds optional read-only cross-package read-model adapters for x-journal evidence summaries, x-action safe CTA summaries, and x-feedback communication delivery summaries. The adapters are optional service-ID seams, not hard Composer dependencies, and degrade safely when unavailable or throwing. They do not write journal entries, execute actions, send feedback, retry deliveries, call providers, mutate vouchers, access wallets, or move money.
- x-change Cockpit navigation hardening adds route-aware sidebar availability. Implemented routes remain active links; planned IA entries for Funding, Templates, Contacts, Operations, Reports, Approvals, and Administration render as disabled “Coming soon” rows until explicit read-only routes exist. This prevents dead navigation without adding placeholder routes, mutation behavior, provider calls, journal writes, feedback delivery, campaign behavior, wallet access, or money movement.
- Wave 5 — x-campaign complete through Phase 15:
  - independent package at `/Users/rli/PhpstormProjects/packages/x-campaign`
  - package identity: `3neti/x-campaign`
  - namespace: `LBHurtado\XCampaign`
  - core campaign planning, audience planning, recipient import planning, execution planning, handoff planning, analytics/read-models, operational readiness, public API descriptors, and host adoption seams are scaffolded
  - `x-campaign` parity report exists at `/Users/rli/PhpstormProjects/packages/x-campaign/docs/PARITY_REPORT.md`
  - host adoption compass exists at `/Users/rli/PhpstormProjects/packages/x-campaign/docs/X_CAMPAIGN_COMPASS.md`
  - final known x-campaign package suite: `429 passed, 4434 assertions`
  - x-campaign remains non-mutating for host adoption: it does not own host routes, controllers, requests, resources, middleware, policies, Pay Code generation semantics, provider delivery, journal storage, wallet mutation, or money movement
- Settlement OS integration readiness report added:
  - [INTEGRATION_READINESS_REPORT.md](INTEGRATION_READINESS_REPORT.md)
- x-change Host Integration Slice 1 — Read-only Campaign Cockpit Adoption:
  - Completed through Host Integration Slice 1I
  - Dashboard campaign adoption panel is safe for read-only operator use
  - Pay Code Explorer campaign navigation context is safe for read-only operator orientation
  - x-change now owns Composer dependency wiring for `3neti/x-journal`, `3neti/x-action`, `3neti/x-feedback`, and `3neti/x-campaign`
  - host applications should remain dumb and should not duplicate Cockpit integration wiring
  - package-local x-change tests now exercise real read-only adapters instead of only fake/fallback unavailable models
  - No dedicated campaign workspace route was added
  - Mutation route scaffolding remains unauthorized
  - No campaign mutation endpoints, Pay Code generation through campaign, delivery dispatch, journal writes, feedback sends/retries, wallet reads/writes, provider calls, campaign execution, or money movement were added
- x-change Host Integration Slice 2A — Journal Cockpit Hydration:
  - Voucher Detail renders available x-journal read-model evidence summaries in the existing audit panel.
  - Journal presentation remains summary-only and payload-policy aware.
  - Missing/unavailable journal read models still degrade to the existing unavailable audit row.
  - No journal writes, raw payload exposure, provider calls, action execution, feedback delivery, voucher mutation, wallet access, or money movement were added.
- x-change Host Integration Slice 2B — Action Cockpit Hydration:
  - Voucher Detail maps available x-action read-model CTA summaries into disabled operator action controls.
  - Disabled reasons are visible and include the action redaction policy.
  - Static fallback actions remain when x-action read models are unavailable or empty.
  - No action execution, workflow authorization, raw diagnostics exposure, target URL exposure, journal writes, feedback delivery, provider calls, voucher mutation, wallet access, or money movement were added.
- x-change Host Integration Slice 2C — Feedback Cockpit Hydration:
  - Voucher Detail maps available x-feedback delivery summaries into read-only distribution rows.
  - Delivery status and channel render as communication facts.
  - Static fallback channel rows remain when x-feedback read models are unavailable or empty.
  - No feedback delivery, retry execution, recipient address exposure, provider payload exposure, raw payload exposure, journal writes, action execution, provider calls, voucher mutation, wallet access, or money movement were added.
- x-change Host Integration Slice 2D — Dashboard Integration Summary:
  - Dashboard renders read-only Journal / Action / Feedback integration summary cards from the existing `read_model` bundle.
  - Cards show status, count, and payload policy only.
  - No new routes, frontend package queries, journal writes, action execution, feedback delivery, retry execution, provider calls, raw payload exposure, voucher mutation, wallet access, or money movement were added.
- x-change Host Integration Slice 2E — Voucher Detail Integration Summary:
  - Voucher Detail renders read-only Journal / Action / Feedback integration summary cards from the existing `read_model` bundle.
  - Cards show status, count, and payload policy only.
  - No new routes, journal writes, action execution, feedback delivery, retry execution, provider calls, raw payload exposure, voucher mutation, wallet access, or money movement were added.
- x-change Host Integration Slice 2F — Pay Code Explorer Integration Summary:
  - Pay Code Explorer renders page-level Journal / Action / Feedback integration badges from the existing `read_model` bundle.
  - Badges show status and payload policy only.
  - Per-row integration facts remain deferred because they are not part of the list read-model contract.
  - No per-row integration payloads, query APIs, list-read scope expansion, journal writes, action execution, feedback delivery, retry execution, provider calls, raw payload exposure, voucher mutation, wallet access, or money movement were added.
- The primary x-journal Codex instruction file is empty; the addendum and functional specifications currently carry the actionable guidance.
- Concrete settlement-envelope and stored-value gateway bindings remain unresolved.
- Existing provider readiness, wallet mutation, claim submission, and reconciliation paths are sensitive; keep characterization tests around them.
- Each package has its own Pest/Testbench environment. Run tests from the relevant package root.
- The top-level roadmap spans multiple repositories. Do not mix unrelated package changes in one commit.

## Next Recommended Workstream

The first read-only host adoption branch is closed:

```text
x-change Host Integration Slice 1 — Read-only Campaign Cockpit Adoption
```

Completed through Host Integration Slice 1I.

Recommended next branch:

```text
x-change Host Integration Slice 2 — Journal/action/feedback read-model hydration into Cockpit surfaces
```

Recommended actions:

1. Read [INTEGRATION_READINESS_REPORT.md](INTEGRATION_READINESS_REPORT.md).
2. Read the Cockpit compass before implementation: `packages/x-change/docs/ui-cockpit/COMPASS.md`.
3. Read the x-campaign parity report before implementation: `/Users/rli/PhpstormProjects/packages/x-campaign/docs/PARITY_REPORT.md`.
4. Keep package access adapter-driven inside x-change; do not duplicate integration wiring in the host app.
5. Mutation route scaffolding remains unauthorized.
6. Do not add campaign mutation endpoints, request validation execution, payload persistence, Pay Code generation, delivery dispatch, execution, journal writes, action execution, feedback delivery, provider calls, campaign state mutation, money movement, raw payload exposure, or wallet access unless explicitly approved.
7. Keep Claim UI protected and keep all productized Cockpit work inside `packages/x-change`.

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

- What is the production correlation mapping from voucher `execution_id` to journal correlation/causation IDs once write-side journaling is authorized?
- Should future execution results be journaled by x-change adapters, voucher event listeners, or a dedicated integration package once write-side journaling is authorized?
- Which visibility-aware pagination strategy should production Cockpit use for x-journal retrieval when redaction/visibility filtering removes records after the retrieval window?
- Which host API/read-model contracts should expose execution, journal, action, and feedback facts to Cockpit without leaking sensitive payloads?
- How should static read-only Cockpit permissions evolve into real operator roles, policies, tenant scoping, and permission checks?
- Should provider callback, reconciliation, and campaign events share the same journal entry schema in production, or use specialized event families mapped into a shared Cockpit read model?
- How should program-level campaign events later connect to journal entries without making x-campaign depend on journal internals?
- When mutation work becomes authorized, which Cockpit actions should remain presentation-only and which should hand off to existing x-change action/service/API paths?

## Resolved Compass Questions

- x-journal exists as an independent package at `/Users/rli/PhpstormProjects/packages/x-journal` and is complete through Phase 15 baseline.
- x-journal uses `spatie/laravel-data` for DTOs.
- x-journal is wired into x-change as a package-owned optional read-only dependency, not as host-app duplicate wiring.
- x-action, x-feedback, and x-campaign are also wired into x-change as package-owned optional read-only dependencies.
- Host apps should remain dumb and should not duplicate Cockpit integration wiring.
- The empty `x-journal_codex_instructions.md` is historical planning context only; the current package compass and parity report carry current implementation guidance.

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
