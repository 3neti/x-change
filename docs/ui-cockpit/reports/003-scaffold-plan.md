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

Status: complete.

Implemented:

- package-local adapter around `VoucherLifecycleServiceContract`
- default `CockpitReadModelProviderContract` binding now resolves the voucher lifecycle adapter
- sanitized voucher summary whitelist:
  - `code`
  - `status`
  - `display_status`
  - `amount`
  - `currency`
  - `claimed`
  - `fully_claimed`
  - `created_at`
  - `starts_at`
  - `expires_at`
  - `redeemed_at`
- explicit redaction metadata for excluded voucher lifecycle detail fields
- null/not-wired fallback for missing voucher codes
- tests proving no instructions, claims, approval metadata, provider payloads, raw payloads, wallets, provider facts, internal IDs, issuer IDs, execution details, journal entries, action bundles, or feedback deliveries are exposed

No mutation endpoints, JSON APIs, execution, journal writes, action execution, feedback delivery, provider calls, wallet access, claim UX changes, campaign behavior, or money movement were added.

## Slice 12 — Voucher Detail Presentation Hydration Baseline

Status: complete.

Implemented:

- hydrate existing Voucher Detail Vue components from the sanitized `read_model.voucher.summary`
- keep execution, journal, action, and feedback panels in explicit empty/not-wired states
- preserve redaction metadata and read-only authorization flags in the UI
- no broad payload exposure, mutations, domain side effects, provider calls, wallet access, or money movement

The route adapter now forwards Inertia props into the Cockpit namespace page, and the page derives display-only overview, timeline, evidence, distribution, and audit items from the sanitized read-model contract.

No new JSON APIs, backend read-model expansion, mutation endpoints, execution, journal writes, action execution, feedback delivery, provider calls, wallet access, claim UX changes, campaign behavior, or money movement were added.

## Slice 13 — Pay Code Explorer Read Model Hydration Baseline

Status: complete.

Implemented:

- hydrate Pay Code Explorer from safe read-model/list props when available
- keep search/filter controls local and read-only until an approved read API exists
- preserve empty/loading/not-wired states
- avoid broad voucher payloads, provider payloads, wallet data, claim payloads, approval metadata, or raw payloads
- no mutations or domain side effects

The frontend now accepts an optional `pay_codes_read_model` prop and maps only safe list-row fields into the existing read-only results table. The route adapter forwards props into the Cockpit namespace page. Empty authorized list models render an explicit empty state.

No backend list adapter, JSON API, host query endpoint, mutation endpoint, execution, journal write, action execution, feedback delivery, provider call, wallet access, claim UX change, campaign behavior, or money movement was added.

## Slice 14 — Cockpit List Read Model Adapter Baseline

Status: complete.

Implemented:

- add a package-local backend adapter for sanitized Pay Code Explorer list rows
- prefer existing `VoucherLifecycleServiceContract::list()` if it can supply safe summary facts
- bind or compose the list adapter into existing read-only page props only if it does not broaden payload exposure
- keep search/filter controls local unless an approved read API/query contract is added
- preserve disabled row actions and no mutation behavior

The backend now extends the Cockpit read-model provider contract with a list method and exposes `pay_codes_read_model` only on the Pay Code Explorer route. The adapter maps only safe list-row fields and preserves redaction metadata for internal IDs, issuer IDs, instructions, claims, approval metadata, provider payloads, raw payloads, wallets, and provider facts.

No backend query API, search/filter execution, mutation endpoint, execution, journal write, action execution, feedback delivery, provider call, wallet access, campaign behavior, or money movement was added.

## Slice 15 — Cockpit Dashboard Read Model Adapter Baseline

Status: complete.

Implemented:

- add package-local backend dashboard summary adapters only if existing x-change lifecycle/read services can provide sanitized facts
- expose dashboard read-model props without broad payloads or live mutation paths
- preserve existing dashboard presentation defaults for unavailable/not-wired state
- no mutation endpoints, execution, journal writes, action execution, feedback delivery, provider calls, wallet access, campaign behavior, or money movement

The backend now extends the Cockpit read-model provider contract with a dashboard method and exposes `dashboard_read_model` only on the Cockpit Dashboard route. The adapter derives aggregate metric cards, lifecycle pipeline counts, risk signals, and recent activity from sanitized voucher lifecycle list rows only.

The frontend Dashboard now hydrates from the optional `dashboard_read_model` prop and keeps static defaults when dashboard read models are missing, unavailable, or unauthorized.

## Slice 16 — Quick Generate Read Model Adapter Baseline

Status: complete.

Implemented:

- added package-local Quick Generate read-model DTOs for sanitized template, runtime input, pricing summary, and disabled action facts
- exposed `quick_generate_read_model` only on the Quick Generate route
- hydrated the Quick Generate page from sanitized read-model props while preserving static defaults for unavailable states
- preserved disabled generate action behavior even when read-model props suggest an enabled action
- no mutation endpoints, voucher issuance, execution, journal writes, action execution, feedback delivery, provider calls, wallet access, campaign behavior, or money movement

The backend now extends the Cockpit read-model provider contract with a Quick Generate method and exposes `quick_generate_read_model` only on the Cockpit Quick Generate route. The baseline deliberately avoids voucher lifecycle reads, pricing service calls, wallet lookup/reservation/debit, provider calls, voucher issuance, journal writes, feedback delivery, and action execution.

The frontend Quick Generate page now hydrates from the optional `quick_generate_read_model` prop and keeps static defaults when read models are missing, unavailable, or unauthorized.

## Slice 17 — Quick Generate Issuance Boundary Plan

Status: complete.

Implemented:

- define the explicit boundary for a future Quick Generate issuance handoff
- identify the existing API/action path that would own actual Pay Code generation
- document required authorization, pricing, funding, idempotency, and redaction gates
- keep generate controls disabled unless a mutation slice is explicitly approved
- no mutation endpoints, voucher issuance, execution, journal writes, action execution, feedback delivery, provider calls, wallet access, campaign behavior, or money movement

Slice 17 added a persisted boundary report and a visible Quick Generate boundary panel. The existing issuance owner remains `GeneratePayCode` / `GeneratePayCodeController`. Cockpit still has no generation mutation route.

## Slice 18 — Quick Generate Request Draft Contract Baseline

Status: complete.

Implemented:

- define the frontend/backend-neutral draft payload shape for future Quick Generate issuance
- keep drafts local/read-only unless an explicit persistence or mutation slice is approved
- identify required fields, optional fields, redacted fields, and idempotency metadata
- no mutation endpoints, voucher issuance, execution, journal writes, action execution, feedback delivery, provider calls, wallet access, campaign behavior, or money movement

Slice 18 added a `x-change.cockpit.quick-generate-draft.v1` draft contract shape to the Quick Generate read model and a visible read-only draft contract panel. Drafts remain local and read-only; no draft persistence or mutation route was registered.

## Slice 19 — Quick Generate Authorization Gate Baseline

Status: complete.

Implemented:

- define the operator authorization gate facts required before a future Quick Generate mutation can be enabled
- keep authorization facts read-only and presentation-safe
- preserve disabled generate controls unless an explicit mutation slice is approved
- no mutation endpoints, voucher issuance, execution, journal writes, action execution, feedback delivery, provider calls, wallet access, campaign behavior, or money movement

Slice 19 added a read-only authorization gate model to the Quick Generate read model and a visible authorization gate panel. The baseline reports that planning/viewing gates pass while generation, provider calls, and money movement remain blocked.

## Slice 20 — Quick Generate Pricing Gate Baseline

Status: complete.

Implemented:

- define the pricing readiness facts required before a future Quick Generate mutation can be enabled
- keep pricing facts read-only and presentation-safe
- preserve disabled generate controls unless an explicit mutation slice is approved
- no pricing calculation, mutation endpoints, voucher issuance, execution, journal writes, action execution, feedback delivery, provider calls, wallet access, campaign behavior, or money movement

Slice 20 added a read-only pricing gate model to the Quick Generate read model and a visible pricing gate panel. The baseline reports the default template as selected while amount input, pricing service wiring, funding source selection, funds reservation, and provider fee quotes remain blocked.

## Slice 21 — Quick Generate Funding Gate Baseline

Status: recommended next.

Candidate scope:

- define the funding readiness facts required before a future Quick Generate mutation can reserve or move funds
- keep funding facts read-only and presentation-safe
- preserve disabled generate controls unless an explicit mutation slice is approved
- no wallet lookup, reservation, debit, mutation endpoints, voucher issuance, execution, journal writes, action execution, feedback delivery, provider calls, campaign behavior, or money movement

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
