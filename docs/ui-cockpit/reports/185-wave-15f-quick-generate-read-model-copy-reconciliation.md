# Cockpit Wave 15F — Quick Generate Read Model Copy Reconciliation

## Status

Implemented.

## Trigger

Manual browser review of `/x/cockpit/quick-generate` showed stale baseline copy after the Quick Generate runtime was already able to submit through the existing `GeneratePayCode` handoff.

The visible mismatch included:

- runtime inputs still saying pending operator input;
- pricing/funding summaries still saying not calculated / not reserved;
- diagnostics still saying no mutation route was registered or authorized;
- validation and precondition diagnostics still implying the route remained blocked.

## Scope

This slice reconciles copy and read-model state only.

It does not change:

- `GeneratePayCode` behavior;
- voucher issuance semantics;
- provider calls;
- wallet movement;
- journal/action/feedback handoffs;
- campaign mutation behavior.

## Changes

- Quick Generate runtime input read-model facts now point operators to the live Quick Generate form.
- Pricing and funding summary facts now explain that operator-safe preflights appear after submit.
- Pricing, funding, validation/redaction, mutation precondition, mutation authorization, and authorization diagnostics now reflect the approved existing issuance handoff.
- Historical diagnostics remain available, but no longer present old slice blockers as current operator guidance.
- Frontend fallbacks were updated so missing or partial props do not regress to stale baseline copy.
- A focused Inertia regression test now rejects stale baseline strings in `quick_generate_read_model`.

## Expected UI Effect

The Quick Generate page should show:

- `Use the Quick Generate form` for Amount and Recipient;
- `Optional form note` for Purpose;
- `Shown after submit` for Pricing Estimate and Funding Impact;
- `Existing handoff` for Execution Summary;
- diagnostic panels labeled as runtime diagnostics instead of old blocked baselines;
- no visible current-state claim that no Cockpit mutation route exists.

## Verification

Completed:

- `php -d memory_limit=1G vendor/bin/pest tests/Feature/Cockpit/CockpitQuickGenerateReadModelCopyReconciliationTest.php tests/Feature/Cockpit/CockpitReadOnlyRoutesTest.php tests/Unit/Cockpit/CockpitReadModelBaselineTest.php`
  - Result: 58 passed, 843 assertions.
- `npm run test:frontend -- CockpitQuickGenerateFoundation.test.ts CockpitQuickGenerateHydration.test.ts`
  - Result: 2 files passed, 24 tests passed.
- `../../vendor/bin/pint --dirty --format agent`
  - Result: passed.
- `php artisan x-change:install --force`
  - Result: host Cockpit assets republished.
- `php artisan x-change:doctor --assets --json`
  - Result: success, checked 56, ok 56, stale 0, missing 0, extra 0.
- Browser log inspection through Laravel Boost.
  - Result: no new slice-specific browser exception found; recent entries are historical Vite reconnect messages.

Not completed:

- Local `curl` smoke check against Herd URL. The required network escalation review timed out twice, so this was not executed.

## Next

After tests and manual review pass, Wave 15 can be accepted and the next planned wave remains:

`Cockpit Wave 16 — Operator Activity Journal Handoff Runtime Enablement`.
