# Cockpit Wave 38B — Campaign Adapter Source Context Normalization

## Status

Completed.

## Mission

Normalize safe Quick Generate source context from x-campaign Cockpit summary metadata into the existing `campaign_read_model.quick_generate_link` contract.

## Implementation

x-change now reads adapter-provided source context from safe campaign read-model metadata keys:

- `quick_generate_context`
- `quick_generate_source_context`
- `quick_generate`

The normalized values may fill:

- campaign id
- audience id
- recipient id
- source
- template key
- amount
- currency
- recipient reference
- purpose

Explicit dashboard query parameters take precedence over adapter metadata.

## Boundaries

- The adapter metadata is read-only source context.
- No campaign mutation is performed.
- No bulk issuance is performed.
- No delivery, feedback, provider, wallet, journal, or action side effect is added.
- Unsafe payload keys remain excluded from the link contract.

## Tests

```bash
php -d memory_limit=1G vendor/bin/pest tests/Feature/Cockpit/CockpitCampaignQuickGenerateSourceLinkTest.php
```

Result: 4 passed, 78 assertions.

## Expected UI result

No new UI component. Existing `Open Quick Generate` links can now be populated from adapter metadata when query parameters are absent.

## Next

Cockpit Wave 38C — Real x-campaign Source Context Fixture / Integration Verification.
