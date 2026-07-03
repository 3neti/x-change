# x-change Cockpit Compass

## Current Objective

Establish the x-change Cockpit workstream as the operator shell for the Settlement Operating System without disturbing the existing Claim UI, execution runtime, journal, action, or feedback package boundaries.

Current slice: Slice 5 — Voucher Detail Foundation
Status: Complete
Last updated: 2026-07-03

## Completed

- Read the Cockpit planning documents under `/Users/rli/PhpstormProjects/x-change-sandbox/docs/todo/x-change_cockpit`.
- Inspected the current x-change package resources, routes, package scripts, frontend tests, and package docs.
- Compared Cockpit intent against the current Execution Engine, x-journal, x-action, and x-feedback baselines.
- Inspected `redeem-x` as prior art only.
- Created the required Slice 0 discovery reports:
  - `reports/000-source-discovery.md`
  - `reports/001-source-of-truth-matrix.md`
  - `reports/002-porting-map.md`
  - `reports/003-scaffold-plan.md`
- Repaired stale frontend expectations for existing `slice_selector` form-flow support before committing Slice 0.
- Completed Slice 1 Cockpit namespace and shell:
  - `resources/js/cockpit/types.ts`
  - `resources/js/cockpit/navigation.ts`
  - `resources/js/cockpit/components/CockpitBalanceHud.vue`
  - `resources/js/cockpit/components/CockpitGlobalHeader.vue`
  - `resources/js/cockpit/components/CockpitSidebar.vue`
  - `resources/js/cockpit/layouts/CockpitLayout.vue`
  - `resources/js/cockpit/pages/Dashboard.vue`
- Added Slice 1 frontend coverage under `tests/frontend/cockpit/`.
- Preserved existing Claim UI resources and tests.
- Completed Slice 2 Dashboard Foundation:
  - `resources/js/cockpit/dashboardDefaults.ts`
  - `resources/js/cockpit/components/CockpitDashboardMetricCard.vue`
  - `resources/js/cockpit/components/CockpitLiquidityHero.vue`
  - `resources/js/cockpit/components/CockpitRedemptionPipeline.vue`
  - `resources/js/cockpit/components/CockpitRiskExpiryPanel.vue`
  - `resources/js/cockpit/components/CockpitRecentActivityPanel.vue`
- Replaced the broad dashboard shell placeholders with read-only dashboard foundation widgets.
- Added Slice 2 frontend coverage for widget rendering, placeholder/empty read-model states, and no-side-effect boundaries.
- Completed Slice 3 Quick Generate Foundation:
  - `resources/js/cockpit/quickGenerateDefaults.ts`
  - `resources/js/cockpit/components/CockpitTemplateSelector.vue`
  - `resources/js/cockpit/components/CockpitRuntimeInputPanel.vue`
  - `resources/js/cockpit/components/CockpitPricingFundingSummary.vue`
  - `resources/js/cockpit/components/CockpitGenerateActionPanel.vue`
  - `resources/js/cockpit/pages/QuickGenerate.vue`
- Added Slice 3 frontend coverage for template selection, runtime input placeholders, pricing/funding summaries, disabled generate action, and no-side-effect boundaries.
- Completed Slice 4 Pay Code Explorer Foundation:
  - `resources/js/cockpit/payCodeExplorerDefaults.ts`
  - `resources/js/cockpit/components/CockpitPayCodeSearchBar.vue`
  - `resources/js/cockpit/components/CockpitPayCodeFilterBuilder.vue`
  - `resources/js/cockpit/components/CockpitPayCodeResultsTable.vue`
  - `resources/js/cockpit/pages/PayCodeExplorer.vue`
- Added Slice 4 frontend coverage for search, filters, read-only result rows, disabled row actions, active navigation, and no-side-effect boundaries.
- Completed Slice 5 Voucher Detail Foundation:
  - `resources/js/cockpit/voucherDetailDefaults.ts`
  - `resources/js/cockpit/components/CockpitVoucherOverviewPanel.vue`
  - `resources/js/cockpit/components/CockpitVoucherTimelinePanel.vue`
  - `resources/js/cockpit/components/CockpitVoucherEvidencePanel.vue`
  - `resources/js/cockpit/components/CockpitVoucherDistributionPanel.vue`
  - `resources/js/cockpit/components/CockpitVoucherAuditPanel.vue`
  - `resources/js/cockpit/pages/VoucherDetail.vue`
- Added Slice 5 frontend coverage for overview, timeline, evidence, distribution, audit, disabled operator actions, active Pay Codes navigation, and no-side-effect boundaries.

## In Progress

No implementation slice is in progress.

## Next

Recommended next slice: Slice 6 — Distribution Workspace Foundation.

Scope should remain foundation-only:

- digital distribution placeholder
- print template placeholder
- share/QR placeholder
- operational analytics placeholder
- no campaign behavior, distribution dispatch, feedback delivery, voucher mutation, execution, journal writes, or provider calls
- Vitest coverage for rendering and no side effects
- preserve all existing Claim UI tests

## Risks

- The current x-change dashboard and layout are useful but not yet a distinct Cockpit namespace.
- Existing `XChangeLayout.vue` still contains starter-kit footer links and a narrow navigation model.
- Cockpit read models can expose sensitive financial, journal, action, feedback, and provider data if redaction is not designed before broad operator exposure.
- x-journal Cockpit read models are available, but production redaction, idempotency, signed verification, and visibility-aware pagination remain deferred.
- x-action host bundles are side-effect-free and correlation-oriented; Cockpit must not treat them as authorization, execution, persistence, or lifecycle truth.
- x-feedback UI component view models are presentation facts only; Cockpit owns pages, navigation, authorization, and operator workflows.
- Voucher execution results have `execution_id`, but execution persistence and host wiring remain deferred.
- Concrete settlement-envelope and stored-value gateway bindings remain unresolved.

## Decisions

- Productized Cockpit work belongs inside `/Users/rli/PhpstormProjects/x-change-sandbox/packages/x-change`.
- Do not create a new `cockpit` package for this wave.
- Do not modify `redeem-x`; use it only as historical/prior-art reference.
- Preserve the current Claim Journey, Paynamics OTP approval UX, rider message UX, splash UX, URL redirect UX, and frontend-tested claim flow.
- Cockpit consumes existing system truth. It must not invent execution, journal, action, or feedback behavior.
- Cockpit should start desktop-first, with PWA/mobile adaptations later.
- Cockpit UI tests should live under `tests/frontend/cockpit/`.
- Slice 1 uses the explicit `resources/js/cockpit/` namespace.
- Slice 1 does not add Laravel routes/controllers. It creates importable frontend shell primitives only.
- Slice 2 keeps dashboard data as supplied read-model placeholders only. It does not fetch wallet/provider data, write journal entries, resolve actions, send feedback, call providers, or move money.
- Slice 3 keeps Quick Generate as a disabled/read-only issuance shell. It does not submit forms, generate vouchers, calculate pricing, reserve funds, call providers, write journal entries, send feedback, or move money.
- Slice 4 keeps Pay Code Explorer as a read-only exploration shell. It does not query host APIs, mutate vouchers, execute drivers, approve claims, write journal entries, send feedback, call providers, or move money.
- Slice 5 keeps Voucher Detail as a read-only single-voucher shell. It does not mutate vouchers, execute drivers, write journal entries, send feedback, call providers, or move money.

## Open Questions

- Which host API/read-model contracts should expose execution, journal, action, and feedback facts to Cockpit without leaking sensitive payloads.
- How operator authorization and redaction should be represented before real Cockpit pages expose journal, action diagnostics, feedback delivery records, or provider payloads.
- Whether existing `/x/dashboard` should be promoted into Cockpit or left as a compatibility route while Cockpit grows under a new route namespace.

## Test Status

- Slice 0 changed documentation only.
- Detected package commands:
  - frontend: `npm run test:frontend`
  - backend: `composer test`
- Ran `npm run test:frontend` from `packages/x-change`.
- Initial result: failed with two stale form-flow expectation mismatches unrelated to Cockpit Slice 0.
  - `tests/frontend/formFlow.test.ts` expected supported field types without `slice_selector`, but `SUPPORTED_FORM_FLOW_FIELD_TYPES` already included `slice_selector`.
  - `tests/frontend/formFlowRendererComponents.test.ts` expected renderer components without `SliceSelectorFieldRenderer`, but `FORM_FLOW_RENDERER_COMPONENTS` already included `SliceSelectorFieldRenderer`.
- Updated those expectations to match the existing runtime.
- Final result: `59 passed, 381 tests`.
- No Cockpit production code or frontend code was changed in Slice 0.
- Slice 1 focused frontend result: `3 passed, 7 tests`.
- Slice 1 full frontend result: `62 passed, 388 tests`.
- Slice 2 focused frontend result: `4 passed, 12 tests`.
- Slice 2 full frontend result: `63 passed, 393 tests`.
- Slice 3 focused frontend result: `5 passed, 17 tests`.
- Slice 3 full frontend result: `64 passed, 398 tests`.
- Slice 4 focused frontend result: `6 passed, 21 tests`.
- Slice 4 full frontend result: `65 passed, 402 tests`.
- Slice 5 focused frontend result: `7 passed, 26 tests`.
- Slice 5 full frontend result: `66 passed, 407 tests`.
