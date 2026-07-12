# Cockpit Wave 39C — Campaign Source Link Template Intent Propagation

## Status

Completed.

## Implementation

Campaign source-link hydration now reuses the existing `CockpitCampaignIssuanceDraftAdapterContract` path when safe adapter metadata is present.

This lets `campaign_read_model.quick_generate_link` normalize campaign template intent into a concrete Quick Generate template key before building the `/x/cockpit/quick-generate` URL.

Example:

```text
template_intent=money_changer
    ↓
campaign_template_key=money-changer
```

Explicit dashboard query values still win over adapter metadata.

## Boundary

This only affects source-link draft hydration.

It does not:

- mutate campaigns;
- execute bulk issuance;
- dispatch feedback;
- call providers;
- access wallets;
- move money;
- write journal entries;
- replace `GeneratePayCode`.

## Tests

```bash
cd packages/x-change
php -d memory_limit=1G vendor/bin/pest tests/Feature/Cockpit/CockpitCampaignQuickGenerateSourceLinkTest.php tests/Unit/Cockpit/CockpitReadModelBaselineTest.php tests/Unit/Cockpit/DefaultCockpitCampaignIssuanceDraftAdapterTest.php
```

Result:

```text
32 passed, 349 assertions
```

## UI effect

No new component.

Existing Campaign Cockpit Adoption `Open Quick Generate` links can now carry normalized campaign template intent into Quick Generate prefill.

## Next checkpoint

Cockpit Wave 39D — Campaign Template Intent Browser / Published Asset Verification.
