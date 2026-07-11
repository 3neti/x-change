# Cockpit Wave 10H — Runtime Characterization with Existing GeneratePayCode Path

## Status

Implemented.

## Purpose

Document and protect the runtime shape after Wave 10B–10G.

## Characterized Runtime

```text
GeneratePayCodeRequest
    ↓
CockpitQuickGenerateDraftFactoryContract
    ↓
CockpitIssuanceDraftValidatorContract
    ↓
CockpitIssuanceDraftCompilerContract
    ↓
EstimatePayCodeCost preflight
    ↓
BuildBalanceOverview preflight
    ↓
GeneratePayCode
    ↓
Operator-safe response
    ↓
Operator issuance activity handoff pipeline
```

## Confirmed

- Quick Generate still uses `GeneratePayCode`.
- No public Pay Code API route replacement occurred.
- The existing public Pay Code generation controller remains separate.
- Pricing and funding preflights are non-blocking.
- Campaign context passes as metadata only.
- Operator activity metadata remains operator-safe.

## Boundary

This slice does not:

- Add UI controls.
- Replace `/x/pay-codes/create`.
- Replace `/x/pay-codes`.
- Replace `/x/balances`.
- Change voucher issuance semantics.
- Change wallet debit semantics.
- Change provider behavior.

## Verification

Focused test:

```bash
php -d memory_limit=1G vendor/bin/pest tests/Unit/Architecture/CockpitWave10hRuntimeCharacterizationTest.php
```

## Expected UI Effect

None.

## Next Recommended Checkpoint

Cockpit Wave 10I — Manual Local Scenario Verification.
