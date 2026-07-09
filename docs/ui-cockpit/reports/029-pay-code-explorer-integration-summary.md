# Host Integration Slice 2F — Pay Code Explorer Integration Summary

## Status

Complete.

## Scope

Render lightweight read-only Journal / Action / Feedback integration badges on Pay Code Explorer.

## Implemented

- Pay Code Explorer accepts the existing `read_model` bundle prop.
- The page renders badges for:
  - Journal
  - Actions
  - Feedback
- Each badge shows status and payload policy only.

## Boundary

This slice intentionally uses page-level integration badges instead of per-row integration facts because per-row journal/action/feedback status is not yet part of the Pay Code list read-model contract.

This slice does not:

- add per-row integration payloads
- add query APIs
- expand list-read scope
- write journal entries
- execute actions
- send feedback
- retry delivery
- call providers
- expose raw payloads
- mutate vouchers
- move money

## Verification

```bash
npm run test:frontend -- tests/frontend/cockpit/CockpitPayCodeExplorerHydration.test.ts tests/frontend/cockpit/CockpitPayCodeExplorerFoundation.test.ts
```

Result:

```text
2 passed, 10 tests
```
