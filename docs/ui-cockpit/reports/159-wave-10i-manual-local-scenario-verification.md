# Cockpit Wave 10I — Manual Local Scenario Verification

## Status

Pending human/local verification.

## Purpose

Provide the local verification checklist for the Wave 10 runtime compiler adoption.

## Local Preconditions

- Run `php artisan x-change:install --force` from the host app if package Cockpit assets changed.
- Run `npm run dev` if testing the Vite UI.
- Ensure the local operator account has enough funding for a small Pay Code.
- Use `/x/cockpit/quick-generate`.

## Manual Scenario

1. Open `/x/cockpit/quick-generate`.
2. Use template `money-changer`.
3. Enter a small PHP amount, for example `25`.
4. Enter a beneficiary mobile number.
5. Click `Generate Pay Code`.
6. Confirm the response creates a Pay Code.
7. Open the Pay Code detail link if visible.
8. Return to `/x/cockpit` and confirm operator issuance activity still shows operator-safe facts only.

## Expected Runtime Facts

- Quick Generate still creates the Pay Code through existing `GeneratePayCode`.
- The response includes `preflight.pricing`.
- The response includes `preflight.funding`.
- The response includes `activity`.
- Activity metadata is operator-safe.
- No raw payloads are visible.
- No wallet internals are visible.
- No provider payloads are visible.
- No campaign mutation occurs.
- No journal/action/feedback handoff is enabled unless separately configured.

## Pass Criteria

- Pay Code generation succeeds for a funded local operator.
- Response/UI does not expose raw payloads, provider payloads, wallet internals, debit records, or allocation internals.
- Existing durable activity display remains stable.
- Campaign fields, if submitted later by host adapters, remain metadata-only.

## Block Criteria

- Quick Generate fails before `GeneratePayCode` for a valid `money-changer` draft.
- The response exposes raw payloads or wallet/provider internals.
- Campaign state is mutated by Quick Generate.
- Pricing or funding preflight blocks issuance unexpectedly.

## Expected UI Effect

Minimal. Existing Quick Generate UI should still work. New `preflight` and `activity` response fields are not necessarily rendered by the current UI.

## Next Recommended Checkpoint

Cockpit Wave 10J — Runtime Compiler Adoption Closure.
