# Form-Flow Driver UI Scaffold

Date: 2026-07-30

## Boundary

This slice is limited to the public claim and form-flow experience.

It does not touch Cockpit, Quick Generate, Campaign import, Rider Stamp canvas,
provider adapters, settlement execution, Treasury accounting, payout
authorization, or money movement.

## What Changed

The form-flow UI now has a safe layout variant scaffold:

- `default`
- `compact`
- `immersive`

The stable production presentation remains `default`.

The form-flow manager exposes the default through:

```env
FORM_FLOW_UI_VARIANT=default
```

x-change now also exposes a public claim profile through:

```env
XCHANGE_CLAIM_UI_VARIANT=default
XCHANGE_CLAIM_UI_SHOW_PROGRESS=true
XCHANGE_CLAIM_UI_SUPPORT_LABEL=
```

`XCHANGE_CLAIM_UI_VARIANT` falls back to `FORM_FLOW_UI_VARIANT`, then to
`default`.

## Package Work

The scaffold was added across:

- `3neti/form-flow`
- `3neti/form-handler-otp`
- `3neti/form-handler-kyc`
- `3neti/form-handler-location`
- `3neti/form-handler-selfie`
- `3neti/form-handler-signature`
- x-change public claim configuration

The shared shell primitives are:

- `FormFlowScreen`
- `FormFlowActions`
- `formFlowUiVariant`

The driver pages now accept or pass a `ui_variant` prop where appropriate.
Driver-specific evidence capture remains owned by each handler.

## x-change Integration

`FormFlowClaimWorkflowMutator` now forwards:

```php
config('x-change.claim.experience_ui.variant')
```

into the x-change-owned wallet/form step as `ui_variant`.

This keeps the public claim wallet step under the x-change profile without
teaching the generic form-flow package to infer x-change semantics from routes,
field names, or handler names.

## Safety Notes

The scaffold is additive.

If no variant is configured, `default` is used.

The form-flow `otp` handler remains redeemer mobile verification.
Paynamics payout OTP remains issuer-side approval and must continue to live in
the issuer/admin approval surface.

## Verification

Focused checks completed:

- form-flow manager variant tests passed;
- x-change claim workflow variant forwarding test passed;
- x-change public claim UI config test passed;
- touched PHP files passed syntax checks;
- no-money claim walkthrough passed with `submitted_claim=false`.

Full host TypeScript checking still reports unrelated non-form-flow errors in
generated actions, Cockpit, x-change claim widgets, and x-rider pages. The
installed `resources/js/pages/form-flow` pages no longer contribute errors.

## Next Work

Do not start with full skins.

Next priority is storyboard QA for the public claim/form-flow journey:

- basic no rider;
- rider message;
- rider splash;
- rider URL;
- named slices;
- skipped duplicate splash;
- fake OTP;
- fake KYC;
- mocked location;
- mocked selfie;
- signature capture.

Use those artifacts to decide whether `compact` or `immersive` should become
the preferred public claim profile.
