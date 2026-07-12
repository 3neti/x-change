# Cockpit Wave 51 — Campaign Template Quick Generate Functional Bridge Closure

## Status

Completed.

## Completed slices

- Wave 51A — Campaign Template Quick Generate Functional Bridge Audit
- Wave 51B — Single Recipient Campaign Draft Characterization
- Wave 51C — Campaign Draft Compiler Request Compatibility
- Wave 51D — Functional Bridge Safety Boundary

## Result

The campaign/template Quick Generate functional bridge is now characterized and protected.

The bridge supports:

```text
Campaign recipient context
    → Cockpit issuance draft
    → GeneratePayCodeRequest-compatible payload
```

The bridge now proves:

- campaign template intent can map to the `ofw-remittance` Quick Generate template;
- campaign recipient amount/currency/reference/contact/purpose can map into a Cockpit issuance draft;
- recipient mobile is carried into both feedback and validation;
- compiler output passes `GeneratePayCodeRequest` validation;
- campaign metadata is preserved under `metadata.campaign`;
- adapter/compiler source remains preparation-only and does not call issuance, providers, wallets, journal, feedback, action, or campaign mutation paths directly.

## Production change made

`DefaultCockpitCampaignIssuanceDraftAdapter` now maps recipient mobile into:

- `validation.mobile`
- `input_fields: ['mobile']`

`DefaultCockpitIssuanceDraftCompiler` now maps campaign data explicitly instead of relying on `spatie/laravel-data` `toArray()` for nested campaign metadata.

## Boundary preserved

No campaign mutation, bulk issuance, distribution dispatch, feedback delivery, journal writes, provider calls, direct wallet movement, lifecycle truth ownership, or unsafe payload exposure was added.

## Next recommended wave

`Cockpit Wave 52 — Campaign Template Quick Generate Runtime Adoption Decision`

Recommended scope:

- decide whether to use the characterized adapter/compiler bridge inside the Quick Generate page/runtime;
- if approved, wire only a single-recipient campaign-prefilled submit path through existing `GeneratePayCode`;
- keep x-campaign state read-only;
- keep campaign bulk issuance blocked;
- keep provider, wallet, journal, action, and feedback side effects behind existing x-change issuance boundaries.
