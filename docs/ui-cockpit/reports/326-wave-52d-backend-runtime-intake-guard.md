# Cockpit Wave 52D — Backend Runtime Intake Guard

## Status

Completed.

## Scope

Guard the backend Quick Generate mutation route after the frontend runtime payload compatibility fix.

## Protected Behavior

Campaign-sourced Quick Generate requests preserve the following facts through draft factory, validation, compiler, and existing issuance handoff:

```text
metadata.campaign.*
cash.validation.mobile
inputs.fields[] = mobile
feedback.mobile
```

The final payload handed to `GeneratePayCode` remains operator-safe and request-compatible.

## Boundary Preserved

The route remains an x-change Cockpit handoff to existing issuance.

It does not:

- call a campaign package;
- mutate campaign state;
- perform bulk issuance;
- call providers directly;
- move money directly;
- write journal entries directly;
- send feedback directly;
- execute action workflows directly.

## Expected UI Result

No new visible UI.

This slice protects backend runtime behavior for the Campaign context prefill flow already visible on `/x/cockpit/quick-generate`.
