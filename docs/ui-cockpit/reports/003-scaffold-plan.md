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

Status: recommended next.

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

## Slice 6 — Distribution Workspace Foundation

Goal: scaffold distribution operators around existing and prior-art sharing concepts.

Regions:

- digital distribution placeholder
- print template placeholder
- share/QR placeholder
- operational analytics placeholder

Guardrails:

- distribution analytics are operational, not marketing automation
- no x-campaign behavior before Wave 5

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
