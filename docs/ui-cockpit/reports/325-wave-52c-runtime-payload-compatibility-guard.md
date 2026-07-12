# Cockpit Wave 52C — Runtime Payload Compatibility Guard

## Status

Completed.

## Scope

Align campaign-prefilled Quick Generate runtime submissions with the request-compatible payload shape characterized in Wave 51.

## Change

When a recipient mobile/reference is present, `CockpitQuickGenerateSubmitPanel` now submits:

```text
cash.validation.mobile
inputs.fields[] = mobile
feedback.mobile
```

This keeps the frontend runtime path consistent with the campaign draft compiler path.

## Test Evidence

The focused frontend test was first tightened and failed against the prior payload shape because `cash.validation.mobile` was missing.

After the runtime payload change, the same test passes.

## Boundary Preserved

- No campaign mutation.
- No bulk issuance.
- No provider call from Cockpit.
- No wallet movement from Cockpit.
- No journal/action/feedback side effect from Cockpit.
- No new issuance runtime.

## Expected UI Result

No new visible panel.

Operators should see the same Campaign context prefill card, but the submitted payload now carries mobile validation semantics required by the existing issuance path.
