# Cockpit Wave 10G — Operator-safe Response / Activity Metadata Alignment

## Status

Implemented.

## Purpose

Align Quick Generate response metadata with durable operator issuance activity metadata using only operator-safe runtime facts.

## Behavior

- Response includes an `activity` section.
- Activity metadata includes draft status, pricing preflight status, funding preflight status, and activity schema.
- No raw payload, wallet, provider payload, or debit facts are added to activity metadata.

## Boundary

This slice does not:

- Change activity persistence behavior.
- Write journal entries.
- Execute actions.
- Send feedback.
- Add retry controls.
- Expose raw issuance payloads.

## Verification

Focused test:

```bash
php -d memory_limit=1G vendor/bin/pest tests/Feature/Cockpit/CockpitQuickGenerateActivityMetadataAlignmentTest.php
```

## Expected UI Effect

None until the frontend renders the new `activity` response section.

## Next Recommended Checkpoint

Cockpit Wave 10H — Runtime Characterization with Existing GeneratePayCode Path.
