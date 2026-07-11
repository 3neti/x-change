# Cockpit Wave 9A — Functional Parity Audit for `/x/dashboard`, `/x/pay-codes`, and `/x/balances`

Status: Scaffolded / Functional audit recorded
Date: 2026-07-11

## Objective

Audit the existing x-change functional surfaces before adding more Cockpit capability.

This audit is functional, not visual. The goal is not to make Cockpit look like the existing pages. The goal is to identify the current capabilities that Cockpit must preserve or consume so operators can generate Pay Codes using the newer template and campaign ideas without bypassing the established x-change execution, funding, validation, and voucher boundaries.

## Audited Routes

| Surface | Route | Current owner | Functional role | Cockpit state |
|---|---|---|---|---|
| Compatibility dashboard | `/dashboard` | Host app redirect | Redirects to `/x/dashboard` | Not a primary parity target |
| Current dashboard | `/x/dashboard` | `DashboardPageController` + dashboard API composable | Loads stats, disbursement summary, recent activity, and quick actions | Cockpit has read-only dashboard read models, but not a full functional replacement |
| Pay Code index | `/x/pay-codes` | `PayCodeIndexPageController` + voucher API fetch | Lists/searches/filters Pay Codes and links to create/detail/claim/approval flows | Cockpit Pay Code Explorer has sanitized list/read-model equivalents |
| Pay Code create | `/x/pay-codes/create` | `PayCodeCreatePageController` + existing generation forms | Existing full Pay Code generation surface with balance overview and provisioning requirement props | Cockpit Quick Generate uses the existing issuance action but has a simplified template-first payload |
| Balances | `/x/balances` | `BalancePageController` + `BuildBalanceOverview` | Shows local/provider balance authority and reconciliation context | Cockpit has funding-gate placeholders, but no balance read-model parity yet |
| Cockpit Quick Generate | `/x/cockpit/quick-generate` | Cockpit page + mutation route shell | Calls the existing `GeneratePayCode` action through `GeneratePayCodeRequest` validation | Functional baseline exists for simple issuance |

## Existing Functional Issuance Path

Current safe issuance path:

```text
Cockpit Quick Generate
    ↓
CockpitQuickGenerateMutationRouteShellController
    ↓
GeneratePayCodeRequest
    ↓
GeneratePayCode
    ↓
EstimatePayCodeCost
    ↓
Provider funding policy / wallet access
    ↓
PayCodeIssuanceContract
    ↓
voucher package issuance
```

Important finding: Cockpit already generates Pay Codes through the real x-change `GeneratePayCode` action. The next problem is not basic issuance. The next problem is making template and campaign concepts first-class functional input to the existing issuance path without inventing a parallel issuance engine.

## Functional Parity Findings

| Capability | Existing x-change surface | Current Cockpit capability | Gap |
|---|---|---|---|
| Simple Pay Code issuance | `/x/pay-codes/create`, `POST /api/x/v1/pay-codes` | `/x/cockpit/quick-generate` posts to existing `GeneratePayCode` action | Cockpit simplified payload is not yet backed by a package-owned template compiler |
| Template selection | Legacy create page has form-driven instruction composition; Cockpit has template selector facts | Cockpit has `money-changer`, `ofw-remittance`, and disabled `settlement-envelope` template descriptors | Template selection currently writes only `metadata.custom.cockpit.template_key`; it does not compile complete instruction profiles |
| Campaign context | x-campaign exists as separate package; Cockpit campaign adoption is read-only/unavailable unless wired | Cockpit has campaign read-model placeholders and campaign navigation context | No campaign-to-issuance draft contract yet |
| Pay Code list/explorer | `/x/pay-codes` fetches vouchers and filters/searches client-side | `/x/cockpit/pay-codes` has sanitized list read model | Cockpit list is safe, but not yet the functional operator replacement for all list actions |
| Balance/funding authority | `/x/balances` uses `BuildBalanceOverview`; `/x/pay-codes/create` also receives `balance_overview` | Quick Generate funding gate is mostly placeholder/readiness text | Cockpit lacks a dedicated balance/funding read model tied to issuance preflight |
| Pricing | Existing create page estimates through existing Pay Code estimate API | Cockpit read model has pricing gate descriptors | Cockpit does not yet perform template-aware estimate preflight before submission |
| Validation | Existing generation uses `GeneratePayCodeRequest` | Cockpit mutation route also uses `GeneratePayCodeRequest` | Good baseline; template compiler must continue producing request-compatible payloads |
| Voucher execution | Owned by voucher package | Cockpit does not execute drivers directly | Boundary is correct and must stay unchanged |

## Functional Boundary Decision

Next implementation should not be a broad UI parity pass.

Next implementation should be a functional template/campaign issuance planning slice:

```text
Cockpit Template/Campaign Draft
    ↓
Template/Campaign Issuance Compiler
    ↓
GeneratePayCodeRequest-compatible payload
    ↓
Existing GeneratePayCode action
```

The compiler must produce the same shape accepted by `GeneratePayCodeRequest`:

- `cash.amount`
- `cash.currency`
- `cash.validation`
- `inputs.fields`
- `feedback`
- `rider`
- `count`
- `metadata`
- optional named slices / slice policy
- optional campaign/template metadata

## Recommended Next Slice

Cockpit Wave 9B — Template/Campaign Issuance Draft Contract Baseline

Scope:

- Add package-local DTO/contracts for a Cockpit issuance draft.
- Include template key, campaign context, amount, currency, recipient/reference, purpose, count, feedback preferences, and metadata.
- Add a compiler contract that targets `GeneratePayCodeRequest`-compatible payload shape.
- Do not add new provider calls, wallet debits, voucher behavior, campaign mutation, queue dispatch, feedback delivery, or journal writes.
- Add tests proving the draft can represent simple Quick Generate and future campaign issuance inputs.

## Later Functional Parity Slices

| Slice | Focus | Expected visible change |
|---|---|---|
| Wave 9B | Template/campaign issuance draft contract | None or minimal |
| Wave 9C | Template compiler to existing `GeneratePayCodeRequest` payload | Functional, may not change UI yet |
| Wave 9D | Campaign context adapter into issuance draft | Functional; campaign-generated draft possible |
| Wave 9E | Balance/funding read-model parity using `BuildBalanceOverview` | Cockpit funding facts become real |
| Wave 9F | Pricing estimate preflight parity | Cockpit can show real estimated cost before submit |
| Wave 9G | Quick Generate uses compiler instead of ad hoc payload builder | Functional behavior improves while keeping same route |
| Wave 9H | Pay Code explorer action parity audit | Defines which `/x/pay-codes` actions move to Cockpit |
| Wave 9I | Balance surface parity audit/adapter | Defines `/x/balances` Cockpit equivalent |
| Wave 9J | Functional parity closure | Decides whether legacy routes remain compatibility surfaces |

## Explicit Non-Goals

This audit does not:

- replace `/x/dashboard`, `/x/pay-codes`, or `/x/balances`;
- change UI/UX layout;
- change voucher execution semantics;
- change `GeneratePayCode` behavior;
- add campaign mutation;
- add delivery, journal writes, or action execution;
- add wallet top-up, debit, provider sync, or money movement;
- make Cockpit the lifecycle truth owner.
