# Cockpit Slice 27 — Cross-Package Read Model Integration Baseline

Date: 2026-07-04

## Objective

Introduce a read-only Cockpit integration seam for package-ready system layers:

- x-journal evidence summaries
- x-action safe CTA/action summaries
- x-feedback communication delivery summaries

This slice keeps x-change installable without hard Composer dependencies on those packages; x-journal, x-action, and x-feedback are optional service seams, not hard Composer dependencies.

## Implementation Summary

- Added `OptionalCockpitIntegrationReadModels`.
- Wired optional integration output into `VoucherLifecycleCockpitReadModelProvider` for voucher-scoped read models.
- Added `x-change.cockpit.integrations.*` config keys so host apps can override service IDs.
- Kept the existing Cockpit route surface read-only.
- Preserved the existing read model DTOs:
  - `CockpitJournalReadModelData`
  - `CockpitActionReadModelData`
  - `CockpitFeedbackReadModelData`

## Boundary Decisions

| Boundary | Decision |
|---|---|
| Composer dependencies | Do not require x-journal, x-action, or x-feedback in this slice. |
| Missing packages | Return safe unavailable/not-loaded read models. |
| Journal | Read evidence only; do not write journal entries. |
| Action | Render safe action summaries only; do not execute or authorize actions. |
| Feedback | Read communication delivery state only; do not send, retry, or call providers. |
| Sensitive data | Redact provider responses, secrets, tokens, raw payloads, account/contact details, and credential-like fields. |

## Read Model Semantics

### Journal

`journal.status = available` means Cockpit received read-only evidence data from a configured/installed x-journal reader.

It does not mean:

- lifecycle truth
- workflow authorization
- journal mutation
- recovery execution

### Actions

`actions.status = available` means Cockpit received safe host-composed action presentation data.

It does not mean:

- action execution
- action authorization
- durable action run persistence
- lifecycle completion

### Feedback

`feedback.status = available` means Cockpit received read-only communication delivery summaries.

It does not mean:

- provider delivery execution
- retry execution
- audit truth
- lifecycle truth

## Tests Added

- Optional journal/action/feedback services hydrate voucher-scoped read models.
- Optional integration payloads are redacted before entering Cockpit read models.
- Optional integration exceptions degrade to safe unavailable read models.
- Optional service construction failures degrade to safe unavailable read models.
- Existing no-hard-dependency architecture coverage remains in place.

## Verification

Focused unit result:

```text
php -d memory_limit=1G vendor/bin/pest tests/Unit/Cockpit/CockpitReadModelBaselineTest.php
16 passed, 116 assertions
```

Focused route/documentation result:

```text
php -d memory_limit=1G vendor/bin/pest tests/Feature/Cockpit/CockpitReadOnlyRoutesTest.php
29 passed, 361 assertions
```

Syntax checks:

```text
php -l src/Services/Cockpit/OptionalCockpitIntegrationReadModels.php
php -l src/Services/Cockpit/VoucherLifecycleCockpitReadModelProvider.php
php -l src/Providers/XChangeServiceProvider.php
php -l config/x-change.php
No syntax errors detected
```

Full package result:

```text
php -d memory_limit=1G vendor/bin/pest
1019 passed, 5 skipped, 5481 assertions
```

Formatter note:

```text
vendor/bin/pint --dirty --format agent
Not available in this package: vendor/bin/pint does not exist.
```

## Deferred

- Dashboard-level cross-package aggregates.
- Frontend-specific journal/action/feedback widgets.
- Cockpit resend/retry execution.
- Cockpit action execution.
- Cockpit journal writes.
- Provider calls.
- Wallet access or money movement.
