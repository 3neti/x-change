# Cockpit Wave 55A — Manual Distribution Copy Decision

## Status

Completed on 2026-07-12.

## Decision

Proceed with a browser-local manual copy affordance for beneficiary Pay Code URLs.

## Scope

Wave 55 may add an operator-facing copy button that copies the already-rendered beneficiary URL to the browser clipboard.

The copy affordance is presentation-only. It may update local UI state such as `Copied`, `Copy unavailable`, or `Copy failed`.

## Required Boundaries

The copy affordance must not:

- call a backend endpoint
- persist copy events
- write journal entries
- execute x-action actions
- send x-feedback deliveries
- dispatch campaigns
- call providers
- mutate vouchers
- move money
- expose raw payloads, wallet internals, provider payloads, or secret claim material

## Implementation Sequence

1. Wave 55B — Manual Copy Component Contract
2. Wave 55C — Voucher Detail Manual Copy Adoption
3. Wave 55D — Distribution Workspace Manual Copy Adoption
4. Wave 55E — Manual Copy Publish / Drift Verification Closure

## Verification

- `php -d memory_limit=1G vendor/bin/pest tests/Unit/Architecture/CockpitWave55aManualDistributionCopyDecisionTest.php`

## Next

Cockpit Wave 55B — Manual Copy Component Contract.
