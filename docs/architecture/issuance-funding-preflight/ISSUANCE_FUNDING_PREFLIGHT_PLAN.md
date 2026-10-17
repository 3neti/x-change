# Reactive Issuance Funding Preflight

## Intent

Give Quick Generate surfaces an authoritative, read-only funding decision as soon as a valid voucher instruction draft exists. The decision drives the reactive cost and action state while final submission remains authoritative.

## Contract

`POST /x/cockpit/quick-generate/funding-preflight` accepts the canonical `GeneratePayCodeRequest` payload and returns `x-change.cockpit.issuance-funding-preflight.v1` with pricing, client-funds availability, required amount, reserved amount, external amount, execution (`direct` or `payment_required`), reason, and a short expiry.

The endpoint creates no Pay Code, funding order, treasury hold, wallet debit, or provider instruction. Final submission recalculates the payload, quote, and funding decision.

## Implementation

- `CockpitQuickGenerateFundingPreflightController` reuses the pricing action, funding policy, funding requirement, and balance read model.
- `useIssuanceFundingPreflight` debounces valid drafts, cancels stale requests, ignores out-of-order responses, and clears invalid decisions.
- Quick Generate’s shared Submit Panel uses the preflight execution state for affordability and funding-required presentation. LLM, Composer, and future POS/batch/widget surfaces share this coordinator.
- Full-amount on-demand funding remains the default. Shortfall behavior remains configuration-driven.

## Verification

- Feature coverage proves payment-required and direct decisions without money movement.
- Frontend coverage preserves the reactive amount and instruction icons and verifies the existing Quick Generate workspace behavior.
- Host publication and browser build must verify the generated Wayfinder route and package asset.
