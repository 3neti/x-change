# Cockpit Wave 40C — Campaign Source Link Recipient Field Propagation

## Status

Completed.

## Implementation

Campaign source-link hydration now carries recipient/payout-normalized draft fields into the existing `/x/cockpit/quick-generate` URL.

The Campaign `Open Quick Generate` link can now be built from adapter metadata containing nested recipient/payout fields, including:

- payout amount;
- payout currency;
- recipient reference;
- payout purpose;
- payout message as purpose/message fallback.

Sensitive contact values such as mobile and email remain off the URL.

## Boundary

This only affects safe source-link prefill. It does not mutate campaigns, execute bulk issuance, dispatch feedback, call providers, access wallets, move money, write journal entries, or replace `GeneratePayCode`.

## Tests

```bash
cd packages/x-change
php -d memory_limit=1G vendor/bin/pest tests/Feature/Cockpit/CockpitCampaignQuickGenerateSourceLinkTest.php tests/Unit/Cockpit/DefaultCockpitCampaignIssuanceDraftAdapterTest.php tests/Unit/Cockpit/CockpitReadModelBaselineTest.php
```

Result:

```text
35 passed, 389 assertions
```

## UI effect

No new component.

Existing Campaign Cockpit Adoption `Open Quick Generate` links can now open Quick Generate with amount, recipient reference, and purpose/message values derived from safe campaign recipient/payout context.

## Next checkpoint

Cockpit Wave 40D — Campaign Recipient Field Browser / Published Asset Verification.
