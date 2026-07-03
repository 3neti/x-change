# Cockpit Slice 20 — Quick Generate Pricing Gate Baseline

## Scope

Cockpit Slice 20 adds a read-only pricing gate baseline for Quick Generate.

The slice exposes pricing readiness as operator-facing facts. It does not calculate prices, reserve funds, select wallets, call providers, or enable Pay Code generation.

## Gate Facts

The baseline gate set is:

- `template-selected`
- `amount-input-present`
- `pricing-service-wired`
- `funding-source-selected`
- `funds-reservation`
- `provider-fee-quote`

Only `template-selected` is marked as passed because the existing Quick Generate read model already exposes the default template catalog.

All calculation, funding, reservation, and provider gates remain blocked.

## Boundary

Pricing gates are read-only facts in Slice 20.

No pricing gate calculates prices, reserves funds, or calls providers in Slice 20.

The slice does not introduce:

- mutation routes
- request persistence
- pricing service calls
- wallet lookups
- funding source selection
- funds reservation
- provider fee quotes
- voucher generation
- journal events
- action runs
- feedback delivery

## Redaction

The pricing gate read model exposes only gate status and diagnostic reasons.

The following payload classes remain excluded:

- `pricing_breakdown`
- `funding_source`
- `wallet`
- `balance`
- `account_number`
- `provider_payload`
- `raw_payload`

## Implementation Notes

The read model additions are:

- `CockpitQuickGeneratePricingGateData`
- `CockpitQuickGeneratePricingGateCheckData`
- `quick_generate_read_model.pricing_gate`
- `CockpitQuickGeneratePricingGatePanel`

The existing `pricing_summaries` remain high-level display summaries. The new `pricing_gate` field records readiness checks that must become true before a future mutation route can safely calculate, reserve, or issue.

## Verification

The Slice 20 tests protect:

- default not-wired pricing gate shape
- hydrated Quick Generate pricing gate facts
- absence of mutation route behavior
- absence of pricing, wallet, funding, and provider payload exposure
- frontend rendering without forms or side effects
