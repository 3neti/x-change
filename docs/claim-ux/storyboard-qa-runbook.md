# Claim Storyboard QA Runbook

## Boundary

This runbook is for the public claim and form-flow journey only.

Do not use Cockpit pages as the acceptance surface for this lane.

Do not pass `--submit-claim` unless the explicit goal is a real payout test.

## Core Command

Start by asking the command for the current QA matrix:

```bash
php artisan xchange:claim-walkthrough --qa-matrix --json
```

The matrix is intentionally a planning/report surface. It lists safe public
claim and form-flow lanes, marks unfinished handler lanes as planned, and never
includes `--submit-claim` or Cockpit routes.

To render every available no-money QA lane and create one HTML index:

```bash
php artisan xchange:claim-walkthrough \
  --qa-batch \
  --preview-cache \
  --profile=qa \
  --json
```

See `claim-storyboard-qa-batch-index.md` for the operator-facing explanation of
the batch output, safety contract, and artifact paths.

Use no-money preview mode first:

```bash
php artisan xchange:claim-walkthrough {scenario} \
  --dry-run \
  --preview-cache \
  --profile=qa \
  --json
```

For browser capture with a disposable preview fixture, still avoid claim
submission:

```bash
php artisan xchange:claim-walkthrough {scenario} \
  --create-fixture \
  --preview-cache \
  --profile=qa \
  --json
```

Browser capture with a disposable preview fixture must report:

```json
{
  "submitted_claim": false
}
```

Dry-run artifacts may omit `submitted_claim` because no browser claim action is
attempted.

## Priority Matrix

| Priority | Scenario | Purpose |
| --- | --- | --- |
| P0 | `claim_basic_no_rider` | Baseline entry, x-ray, form, confirmation handoff |
| P0 | `claim_basic_15_no_inputs_no_riders_no_feedbacks` | Minimal cash claim with the smallest useful story |
| P1 | `claim_basic_15_preview_with_rider` | Rider message, splash, URL, and redirect preview |
| P1 | `claim_named_three_slices_preview` | Named slices and amount explanation |
| P1 | rider splash plus form-flow splash | Confirm duplicate splash is skipped |
| P2 | `claim_fake_otp_handler_preview` | Verify redeemer mobile OTP copy does not resemble Paynamics issuer OTP |
| P2 | `claim_fake_kyc_handler_preview` | Verify identity-verification copy, loading, retry, and continue states |
| P2 | `claim_mocked_location_handler_preview` | Verify permission explanation, retry controls, and map sizing |
| P2 | `claim_mocked_selfie_handler_preview` | Verify camera permission, preview, retake, and continue states |
| P2 | `claim_signature_handler_preview` | Verify canvas sizing, clear, cancel, and continue states |
| P3 | Paynamics approval waiting | Verify redeemer waits and issuer OTP remains separate |

## What To Inspect

For each storyboard, record:

- whether frames duplicate each other without visible change;
- whether the page scrolls on a common phone viewport;
- whether primary and secondary actions are obvious;
- whether permission copy explains why evidence is needed;
- whether retry/cancel behavior is visible;
- whether the Pay Code and amount remain understandable;
- whether the success/rider handoff reads as a continuation, not a new app.

## Variant Checks

Run the same scenario with:

```env
FORM_FLOW_UI_VARIANT=default
FORM_FLOW_UI_VARIANT=compact
FORM_FLOW_UI_VARIANT=immersive
```

When testing x-change claim workflow forms, also test:

```env
XCHANGE_CLAIM_UI_VARIANT=immersive
```

Expected behavior:

- `default` should match the stable current UX;
- `compact` should reduce vertical space without hiding intent;
- `immersive` should make signature, selfie, and location surfaces feel large
  enough for first-time users.

## Acceptance Notes

The storyboard artifact is the human QA record. Prefer one frame per meaningful
human-visible state.

If two frames look the same, collapse or mark one as non-essential unless the
state changed in a way that helps a redeemer understand the journey.

If a scenario requires real provider credentials or money movement, move it out
of this runbook and into a provider regression test plan.

## OTP Boundary

`claim_fake_otp_handler_preview` is redeemer-side mobile verification. It should
never reuse Paynamics payout authorization copy such as "issuer approval",
"payout OTP", or "Paynamics OTP".

`claim_paynamics_approval_walkthrough` is issuer-side payout authorization. It
should keep the redeemer waiting page separate from the issuer OTP entry page.
