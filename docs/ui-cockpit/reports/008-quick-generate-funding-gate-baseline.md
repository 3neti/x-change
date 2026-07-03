# Cockpit Slice 21 — Quick Generate Funding Gate Baseline

## Scope

Cockpit Slice 21 adds a read-only funding gate baseline for Quick Generate.

The slice exposes funding readiness as operator-facing facts. It does not resolve wallets, read balances, evaluate sufficient funds, reserve funds, debit balances, call providers, or enable Pay Code generation.

## Gate Facts

The baseline gate set is:

- `funding-policy-known`
- `issuer-wallet-identified`
- `wallet-balance-available`
- `sufficient-funds`
- `funds-reservation-ready`
- `provider-funding-ready`

Only `funding-policy-known` is marked as passed because Cockpit can represent the funding boundary as a read-only readiness fact.

All wallet, balance, sufficiency, reservation, and provider funding gates remain blocked.

## Boundary

Funding gates are read-only facts in Slice 21.

No funding gate reads wallets, reserves funds, debits balances, or calls providers in Slice 21.

The slice does not introduce:

- mutation routes
- request persistence
- wallet lookup
- wallet balance reads
- funding source selection
- sufficient-funds evaluation
- funds reservation
- wallet debit or transfer
- provider funding checks
- provider account-readiness calls
- voucher generation
- journal events
- action runs
- feedback delivery

## Redaction

The funding gate read model exposes only gate status and diagnostic reasons.

The following payload classes remain excluded:

- `funding_source`
- `wallet`
- `balance`
- `available_balance`
- `account_number`
- `provider_wallet`
- `provider_payload`
- `raw_payload`

## Implementation Notes

The read model additions are:

- `CockpitQuickGenerateFundingGateData`
- `CockpitQuickGenerateFundingGateCheckData`
- `quick_generate_read_model.funding_gate`
- `CockpitQuickGenerateFundingGatePanel`

The existing `pricing_gate` field remains the calculation readiness baseline. The new `funding_gate` field records wallet, balance, sufficiency, reservation, and provider-funding readiness checks that must become true before a future mutation route can safely reserve or move funds.

## Verification

The Slice 21 tests protect:

- default not-wired funding gate shape
- hydrated Quick Generate funding gate facts
- absence of mutation route behavior
- absence of wallet, balance, funding source, provider wallet, provider payload, and raw payload exposure
- frontend rendering without forms or side effects
