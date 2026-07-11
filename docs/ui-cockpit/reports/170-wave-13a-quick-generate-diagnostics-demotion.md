# Cockpit Wave 13A — Quick Generate Diagnostics Demotion

## Status

Implemented.

## Purpose

Demote historical baseline and gate panels so operators see the working Quick Generate runtime first.

## Behavior

- Quick Generate still shows the template selector, runtime inputs, submit form, result, and existing issuance handoff as primary UI.
- Historical pricing/funding/idempotency/validation/mutation/authorization/draft/boundary panels are now grouped under a diagnostics disclosure.
- The old panels remain available for architecture history and engineering inspection.

## Boundary

This slice does not change issuance, pricing, funding, idempotency, wallet, provider, journal, action, feedback, or campaign behavior.

## Verification

```bash
npm run test:frontend -- CockpitQuickGenerateFoundation.test.ts
```

## Next Recommended Checkpoint

Cockpit Wave 13B — Quick Generate Runtime Copy Alignment.
