# Cockpit Wave 39B — Campaign Template Intent Normalizer / Draft Adapter

## Status

Completed.

## Implementation

`DefaultCockpitCampaignIssuanceDraftAdapter` now normalizes campaign template intent aliases into existing Quick Generate template keys.

Supported normalized outputs:

- `money-changer`
- `ofw-remittance`
- `settlement-envelope`

Accepted campaign intent sources include:

- `template_intent`
- `product_key`
- `product.key`
- `product.slug`
- `template.intent`
- `template.key`
- `template.profile`
- `program.type`

Explicit `template_key` still wins over inferred campaign intent.

## Boundary

This is draft normalization only. It does not issue Pay Codes, mutate campaigns, dispatch feedback, call providers, access wallets, write journal entries, or replace the existing `GeneratePayCode` handoff.

## Tests

```bash
cd packages/x-change
php -d memory_limit=1G vendor/bin/pest tests/Unit/Cockpit/DefaultCockpitCampaignIssuanceDraftAdapterTest.php tests/Unit/Cockpit/DefaultCockpitIssuanceDraftCompilerTest.php tests/Unit/Cockpit/DefaultCockpitQuickGenerateDraftFactoryTest.php
```

Result:

```text
15 passed, 114 assertions
```

## UI effect

No direct UI change.

Campaign-sourced Quick Generate forms can now receive normalized template keys from campaign product/template intent instead of requiring the campaign read model to already speak Cockpit template-key language.

## Next checkpoint

Cockpit Wave 39C — Campaign Source Link Template Intent Propagation.
