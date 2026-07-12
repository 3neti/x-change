# Cockpit Wave 37B — Campaign Quick Generate Source Link Read Model Contract / Hydration

## Status

Completed.

## Mission

Add a backend read-model contract that lets campaign Cockpit surfaces expose a full, safe Quick Generate entry URL.

## Contract

`campaign_read_model.quick_generate_link` is an operator-safe link descriptor.

It may include:

- schema.
- status.
- label.
- href.
- route name.
- read-only and campaign-mutation flags.
- planning key.
- execution id.
- campaign id.
- audience id.
- recipient id.
- campaign source.
- draft query values for template, amount, currency, recipient reference, and purpose.
- redaction metadata.

It must not include:

- campaign mutation endpoints.
- Pay Code generation payloads.
- delivery dispatch payloads.
- provider payloads.
- wallet or balance internals.
- raw campaign or recipient payloads.

## Expected UI result

No visible UI change until Wave 37C renders the link in the campaign adoption panel.

## Verification

- Added `campaign_read_model.quick_generate_link`.
- Dashboard campaign query intake now accepts and forwards the same campaign context fields used by Quick Generate.
- The link points to `x-change.cockpit.quick-generate`.
- The link carries campaign planning, execution, campaign, audience, recipient, source, template, amount, currency, recipient reference, and purpose query values when present.
- Campaign context remains read-only and `mutates_campaign` remains false.

## Tests

```bash
php -d memory_limit=1G vendor/bin/pest tests/Feature/Cockpit/CockpitCampaignQuickGenerateSourceLinkTest.php tests/Unit/Cockpit/CockpitReadModelBaselineTest.php tests/Feature/Cockpit/CockpitReadOnlyRoutesTest.php
```

Result: 65 passed, 984 assertions.

## Next

Cockpit Wave 37C — Campaign Quick Generate Source Link UI Presentation.
