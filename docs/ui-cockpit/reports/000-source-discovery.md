# 000 — Cockpit Source Discovery

## Scope

This report records the current sources inspected before Cockpit implementation. Slice 0 is discovery-only and does not add routes, controllers, Vue pages, shell components, or package integrations.

## x-change Package Baseline

| Area | File path | Purpose | Current maturity | Test coverage | Recommendation |
|---|---|---|---|---|---|
| Package metadata | `composer.json` | Laravel package definition and PHP test scripts | mature | package suite via `composer test` | preserve |
| Frontend tooling | `package.json` | Vitest frontend test command | mature | `npm run test:frontend` | preserve |
| Vitest config | `vitest.config.ts` | Vue test aliases and jsdom setup | mature | used by existing frontend suite | preserve |
| Package provider | `src/Providers/XChangeServiceProvider.php` | package service registration | mature | backend package tests | preserve; add Cockpit bindings only when needed |
| Routes | `routes/web.php`, `routes/api.php`, `routes/lifecycle-api.php` | current web/API/lifecycle surfaces | mature | feature/API tests | do not expand in Slice 0 |
| Current dashboard page | `resources/js/pages/x-change/Dashboard.vue` | current x-change dashboard | useful but not Cockpit-namespaced | frontend tests exist for dashboard-adjacent x-change pages | promote or wrap in Slice 1/2 |
| Current layout | `resources/js/layouts/x-change/XChangeLayout.vue` | sidebar layout for existing x-change pages | useful but starter-kit residue remains | indirect frontend coverage | enhance into Cockpit shell later |
| Dashboard cards | `resources/js/components/x-change/StatCard.vue`, `QuickActions.vue`, `RecentActivity.vue` | current dashboard widgets | useful baseline | frontend suite covers x-change pages/components | promote into Cockpit widgets where aligned |
| Dashboard API composable | `resources/js/composables/useXChangeDashboardApi.ts` | current dashboard data fetcher | useful but not OS-wide | frontend coverage indirect | preserve until Cockpit read models exist |
| Route composable | `resources/js/composables/useXChangeRoutes.ts` | frontend route helpers for x-change pages | useful | frontend coverage indirect | extend carefully in Slice 1 |

## Protected Claim UX Assets

| Capability | File path | Purpose | Current maturity | Test coverage | Recommendation |
|---|---|---|---|---|---|
| Claim widget | `resources/js/components/x-change/ClaimWidget.vue` | claimant/redeemer experience | mature | multiple `ClaimWidget.*.test.ts` files | preserve |
| Claim compiler view models | `resources/js/components/x-change/compiledForm*.ts`, `compiledClaimFormSubmission.ts` | compiled claim rendering/submission | mature | dedicated frontend tests | preserve |
| Claim entry page | frontend claim entry resources and `tests/frontend/ClaimEntryPage.test.ts` | redeem/claim entry | mature | direct frontend test | preserve |
| Paynamics OTP approval UX | approval metadata/action/page/OTP resources and `tests/frontend/ClaimApprovalPage.test.ts` | issuer-side approval UX | mature | direct frontend tests | preserve |
| Rider message UX | `resources/js/components/x-rider/*`, `resources/js/components/x-change/successRider.ts` | post-claim rider continuation | mature | success/rider tests | preserve |
| Success redirect UX | `resources/js/components/x-change/successRedirect*.ts` | redirect countdown/ownership behavior | mature | success redirect tests | preserve |
| Form-flow renderer | `FormFlowRenderer.vue`, renderer registry/components | compiled form-flow rendering | mature | renderer contract/rendering tests | preserve |

## Prior Waves Available to Cockpit Later

| Workstream | Source inspected | Current status | Cockpit implication |
|---|---|---|---|
| Execution Engine | `packages/x-change/docs/architecture/execution-engine/EXECUTION_ENGINE_COMPASS.md`; voucher execution classes | Slices 0–9 complete | Cockpit can eventually display execution status/correlation but must not execute money directly. |
| x-journal | `/Users/rli/PhpstormProjects/packages/x-journal/docs/architecture/x-journal/X_JOURNAL_COMPASS.md`; Cockpit journal reader classes | Phases 0–15 complete | Cockpit can consume read models, but host redaction, idempotency, and visibility-aware pagination remain important. |
| x-action | `/Users/rli/PhpstormProjects/packages/x-action/docs/x-action-compass.md`; host composer classes | Phase 7 complete | Cockpit can consume action bundles, but actions are not authorization or execution. |
| x-feedback | `/Users/rli/PhpstormProjects/packages/x-feedback/docs/architecture/x-feedback/X_FEEDBACK_COMPASS.md`; console/UI component classes | Phase 23 complete | Cockpit can consume communication view models, but Cockpit owns pages and operator workflows. |

## redeem-x Prior Art

`redeem-x` was inspected read-only as prior art. It is not canonical over the current x-change package.

| Prior-art area | Example paths | Recommendation |
|---|---|---|
| Voucher/PWA detail surfaces | `resources/js/components/pwa/VoucherDetailsSheet.vue`, `VoucherOverviewTab.vue`, `VoucherEnvelopeTab.vue`, `VoucherInstructionsTab.vue` | adapt concepts later for Voucher Detail foundation |
| Share/QR patterns | `resources/js/components/QrSharePanel.vue`, `VoucherShareDialog.vue` | adapt later for Distribution Workspace |
| PWA operational panels | `PendingActionsCard.vue`, `QuickActionsCard.vue`, `RecentVoucherDrawer.vue` | adapt later for mobile/PWA Cockpit surfaces |
| Older generated route/action TypeScript | `resources/js/actions/**` | reference only; current x-change should use its own route conventions |

## Tooling Detected

- PHP tests: `composer test` runs `php -d memory_limit=1G vendor/bin/pest`.
- Frontend tests: `npm run test:frontend` runs `vitest run`.
- Package uses Inertia v3/Vue 3 frontend resources and Pest/Testbench backend tests.

## Discovery Notes

- The Cockpit docs are broad product/design specifications, not a direct implementation checklist.
- The first authorized Cockpit slice in `codex_instructions.md` is Slice 0: Discovery and Compass.
- Existing Claim UI has substantial frontend coverage and should be treated as protected.
- The current x-change dashboard can seed Cockpit, but the Cockpit namespace/shell should be introduced test-first.

