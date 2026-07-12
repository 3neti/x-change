# Cockpit Wave 40B — Campaign Recipient Field Normalizer / Draft Adapter

## Status

Completed.

## Implementation

`DefaultCockpitCampaignIssuanceDraftAdapter` now normalizes safe campaign recipient/payout aliases into `CockpitIssuanceDraftData`.

Mapped draft fields:

- `amount`
- `currency`
- `recipient_reference`
- feedback `mobile`
- feedback `email`
- `purpose`
- rider `message`

Accepted source aliases include:

- `recipient.reference`
- `recipient.id`
- `recipient.code`
- `recipient.mobile`
- `recipient.mobile_number`
- `recipient.msisdn`
- `recipient.email`
- `recipient.email_address`
- `payout.amount`
- `allocation.amount`
- `recipient.amount`
- `payout.purpose`
- `allocation.purpose`
- `recipient.purpose`
- `payout.message`
- `recipient.message`

Explicit draft fields remain authoritative.

## Boundary

This is draft normalization only. It does not mutate campaigns, issue Pay Codes, send feedback, call providers, access wallets, move money, write journal entries, or replace the existing `GeneratePayCode` handoff.

## Tests

```bash
cd packages/x-change
php -d memory_limit=1G vendor/bin/pest tests/Unit/Cockpit/DefaultCockpitCampaignIssuanceDraftAdapterTest.php tests/Unit/Cockpit/DefaultCockpitIssuanceDraftCompilerTest.php tests/Unit/Cockpit/DefaultCockpitQuickGenerateDraftFactoryTest.php
```

Result:

```text
17 passed, 139 assertions
```

## UI effect

No direct UI change.

Campaign-sourced Quick Generate forms can now receive recipient reference, amount, feedback contact, purpose, and rider message from campaign recipient/payout context.

## Next checkpoint

Cockpit Wave 40C — Campaign Source Link Recipient Field Propagation.
