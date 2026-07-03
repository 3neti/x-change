# 003 — Cockpit Scaffold Plan

## Slice 0 — Discovery and Compass

Status: complete.

Deliverables:

- `docs/ui-cockpit/COMPASS.md`
- `docs/ui-cockpit/reports/000-source-discovery.md`
- `docs/ui-cockpit/reports/001-source-of-truth-matrix.md`
- `docs/ui-cockpit/reports/002-porting-map.md`
- `docs/ui-cockpit/reports/003-scaffold-plan.md`

## Slice 1 — Cockpit Namespace and Shell

Status: complete.

Goal: introduce the Cockpit shell without changing Claim UI or domain behavior.

Proposed production files:

- `resources/js/cockpit/layouts/CockpitLayout.vue`
- `resources/js/cockpit/components/CockpitGlobalHeader.vue`
- `resources/js/cockpit/components/CockpitSidebar.vue`
- `resources/js/cockpit/components/CockpitBalanceHud.vue`
- `resources/js/cockpit/pages/Dashboard.vue`
- `resources/js/cockpit/navigation.ts`
- `resources/js/cockpit/types.ts`

Acceptable alternative if imports fit existing conventions better:

- `resources/js/components/cockpit/*`
- `resources/js/pages/cockpit/*`

Tests:

- `tests/frontend/cockpit/CockpitLayout.test.ts`
- `tests/frontend/cockpit/CockpitNavigation.test.ts`
- `tests/frontend/cockpit/CockpitDashboardShell.test.ts`

Guardrails:

- no routes/controllers unless required for a render smoke test and explicitly approved
- no execution, journal, action, feedback, or provider side effects
- no claim component rewrites
- no campaign features

## Slice 2 — Dashboard Foundation

Status: complete.

Goal: create a read-only dashboard composition over placeholders and existing safe summaries.

Widgets:

- Liquidity Hero placeholder
- Balance cards
- Redemption Pipeline placeholder
- Risk/Expiry placeholder
- Recent Activity placeholder

Tests:

- widget rendering
- empty/loading states
- no provider calls or money movement

## Slice 3 — Quick Generate Foundation

Status: complete.

Goal: create a template-first operator issuance shell without changing issuance semantics.

Components:

- template selector placeholder
- runtime input area placeholder
- pricing/funding summary placeholder
- generate action placeholder

Guardrails:

- use existing issuance APIs/actions only when explicitly wired
- no direct voucher generation from Vue-only logic
- no money movement outside existing services

## Slice 4 — Pay Code Explorer Foundation

Status: complete.

Goal: create operator search/explorer scaffolding.

Components:

- search bar
- filter builder placeholder
- results table placeholder
- row action placeholders

Guardrails:

- no broad payload exposure without redaction
- no hidden mutations from row actions

## Slice 5 — Voucher Detail Foundation

Status: complete.

Goal: display a single Pay Code/Voucher operator view.

Regions:

- overview
- timeline
- evidence tab placeholder
- distribution tab placeholder
- audit tab placeholder

Sources:

- x-change voucher services
- x-journal read models
- x-feedback delivery read models
- x-action CTA bundles

Implemented as read-only frontend placeholders only. No host API wiring, voucher mutation, execution, journal writes, action execution, feedback delivery, provider calls, or money movement were added in Slice 5.

## Slice 6 — Distribution Workspace Foundation

Status: complete.

Goal: scaffold distribution operators around existing and prior-art sharing concepts.

Regions:

- digital distribution placeholder
- print template placeholder
- share/QR placeholder
- operational analytics placeholder

Guardrails:

- distribution analytics are operational, not marketing automation
- no x-campaign behavior before Wave 5

Implemented as read-only frontend placeholders only. No host API wiring, campaign behavior, distribution dispatch, feedback delivery, voucher mutation, execution, journal writes, provider calls, or money movement were added in Slice 6.

## Slice 7 — Planning Checkpoint

Status: complete.

The initial scaffold plan was completed through the Distribution Workspace foundation. Slice 7 was explicitly authorized as a read-only route/API wiring plan before adding more Cockpit features.

Implemented:

- authenticated GET-only web routes under `/x/cockpit`
- thin invokable Inertia controllers for existing Cockpit pages
- Inertia page adapters under `resources/js/pages/x-change/cockpit`
- route tests proving component names and route names
- mutation-route invariant test proving no Cockpit POST/PUT/PATCH/DELETE routes exist

No separate JSON read APIs were introduced. No host read-model calls, voucher mutation, execution, journal writes, action execution, feedback delivery, provider calls, campaign behavior, or money movement were added.

## Slice 8 — Operator Authorization and Redaction Baseline

Status: complete.

Implemented:

- authenticated Cockpit route coverage for explicit read-only `can` props
- shared read-only page props for all Cockpit Inertia endpoints
- redaction policy metadata for Cockpit pages
- `CockpitRedactorContract`
- default recursive redactor with caller-supplied sensitive-key extension
- service-provider binding for the redactor contract
- route context propagation for voucher-specific pages without loading voucher payloads

No journal, action, feedback, provider, voucher, or execution payloads are exposed in Slice 8. No mutation endpoints, execution, journal writes, feedback delivery, provider calls, campaign behavior, or money movement were added.

## Slice 9 — Read Model Contract Baselines

Status: complete.

Implemented:

- `CockpitReadModelProviderContract`
- `CockpitReadModelQueryData`
- `CockpitReadModelBundleData`
- typed placeholder DTOs for voucher, execution, journal, actions, and feedback
- `NullCockpitReadModelProvider`
- service-provider binding for the null provider
- tests proving the default read-model contract is side-effect-free, not wired, and free of direct x-journal/x-action/x-feedback/voucher model dependencies

No live cross-package calls, route/controller read-model wiring, payload exposure, mutation endpoints, execution, journal writes, feedback delivery, provider calls, campaign behavior, or money movement were added.

## Slice 10 — Read Model Presentation Wiring

Status: complete.

Implemented:

- composed the null/not-wired Cockpit read-model bundle into authenticated Inertia props through `CockpitReadOnlyPageProps`
- added route coverage for voucher-scoped Cockpit pages receiving `read_model` facts
- preserved top-level payload safety: no voucher, journal, actions, feedback, provider payload, wallet, or raw payload props are exposed

No live cross-package calls, JSON read APIs, mutation endpoints, execution, journal writes, feedback delivery, provider calls, campaign behavior, wallet access, raw payload exposure, or money movement were added.

## Slice 11 — Voucher Detail Read Model Adapter Baseline

Status: recommended next.

Candidate scope:

- add a first package-local adapter around existing x-change voucher lifecycle/read services
- limit real data to sanitized voucher summary fields only
- preserve redaction metadata and authorization flags
- keep journal, actions, feedback, execution details, provider payloads, wallets, and raw payloads not wired
- no mutation endpoints or domain side effects

## Verification Plan

For frontend-only slices:

```bash
npm run test:frontend
```

For PHP/backend touched slices:

```bash
composer test
```

Before committing each slice:

- update `docs/ui-cockpit/COMPASS.md`
- run focused tests first where possible
- run the relevant package suite
- keep Claim UI tests green
