# x-change Cockpit Compass

## Current Objective

Establish the x-change Cockpit workstream as the operator shell for the Settlement Operating System without disturbing the existing Claim UI, execution runtime, journal, action, or feedback package boundaries.

Current slice: Slice 8 — Operator Authorization and Redaction Baseline
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
- Completed Slice 6 Distribution Workspace Foundation:
  - `resources/js/cockpit/distributionWorkspaceDefaults.ts`
  - `resources/js/cockpit/components/CockpitDigitalDistributionPanel.vue`
  - `resources/js/cockpit/components/CockpitPrintTemplatePanel.vue`
  - `resources/js/cockpit/components/CockpitShareQrPanel.vue`
  - `resources/js/cockpit/components/CockpitDistributionAnalyticsPanel.vue`
  - `resources/js/cockpit/pages/DistributionWorkspace.vue`
- Added Slice 6 frontend coverage for distribution channel planning, print templates, share/QR assets, operational analytics, disabled actions, active Pay Codes navigation, and no-side-effect boundaries.
- Completed Slice 7 Read-Only Route/API Wiring Plan:
  - `routes/web.php`
  - `src/Http/Controllers/Web/Cockpit/CockpitDashboardPageController.php`
  - `src/Http/Controllers/Web/Cockpit/CockpitQuickGeneratePageController.php`
  - `src/Http/Controllers/Web/Cockpit/CockpitPayCodeExplorerPageController.php`
  - `src/Http/Controllers/Web/Cockpit/CockpitVoucherDetailPageController.php`
  - `src/Http/Controllers/Web/Cockpit/CockpitDistributionWorkspacePageController.php`
  - `resources/js/pages/x-change/cockpit/Dashboard.vue`
  - `resources/js/pages/x-change/cockpit/QuickGenerate.vue`
  - `resources/js/pages/x-change/cockpit/PayCodeExplorer.vue`
  - `resources/js/pages/x-change/cockpit/VoucherDetail.vue`
  - `resources/js/pages/x-change/cockpit/DistributionWorkspace.vue`
- Added Slice 7 PHP route coverage for authenticated GET-only Cockpit routes, Inertia component names, and no Cockpit mutation routes.
- Added Slice 7 frontend coverage for Inertia page adapters importing the Cockpit namespace pages.
- Completed Slice 8 Operator Authorization and Redaction Baseline:
  - `src/Contracts/CockpitRedactorContract.php`
  - `src/Support/Cockpit/DefaultCockpitRedactor.php`
  - `src/Support/Cockpit/CockpitReadOnlyPageProps.php`
  - `src/Providers/XChangeServiceProvider.php`
  - `src/Http/Controllers/Web/Cockpit/CockpitDashboardPageController.php`
  - `src/Http/Controllers/Web/Cockpit/CockpitQuickGeneratePageController.php`
  - `src/Http/Controllers/Web/Cockpit/CockpitPayCodeExplorerPageController.php`
  - `src/Http/Controllers/Web/Cockpit/CockpitVoucherDetailPageController.php`
  - `src/Http/Controllers/Web/Cockpit/CockpitDistributionWorkspacePageController.php`
- Added Slice 8 PHP coverage for authenticated Cockpit routes exposing explicit read-only authorization props, redaction metadata, voucher route context, and no sensitive payload loading.
- Added Slice 8 unit coverage for recursive redaction, original-payload immutability, caller-supplied sensitive keys, and redactor contract binding.

## In Progress

No implementation slice is in progress.

## Next

Recommended next slice: Slice 9 — Read Model Contract Baselines.

Scope should remain foundation-only:

- execution, journal, action, feedback, and voucher read-model DTO/contract baselines
- host-facing presentation contracts that require authorization/redaction before payload exposure
- tests proving read-model contracts are side-effect-free
- no live cross-package calls unless explicitly approved
- no broad exposure of journal, action, feedback, provider, or voucher payloads
- no mutation endpoints, execution, journal writes, feedback delivery, provider calls, campaign behavior, or money movement
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
- Slice 8 exposes static read-only authorization props only. Real operator roles, permissions, policies, and tenant scoping remain future host integration work.

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
- Slice 6 keeps Distribution Workspace as a read-only planning shell. It does not dispatch distribution, send feedback, create campaigns, mutate vouchers, execute drivers, write journal entries, call providers, or move money.
- Slice 7 exposes existing Cockpit pages through authenticated GET-only Inertia routes under `/x/cockpit`. It does not add JSON read APIs, mutation routes, domain read-model calls, execution, journal writes, feedback delivery, provider calls, campaign behavior, or money movement.
- Slice 8 keeps Cockpit authorization/redaction as a boundary baseline. It exposes explicit read-only `can` props and redaction metadata, adds a default redactor contract, and avoids loading voucher, journal, action, feedback, or provider payloads until authorized read models exist.

## Open Questions

- Which host API/read-model contracts should expose execution, journal, action, and feedback facts to Cockpit without leaking sensitive payloads.
- How static read-only Cockpit permissions should evolve into real operator roles, policies, tenant scoping, and permission checks.
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
- Slice 6 focused frontend result: `8 passed, 31 tests`.
- Slice 6 full frontend result: `67 passed, 412 tests`.
- Slice 7 focused PHP route result: `7 passed, 16 assertions`.
- Slice 7 focused frontend result: `9 passed, 36 tests`.
- Slice 7 full frontend result: `68 passed, 417 tests`.
- Slice 7 full package Pest result: `977 passed, 5 skipped, 5007 assertions`.
- Slice 8 focused PHP route result: `13 passed, 73 assertions`.
- Slice 8 focused redactor result: `4 passed, 13 assertions`.
- Slice 8 full package Pest result: `987 passed, 5 skipped, 5077 assertions`.
